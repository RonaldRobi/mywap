<?php

namespace App\Services;

use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\FormResponse;
use App\Models\Payment;
use App\Models\Registration;
use App\Support\NameMatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Detect and merge duplicate registrations created by repeated imports or
 * repeated manual/public registrations.
 */
class RegistrationDedupeService
{
    /**
     * @return array{total_rows: int, unique_identities: int, duplicate_rows: int, groups_count: int, missing_identity_rows: int}
     */
    public function summary(Event $event): array
    {
        $registrations = Registration::query()
            ->where('event_id', $event->id)
            ->get(['id', 'name', 'email', 'phone', 'ic_number', 'created_at', 'updated_at']);

        $groups = $this->groups($registrations);

        $duplicateGroups = array_filter($groups, fn (array $indices) => count($indices) > 1);
        $duplicateRows = array_sum(array_map(fn (array $indices) => count($indices) - 1, $duplicateGroups));

        $missingIdentity = $registrations->filter(function (Registration $registration) {
            return Registration::normalizeIc($registration->ic_number) === null
                && Registration::normalizePhone($registration->phone) === null
                && trim((string) $registration->email) === '';
        })->count();

        return [
            'total_rows' => $registrations->count(),
            'unique_identities' => count($groups),
            'duplicate_rows' => $duplicateRows,
            'groups_count' => count($duplicateGroups),
            'missing_identity_rows' => $missingIdentity,
        ];
    }

    /**
     * Merge every duplicate group and keep attendance/payment history.
     *
     * @return array{groups: int, deleted: int, remaining: int}
     */
    public function merge(Event $event): array
    {
        $registrations = Registration::query()
            ->with(['attendance', 'payments', 'formResponse'])
            ->where('event_id', $event->id)
            ->get()
            ->values();

        $groups = array_filter(
            $this->groups($registrations),
            fn (array $indices) => count($indices) > 1,
        );

        $deleted = 0;

        DB::transaction(function () use ($registrations, $groups, &$deleted) {
            foreach ($groups as $indices) {
                $models = array_map(fn (int $index) => $registrations[$index], $indices);
                $deleted += $this->mergeGroup($models);
            }
        });

        return [
            'groups' => count($groups),
            'deleted' => $deleted,
            'remaining' => Registration::where('event_id', $event->id)->count(),
        ];
    }

    /**
     * @return array<int, array<int, int>>
     */
    private function groups(Collection $registrations): array
    {
        $items = $registrations->values();
        $count = $items->count();

        if ($count === 0) {
            return [];
        }

        $parent = range(0, $count - 1);
        $find = function (int $index) use (&$parent): int {
            while ($parent[$index] !== $index) {
                $parent[$index] = $parent[$parent[$index]];
                $index = $parent[$index];
            }

            return $index;
        };
        $union = function (int $a, int $b) use (&$parent, $find): void {
            $rootA = $find($a);
            $rootB = $find($b);

            if ($rootA !== $rootB) {
                $parent[$rootB] = $rootA;
            }
        };

        $icBuckets = [];
        $phoneBuckets = [];
        $emailBuckets = [];

        foreach ($items as $index => $registration) {
            $name = (string) $registration->name;
            $ic = Registration::normalizeIc($registration->ic_number);
            $phone = Registration::normalizePhone($registration->phone);
            $email = mb_strtolower(trim((string) $registration->email)) ?: null;

            if ($ic !== null) {
                foreach ($icBuckets[$ic] ?? [] as $other) {
                    if (NameMatcher::similar($name, (string) $items[$other]->name)) {
                        $union($index, $other);
                    }
                }
                $icBuckets[$ic][] = $index;
            }

            if ($phone !== null) {
                foreach ($phoneBuckets[$phone] ?? [] as $other) {
                    if (NameMatcher::similar($name, (string) $items[$other]->name)) {
                        $union($index, $other);
                    }
                }
                $phoneBuckets[$phone][] = $index;
            }

            if ($email !== null) {
                foreach ($emailBuckets[$email] ?? [] as $other) {
                    if (NameMatcher::similar($name, (string) $items[$other]->name)) {
                        $union($index, $other);
                    }
                }
                $emailBuckets[$email][] = $index;
            }
        }

        $groups = [];

        foreach ($items as $index => $registration) {
            $groups[$find($index)][] = $index;
        }

        return array_values($groups);
    }

    /**
     * @param  array<int, Registration>  $models
     */
    private function mergeGroup(array $models): int
    {
        usort($models, function (Registration $a, Registration $b): int {
            $aAttended = $a->attendance !== null;
            $bAttended = $b->attendance !== null;

            if ($aAttended !== $bAttended) {
                return $bAttended <=> $aAttended;
            }

            $aPaid = $a->payments->contains(fn (Payment $p) => $p->status === 'successful');
            $bPaid = $b->payments->contains(fn (Payment $p) => $p->status === 'successful');

            if ($aPaid !== $bPaid) {
                return $bPaid <=> $aPaid;
            }

            $aTime = $a->updated_at?->timestamp ?? $a->created_at?->timestamp ?? 0;
            $bTime = $b->updated_at?->timestamp ?? $b->created_at?->timestamp ?? 0;

            return $bTime <=> $aTime ?: $a->id <=> $b->id;
        });

        /** @var Registration $keeper */
        $keeper = array_shift($models);

        if ($models === []) {
            return 0;
        }

        $all = array_merge([$keeper], $models);
        usort($all, fn (Registration $a, Registration $b) => ($a->created_at?->timestamp ?? 0) <=> ($b->created_at?->timestamp ?? 0));

        $rawData = [];

        foreach ($all as $registration) {
            $rawData = array_merge($rawData, $registration->raw_data ?? []);
        }

        /** @var Registration $latest */
        $latest = collect($all)->sortByDesc(fn (Registration $r) => $r->updated_at ?? $r->created_at)->first();

        $status = $keeper->status;
        if ($all !== [] && collect($all)->contains(fn (Registration $r) => $r->status === RegistrationStatus::Confirmed)) {
            $status = RegistrationStatus::Confirmed;
        }

        $keeper->fill([
            'name' => $latest->name ?: $keeper->name,
            'email' => $latest->email ?: $keeper->email,
            'phone' => $latest->phone ?: $keeper->phone,
            'ic_number' => $latest->ic_number ?: $keeper->ic_number,
            'member_no' => $latest->member_no ?: $keeper->member_no,
            'ticket_type' => $latest->ticket_type ?: $keeper->ticket_type,
            'document_path' => $latest->document_path ?: $keeper->document_path,
            'organization_id' => $latest->organization_id ?? $keeper->organization_id,
            'form_id' => $latest->form_id ?? $keeper->form_id,
            'raw_data' => $rawData !== [] ? $rawData : $keeper->raw_data,
            'status' => $status,
        ])->save();

        $duplicateIds = array_map(fn (Registration $r) => $r->id, $models);

        if ($keeper->attendance === null) {
            $attendance = collect($models)->pluck('attendance')->filter()->first();

            if ($attendance !== null) {
                $attendance->update(['registration_id' => $keeper->id]);
            }
        }

        Payment::query()
            ->where('payable_type', Registration::class)
            ->whereIn('payable_id', $duplicateIds)
            ->update(['payable_id' => $keeper->id]);

        FormResponse::query()
            ->whereIn('registration_id', $duplicateIds)
            ->update(['registration_id' => $keeper->id]);

        Registration::query()->whereKey($duplicateIds)->delete();

        return count($duplicateIds);
    }
}

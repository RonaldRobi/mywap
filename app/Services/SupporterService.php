<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\Scopes\OrganizationScope;
use App\Models\Supporter;
use App\Models\User;
use App\Support\NameMatcher;
use Throwable;

class SupporterService
{
    /**
     * Rekod pendaftaran bukan ahli ke dalam direktori Pendukung dan pautkan
     * peserta itu dengan program berkenaan. Selamat dipanggil berulang kali.
     */
    public function syncFromRegistration(Registration $registration): ?Supporter
    {
        if ($registration->user_id !== null) {
            return null;
        }

        $registration->loadMissing(['event.organizations']);

        $email = $this->normalizeEmail($registration->email);
        $ic = Registration::normalizeIc($registration->ic_number);
        $rawIc = $this->rawIc($registration->ic_number);
        $phone = Registration::normalizePhone($registration->phone);

        if ($email === null && $phone === null && $ic === null && $rawIc === null) {
            return null;
        }

        if ($this->isExistingMember($email, $ic, $rawIc, $registration->member_no)) {
            return null;
        }

        $organizationId = $this->organizationIdForRegistration($registration);

        $supporter = $this->findExisting(
            $organizationId,
            $email,
            $ic,
            $rawIc,
            $phone,
            $registration->name,
        );

        if ($supporter === null) {
            $supporter = Supporter::create([
                'organization_id' => $organizationId,
                'name' => $registration->name,
                'email' => $email,
                'phone' => $registration->phone,
                'ic_number' => $registration->ic_number,
                'source' => Supporter::SOURCE_PROGRAM,
                'is_active' => true,
            ]);
        } else {
            $supporter = $this->fillMissingFields($supporter, $registration, $organizationId);
        }

        if ($registration->event_id !== null) {
            $supporter->events()->syncWithoutDetaching([
                $registration->event_id => ['source' => $registration->source ?: 'registration'],
            ]);
        }

        return $supporter;
    }

    public function findExisting(
        ?int $organizationId,
        ?string $email,
        ?string $ic,
        ?string $rawIc,
        ?string $phone,
        ?string $name,
    ): ?Supporter {
        $query = $this->organizationQuery($organizationId);

        if ($ic !== null) {
            $match = (clone $query)->where('ic_normalized', $ic)->first();

            if ($match !== null) {
                return $match;
            }
        }

        if ($rawIc !== null) {
            $match = (clone $query)
                ->whereRaw('UPPER(ic_number) = ?', [mb_strtoupper($rawIc)])
                ->first();

            if ($match !== null) {
                return $match;
            }
        }

        if ($email !== null) {
            foreach ((clone $query)->whereRaw('LOWER(email) = ?', [$email])->limit(5)->get() as $candidate) {
                if ($this->namesMatch($name, $candidate->name)) {
                    return $candidate;
                }
            }
        }

        if ($phone !== null) {
            foreach ((clone $query)->where('phone_normalized', $phone)->limit(5)->get() as $candidate) {
                if ($this->namesMatch($name, $candidate->name)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    public function isExistingMember(?string $email, ?string $ic, ?string $rawIc, ?string $memberNo = null): bool
    {
        $memberNo = trim((string) $memberNo);

        if ($email === null && $ic === null && $rawIc === null && $memberNo === '') {
            return false;
        }

        return User::withoutGlobalScope(OrganizationScope::class)
            ->where(function ($query) use ($email, $ic, $rawIc, $memberNo) {
                if ($email !== null) {
                    $query->orWhereRaw('LOWER(email) = ?', [$email]);
                }

                if ($memberNo !== '') {
                    $query->orWhere('member_no', $memberNo);
                }

                $compactIc = $this->compactIc($rawIc);

                if ($compactIc !== '') {
                    $query->orWhereRaw(
                        "UPPER(REPLACE(REPLACE(REPLACE(ic_number, '-', ''), ' ', ''), '.', '')) = ?",
                        [$compactIc],
                    );
                } elseif ($ic !== null) {
                    $query->orWhereRaw(
                        "UPPER(REPLACE(REPLACE(REPLACE(ic_number, '-', ''), ' ', ''), '.', '')) = ?",
                        [$ic],
                    );
                }
            })
            ->exists();
    }

    /**
     * Tandakan Pendukung sebagai sudah mendaftar sebagai ahli supaya senarai
     * tidak lagi mencampurkan ahli aktif dengan prospek.
     */
    public function markConverted(User $user): int
    {
        $email = $this->normalizeEmail($user->email);
        $ic = Registration::normalizeIc($user->ic_number);
        $rawIc = $this->rawIc($user->ic_number);
        $phone = Registration::normalizePhone($user->phone);

        if ($email === null && $ic === null && $rawIc === null && $phone === null) {
            return 0;
        }

        $query = Supporter::query()->whereNull('converted_at');

        $query->where(function ($inner) use ($email, $ic, $rawIc, $phone) {
            $compactIc = $this->compactIc($rawIc);

            if ($email !== null) {
                $inner->orWhereRaw('LOWER(email) = ?', [$email]);
            }

            if ($compactIc !== '' || $ic !== null) {
                $inner->orWhereRaw(
                    "UPPER(REPLACE(REPLACE(REPLACE(ic_number, '-', ''), ' ', ''), '.', '')) = ?",
                    [$compactIc !== '' ? $compactIc : $ic],
                );
            }

            if ($phone !== null) {
                $inner->orWhere('phone_normalized', $phone);
            }
        });

        return $query->update([
            'converted_user_id' => $user->id,
            'converted_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function backfillFromRegistrations(): int
    {
        $synced = 0;

        Registration::query()
            ->whereNull('user_id')
            ->with(['event.organizations'])
            ->orderBy('id')
            ->chunkById(200, function ($registrations) use (&$synced) {
                foreach ($registrations as $registration) {
                    try {
                        if ($this->syncFromRegistration($registration) !== null) {
                            $synced++;
                        }
                    } catch (Throwable $exception) {
                        report($exception);
                    }
                }
            });

        return $synced;
    }

    private function fillMissingFields(Supporter $supporter, Registration $registration, ?int $organizationId): Supporter
    {
        $updates = [];

        if (blank($supporter->name) && filled($registration->name)) {
            $updates['name'] = $registration->name;
        }
        if (blank($supporter->email) && filled($registration->email)) {
            $updates['email'] = $registration->email;
        }
        if (blank($supporter->phone) && filled($registration->phone)) {
            $updates['phone'] = $registration->phone;
        }
        if (blank($supporter->ic_number) && filled($registration->ic_number)) {
            $updates['ic_number'] = $registration->ic_number;
        }
        if ($supporter->organization_id === null && $organizationId !== null) {
            $updates['organization_id'] = $organizationId;
        }

        if ($updates !== []) {
            $supporter->fill($updates)->save();
        }

        return $supporter;
    }

    private function organizationIdForRegistration(Registration $registration): ?int
    {
        if ($registration->organization_id !== null) {
            return (int) $registration->organization_id;
        }

        $event = $registration->event;

        if ($event?->organization_id !== null) {
            return (int) $event->organization_id;
        }

        $organizations = $event?->organizations;

        if ($organizations !== null && $organizations->count() === 1) {
            return (int) $organizations->first()->id;
        }

        return null;
    }

    private function organizationQuery(?int $organizationId)
    {
        $query = Supporter::query();

        if ($organizationId === null) {
            return $query;
        }

        return $query->where(function ($inner) use ($organizationId) {
            $inner->where('organization_id', $organizationId)
                ->orWhereNull('organization_id');
        });
    }

    private function namesMatch(?string $first, ?string $second): bool
    {
        if (blank($first) || blank($second)) {
            return true;
        }

        return NameMatcher::similar($first, $second);
    }

    public function normalizeEmail(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return $email === '' ? null : $email;
    }

    private function rawIc(?string $ic): ?string
    {
        $ic = trim((string) $ic);

        return $ic === '' ? null : $ic;
    }

    private function compactIc(?string $ic): string
    {
        return mb_strtoupper(preg_replace('/[\s\-\.]+/', '', (string) $ic) ?? '');
    }
}

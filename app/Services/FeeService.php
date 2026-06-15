<?php

namespace App\Services;

use App\Enums\FeeStatus;
use App\Models\MembershipFee;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FeeService
{
    public function getStatus(User $user, ?int $year = null): array
    {
        $year ??= now()->year;

        if ($this->isLifeMember($user) || $this->isExempted($user)) {
            $fee = MembershipFee::where('user_id', $user->id)
                ->whereIn('status', ['life_member', 'exempted'])
                ->latest('paid_at')
                ->first();

            return [
                'status' => 'active',
                'amount_due' => 0.0,
                'last_paid_at' => $fee?->paid_at?->toISOString(),
                'last_reference' => null,
            ];
        }

        $fee = MembershipFee::where('user_id', $user->id)
            ->where('year', $year)
            ->first();

        if (! $fee) {
            return [
                'status' => 'due',
                'amount_due' => (float) ($user->organization?->fee_amount ?? 0),
                'last_paid_at' => null,
                'last_reference' => null,
            ];
        }

        if ($fee->status === FeeStatus::Paid) {
            return [
                'status' => 'active',
                'amount_due' => 0.0,
                'last_paid_at' => $fee->paid_at?->toISOString(),
                'last_reference' => $fee->payment?->reference,
            ];
        }

        return [
            'status' => 'due',
            'amount_due' => (float) $fee->amount,
            'last_paid_at' => null,
            'last_reference' => null,
        ];
    }

    public function markAsPaid(User $user, int $year, float $amount, ?int $paymentId = null): MembershipFee
    {
        $fee = MembershipFee::updateOrCreate(
            ['user_id' => $user->id, 'year' => $year],
            [
                'organization_id' => $user->current_organization_id,
                'amount' => $amount,
                'status' => 'paid',
                'paid_at' => now(),
                'payment_id' => $paymentId,
            ]
        );

        Cache::forget("life_member:{$user->id}");
        Cache::forget("exempted:{$user->id}");

        return $fee;
    }

    public function markAsLifeMember(User $user, Organization $org, ?string $notes = null): MembershipFee
    {
        MembershipFee::where('user_id', $user->id)
            ->where('status', 'unpaid')
            ->delete();

        $fee = MembershipFee::updateOrCreate(
            ['user_id' => $user->id, 'year' => now()->year],
            [
                'organization_id' => $org->id,
                'amount' => 0,
                'status' => 'life_member',
                'paid_at' => now(),
                'notes' => $notes ?? 'Ahli seumur hidup',
            ]
        );

        Cache::forget("life_member:{$user->id}");
        Cache::forget("exempted:{$user->id}");

        return $fee;
    }

    public function unmarkLifeMember(User $user): void
    {
        MembershipFee::where('user_id', $user->id)
            ->where('status', 'life_member')
            ->delete();

        MembershipFee::where('user_id', $user->id)
            ->where('status', 'exempted')
            ->delete();

        Cache::forget("life_member:{$user->id}");
        Cache::forget("exempted:{$user->id}");
    }

    public function markAsExempted(User $user, string $reason): MembershipFee
    {
        MembershipFee::where('user_id', $user->id)
            ->where('status', 'unpaid')
            ->delete();

        $fee = MembershipFee::updateOrCreate(
            ['user_id' => $user->id, 'year' => now()->year],
            [
                'organization_id' => $user->current_organization_id,
                'amount' => 0,
                'status' => 'exempted',
                'paid_at' => now(),
                'notes' => $reason,
            ]
        );

        Cache::forget("life_member:{$user->id}");
        Cache::forget("exempted:{$user->id}");

        return $fee;
    }

    public function isLifeMember(User $user): bool
    {
        return Cache::remember("life_member:{$user->id}", 300, fn () =>
            MembershipFee::where('user_id', $user->id)
                ->where('status', 'life_member')
                ->exists()
        );
    }

    public function isExempted(User $user): bool
    {
        return Cache::remember("exempted:{$user->id}", 300, fn () =>
            MembershipFee::where('user_id', $user->id)
                ->where('status', 'exempted')
                ->exists()
        );
    }

    public function getDueCount(int $organizationId, ?int $year = null): int
    {
        $year ??= now()->year;

        return Cache::remember("due_count:{$organizationId}:{$year}", 60, function () use ($organizationId, $year) {
            return User::withoutGlobalScopes()
                ->where('current_organization_id', $organizationId)
                ->whereDoesntHave('membershipFees', fn ($q) => $q->whereIn('status', ['life_member', 'exempted']))
                ->whereDoesntHave('membershipFees', fn ($q) => $q->where('year', $year)->where('status', 'paid'))
                ->count();
        });
    }

    public function getPaymentHistory(User $user, int $limit = 10): array
    {
        return MembershipFee::with('payment')
            ->where('user_id', $user->id)
            ->where('status', FeeStatus::Paid)
            ->latest('year')
            ->take($limit)
            ->get()
            ->map(function (MembershipFee $fee) {
                $ref = $fee->payment?->reference ?? '';
                $hasProof = !is_null($fee->payment?->proof_path);
                $uploadedBy = $fee->payment?->uploaded_by;

                if (str_starts_with($ref, 'DUMMY-')) {
                    $source = 'Dummy Payment';
                    $sourceType = 'dummy';
                } elseif ($hasProof && $uploadedBy) {
                    $source = str_starts_with($ref, 'CSV-') ? 'Import CSV' : (str_starts_with($ref, 'MANUAL-') ? 'Manual (Admin)' : 'Manual (Ada Bukti)');
                    $sourceType = str_starts_with($ref, 'CSV-') ? 'csv' : 'manual';
                } else {
                    $source = 'Tidak Diketahui';
                    $sourceType = 'unknown';
                }

                return [
                    'payment_id' => $fee->payment?->id,
                    'year' => $fee->year,
                    'amount' => (float) $fee->amount,
                    'paid_at' => $fee->paid_at?->toISOString(),
                    'reference' => $ref,
                    'source' => $source,
                    'source_type' => $sourceType,
                    'status' => $fee->payment?->status,
                    'has_proof' => $hasProof,
                    'proof_url' => $fee->payment?->proof_path
                        ? Storage::disk('local')->url($fee->payment->proof_path)
                        : null,
                ];
            })->all();
    }

    public function getAdminStats(?int $organizationId, int $year): array
    {
        return Cache::remember("fee_stats:{$organizationId}:{$year}", 60, function () use ($organizationId, $year) {
            $baseQuery = User::withoutGlobalScopes();
            if ($organizationId) {
                $baseQuery->where('current_organization_id', $organizationId);
            }

            $stats = (clone $baseQuery)->selectRaw("
                COUNT(*) as total,
                SUM(EXISTS(
                    SELECT 1 FROM membership_fees
                    WHERE membership_fees.user_id = users.id
                    AND membership_fees.year = ?
                    AND membership_fees.status = 'paid'
                )) as paid,
                SUM(EXISTS(
                    SELECT 1 FROM membership_fees
                    WHERE membership_fees.user_id = users.id
                    AND membership_fees.status = 'life_member'
                )) as life_member,
                SUM(EXISTS(
                    SELECT 1 FROM membership_fees
                    WHERE membership_fees.user_id = users.id
                    AND membership_fees.status = 'exempted'
                )) as exempted
            ", [$year])->first();

            $total = (int) $stats->total;
            $paid = (int) $stats->paid;
            $lifeMember = (int) $stats->life_member;
            $exempted = (int) $stats->exempted;

            $collectedQuery = Payment::query()
                ->where('status', 'successful')
                ->whereYear('created_at', $year)
                ->where('payable_type', MembershipFee::class);

            if ($organizationId) {
                $collectedQuery->whereHas('user', fn ($q) => $q->withoutGlobalScopes()->where('current_organization_id', $organizationId));
            }

            $collectedAmount = $collectedQuery->sum('amount');
            $due = $total - $paid - $lifeMember - $exempted;

            return [
                'total' => max(0, $total),
                'paid' => max(0, $paid),
                'due' => max(0, $due),
                'life_member' => max(0, $lifeMember),
                'exempted' => max(0, $exempted),
                'collected_amount' => (float) $collectedAmount,
            ];
        });
    }

    public function generateAnnualFees(Organization $org, int $year): int
    {
        $amount = (float) ($org->fee_amount ?? 0);
        $count = 0;

        User::withoutGlobalScopes()
            ->where('current_organization_id', $org->id)
            ->whereDoesntHave('membershipFees', fn ($q) => $q->whereIn('status', ['life_member', 'exempted']))
            ->whereDoesntHave('membershipFees', fn ($q) => $q->where('year', $year))
            ->chunk(100, function ($users) use ($year, $amount, &$count) {
                $data = $users->map(fn ($user) => [
                    'user_id' => $user->id,
                    'organization_id' => $user->current_organization_id,
                    'year' => $year,
                    'amount' => $amount,
                    'status' => 'unpaid',
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->toArray();

                MembershipFee::insert($data);
                $count += count($data);
            });

        return $count;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Scopes\OrganizationScope;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ResetMembershipFees extends Command
{
    protected $signature = 'fees:reset-baseline
        {--year= : Tahun baseline (default: tahun sebelum fee_start_year)}
        {--dry-run : Papar ringkasan tanpa mengubah data}';

    protected $description = 'Reset semua yuran sebelum tahun mula supaya tiada hutang, kecuali ahli seumur hidup';

    public function handle(): int
    {
        $startYear = (int) config('membership.fee_start_year', 2027);
        $baselineYear = (int) ($this->option('year') ?? ($startYear - 1));
        $dryRun = (bool) $this->option('dry-run');
        $note = "Reset baseline — mula kira {$startYear}";

        if ($baselineYear >= $startYear) {
            $this->error("Tahun baseline ({$baselineYear}) mesti sebelum tahun mula yuran ({$startYear}).");

            return self::FAILURE;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '')."Reset yuran bagi tahun <= {$baselineYear}...");

        $deceasedBackfill = $this->backfillDeceased($dryRun);

        $adminIds = User::withTrashed()
            ->withoutGlobalScope(OrganizationScope::class)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', [
                'Superadmin',
                'Admin',
                'org-admin',
                'Admin Cawangan',
            ]))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $adminRows = DB::table('membership_fees')
            ->whereIn('user_id', $adminIds)
            ->where('status', '!=', 'life_member');

        $adminRowsCount = (clone $adminRows)->count();

        if (! $dryRun && $adminRowsCount > 0) {
            foreach (array_chunk($adminIds, 500) as $chunk) {
                DB::table('membership_fees')
                    ->whereIn('user_id', $chunk)
                    ->where('status', '!=', 'life_member')
                    ->delete();
            }
        }

        $feeRows = DB::table('membership_fees')
            ->where('year', '<=', $baselineYear)
            ->where('status', '!=', 'life_member');

        if ($adminIds !== []) {
            $feeRows->whereNotIn('user_id', $adminIds);
        }

        $feeRowsCount = (clone $feeRows)->count();

        if (! $dryRun && $feeRowsCount > 0) {
            $feeRows->update([
                'amount' => 0,
                'status' => 'paid',
                'payment_id' => null,
                'paid_at' => null,
                'notes' => $note,
                'updated_at' => now(),
            ]);
        }

        $baselineQuery = User::withoutGlobalScope(OrganizationScope::class)
            ->whereNotNull('current_organization_id')
            ->when($adminIds !== [], fn ($q) => $q->whereNotIn('id', $adminIds))
            ->whereDoesntHave('membershipFees', fn ($q) => $q->where('year', $baselineYear))
            ->whereDoesntHave('membershipFees', fn ($q) => $q->where('status', 'life_member'));

        $baselineCount = (clone $baselineQuery)->count();

        if (! $dryRun && $baselineCount > 0) {
            $baselineQuery->select('id', 'current_organization_id')
                ->chunkById(500, function ($users) use ($baselineYear, $note) {
                    $now = now();
                    $rows = $users->map(fn (User $user) => [
                        'user_id' => $user->id,
                        'organization_id' => $user->current_organization_id,
                        'year' => $baselineYear,
                        'amount' => 0,
                        'status' => 'paid',
                        'paid_at' => null,
                        'payment_id' => null,
                        'notes' => $note,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->toArray();

                    DB::table('membership_fees')->insertOrIgnore($rows);
                });
        }

        if (! $dryRun) {
            Cache::flush();
        }

        $this->table(
            ['Item', 'Jumlah'],
            [
                ['Ahli meninggal di-backfill', $deceasedBackfill],
                ['Rekod yuran admin dipadam', $adminRowsCount],
                ['Rekod yuran direset (paid RM0)', $feeRowsCount],
                ['Rekod baseline baharu', $baselineCount],
            ],
        );

        $this->info($dryRun ? 'Dry run selesai. Tiada data diubah.' : 'Reset yuran selesai.');

        return self::SUCCESS;
    }

    private function backfillDeceased(bool $dryRun): int
    {
        $deceasedPositionIds = DB::table('organization_positions')
            ->whereRaw('UPPER(name) = ?', ['MENINGGAL DUNIA'])
            ->pluck('id')
            ->all();

        $query = DB::table('users')
            ->where(function ($q) {
                $q->whereNull('membership_status')
                    ->orWhere('membership_status', '!=', 'deceased');
            })
            ->where(function ($q) use ($deceasedPositionIds) {
                $q->where('position', 'Meninggal Dunia')
                    ->orWhereRaw('LOWER(notes) LIKE ?', ['%meninggal dunia%']);

                if ($deceasedPositionIds !== []) {
                    $q->orWhereIn('position_id', $deceasedPositionIds);
                }
            });

        $count = (clone $query)->count();

        if (! $dryRun && $count > 0) {
            $query->update([
                'membership_status' => 'deceased',
                'updated_at' => now(),
            ]);
        }

        return $count;
    }
}

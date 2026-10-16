<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * AbimStateAdminSeeder
 *
 * Cipta satu akaun "Admin Negeri" bagi setiap negeri untuk ABIM (14 negeri).
 * Setiap akaun:
 *   - emel   : abim-<slug-negeri>@mywap.my
 *   - katalaluan bersama (lihat PASSWORD)
 *   - peranan: Admin (Admin Organisasi) — skop seluruh organisasi ABIM
 *   - organisasi: ABIM
 *   - cawangan: cawangan negeri berkenaan (jika wujud)
 *
 * Idempotent: selamat dijalankan berulang. Katalaluan akaun sedia ada TIDAK
 * ditimpa supaya admin boleh tukar katalaluan tanpa direset pada deploy akan
 * datang.
 */
class AbimStateAdminSeeder extends Seeder
{
    /** Katalaluan sama untuk semua akaun negeri (boleh ditukar selepas log masuk). */
    public const PASSWORD = 'Abim@2026';

    /**
     * 14 negeri/cawangan ABIM (sepadan dengan BranchSeeder).
     *
     * @var list<string>
     */
    public const STATES = [
        'Selangor',
        'Kuala Lumpur',
        'Johor',
        'Kedah',
        'Kelantan',
        'Melaka',
        'Negeri Sembilan',
        'Pahang',
        'Perak',
        'Perlis',
        'Pulau Pinang',
        'Sabah',
        'Sarawak',
        'Terengganu',
    ];

    public function run(): void
    {
        $abim = Organization::where('slug', 'abim')->first();

        if (! $abim) {
            $this->command?->warn('Organisasi ABIM tidak dijumpai — seeder Admin Negeri dilangkau.');

            return;
        }

        // Pastikan peranan Admin wujud (production mungkin belum di-seed).
        $role = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

        $rows = [];

        foreach (self::STATES as $state) {
            $slug = Str::slug($state);
            $email = "abim-{$slug}@mywap.my";

            $branch = Branch::query()
                ->where('organization_id', $abim->id)
                ->where(function ($q) use ($state) {
                    $q->where('state', $state)
                        ->orWhereIn('name', [$state, "ABIM {$state}", "Cawangan {$state}"]);
                })
                ->first();

            /** @var User $user */
            $user = User::withoutGlobalScopes()->firstOrCreate(
                ['email' => $email],
                [
                    'name' => "Admin ABIM {$state}",
                    'password' => self::PASSWORD,
                    'current_organization_id' => $abim->id,
                    'branch_id' => $branch?->id,
                    'is_active' => true,
                    'is_public_in_directory' => false,
                ]
            );

            // Betulkan pautan organisasi/cawangan untuk akaun sedia ada tanpa
            // menimpa katalaluan yang mungkin telah ditukar.
            $user->forceFill([
                'name' => $user->name ?: "Admin ABIM {$state}",
                'current_organization_id' => $abim->id,
                'branch_id' => $branch?->id ?? $user->branch_id,
                'is_active' => true,
                'email_verified_at' => $user->email_verified_at ?? now(),
                'profile_completed_at' => $user->profile_completed_at ?? now(),
            ])->save();

            $user->syncRoles([$role->name]);

            $rows[] = [$email, $state, $branch?->name ?? '-'];
        }

        $this->command?->info('✅  '.count($rows).' akaun Admin Negeri ABIM sedia (katalaluan: '.self::PASSWORD.').');

        if ($this->command) {
            $this->command->table(['Emel', 'Negeri', 'Cawangan'], $rows);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CredentialSeeder extends Seeder
{
    public function run(): void
    {
        $pkpim = Organization::where('slug', 'pkpim')->first();
        $abim  = Organization::where('slug', 'abim')->first();
        $wadah = Organization::where('slug', 'wadah')->first();

        // ── Superadmin ────────────────────────────────────────────────────────
        $su = User::withoutGlobalScopes()->updateOrCreate(
            ['email' => 'superadmin@mywap.my'],
            [
                'name'                    => 'Super Admin',
                'ic_number'               => '700101011111',
                'password'                => Hash::make('password'),
                'phone'                   => '+60123456789',
                'current_organization_id' => $wadah?->id,
                'gender'                  => 'lelaki',
                'marital_status'          => 'berkahwin',
                'position'                => 'Presiden',
                'is_public_in_directory'  => true,
            ]
        );
        $su->assignRole('Superadmin');

        // ── Admin per Organisation ────────────────────────────────────────────
        $this->makeAdmin('admin-pkpim@mywap.my', 'Admin PKPIM', $pkpim);
        $this->makeAdmin('admin-abim@mywap.my',  'Admin ABIM',  $abim);
        $this->makeAdmin('admin-wadah@mywap.my', 'Admin WADAH', $wadah);

        // ── Member per Organisation ───────────────────────────────────────────
        $this->makeMember('member-pkpim@mywap.my', 'Member PKPIM', '111111111111', $pkpim);
        $this->makeMember('member-abim@mywap.my',  'Member ABIM',  '222222222222', $abim);
        $this->makeMember('member-wadah@mywap.my', 'Member WADAH', '333333333333', $wadah);

        $this->command->info('✅  7 credential users seeded: superadmin (1), admin (3), member (3) — password: password');
    }

    private function makeAdmin(string $email, string $name, ?Organization $org): void
    {
        $user = User::withoutGlobalScopes()->updateOrCreate(
            ['email' => $email],
            [
                'name'                    => $name,
                'password'                => Hash::make('password'),
                'current_organization_id' => $org?->id,
                'is_public_in_directory'  => false,
            ]
        );
        $user->assignRole('Admin');
    }

    private function makeMember(string $email, string $name, string $ic, ?Organization $org): void
    {
        $user = User::withoutGlobalScopes()->updateOrCreate(
            ['email' => $email],
            [
                'name'                    => $name,
                'ic_number'               => $ic,
                'password'                => Hash::make('password'),
                'current_organization_id' => $org?->id,
                'is_public_in_directory'  => false,
            ]
        );
        $user->assignRole('Member');
    }
}

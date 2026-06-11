<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * AdminUserSeeder
 *
 * Creates a Superadmin demo account that is attached to WADAH (most senior tier)
 * and assigned the 'Superadmin' role, granting cross-organization visibility.
 *
 * ⚠️  Change the credentials before deploying to production.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Superadmins are conventionally associated with the most senior tier.
        $wadah = Organization::where('slug', 'wadah')->first();

        /** @var User $superadmin */
        $superadmin = User::withoutGlobalScopes()->updateOrCreate(
            ['email' => 'superadmin@mywap.my'],
            [
                'name'                    => 'Super Admin',
                'password'                => Hash::make('password'),
                'dob'                     => null,
                'phone'                   => '+60123456789',
                'current_organization_id' => $wadah?->id,
            ]
        );

        $superadmin->assignRole('Superadmin');

        $this->command->info('✅  Superadmin seeded: superadmin@mywap.my / password');
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * RoleAndPermissionSeeder
 *
 * Defines the role hierarchy for the MyMarhalah ecosystem:
 *
 *  Superadmin  — Full cross-organization access; bypasses OrganizationScope.
 *  Admin       — Organization-level administrator (scoped to their NGO tier).
 *  Member      — Standard member with read access to their organization's content.
 *
 * Permissions follow the format: action.resource (e.g. view.events, manage.members)
 */
class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles & permissions to avoid stale data during re-seeding.
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ─── Permissions ────────────────────────────────────────────────────────

        $permissions = [
            // Members
            'view.members',
            'create.members',
            'edit.members',
            'delete.members',
            'transition.members',   // Trigger / override an age transition

            // Events
            'view.events',
            'create.events',
            'edit.events',
            'delete.events',

            // Finance / Payments
            'view.payments',
            'manage.payments',

            // Documents / S3 Library
            'view.documents',
            'manage.documents',

            // Settings
            'manage.settings',
            'manage.organizations',  // Superadmin only
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // ─── Roles ──────────────────────────────────────────────────────────────

        $member = Role::firstOrCreate(['name' => 'Member']);
        $member->syncPermissions([
            'view.members',
            'view.events',
            'view.payments',
            'view.documents',
        ]);

        $admin = Role::firstOrCreate(['name' => 'Admin']);
        $admin->syncPermissions([
            'view.members', 'create.members', 'edit.members',
            'view.events', 'create.events', 'edit.events', 'delete.events',
            'view.payments', 'manage.payments',
            'view.documents', 'manage.documents',
            'manage.settings',
        ]);

        // Superadmin gets all permissions unconditionally via Spatie's gate bypass.
        $superadmin = Role::firstOrCreate(['name' => 'Superadmin']);
        $superadmin->syncPermissions(Permission::all());

        $this->command->info('✅  Roles & permissions seeded: Superadmin, Admin, Member');
    }
}

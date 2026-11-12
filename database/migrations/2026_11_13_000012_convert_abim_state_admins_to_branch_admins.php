<?php

use App\Models\BranchAdmin;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('branch_admins') || ! Schema::hasTable('roles') || ! Schema::hasTable('model_has_roles')) {
            return;
        }

        $adminRoleId = DB::table('roles')->where('name', 'Admin')->value('id');
        $branchAdminRoleId = DB::table('roles')->where('name', 'Admin Cawangan')->value('id');

        if (! $adminRoleId || ! $branchAdminRoleId) {
            return;
        }

        $stateAdmins = DB::table('users')
            ->where('email', 'like', 'abim-%@mywap.my')
            ->where('name', 'like', 'Admin ABIM %')
            ->whereNotNull('branch_id')
            ->whereExists(function ($query) use ($adminRoleId) {
                $query->select(DB::raw(1))
                    ->from('model_has_roles')
                    ->whereColumn('model_has_roles.model_id', 'users.id')
                    ->where('model_has_roles.model_type', User::class)
                    ->where('model_has_roles.role_id', $adminRoleId);
            })
            ->get(['id', 'branch_id']);

        foreach ($stateAdmins as $user) {
            DB::table('model_has_roles')
                ->where('model_id', $user->id)
                ->where('model_type', User::class)
                ->where('role_id', $adminRoleId)
                ->delete();

            DB::table('model_has_roles')->insertOrIgnore([
                'role_id' => $branchAdminRoleId,
                'model_type' => User::class,
                'model_id' => $user->id,
            ]);

            DB::table('branch_admins')->insertOrIgnore([
                'branch_id' => $user->branch_id,
                'user_id' => $user->id,
                'created_by' => null,
                'role' => BranchAdmin::ROLE_MANAGER,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Data conversion is intentionally irreversible.
    }
};

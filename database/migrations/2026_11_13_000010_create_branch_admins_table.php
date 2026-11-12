<?php

use App\Models\BranchAdmin;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_admins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role', 20)->default(BranchAdmin::ROLE_MANAGER);
            $table->timestamps();

            $table->unique(['branch_id', 'user_id']);
            $table->index(['branch_id', 'role']);
        });

        if (! Schema::hasTable('roles') || ! Schema::hasTable('model_has_roles')) {
            return;
        }

        $roleId = DB::table('roles')->where('name', 'Admin Cawangan')->value('id');

        if (! $roleId) {
            return;
        }

        $userIds = DB::table('model_has_roles')
            ->where('role_id', $roleId)
            ->where('model_type', User::class)
            ->pluck('model_id');

        $users = DB::table('users')
            ->whereIn('id', $userIds)
            ->whereNotNull('branch_id')
            ->get(['id', 'branch_id']);

        foreach ($users as $user) {
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
        Schema::dropIfExists('branch_admins');
    }
};

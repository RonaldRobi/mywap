<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add branch_id foreign key to users.
     *
     * nullable — existing users won't have a branch yet; they'll pick one
     * when they next update their profile.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('branch_id')
                  ->nullable()
                  ->after('current_organization_id')
                  ->constrained('branches')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\Branch::class, 'branch_id');
            $table->dropColumn('branch_id');
        });
    }
};

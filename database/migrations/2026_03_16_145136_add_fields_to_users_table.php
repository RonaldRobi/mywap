<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds lifecycle-membership columns to the users table:
     * - dob: used by the Age Transition Engine to detect birthday milestones.
     * - phone: contact field required for NGO communications.
     * - current_organization_id: the FK that drives multi-tenancy scoping and UI theming.
     *   It is nullable so that a freshly created account (before seeding) is valid.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('dob')->nullable()->after('email');                          // Date of Birth
            $table->string('phone', 20)->nullable()->after('dob');                   // Mobile / WhatsApp
            $table->foreignId('current_organization_id')                             // Active NGO tier
                  ->nullable()
                  ->after('phone')
                  ->constrained('organizations')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\Organization::class, 'current_organization_id');
            $table->dropColumn(['dob', 'phone', 'current_organization_id']);
        });
    }
};

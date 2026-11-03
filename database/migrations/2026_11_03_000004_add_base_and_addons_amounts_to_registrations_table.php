<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Explicitly record how much of a registration was the base fee vs optional
     * add-ons. This makes "free registration vs purchased items" auditable even
     * when no Payment row exists (free registration). Existing rows default to 0.
     */
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->decimal('base_amount', 12, 2)->default(0)->after('ticket_type');
            $table->decimal('addons_amount', 12, 2)->default(0)->after('base_amount');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn(['base_amount', 'addons_amount']);
        });
    }
};

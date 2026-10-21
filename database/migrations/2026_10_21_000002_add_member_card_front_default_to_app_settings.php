<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Design depan kad keahlian lalai (satu untuk semua organisasi).
 * Organisasi yang ada design sendiri (organizations.card_front_path) akan
 * mengatasi nilai lalai ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('app_settings') && ! Schema::hasColumn('app_settings', 'member_card_front_path')) {
            Schema::table('app_settings', function (Blueprint $table) {
                $table->string('member_card_front_path')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('app_settings') && Schema::hasColumn('app_settings', 'member_card_front_path')) {
            Schema::table('app_settings', function (Blueprint $table) {
                $table->dropColumn('member_card_front_path');
            });
        }
    }
};

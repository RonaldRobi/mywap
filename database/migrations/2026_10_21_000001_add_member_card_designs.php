<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reka bentuk kad keahlian (saiz kad TNG 54 × 85 mm, kanvas 638 × 1004 px).
 *  - Depan: satu design per organisasi (organizations.card_front_path).
 *  - Belakang: satu design untuk semua (app_settings.member_card_back_path).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('organizations', 'card_front_path')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->string('card_front_path')->nullable()->after('logo_path');
            });
        }

        if (Schema::hasTable('app_settings') && ! Schema::hasColumn('app_settings', 'member_card_back_path')) {
            Schema::table('app_settings', function (Blueprint $table) {
                $table->string('member_card_back_path')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('organizations', 'card_front_path')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->dropColumn('card_front_path');
            });
        }

        if (Schema::hasTable('app_settings') && Schema::hasColumn('app_settings', 'member_card_back_path')) {
            Schema::table('app_settings', function (Blueprint $table) {
                $table->dropColumn('member_card_back_path');
            });
        }
    }
};

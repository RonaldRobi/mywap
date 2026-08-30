<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->string('mobile_login_title', 120)->nullable()->after('login_image_path');
            $table->string('mobile_login_subtitle', 255)->nullable()->after('mobile_login_title');
            $table->string('mobile_login_background_start', 7)->default('#F4F6F1')->after('mobile_login_subtitle');
            $table->string('mobile_login_background_end', 7)->default('#EDF5EE')->after('mobile_login_background_start');
            $table->string('mobile_login_accent', 7)->default('#2F6B32')->after('mobile_login_background_end');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn(['mobile_login_title', 'mobile_login_subtitle', 'mobile_login_background_start', 'mobile_login_background_end', 'mobile_login_accent']);
        });
    }
};

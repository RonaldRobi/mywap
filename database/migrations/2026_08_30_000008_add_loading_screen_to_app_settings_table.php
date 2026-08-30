<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->string('loading_screen_gif_path')->nullable()->after('mobile_login_accent');
            $table->string('loading_screen_background_start', 7)->default('#071525')->after('loading_screen_gif_path');
            $table->string('loading_screen_background_end', 7)->default('#2F6B32')->after('loading_screen_background_start');
            $table->unsignedInteger('loading_screen_duration_ms')->default(2500)->after('loading_screen_background_end');
            $table->boolean('loading_screen_enabled')->default(true)->after('loading_screen_duration_ms');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn([
                'loading_screen_gif_path',
                'loading_screen_background_start',
                'loading_screen_background_end',
                'loading_screen_duration_ms',
                'loading_screen_enabled',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->string('splash_image_path')->nullable()->after('system_logo_path');
            $table->string('splash_background_color', 20)->default('#0f172a')->after('splash_image_path');
            $table->string('splash_title')->nullable()->after('splash_background_color');
            $table->unsignedInteger('splash_duration_ms')->default(1800)->after('splash_title');
            $table->boolean('splash_enabled')->default(true)->after('splash_duration_ms');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn([
                'splash_image_path',
                'splash_background_color',
                'splash_title',
                'splash_duration_ms',
                'splash_enabled',
            ]);
        });
    }
};

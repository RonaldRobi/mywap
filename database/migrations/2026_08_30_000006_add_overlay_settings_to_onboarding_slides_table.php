<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onboarding_slides', function (Blueprint $table) {
            $table->string('overlay_start_color', 7)->default('#071525')->after('text_color');
            $table->string('overlay_end_color', 7)->default('#071525')->after('overlay_start_color');
            $table->unsignedTinyInteger('overlay_start_opacity')->default(0)->after('overlay_end_color');
            $table->unsignedTinyInteger('overlay_end_opacity')->default(90)->after('overlay_start_opacity');
        });
    }

    public function down(): void
    {
        Schema::table('onboarding_slides', function (Blueprint $table) {
            $table->dropColumn(['overlay_start_color', 'overlay_end_color', 'overlay_start_opacity', 'overlay_end_opacity']);
        });
    }
};

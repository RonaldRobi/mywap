<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onboarding_slides', function (Blueprint $table) {
            // Kedudukan gradient dalam peratus dari ATAS skrin.
            // 0 = paling atas, 100 = paling bawah.
            $table->unsignedTinyInteger('overlay_start_position')->default(0)->after('overlay_end_opacity');
            $table->unsignedTinyInteger('overlay_end_position')->default(100)->after('overlay_start_position');
        });
    }

    public function down(): void
    {
        Schema::table('onboarding_slides', function (Blueprint $table) {
            $table->dropColumn(['overlay_start_position', 'overlay_end_position']);
        });
    }
};

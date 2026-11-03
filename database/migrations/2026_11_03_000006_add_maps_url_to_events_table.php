<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Explicit Google Maps link for physical events. Previously the map button
     * was derived by searching `location_or_link`, which is fine for legacy
     * rows but wrong for a proper venue name. Null keeps the old behaviour.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'maps_url')) {
                $table->string('maps_url', 500)->nullable()->after('location_or_link');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'maps_url')) {
                $table->dropColumn('maps_url');
            }
        });
    }
};

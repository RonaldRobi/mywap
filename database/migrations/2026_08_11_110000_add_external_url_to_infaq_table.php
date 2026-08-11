<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add an optional external payment URL to infaq campaigns.
     *
     * When present, the campaign is "external" (e.g. a DOKU Payment Link /
     * catalog page managed by the organisation directly). myWAP only displays
     * the campaign and links out to that URL; no internal donation flow,
     * webhook, or progress tracking is performed for these.
     */
    public function up(): void
    {
        Schema::table('infaq', function (Blueprint $table) {
            if (! Schema::hasColumn('infaq', 'external_url')) {
                $table->string('external_url', 1000)->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('infaq', function (Blueprint $table) {
            if (Schema::hasColumn('infaq', 'external_url')) {
                $table->dropColumn('external_url');
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store the payment channel (e.g. INTERNET_BANKING_FPX, EWALLET_TNG) chosen
     * by the payer. Used by DOKU (and informational for BayarCash).
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'channel')) {
                $table->string('channel')->nullable()->after('gateway_ref');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'channel')) {
                $table->dropColumn('channel');
            }
        });
    }
};

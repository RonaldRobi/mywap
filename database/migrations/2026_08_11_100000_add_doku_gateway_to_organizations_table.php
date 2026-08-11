<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add DOKU (Malaysia) Payment Gateway credentials to organizations, plus a
     * per-organisation gateway selector so superadmin can choose which gateway
     * each organisation uses (bayarcash or doku).
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            // Which gateway this organisation collects money through.
            // Values: 'bayarcash' | 'doku'. Nullable so existing orgs keep
            // their implicit BayarCash behaviour until explicitly set.
            $table->string('payment_gateway')->nullable()->after('bayarcash_environment');

            // DOKU Malaysia Payment API v3 credentials (per environment set).
            $table->string('doku_client_id')->nullable()->after('payment_gateway');
            $table->text('doku_api_key')->nullable()->after('doku_client_id');
            $table->text('doku_secret_key')->nullable()->after('doku_api_key');
            $table->string('doku_environment')->default('sandbox')->after('doku_secret_key');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'payment_gateway',
                'doku_client_id',
                'doku_api_key',
                'doku_secret_key',
                'doku_environment',
            ]);
        });
    }
};

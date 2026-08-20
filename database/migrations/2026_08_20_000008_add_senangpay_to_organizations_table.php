<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('senangpay_merchant_id')->nullable()->after('doku_environment');
            $table->string('senangpay_secret_key')->nullable()->after('senangpay_merchant_id');
            $table->string('senangpay_environment')->nullable()->after('senangpay_secret_key');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['senangpay_merchant_id', 'senangpay_secret_key', 'senangpay_environment']);
        });
    }
};

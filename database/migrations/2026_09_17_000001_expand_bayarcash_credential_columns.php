<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * BayarCash Personal Access Token is a JWT (~1000+ chars) and the portal
     * key can be long too. The original varchar(255) columns truncate/reject
     * them, so widen to TEXT. senangpay_secret_key is widened as well to match
     * its 500-char validation rule.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->text('bayarcash_api_token')->nullable()->change();
            $table->text('bayarcash_portal_key')->nullable()->change();
            $table->text('senangpay_secret_key')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('bayarcash_api_token')->nullable()->change();
            $table->string('bayarcash_portal_key')->nullable()->change();
            $table->string('senangpay_secret_key')->nullable()->change();
        });
    }
};

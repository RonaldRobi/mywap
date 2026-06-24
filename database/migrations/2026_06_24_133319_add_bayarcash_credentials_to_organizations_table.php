<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('bayarcash_api_token')->nullable()->after('fee_amount');
            $table->string('bayarcash_portal_key')->nullable()->after('bayarcash_api_token');
            $table->text('bayarcash_secret_key')->nullable()->after('bayarcash_portal_key');
            $table->string('bayarcash_environment')->default('sandbox')->after('bayarcash_secret_key');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'bayarcash_api_token',
                'bayarcash_portal_key',
                'bayarcash_secret_key',
                'bayarcash_environment',
            ]);
        });
    }
};

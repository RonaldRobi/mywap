<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->string('onesender_url')->nullable();
            $table->text('onesender_api_key')->nullable();
            $table->string('onesender_contact')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn(['onesender_url', 'onesender_api_key', 'onesender_contact']);
        });
    }
};

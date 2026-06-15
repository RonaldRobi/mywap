<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->string('admin_contact_email')->nullable()->after('splash_enabled');
            $table->string('admin_contact_phone')->nullable()->after('admin_contact_email');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn(['admin_contact_email', 'admin_contact_phone']);
        });
    }
};

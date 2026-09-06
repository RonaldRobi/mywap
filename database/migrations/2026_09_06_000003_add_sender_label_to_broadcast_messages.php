<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Label sumber siaran yang jelas untuk paparan admin.
 *
 * Superadmin yang menghantar tanpa memilih organisasi sasaran (siaran
 * platform: 'all' merentas tier / 'specific_members') dianggap daripada
 * MyWAP, bukan daripada organisasi akaun log masuk mereka (cth. WADAH).
 * Column ini menyimpan label itu; bila NULL, paparan jatuh kepada nama
 * organisasi penghantar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broadcast_messages', function (Blueprint $table) {
            $table->string('sender_label')->nullable()->after('organization_id');
        });
    }

    public function down(): void
    {
        Schema::table('broadcast_messages', function (Blueprint $table) {
            $table->dropColumn('sender_label');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->decimal('member_price_per_unit', 10, 2)->nullable()->after('price_per_unit');
        });

        Schema::table('facility_bookings', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('contact_name')->nullable()->after('user_id');
            $table->string('contact_phone')->nullable()->after('contact_name');
        });
    }

    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->dropColumn('member_price_per_unit');
        });

        Schema::table('facility_bookings', function (Blueprint $table) {
            $table->dropColumn(['contact_name', 'contact_phone']);
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};

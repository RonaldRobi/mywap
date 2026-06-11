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
        Schema::table('users', function (Blueprint $table) {
            $table->string('member_no')->nullable()->after('id');
            $table->string('wadah_state')->nullable();
            $table->date('key_in_date')->nullable();
            $table->string('registration_year', 4)->nullable();
            $table->string('birth_year', 4)->nullable();
            $table->string('address_1')->nullable();
            $table->string('address_2')->nullable();
            $table->string('postcode')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('office_phone')->nullable();
            $table->string('home_phone')->nullable();
            $table->string('fax_number')->nullable();
            $table->string('legacy_update_note')->nullable();
            $table->text('notes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'member_no',
                'wadah_state',
                'key_in_date',
                'registration_year',
                'birth_year',
                'address_1',
                'address_2',
                'postcode',
                'city',
                'state',
                'office_phone',
                'home_phone',
                'fax_number',
                'legacy_update_note',
                'notes',
            ]);
        });
    }
};

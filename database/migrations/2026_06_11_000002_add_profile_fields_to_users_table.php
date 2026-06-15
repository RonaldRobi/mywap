<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('gender', ['lelaki', 'perempuan'])->nullable()->after('dob');
            $table->enum('marital_status', ['bujang', 'berkahwin', 'bercerai', 'duda/janda'])->nullable()->after('gender');
            $table->string('emergency_contact_name')->nullable()->after('notes');
            $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
            $table->string('position')->nullable()->after('emergency_contact_phone');
            $table->text('topics')->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['gender', 'marital_status', 'emergency_contact_name', 'emergency_contact_phone', 'position', 'topics']);
        });
    }
};

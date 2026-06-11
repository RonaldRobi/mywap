<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('branch_name')->nullable()->after('industry');
            $table->string('locality')->nullable()->after('branch_name');
            $table->string('profile_photo_path')->nullable()->after('locality');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['branch_name', 'locality', 'profile_photo_path']);
        });
    }
};

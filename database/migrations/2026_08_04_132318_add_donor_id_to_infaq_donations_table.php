<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('infaq_donations', function (Blueprint $table) {
            $table->foreignId('donor_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('infaq_donations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('donor_id');
        });
    }
};

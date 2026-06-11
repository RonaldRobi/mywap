<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('industry')->nullable()->after('current_profession');
            $table->string('expertise')->nullable()->after('industry');
            $table->string('linkedin_url')->nullable()->after('expertise');
            $table->boolean('is_public_in_directory')->default(true)->after('linkedin_url');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['industry', 'expertise', 'linkedin_url', 'is_public_in_directory']);
        });
    }
};

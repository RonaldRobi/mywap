<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->json('raw_data')->nullable()->after('document_path');
        });

        Schema::table('registration_imports', function (Blueprint $table) {
            $table->json('headers')->nullable()->after('filename');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn('raw_data');
        });

        Schema::table('registration_imports', function (Blueprint $table) {
            $table->dropColumn('headers');
        });
    }
};

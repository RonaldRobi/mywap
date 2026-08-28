<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->json('price_tiers')->nullable()->after('price');
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->string('ticket_type')->nullable()->after('status');
            $table->string('document_path')->nullable()->after('ticket_type');
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn('price_tiers');
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn(['ticket_type', 'document_path']);
        });
    }
};

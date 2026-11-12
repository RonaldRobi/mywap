<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // Null = kategori global (dikongsi semua organisasi, urus Superadmin).
            // Nilai = kategori khusus organisasi tersebut sahaja.
            $table->foreignId('organization_id')
                ->nullable()
                ->after('description')
                ->constrained('organizations')
                ->nullOnDelete();

            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
        });
    }
};

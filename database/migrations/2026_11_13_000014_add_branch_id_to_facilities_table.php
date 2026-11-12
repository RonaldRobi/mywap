<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('organization_id')
                ->constrained('branches')
                ->nullOnDelete();

            $table->index(['branch_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'name']);
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};

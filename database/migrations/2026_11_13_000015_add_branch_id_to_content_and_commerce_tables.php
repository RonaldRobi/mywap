<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $organizationTables = [
        'articles',
        'videos',
        'library_items',
        'announcements',
        'infaq',
        'forms',
    ];

    public function up(): void
    {
        foreach ($this->organizationTables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'branch_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('branch_id')
                    ->nullable()
                    ->after('organization_id')
                    ->constrained('branches')
                    ->nullOnDelete();

                $blueprint->index(['branch_id']);
            });
        }

        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'branch_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('organisasi_id')
                    ->constrained('branches')
                    ->nullOnDelete();

                $table->index(['branch_id']);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->organizationTables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'branch_id')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropIndex(['branch_id']);
                    $blueprint->dropConstrainedForeignId('branch_id');
                });
            }
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'branch_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex(['branch_id']);
                $table->dropConstrainedForeignId('branch_id');
            });
        }
    }
};

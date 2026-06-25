<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetSequences extends Command
{
    protected $signature = 'db:reset-sequences
                            {--table=* : Only reset specific tables (comma-separated)}';

    protected $description = 'Reset PostgreSQL sequences to MAX(id) for all tables';

    public function handle(): int
    {
        $tables = $this->option('table') ?: [
            'events', 'roles', 'organizations', 'campaigns', 'library_items',
            'permissions', 'dashboard_banners', 'infaq', 'users',
        ];

        foreach ($tables as $table) {
            $table = trim($table);
            $max = DB::table($table)->max('id') ?? 1;
            DB::statement("SELECT setval('{$table}_id_seq', {$max})");
            $this->info("{$table}_id_seq → {$max}");
        }

        $this->newLine();
        $this->info('All sequences reset successfully.');

        return self::SUCCESS;
    }
}

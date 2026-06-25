<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE polls DROP CONSTRAINT IF EXISTS polls_target_type_check');
            DB::statement("ALTER TABLE polls ALTER COLUMN target_type TYPE VARCHAR(20)");
            DB::statement("ALTER TABLE polls ALTER COLUMN target_type SET DEFAULT 'all'");
            DB::statement("ALTER TABLE polls ALTER COLUMN type TYPE VARCHAR(20)");
            DB::statement("ALTER TABLE polls ALTER COLUMN type SET DEFAULT 'poll'");
        } elseif ($driver === 'mysql') {
            DB::statement("ALTER TABLE polls MODIFY target_type VARCHAR(20) DEFAULT 'all'");
            DB::statement("ALTER TABLE polls MODIFY type VARCHAR(20) DEFAULT 'poll'");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE polls ALTER COLUMN target_type TYPE VARCHAR(20)");
            DB::statement("ALTER TABLE polls ALTER COLUMN target_type SET DEFAULT 'all'");
            DB::statement("ALTER TABLE polls ALTER COLUMN type TYPE VARCHAR(20)");
            DB::statement("ALTER TABLE polls ALTER COLUMN type SET DEFAULT 'poll'");
        } elseif ($driver === 'mysql') {
            DB::statement("ALTER TABLE polls MODIFY target_type VARCHAR(20) DEFAULT 'all'");
            DB::statement("ALTER TABLE polls MODIFY type VARCHAR(20) DEFAULT 'poll'");
        }
    }
};

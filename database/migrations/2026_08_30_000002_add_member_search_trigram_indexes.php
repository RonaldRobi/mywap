<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Trigram GIN indexes let PG use `LIKE '%term%'` (leading-wildcard)
        // without a full seq scan — MemberSearchController search at 100k+ users.
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_users_name_trgm ON users USING gin (name gin_trgm_ops)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_users_member_no_trgm ON users USING gin (member_no gin_trgm_ops)');
        } catch (Throwable $e) {
            // Tolerant: if the extension is unavailable, search falls back to seq scan.
            report($e);
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS idx_users_name_trgm');
        DB::statement('DROP INDEX IF EXISTS idx_users_member_no_trgm');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Buang check constraint lama pada broadcast_messages.target_criteria yang
 * tertinggal (dari penukaran MySQL enum → Postgres). Constraint tersebut
 * hanya membenarkan nilai lama ('all', 'unpaid_fees', 'specific_usrah')
 * sedangkan column sekarang adalah enum broadcast_target_criteria
 * ('all', 'organization', 'specific_members') — menyebabkan insert siaran
 * ke organisasi/ahli spesifik gagal dengan SQLSTATE 23514 (check violation).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE broadcast_messages DROP CONSTRAINT IF EXISTS broadcast_messages_target_criteria_check');
        }
    }

    public function down(): void
    {
        // Tiada pemulihan diperlukan; constraint lama tidak sepatutnya wujud.
    }
};

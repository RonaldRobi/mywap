<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lajur broadcast_messages.target_criteria masih enum di PostgreSQL/MySQL
 * dengan nilai lama sahaja. Ciri siaran cawangan dan Pendukung menulis
 * nilai 'branch' / 'supporters', jadi production menolak insert itu dengan
 * SQLSTATE 22P02 dan admin cawangan nampak ralat 500.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // Buang sisa CHECK lama jika migrasi 2026_09_06 belum sempat jalan.
            DB::statement('ALTER TABLE broadcast_messages DROP CONSTRAINT IF EXISTS broadcast_messages_target_criteria_check');

            $typeExists = DB::selectOne(
                "SELECT 1 FROM pg_type WHERE typname = 'broadcast_target_criteria'"
            ) !== null;

            if ($typeExists) {
                DB::statement("ALTER TYPE broadcast_target_criteria ADD VALUE IF NOT EXISTS 'branch'");
                DB::statement("ALTER TYPE broadcast_target_criteria ADD VALUE IF NOT EXISTS 'supporters'");
            }

            return;
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE broadcast_messages MODIFY COLUMN target_criteria ENUM('all', 'organization', 'branch', 'specific_members', 'supporters') NOT NULL DEFAULT 'all'");
        }
    }

    public function down(): void
    {
        // Nilai enum tidak boleh dibuang tanpa membina semula lajur.
    }
};

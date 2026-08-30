<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // forms — active-form lookups scoped by event / organization
        Schema::table('forms', function (Blueprint $table) {
            $table->index(['event_id', 'is_active']);
            $table->index(['organization_id', 'is_active']);
        });

        // event_comments — latest() ordering parity with article/news comments
        Schema::table('event_comments', function (Blueprint $table) {
            $table->index(['event_id', 'created_at']);
        });

        // registrations — event-scoped user checks / dedup
        Schema::table('registrations', function (Blueprint $table) {
            $table->index(['event_id', 'user_id']);
        });

        // poll_answers — GROUP BY counts (results tabulation)
        Schema::table('poll_answers', function (Blueprint $table) {
            $table->index(['poll_question_id', 'poll_option_id']);
        });

        // article_reactions — per-user reaction lookup / dedup guard
        Schema::table('article_reactions', function (Blueprint $table) {
            $table->unique(['article_id', 'user_id']);
        });

        // ── PostgreSQL partial indexes (skip on other drivers) ─────────────
        // Partial index on notifications is not expressible via Schema,
        // so it is guarded by driver to keep sqlite test runs green.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX IF NOT EXISTS idx_notifications_unread ON notifications (notifiable_type, notifiable_id) WHERE read_at IS NULL');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_payments_success_created ON payments (created_at) WHERE status = \'successful\'');
        }
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropIndex(['event_id', 'is_active']);
            $table->dropIndex(['organization_id', 'is_active']);
        });

        Schema::table('event_comments', function (Blueprint $table) {
            $table->dropIndex(['event_id', 'created_at']);
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->dropIndex(['event_id', 'user_id']);
        });

        Schema::table('poll_answers', function (Blueprint $table) {
            $table->dropIndex(['poll_question_id', 'poll_option_id']);
        });

        Schema::table('article_reactions', function (Blueprint $table) {
            $table->dropUnique(['article_id', 'user_id']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS idx_notifications_unread');
            DB::statement('DROP INDEX IF EXISTS idx_payments_success_created');
        }
    }
};

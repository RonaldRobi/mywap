<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('broadcast_messages')
            ->where('target_criteria', 'unpaid_fees')
            ->update(['target_criteria' => 'organization']);

        DB::table('broadcast_messages')
            ->where('target_criteria', 'specific_usrah')
            ->update(['target_criteria' => 'specific_members']);

        if (DB::getDriverName() === 'sqlite') {
            DB::statement("
                CREATE TABLE broadcast_messages_new (
                    id integer primary key autoincrement not null,
                    organization_id integer not null,
                    target_organization_id integer references organizations(id) on delete set null,
                    title varchar not null,
                    content text not null,
                    target_criteria varchar check (target_criteria in ('all', 'organization', 'specific_members')) not null default 'all',
                    recipient_ids text,
                    notification_channels text,
                    email_use_template tinyint(1) not null default 0,
                    sent_at datetime,
                    created_at datetime,
                    updated_at datetime,
                    foreign key (organization_id) references organizations (id) on delete cascade
                )
            ");

            DB::statement("
                INSERT INTO broadcast_messages_new (id, organization_id, title, content, target_criteria, sent_at, created_at, updated_at)
                SELECT id, organization_id, title, content, target_criteria, sent_at, created_at, updated_at
                FROM broadcast_messages
            ");

            DB::statement("DROP TABLE broadcast_messages");
            DB::statement("ALTER TABLE broadcast_messages_new RENAME TO broadcast_messages");

            Schema::table('broadcast_messages', function (Blueprint $table) {
                $table->index(['organization_id', 'target_criteria']);
            });
        } else {
            Schema::table('broadcast_messages', function (Blueprint $table) {
                $table->dropForeign(['usrah_group_id']);
                $table->dropColumn('usrah_group_id');
                $table->dropIndex(['organization_id', 'target_criteria']);
            });

            if (DB::getDriverName() === 'pgsql') {
                DB::statement("DO $$ BEGIN CREATE TYPE broadcast_target_criteria AS ENUM ('all', 'organization', 'specific_members'); EXCEPTION WHEN duplicate_object THEN NULL; END $$;");
                DB::statement("ALTER TABLE broadcast_messages ALTER COLUMN target_criteria DROP DEFAULT");
                DB::statement("ALTER TABLE broadcast_messages ALTER COLUMN target_criteria TYPE broadcast_target_criteria USING target_criteria::broadcast_target_criteria");
                DB::statement("ALTER TABLE broadcast_messages ALTER COLUMN target_criteria SET DEFAULT 'all'");
                DB::statement("ALTER TABLE broadcast_messages ALTER COLUMN target_criteria SET NOT NULL");
            } else {
                DB::statement("ALTER TABLE broadcast_messages MODIFY COLUMN target_criteria ENUM('all', 'organization', 'specific_members') NOT NULL DEFAULT 'all'");
            }

            Schema::table('broadcast_messages', function (Blueprint $table) {
                $table->foreignId('target_organization_id')->nullable()->after('organization_id')->constrained('organizations')->nullOnDelete();
                $table->json('recipient_ids')->nullable()->after('target_criteria');
                $table->json('notification_channels')->nullable()->after('recipient_ids');
                $table->boolean('email_use_template')->default(false)->after('notification_channels');

                $table->index(['organization_id', 'target_criteria']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('broadcast_messages', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'target_criteria']);
        });

        if (DB::getDriverName() === 'sqlite') {
            DB::statement("
                CREATE TABLE broadcast_messages_old (
                    id integer primary key autoincrement not null,
                    organization_id integer not null,
                    title varchar not null,
                    content text not null,
                    target_criteria varchar check (target_criteria in ('all', 'unpaid_fees', 'specific_usrah')) not null default 'all',
                    usrah_group_id integer references usrah_groups(id) on delete set null,
                    sent_at datetime,
                    created_at datetime,
                    updated_at datetime,
                    foreign key (organization_id) references organizations (id) on delete cascade
                )
            ");

            DB::statement("
                INSERT INTO broadcast_messages_old (id, organization_id, title, content, target_criteria, sent_at, created_at, updated_at)
                SELECT id, organization_id, title, content, target_criteria, sent_at, created_at, updated_at
                FROM broadcast_messages
            ");

            DB::statement("DROP TABLE broadcast_messages");
            DB::statement("ALTER TABLE broadcast_messages_old RENAME TO broadcast_messages");
        } else {
            Schema::table('broadcast_messages', function (Blueprint $table) {
                $table->dropForeign(['target_organization_id']);
                $table->dropColumn(['target_organization_id', 'recipient_ids', 'notification_channels', 'email_use_template']);

                $table->foreignId('usrah_group_id')->nullable()->constrained('usrah_groups')->nullOnDelete();
            });

            if (DB::getDriverName() === 'pgsql') {
                DB::statement("DO $$ BEGIN CREATE TYPE broadcast_target_criteria_old AS ENUM ('all', 'unpaid_fees', 'specific_usrah'); EXCEPTION WHEN duplicate_object THEN NULL; END $$;");
                DB::statement("ALTER TABLE broadcast_messages ALTER COLUMN target_criteria DROP DEFAULT");
                DB::statement("ALTER TABLE broadcast_messages ALTER COLUMN target_criteria TYPE broadcast_target_criteria_old USING target_criteria::broadcast_target_criteria_old");
                DB::statement("ALTER TABLE broadcast_messages ALTER COLUMN target_criteria SET DEFAULT 'all'");
                DB::statement("ALTER TABLE broadcast_messages ALTER COLUMN target_criteria SET NOT NULL");
            } else {
                DB::statement("ALTER TABLE broadcast_messages MODIFY COLUMN target_criteria ENUM('all', 'unpaid_fees', 'specific_usrah') NOT NULL DEFAULT 'all'");
            }
        }

        DB::table('broadcast_messages')
            ->where('target_criteria', 'organization')
            ->update(['target_criteria' => 'unpaid_fees']);

        DB::table('broadcast_messages')
            ->where('target_criteria', 'specific_members')
            ->update(['target_criteria' => 'specific_usrah']);

        Schema::table('broadcast_messages', function (Blueprint $table) {
            $table->index(['organization_id', 'target_criteria']);
        });
    }
};

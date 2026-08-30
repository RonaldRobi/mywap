<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement("
                CREATE TABLE facilities_new (
                    id integer primary key autoincrement not null,
                    organization_id integer not null,
                    name varchar not null,
                    description text,
                    location varchar,
                    type varchar check (type in ('hourly', 'halfday', 'daily')) not null default 'hourly',
                    price_per_unit numeric not null default '0',
                    member_price_per_unit numeric,
                    capacity integer,
                    image_path varchar,
                    is_active tinyint(1) not null default '1',
                    created_at datetime,
                    updated_at datetime,
                    foreign key (organization_id) references organizations (id) on delete cascade
                )
            ");

            DB::statement('
                INSERT INTO facilities_new (id, organization_id, name, description, location, type, price_per_unit, member_price_per_unit, capacity, image_path, is_active, created_at, updated_at)
                SELECT id, organization_id, name, description, location, type, price_per_unit, member_price_per_unit, capacity, image_path, is_active, created_at, updated_at
                FROM facilities
            ');

            DB::statement('DROP TABLE facilities');
            DB::statement('ALTER TABLE facilities_new RENAME TO facilities');
            DB::statement('CREATE INDEX facilities_organization_id_is_active_index ON facilities (organization_id, is_active)');
        } else {
            DB::statement("ALTER TABLE facilities MODIFY COLUMN type ENUM('hourly', 'halfday', 'daily') NOT NULL DEFAULT 'hourly'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement("
                CREATE TABLE facilities_old (
                    id integer primary key autoincrement not null,
                    organization_id integer not null,
                    name varchar not null,
                    description text,
                    location varchar,
                    type varchar check (type in ('hourly', 'daily')) not null default 'hourly',
                    price_per_unit numeric not null default '0',
                    member_price_per_unit numeric,
                    capacity integer,
                    image_path varchar,
                    is_active tinyint(1) not null default '1',
                    created_at datetime,
                    updated_at datetime,
                    foreign key (organization_id) references organizations (id) on delete cascade
                )
            ");

            DB::statement('
                INSERT INTO facilities_old (id, organization_id, name, description, location, type, price_per_unit, member_price_per_unit, capacity, image_path, is_active, created_at, updated_at)
                SELECT id, organization_id, name, description, location, type, price_per_unit, member_price_per_unit, capacity, image_path, is_active, created_at, updated_at
                FROM facilities
            ');

            DB::statement('DROP TABLE facilities');
            DB::statement('ALTER TABLE facilities_old RENAME TO facilities');
            DB::statement('CREATE INDEX facilities_organization_id_is_active_index ON facilities (organization_id, is_active)');
        } else {
            DB::statement("ALTER TABLE facilities MODIFY COLUMN type ENUM('hourly', 'daily') NOT NULL DEFAULT 'hourly'");
        }
    }
};

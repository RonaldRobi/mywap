<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Turutan ni PENTING untuk elak Error Foreign Key (FK).
     */
    public function run(): void
    {
        // 1. Matikan sekatan Foreign Key supaya MariaDB tak 'bising'
        Schema::disableForeignKeyConstraints();

        $this->call([
            // --- ASAS (Roles & Permissions) ---
            RolesTableSeeder::class,
            PermissionsTableSeeder::class,

            // --- STRUKTUR (Organizations) ---
            OrganizationsTableSeeder::class,

            // --- PENGGUNA (Users) ---
            UsersTableSeeder::class,

            // --- HUBUNGAN (Pivot Tables Spatie) ---
            // Wajib masuk untuk elak 403 Forbidden
            ModelHasRolesTableSeeder::class,

            // --- DATA KONTEN (Events, Infaq, Pustaka, Banners) ---
            EventsTableSeeder::class,
            InfaqTableSeeder::class,
            UsrahGroupsTableSeeder::class,
            
            // Masukkan yang baru kat sini biar dia jalan sekali 'go'
            LibraryItemsTableSeeder::class,
            CampaignsTableSeeder::class,
            DashboardBannersTableSeeder::class,

            // --- E-Commerce (Dummy Catalog) ---
            EcommerceDummySeeder::class,
            
            // Tambah lagi fail seeder iseed abang kat bawah ni kalau ada...
        ]);

        // 2. Hidupkan balik sekatan Foreign Key
        Schema::enableForeignKeyConstraints();
    }
}
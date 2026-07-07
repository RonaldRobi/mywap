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
        // 0. Copy placeholder media ke public storage
        $this->copySeedMedia();

        // 1. Matikan sekatan Foreign Key supaya MariaDB tak 'bising'
        Schema::disableForeignKeyConstraints();

        // --- POSTCODES (no FK, seed first) ---
        $this->call([
            PostcodeSeeder::class,
        ]);

        $this->call([
            RolesTableSeeder::class,
            PermissionsTableSeeder::class,

            // --- STRUKTUR (Organizations) ---
            OrganizationsTableSeeder::class,

            // --- KREDENSIAL (Users + Roles, guna updateOrCreate utk elak delete) ---
            CredentialSeeder::class,

            // --- DATA KONTEN (Events, Infaq, Pustaka, Banners) ---
            EventsTableSeeder::class,
            InfaqTableSeeder::class,
            UsrahGroupsTableSeeder::class,
            
            // Masukkan yang baru kat sini biar dia jalan sekali 'go'
            LibraryItemsTableSeeder::class,
            CampaignsTableSeeder::class,
            DashboardBannersTableSeeder::class,

            // --- ARTIKEL (categories, tags, then demo content) ---
            ArticleCategorySeeder::class,
            ArticleTagSeeder::class,
            ArticleDemoSeeder::class,

            // --- VIDEO ---
            VideoDemoSeeder::class,

            // --- BERITA (Info Terkini) ---
            NewsDemoSeeder::class,

            // --- UNDIAN (Poll) ---
            PollDemoSeeder::class,

            // --- E-Commerce (Dummy Catalog) ---
            EcommerceDummySeeder::class,

            // --- KNOWLEDGE BASE (Chatbot) ---
            KnowledgeBaseSeeder::class,

            // --- JAWATAN ORGANISASI ---
            OrganizationPositionSeeder::class,

            // --- DEMO DATA YURAN (Fee Module) ---
            FeeDemoSeeder::class,
            
            // Tambah lagi fail seeder iseed abang kat bawah ni kalau ada...
        ]);

        // 2. Hidupkan balik sekatan Foreign Key
        Schema::enableForeignKeyConstraints();
    }

    private function copySeedMedia(): void
    {
        // Pastikan storage symlink wujud
        $link = public_path('storage');
        if (! file_exists($link)) {
            try {
                symlink(storage_path('app/public'), $link);
            } catch (\Throwable $e) {
                // maybe already exists
            }
        }

        $source = database_path('seeders/media');
        $dest = storage_path('app/public');

        if (! is_dir($source)) {
            return;
        }

        $files = \File::allFiles($source);
        foreach ($files as $file) {
            $relative = $file->getRelativePathname();
            $target = $dest . '/' . $relative;

            if (! is_dir(dirname($target))) {
                mkdir(dirname($target), 0755, true);
            }

            if (! file_exists($target)) {
                copy($file->getRealPath(), $target);
            }
        }
    }
}
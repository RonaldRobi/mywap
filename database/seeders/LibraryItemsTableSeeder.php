<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class LibraryItemsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('library_items')->delete();
        
        \DB::table('library_items')->insert(array (
            0 => 
            array (
                'id' => 1,
                'organization_id' => 1,
                'title' => 'Kunci Dakwah Generasi',
                'description' => 'Dummy buku tarbiah untuk semakan UI.',
                'file_path' => '/storage/library/dummy/org-1/kunci-dakwah-generasi-1.pdf',
                'category' => 'Tarbiah',
                'created_at' => '2026-03-16 16:55:59',
                'updated_at' => '2026-03-16 17:01:04',
                'cover_image_path' => '/storage/library/covers/org-1/kunci-dakwah-generasi-1.svg',
            ),
            1 => 
            array (
                'id' => 2,
                'organization_id' => 1,
                'title' => 'Panduan Usrah Mingguan',
                'description' => 'Contoh modul usrah dalam format PDF.',
                'file_path' => '/storage/library/dummy/org-1/panduan-usrah-mingguan-2.pdf',
                'category' => 'Modul',
                'created_at' => '2026-03-16 16:55:59',
                'updated_at' => '2026-03-16 17:01:04',
                'cover_image_path' => '/storage/library/covers/org-1/panduan-usrah-mingguan-2.svg',
            ),
            2 => 
            array (
                'id' => 3,
                'organization_id' => 1,
                'title' => 'Fiqh Masyarakat Madani',
                'description' => 'Bahan bacaan komuniti.',
                'file_path' => '/storage/library/dummy/org-1/fiqh-masyarakat-madani-3.pdf',
                'category' => 'Ilmiah',
                'created_at' => '2026-03-16 16:55:59',
                'updated_at' => '2026-03-16 17:01:04',
                'cover_image_path' => '/storage/library/covers/org-1/fiqh-masyarakat-madani-3.svg',
            ),
            3 => 
            array (
                'id' => 4,
                'organization_id' => 1,
                'title' => 'Strategi Belia Berimpak',
                'description' => 'Rujukan kepimpinan belia.',
                'file_path' => '/storage/library/dummy/org-1/strategi-belia-berimpak-4.pdf',
                'category' => 'Kepimpinan',
                'created_at' => '2026-03-16 16:55:59',
                'updated_at' => '2026-03-16 17:01:04',
                'cover_image_path' => '/storage/library/covers/org-1/strategi-belia-berimpak-4.svg',
            ),
            4 => 
            array (
                'id' => 5,
                'organization_id' => 1,
                'title' => 'Adab Aktivis Muslim',
                'description' => 'Ringkasan adab dan akhlak gerakan.',
                'file_path' => '/storage/library/dummy/org-1/adab-aktivis-muslim-5.pdf',
                'category' => 'Tazkiyah',
                'created_at' => '2026-03-16 16:55:59',
                'updated_at' => '2026-03-16 17:01:04',
                'cover_image_path' => '/storage/library/covers/org-1/adab-aktivis-muslim-5.svg',
            ),
            5 => 
            array (
                'id' => 6,
                'organization_id' => 2,
                'title' => 'Kunci Dakwah Generasi',
                'description' => 'Dummy buku tarbiah untuk semakan UI.',
                'file_path' => '/storage/library/dummy/org-2/kunci-dakwah-generasi-1.pdf',
                'category' => 'Tarbiah',
                'created_at' => '2026-03-16 17:01:04',
                'updated_at' => '2026-03-16 17:01:04',
                'cover_image_path' => '/storage/library/covers/org-2/kunci-dakwah-generasi-1.svg',
            ),
            6 => 
            array (
                'id' => 7,
                'organization_id' => 2,
                'title' => 'Panduan Usrah Mingguan',
                'description' => 'Contoh modul usrah dalam format PDF.',
                'file_path' => '/storage/library/dummy/org-2/panduan-usrah-mingguan-2.pdf',
                'category' => 'Modul',
                'created_at' => '2026-03-16 17:01:04',
                'updated_at' => '2026-03-16 17:01:04',
                'cover_image_path' => '/storage/library/covers/org-2/panduan-usrah-mingguan-2.svg',
            ),
            7 => 
            array (
                'id' => 8,
                'organization_id' => 2,
                'title' => 'Fiqh Masyarakat Madani',
                'description' => 'Bahan bacaan komuniti.',
                'file_path' => '/storage/library/dummy/org-2/fiqh-masyarakat-madani-3.pdf',
                'category' => 'Ilmiah',
                'created_at' => '2026-03-16 17:01:04',
                'updated_at' => '2026-03-16 17:01:04',
                'cover_image_path' => '/storage/library/covers/org-2/fiqh-masyarakat-madani-3.svg',
            ),
            8 => 
            array (
                'id' => 9,
                'organization_id' => 2,
                'title' => 'Strategi Belia Berimpak',
                'description' => 'Rujukan kepimpinan belia.',
                'file_path' => '/storage/library/dummy/org-2/strategi-belia-berimpak-4.pdf',
                'category' => 'Kepimpinan',
                'created_at' => '2026-03-16 17:01:04',
                'updated_at' => '2026-03-16 17:01:04',
                'cover_image_path' => '/storage/library/covers/org-2/strategi-belia-berimpak-4.svg',
            ),
            9 => 
            array (
                'id' => 10,
                'organization_id' => 2,
                'title' => 'Adab Aktivis Muslim',
                'description' => 'Ringkasan adab dan akhlak gerakan.',
                'file_path' => '/storage/library/dummy/org-2/adab-aktivis-muslim-5.pdf',
                'category' => 'Tazkiyah',
                'created_at' => '2026-03-16 17:01:04',
                'updated_at' => '2026-03-16 17:01:04',
                'cover_image_path' => '/storage/library/covers/org-2/adab-aktivis-muslim-5.svg',
            ),
            10 => 
            array (
                'id' => 11,
                'organization_id' => 3,
                'title' => 'Kunci Dakwah Generasi',
                'description' => 'Dummy buku tarbiah untuk semakan UI.',
                'file_path' => '/storage/library/dummy/org-3/kunci-dakwah-generasi-1.pdf',
                'category' => 'Tarbiah',
                'created_at' => '2026-03-16 17:01:04',
                'updated_at' => '2026-03-16 17:01:04',
                'cover_image_path' => '/storage/library/covers/org-3/kunci-dakwah-generasi-1.svg',
            ),
            11 => 
            array (
                'id' => 12,
                'organization_id' => 3,
                'title' => 'Panduan Usrah Mingguan',
                'description' => 'Contoh modul usrah dalam format PDF.',
                'file_path' => '/storage/library/dummy/org-3/panduan-usrah-mingguan-2.pdf',
                'category' => 'Modul',
                'created_at' => '2026-03-16 17:01:04',
                'updated_at' => '2026-03-16 17:01:04',
                'cover_image_path' => '/storage/library/covers/org-3/panduan-usrah-mingguan-2.svg',
            ),
            12 => 
            array (
                'id' => 13,
                'organization_id' => 3,
                'title' => 'Fiqh Masyarakat Madani',
                'description' => 'Bahan bacaan komuniti.',
                'file_path' => '/storage/library/dummy/org-3/fiqh-masyarakat-madani-3.pdf',
                'category' => 'Ilmiah',
                'created_at' => '2026-03-16 17:01:04',
                'updated_at' => '2026-03-16 17:01:04',
                'cover_image_path' => '/storage/library/covers/org-3/fiqh-masyarakat-madani-3.svg',
            ),
            13 => 
            array (
                'id' => 14,
                'organization_id' => 3,
                'title' => 'Strategi Belia Berimpak',
                'description' => 'Rujukan kepimpinan belia.',
                'file_path' => '/storage/library/dummy/org-3/strategi-belia-berimpak-4.pdf',
                'category' => 'Kepimpinan',
                'created_at' => '2026-03-16 17:01:04',
                'updated_at' => '2026-03-16 17:01:04',
                'cover_image_path' => '/storage/library/covers/org-3/strategi-belia-berimpak-4.svg',
            ),
            14 => 
            array (
                'id' => 15,
                'organization_id' => 3,
                'title' => 'Adab Aktivis Muslim',
                'description' => 'Ringkasan adab dan akhlak gerakan.',
                'file_path' => '/storage/library/dummy/org-3/adab-aktivis-muslim-5.pdf',
                'category' => 'Tazkiyah',
                'created_at' => '2026-03-16 17:01:04',
                'updated_at' => '2026-03-16 17:01:04',
                'cover_image_path' => '/storage/library/covers/org-3/adab-aktivis-muslim-5.svg',
            ),
        ));
        
        
    }
}
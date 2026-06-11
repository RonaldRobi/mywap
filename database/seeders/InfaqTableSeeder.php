<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class InfaqTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('infaq')->delete();
        
        \DB::table('infaq')->insert(array (
            0 => 
            array (
                'id' => 1,
                'organization_id' => NULL,
                'title' => 'Infaq Masjid Al-Iman',
                'description' => 'Bantu kami membina kemudahan solat yang lebih selesa untuk komuniti.',
                'image_path' => '/storage/infaq/demo_infaq_1.svg',
                'type' => 'progress',
                'target_amount' => 50000,
                'collected_amount' => 23750,
                'is_active' => 1,
                'display_order' => 1,
                'created_at' => '2026-03-17 03:27:21',
                'updated_at' => '2026-03-17 03:27:21',
            ),
            1 => 
            array (
                'id' => 2,
                'organization_id' => NULL,
                'title' => 'Infaq Anak Yatim Ramadan',
                'description' => 'Sumbangan untuk anak-anak yatim sempena bulan Ramadan yang mulia.',
                'image_path' => '/storage/infaq/demo_infaq_2.svg',
                'type' => 'one_off',
                'target_amount' => NULL,
                'collected_amount' => 8100,
                'is_active' => 1,
                'display_order' => 2,
                'created_at' => '2026-03-17 03:27:21',
                'updated_at' => '2026-03-17 03:27:21',
            ),
            2 => 
            array (
                'id' => 3,
                'organization_id' => NULL,
                'title' => 'Dana Pendidikan Islam',
                'description' => 'Tajaan kelas Quran & fardhu ain untuk pelajar kurang berkemampuan.',
                'image_path' => '/storage/infaq/demo_infaq_3.svg',
                'type' => 'progress',
                'target_amount' => 15000,
                'collected_amount' => 9600,
                'is_active' => 1,
                'display_order' => 3,
                'created_at' => '2026-03-17 03:27:21',
                'updated_at' => '2026-03-17 03:27:21',
            ),
            3 => 
            array (
                'id' => 4,
                'organization_id' => NULL,
                'title' => 'Infaq Buku & Pustaka',
                'description' => 'Sumbangkan untuk pengembangan koleksi buku perpustakaan komuniti.',
                'image_path' => '/storage/infaq/demo_infaq_4.svg',
                'type' => 'progress',
                'target_amount' => 8000,
                'collected_amount' => 4200,
                'is_active' => 1,
                'display_order' => 4,
                'created_at' => '2026-03-17 03:27:21',
                'updated_at' => '2026-03-17 03:27:21',
            ),
            4 => 
            array (
                'id' => 5,
                'organization_id' => NULL,
                'title' => 'Infaq Am — Derma Bebas',
                'description' => 'Sumbangan am untuk kegunaan operasi pertubuhan.',
                'image_path' => '/storage/infaq/demo_infaq_5.svg',
                'type' => 'one_off',
                'target_amount' => NULL,
                'collected_amount' => 3300,
                'is_active' => 1,
                'display_order' => 5,
                'created_at' => '2026-03-17 03:27:21',
                'updated_at' => '2026-03-17 03:27:21',
            ),
        ));
        
        
    }
}
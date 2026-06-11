<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DashboardBannersTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('dashboard_banners')->delete();
        
        \DB::table('dashboard_banners')->insert(array (
            0 => 
            array (
                'id' => 1,
                'organization_id' => NULL,
                'title' => 'Minggu Ukhuwah Nasional',
                'image_path' => '/storage/dashboard-banners/dummy/global-minggu-ukhuwah-nasional.svg',
                'is_active' => 1,
                'display_order' => 1,
                'created_at' => '2026-03-16 17:16:06',
                'updated_at' => '2026-03-16 17:16:06',
            ),
            1 => 
            array (
                'id' => 2,
                'organization_id' => NULL,
                'title' => 'Kempen Infaq Ramadan',
                'image_path' => '/storage/dashboard-banners/dummy/global-kempen-infaq-ramadan.svg',
                'is_active' => 1,
                'display_order' => 2,
                'created_at' => '2026-03-16 17:16:06',
                'updated_at' => '2026-03-16 17:16:06',
            ),
            2 => 
            array (
                'id' => 3,
                'organization_id' => NULL,
                'title' => 'Jelajah Kepimpinan Belia',
                'image_path' => '/storage/dashboard-banners/dummy/global-jelajah-kepimpinan-belia.svg',
                'is_active' => 1,
                'display_order' => 3,
                'created_at' => '2026-03-16 17:16:06',
                'updated_at' => '2026-03-16 17:16:06',
            ),
            3 => 
            array (
                'id' => 4,
                'organization_id' => 1,
                'title' => 'Forum Komuniti PKPIM',
                'image_path' => '/storage/dashboard-banners/dummy/org-1-forum-komuniti-pkpim.svg',
                'is_active' => 1,
                'display_order' => 10,
                'created_at' => '2026-03-16 17:16:06',
                'updated_at' => '2026-03-16 17:16:06',
            ),
            4 => 
            array (
                'id' => 5,
                'organization_id' => 1,
                'title' => 'Program Sukarelawan PKPIM',
                'image_path' => '/storage/dashboard-banners/dummy/org-1-program-sukarelawan-pkpim.svg',
                'is_active' => 1,
                'display_order' => 11,
                'created_at' => '2026-03-16 17:16:06',
                'updated_at' => '2026-03-16 17:16:06',
            ),
            5 => 
            array (
                'id' => 6,
                'organization_id' => 2,
                'title' => 'Forum Komuniti ABIM',
                'image_path' => '/storage/dashboard-banners/dummy/org-2-forum-komuniti-abim.svg',
                'is_active' => 1,
                'display_order' => 10,
                'created_at' => '2026-03-16 17:16:06',
                'updated_at' => '2026-03-16 17:16:06',
            ),
            6 => 
            array (
                'id' => 7,
                'organization_id' => 2,
                'title' => 'Program Sukarelawan ABIM',
                'image_path' => '/storage/dashboard-banners/dummy/org-2-program-sukarelawan-abim.svg',
                'is_active' => 1,
                'display_order' => 11,
                'created_at' => '2026-03-16 17:16:06',
                'updated_at' => '2026-03-16 17:16:06',
            ),
            7 => 
            array (
                'id' => 8,
                'organization_id' => 3,
                'title' => 'Forum Komuniti WADAH',
                'image_path' => '/storage/dashboard-banners/dummy/org-3-forum-komuniti-wadah.svg',
                'is_active' => 1,
                'display_order' => 10,
                'created_at' => '2026-03-16 17:16:06',
                'updated_at' => '2026-03-16 17:16:06',
            ),
            8 => 
            array (
                'id' => 9,
                'organization_id' => 3,
                'title' => 'Program Sukarelawan WADAH',
                'image_path' => '/storage/dashboard-banners/dummy/org-3-program-sukarelawan-wadah.svg',
                'is_active' => 1,
                'display_order' => 11,
                'created_at' => '2026-03-16 17:16:06',
                'updated_at' => '2026-03-16 17:16:06',
            ),
        ));
        
        
    }
}
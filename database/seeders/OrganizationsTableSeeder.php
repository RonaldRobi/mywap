<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class OrganizationsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('organizations')->delete();
        
        \DB::table('organizations')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'PKPIM',
                'slug' => 'pkpim',
                'color_theme' => '#6366f1',
                'min_age' => 0,
                'max_age' => 19,
                'created_at' => '2026-03-16 15:25:34',
                'updated_at' => '2026-03-17 18:07:50',
                'fee_amount' => 30,
                'logo_path' => '/storage/logos/organizations/zratUgj9brjqSZMoHiHh8BeyXdUl3Uy2SrgWEph1.png',
                'sort_order' => NULL,
            ),
            1 => 
            array (
                'id' => 2,
                'name' => 'ABIM',
                'slug' => 'abim',
                'color_theme' => '#10b981',
                'min_age' => 20,
                'max_age' => 29,
                'created_at' => '2026-03-16 15:25:34',
                'updated_at' => '2026-03-17 18:07:54',
                'fee_amount' => 50,
                'logo_path' => '/storage/logos/organizations/hREFAvHpwkZILTQczpAomZ0nyoEq4F2JxweJ1zeU.png',
                'sort_order' => NULL,
            ),
            2 => 
            array (
                'id' => 3,
                'name' => 'WADAH',
                'slug' => 'wadah',
                'color_theme' => '#f59e0b',
                'min_age' => 30,
                'max_age' => NULL,
                'created_at' => '2026-03-16 15:25:34',
                'updated_at' => '2026-03-17 18:07:59',
                'fee_amount' => 60,
                'logo_path' => '/storage/logos/organizations/TQphsffDuK8ikn8duJ5LNTSIqBb5PnYfwZGlMMl4.png',
                'sort_order' => NULL,
            ),
        ));
        
        
    }
}
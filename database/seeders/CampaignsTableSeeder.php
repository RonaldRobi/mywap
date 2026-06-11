<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CampaignsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('campaigns')->delete();
        
        \DB::table('campaigns')->insert(array (
            0 => 
            array (
                'id' => 1,
                'organization_id' => 3,
                'title' => 'TEST',
                'slug' => 'test',
                'description' => NULL,
                'target_amount' => 3,
                'current_amount' => 0,
                'status' => 'active',
                'created_at' => '2026-03-17 03:32:03',
                'updated_at' => '2026-03-17 03:32:03',
            ),
        ));
        
        
    }
}
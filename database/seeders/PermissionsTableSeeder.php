<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PermissionsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('permissions')->delete();
        
        \DB::table('permissions')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'view.members',
                'guard_name' => 'web',
                'created_at' => '2026-03-16 15:25:34',
                'updated_at' => '2026-03-16 15:25:34',
            ),
            1 => 
            array (
                'id' => 2,
                'name' => 'create.members',
                'guard_name' => 'web',
                'created_at' => '2026-03-16 15:25:34',
                'updated_at' => '2026-03-16 15:25:34',
            ),
            2 => 
            array (
                'id' => 3,
                'name' => 'edit.members',
                'guard_name' => 'web',
                'created_at' => '2026-03-16 15:25:34',
                'updated_at' => '2026-03-16 15:25:34',
            ),
            3 => 
            array (
                'id' => 4,
                'name' => 'delete.members',
                'guard_name' => 'web',
                'created_at' => '2026-03-16 15:25:34',
                'updated_at' => '2026-03-16 15:25:34',
            ),
            4 => 
            array (
                'id' => 5,
                'name' => 'transition.members',
                'guard_name' => 'web',
                'created_at' => '2026-03-16 15:25:34',
                'updated_at' => '2026-03-16 15:25:34',
            ),
            5 => 
            array (
                'id' => 6,
                'name' => 'view.events',
                'guard_name' => 'web',
                'created_at' => '2026-03-16 15:25:34',
                'updated_at' => '2026-03-16 15:25:34',
            ),
            6 => 
            array (
                'id' => 7,
                'name' => 'create.events',
                'guard_name' => 'web',
                'created_at' => '2026-03-16 15:25:34',
                'updated_at' => '2026-03-16 15:25:34',
            ),
            7 => 
            array (
                'id' => 8,
                'name' => 'edit.events',
                'guard_name' => 'web',
                'created_at' => '2026-03-16 15:25:34',
                'updated_at' => '2026-03-16 15:25:34',
            ),
            8 => 
            array (
                'id' => 9,
                'name' => 'delete.events',
                'guard_name' => 'web',
                'created_at' => '2026-03-16 15:25:34',
                'updated_at' => '2026-03-16 15:25:34',
            ),
            9 => 
            array (
                'id' => 10,
                'name' => 'view.payments',
                'guard_name' => 'web',
                'created_at' => '2026-03-16 15:25:34',
                'updated_at' => '2026-03-16 15:25:34',
            ),
            10 => 
            array (
                'id' => 11,
                'name' => 'manage.payments',
                'guard_name' => 'web',
                'created_at' => '2026-03-16 15:25:34',
                'updated_at' => '2026-03-16 15:25:34',
            ),
            11 => 
            array (
                'id' => 12,
                'name' => 'view.documents',
                'guard_name' => 'web',
                'created_at' => '2026-03-16 15:25:34',
                'updated_at' => '2026-03-16 15:25:34',
            ),
            12 => 
            array (
                'id' => 13,
                'name' => 'manage.documents',
                'guard_name' => 'web',
                'created_at' => '2026-03-16 15:25:34',
                'updated_at' => '2026-03-16 15:25:34',
            ),
            13 => 
            array (
                'id' => 14,
                'name' => 'manage.settings',
                'guard_name' => 'web',
                'created_at' => '2026-03-16 15:25:34',
                'updated_at' => '2026-03-16 15:25:34',
            ),
            14 => 
            array (
                'id' => 15,
                'name' => 'manage.organizations',
                'guard_name' => 'web',
                'created_at' => '2026-03-16 15:25:34',
                'updated_at' => '2026-03-16 15:25:34',
            ),
        ));
        
        
    }
}
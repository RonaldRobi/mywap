<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class UsersTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('users')->delete();
        
        \DB::table('users')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'Super Admin',
                'email' => 'superadmin@mywap.my',
                'ic_number' => '700101011111',
                'email_verified_at' => NULL,
                'password' => '$2y$12$X44CJ5AGn6ZWzyGoYzMzIeuokQhQHcPpf92Gq31uTh1Cgj0W7WUPO',
                'remember_token' => '8Iok9YmgMCwR4F4PwLXbHhVPLDUXuIu9iH91iyzGLV1fagFMv15DL96tQRw4',
                'created_at' => '2026-03-16 15:25:34',
                'updated_at' => '2026-03-16 16:14:04',
                'dob' => NULL,
                'phone' => '+60123456789',
                'current_organization_id' => 3,
                'profile_completed_at' => NULL,
                'education_level' => NULL,
                'current_profession' => NULL,
                'industry' => NULL,
                'expertise' => NULL,
                'linkedin_url' => NULL,
                'is_public_in_directory' => 1,
                'branch_name' => NULL,
                'locality' => NULL,
                'profile_photo_path' => NULL,
            ),
            1 => 
            array (
                'id' => 2,
                'name' => 'Admin PKPIM',
                'email' => 'admin@mywap.my',
                'ic_number' => '800101011111',
                'email_verified_at' => NULL,
                'password' => '$2y$12$qJ08S2gjkJtI/Yj6F/Bm3.dpPPxiq/ma1c/Tzs7N/5Fe5/oLupJQS',
                'remember_token' => 'qoFNm9Y1FAE4qWUIP1Q0L6BXELplvj1UXYXmmyty5BwWdQPWyrjHCJnTWF3I',
                'created_at' => '2026-03-16 15:59:33',
                'updated_at' => '2026-03-16 15:59:33',
                'dob' => NULL,
                'phone' => NULL,
                'current_organization_id' => 1,
                'profile_completed_at' => NULL,
                'education_level' => NULL,
                'current_profession' => NULL,
                'industry' => NULL,
                'expertise' => NULL,
                'linkedin_url' => NULL,
                'is_public_in_directory' => 1,
                'branch_name' => NULL,
                'locality' => NULL,
                'profile_photo_path' => NULL,
            ),
            2 => 
            array (
                'id' => 3,
                'name' => 'Ahmad Firdaus',
                'email' => 'member@mywap.my',
                'ic_number' => '980512101234',
                'email_verified_at' => NULL,
                'password' => '$2y$12$mF6RivFhPsyO.Pmkeo9owuARg.Gw.rwaMkKrleQ3AB2yu9ItivEti',
                'remember_token' => 'SkJ4RqkQVMFzPxwr5pfPNMK2AWSts4fY7YVrHNh3dy31gEcpAPAUSibwq2E0',
                'created_at' => '2026-03-16 15:59:34',
                'updated_at' => '2026-03-17 18:13:29',
                'dob' => '1998-05-12 00:00:00',
                'phone' => '0123456789',
                'current_organization_id' => 2,
                'profile_completed_at' => '2026-03-16 16:21:57',
                'education_level' => 'Ijazah Sarjana',
                'current_profession' => 'Guru',
                'industry' => NULL,
                'expertise' => NULL,
                'linkedin_url' => NULL,
                'is_public_in_directory' => 1,
                'branch_name' => 'ABIM Selangor',
                'locality' => 'Bandar Baru Bangi',
                'profile_photo_path' => '/storage/profiles/vKp2RQ5pOUQWJIqzWSIIvCh9DzjGinWWzejPBpZE.png',
            ),
            3 => 
            array (
                'id' => 4,
                'name' => 'Nurul Izzah',
                'email' => 'member2@mywap.my',
                'ic_number' => '070301101234',
                'email_verified_at' => NULL,
                'password' => '$2y$12$QiSvE0k7T6v4B5fQDf/rtusdtO57NYOnSnep4lecEYtPpIJmAeStC',
                'remember_token' => NULL,
                'created_at' => '2026-03-16 15:59:34',
                'updated_at' => '2026-03-16 15:59:34',
                'dob' => '2007-03-01 00:00:00',
                'phone' => '0198765432',
                'current_organization_id' => 1,
                'profile_completed_at' => NULL,
                'education_level' => NULL,
                'current_profession' => NULL,
                'industry' => NULL,
                'expertise' => NULL,
                'linkedin_url' => NULL,
                'is_public_in_directory' => 1,
                'branch_name' => NULL,
                'locality' => NULL,
                'profile_photo_path' => NULL,
            ),
        ));
        
        
    }
}
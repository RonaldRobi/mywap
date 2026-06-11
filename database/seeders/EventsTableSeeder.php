<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class EventsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('events')->delete();
        
        \DB::table('events')->insert(array (
            0 => 
            array (
                'id' => 1,
                'organization_id' => 1,
                'title' => 'Kem Kepimpinan Pelajar PKPIM 2026',
                'slug' => 'kem-kepimpinan-pelajar-pkpim-2026-yybuph',
                'description' => 'Program pembangunan diri untuk pelajar di bawah 20 tahun yang berminat dalam kepimpinan Islam.',
                'type' => 'physical',
                'location_or_link' => 'Kem Bina Negara, Tanjung Rhu, Kemaman',
                'start_time' => '2026-03-23 08:00:00',
                'end_time' => '2026-03-25 17:00:00',
                'featured_image_path' => NULL,
                'attendance_token' => 'KncnNSXMjX7yQYAnQmBVa94OCtb4WtEc',
                'created_at' => '2026-03-16 15:25:34',
                'updated_at' => '2026-03-16 15:25:34',
                'deleted_at' => NULL,
            ),
            1 => 
            array (
                'id' => 2,
                'organization_id' => 2,
                'title' => 'Bengkel Kepimpinan Pemuda ABIM 2026',
                'slug' => 'bengkel-kepimpinan-pemuda-abim-2026-pxo2t2',
                'description' => 'Bengkel intensif untuk ahli ABIM berusia 20-29 tahun. Topik: Kepimpinan, Ekonomi & Sosial.',
                'type' => 'physical',
                'location_or_link' => 'Dewan Seminar ABIM, No. 2 Jalan Ipoh, Kuala Lumpur',
                'start_time' => '2026-03-30 08:00:00',
                'end_time' => '2026-03-30 17:30:00',
                'featured_image_path' => NULL,
                'attendance_token' => 'CqRE7FEuhvo3kFzm0VORSl4qCijx6WRq',
                'created_at' => '2026-03-16 15:25:34',
                'updated_at' => '2026-03-16 15:25:34',
                'deleted_at' => NULL,
            ),
            2 => 
            array (
                'id' => 3,
                'organization_id' => 3,
                'title' => 'Seminar Veteran WADAH: Masa Depan Ummah',
                'slug' => 'seminar-veteran-wadah-masa-depan-ummah-dcux2p',
                'description' => 'Seminar khas untuk ahli WADAH membincangkan hala tuju gerakan Islam pada abad ke-21.',
                'type' => 'online',
                'location_or_link' => 'https://meet.google.com/demo-wadah-2026',
                'start_time' => '2026-04-06 09:00:00',
                'end_time' => '2026-04-06 13:00:00',
                'featured_image_path' => NULL,
                'attendance_token' => 'dfnFOHDPtyPFXS2bBqILtm4mRcgzgyPu',
                'created_at' => '2026-03-16 15:25:34',
                'updated_at' => '2026-03-16 15:25:34',
                'deleted_at' => NULL,
            ),
            3 => 
            array (
                'id' => 4,
                'organization_id' => 1,
                'title' => 'Kem Kepimpinan Pelajar PKPIM 2026',
                'slug' => 'kem-kepimpinan-pelajar-pkpim-2026-d6lwdg',
                'description' => 'Program pembangunan diri untuk pelajar di bawah 20 tahun yang berminat dalam kepimpinan Islam.',
                'type' => 'physical',
                'location_or_link' => 'Kem Bina Negara, Tanjung Rhu, Kemaman',
                'start_time' => '2026-03-23 08:00:00',
                'end_time' => '2026-03-25 17:00:00',
                'featured_image_path' => NULL,
                'attendance_token' => 'xi7R8IjpK3JB5cdxnrAn73VLq3oPqHui',
                'created_at' => '2026-03-16 16:14:04',
                'updated_at' => '2026-03-16 16:14:04',
                'deleted_at' => NULL,
            ),
            4 => 
            array (
                'id' => 5,
                'organization_id' => 2,
                'title' => 'Bengkel Kepimpinan Pemuda ABIM 2026',
                'slug' => 'bengkel-kepimpinan-pemuda-abim-2026-oh8u0f',
                'description' => 'Bengkel intensif untuk ahli ABIM berusia 20-29 tahun. Topik: Kepimpinan, Ekonomi & Sosial.',
                'type' => 'physical',
                'location_or_link' => 'Dewan Seminar ABIM, No. 2 Jalan Ipoh, Kuala Lumpur',
                'start_time' => '2026-03-30 08:00:00',
                'end_time' => '2026-03-30 17:30:00',
                'featured_image_path' => NULL,
                'attendance_token' => 'a3fOUqEHUl3Q8V0jPkaxdq3hhq54tdUw',
                'created_at' => '2026-03-16 16:14:04',
                'updated_at' => '2026-03-16 16:14:04',
                'deleted_at' => NULL,
            ),
            5 => 
            array (
                'id' => 6,
                'organization_id' => 3,
                'title' => 'Seminar Veteran WADAH: Masa Depan Ummah',
                'slug' => 'seminar-veteran-wadah-masa-depan-ummah-8enxxe',
                'description' => 'Seminar khas untuk ahli WADAH membincangkan hala tuju gerakan Islam pada abad ke-21.',
                'type' => 'online',
                'location_or_link' => 'https://meet.google.com/demo-wadah-2026',
                'start_time' => '2026-04-06 09:00:00',
                'end_time' => '2026-04-06 13:00:00',
                'featured_image_path' => NULL,
                'attendance_token' => 'E9JNSfqYNNxpj7YX0AxPvVLh1x8O8Evt',
                'created_at' => '2026-03-16 16:14:04',
                'updated_at' => '2026-03-16 16:14:04',
                'deleted_at' => NULL,
            ),
        ));
        
        
    }
}
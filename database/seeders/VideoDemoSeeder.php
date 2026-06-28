<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Video;
use Illuminate\Database\Seeder;

class VideoDemoSeeder extends Seeder
{
    public function run(): void
    {
        $pkpim = Organization::where('slug', 'pkpim')->first();
        $abim  = Organization::where('slug', 'abim')->first();
        $wadah = Organization::where('slug', 'wadah')->first();

        if (! $pkpim && ! $abim && ! $wadah) {
            $this->command->warn('No organizations found. Skipping VideoDemoSeeder.');
            return;
        }

        $videos = [
            // PKPIM
            [
                'organization_id' => $pkpim?->id,
                'title'          => '[Demo] Kepimpinan Pelajar Islam',
                'youtube_url'    => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'youtube_id'     => 'dQw4w9WgXcQ',
            ],
            [
                'organization_id' => $pkpim?->id,
                'title'          => '[Demo] Seminar Tarbiyah PKPIM',
                'youtube_url'    => 'https://www.youtube.com/watch?v=jNQXAC9IVRw',
                'youtube_id'     => 'jNQXAC9IVRw',
            ],
            // ABIM
            [
                'organization_id' => $abim?->id,
                'title'          => '[Demo] Belia Islam dan Media Sosial',
                'youtube_url'    => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'youtube_id'     => 'dQw4w9WgXcQ',
            ],
            [
                'organization_id' => $abim?->id,
                'title'          => '[Demo] Forum Belia ABIM 2026',
                'youtube_url'    => 'https://www.youtube.com/watch?v=jNQXAC9IVRw',
                'youtube_id'     => 'jNQXAC9IVRw',
            ],
            // WADAH
            [
                'organization_id' => $wadah?->id,
                'title'          => '[Demo] Pengurusan Rumah Tangga Islam',
                'youtube_url'    => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'youtube_id'     => 'dQw4w9WgXcQ',
            ],
            [
                'organization_id' => $wadah?->id,
                'title'          => '[Demo] Kewangan Patuh Syariah',
                'youtube_url'    => 'https://www.youtube.com/watch?v=jNQXAC9IVRw',
                'youtube_id'     => 'jNQXAC9IVRw',
            ],
        ];

        foreach ($videos as $data) {
            Video::updateOrCreate(
                ['title' => $data['title']],
                $data
            );
        }

        $this->command->info('✅  '.Video::count().' videos seeded.');
    }
}

<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * EventSeeder
 *
 * Seeds three demo events — one per NGO tier.
 * Slug and attendance_token are generated explicitly here because
 * DatabaseSeeder uses WithoutModelEvents, which suppresses Eloquent's
 * creating hooks (including our boot() auto-generators).
 */
class EventSeeder extends Seeder
{
    public function run(): void
    {
        $pkpim = Organization::where('slug', 'pkpim')->first();
        $abim  = Organization::where('slug', 'abim')->first();
        $wadah = Organization::where('slug', 'wadah')->first();

        $seedEvents = [
            [
                'organization_id'  => $pkpim?->id,
                'title'            => 'Kem Kepimpinan Pelajar PKPIM 2026',
                'description'      => 'Program pembangunan diri untuk pelajar di bawah 20 tahun yang berminat dalam kepimpinan Islam.',
                'type'             => 'physical',
                'location_or_link' => 'Kem Bina Negara, Tanjung Rhu, Kemaman',
                'start_time'       => now()->addDays(7)->setTime(8, 0),
                'end_time'         => now()->addDays(9)->setTime(17, 0),
            ],
            [
                'organization_id'  => $abim?->id,
                'title'            => 'Bengkel Kepimpinan Pemuda ABIM 2026',
                'description'      => 'Bengkel intensif untuk ahli ABIM berusia 20-29 tahun. Topik: Kepimpinan, Ekonomi & Sosial.',
                'type'             => 'physical',
                'location_or_link' => 'Dewan Seminar ABIM, No. 2 Jalan Ipoh, Kuala Lumpur',
                'start_time'       => now()->addDays(14)->setTime(8, 0),
                'end_time'         => now()->addDays(14)->setTime(17, 30),
            ],
            [
                'organization_id'  => $wadah?->id,
                'title'            => 'Seminar Veteran WADAH: Masa Depan Ummah',
                'description'      => 'Seminar khas untuk ahli WADAH membincangkan hala tuju gerakan Islam pada abad ke-21.',
                'type'             => 'online',
                'location_or_link' => 'https://meet.google.com/demo-wadah-2026',
                'start_time'       => now()->addDays(21)->setTime(9, 0),
                'end_time'         => now()->addDays(21)->setTime(13, 0),
            ],
        ];

        foreach ($seedEvents as $data) {
            if (! $data['organization_id']) {
                continue;
            }

            // Explicit slug + token because WithoutModelEvents suppresses boot() hooks.
            Event::create(array_merge($data, [
                'slug'             => Str::slug($data['title']) . '-' . Str::lower(Str::random(6)),
                'attendance_token' => Str::random(32),
            ]));
        }

        $this->command->info('✅  Events seeded: 3 demo events (PKPIM, ABIM, WADAH)');
    }
}

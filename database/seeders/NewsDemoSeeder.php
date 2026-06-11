<?php

namespace Database\Seeders;

use App\Models\NewsCategory;
use App\Models\NewsPost;
use App\Models\NewsPostComment;
use App\Models\NewsPostReaction;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class NewsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $superadmin = User::query()->where('email', 'superadmin@mywap.my')->first()
            ?? User::query()->first();
        $admin = User::query()->where('email', 'admin@mywap.my')->first()
            ?? $superadmin;

        if (! $superadmin) {
            return;
        }

        $pkpimId = Organization::query()->where('slug', 'pkpim')->value('id');
        $abimId = Organization::query()->where('slug', 'abim')->value('id');
        $wadahId = Organization::query()->where('slug', 'wadah')->value('id');

        $categories = collect([
            ['name' => 'Pengumuman', 'slug' => 'pengumuman', 'icon' => '📢', 'display_order' => 1],
            ['name' => 'Program', 'slug' => 'program', 'icon' => '🗓️', 'display_order' => 2],
            ['name' => 'Komuniti', 'slug' => 'komuniti', 'icon' => '🤝', 'display_order' => 3],
            ['name' => 'Teknologi', 'slug' => 'teknologi', 'icon' => '💡', 'display_order' => 4],
        ])->mapWithKeys(function (array $category) {
            $model = NewsCategory::query()->updateOrCreate(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'icon' => $category['icon'],
                    'is_active' => true,
                    'display_order' => $category['display_order'],
                ]
            );

            return [$category['slug'] => $model];
        });

        $posts = [
            [
                'lookup' => ['title' => '[DEMO] Majlis Pelancaran Aplikasi Komuniti', 'organization_id' => null],
                'values' => [
                    'author_id' => $superadmin->id,
                    'news_category_id' => $categories['pengumuman']->id,
                    'excerpt' => 'Pelancaran rasmi platform baharu untuk semua ahli organisasi.',
                    'content' => "Platform myMarhalah kini dibuka kepada semua ahli.\n\nGunakan modul Info Terkini untuk dapatkan makluman secara pantas, beri reaksi, dan tinggalkan komen.",
                    'cover_image_path' => null,
                    'is_published' => true,
                    'published_at' => now()->subDays(1),
                ],
            ],
            [
                'lookup' => ['title' => '[DEMO] Jadual Usrah PKPIM Bulan Ini', 'organization_id' => $pkpimId],
                'values' => [
                    'author_id' => $admin?->id ?? $superadmin->id,
                    'news_category_id' => $categories['program']->id,
                    'excerpt' => 'Senarai sesi usrah mingguan PKPIM untuk bulan semasa.',
                    'content' => "Sesi usrah akan diadakan setiap hari Sabtu.\n\nPastikan semak masa dan lokasi sebelum hadir.",
                    'cover_image_path' => null,
                    'is_published' => true,
                    'published_at' => now()->subHours(20),
                ],
            ],
            [
                'lookup' => ['title' => '[DEMO] Kelas Kepimpinan Belia ABIM', 'organization_id' => $abimId],
                'values' => [
                    'author_id' => $superadmin->id,
                    'news_category_id' => $categories['komuniti']->id,
                    'excerpt' => 'Program latihan kepimpinan khas untuk ahli belia ABIM.',
                    'content' => "Program intensif 2 hari 1 malam.\n\nFokus utama: komunikasi, kepimpinan, dan pengurusan projek komuniti.",
                    'cover_image_path' => null,
                    'is_published' => true,
                    'published_at' => now()->subHours(12),
                ],
            ],
            [
                'lookup' => ['title' => '[DEMO] WADAH Digital Literacy Series', 'organization_id' => $wadahId],
                'values' => [
                    'author_id' => $superadmin->id,
                    'news_category_id' => $categories['teknologi']->id,
                    'excerpt' => 'Siri pembelajaran digital untuk memperkasa komuniti.',
                    'content' => "Siri ini merangkumi topik keselamatan digital, produktiviti, dan kolaborasi atas talian.\n\nTerbuka kepada ahli WADAH.",
                    'cover_image_path' => null,
                    'is_published' => true,
                    'published_at' => now()->subHours(6),
                ],
            ],
            [
                'lookup' => ['title' => '[DEMO] Draf Dalaman: Perancangan Suku Tahun', 'organization_id' => null],
                'values' => [
                    'author_id' => $superadmin->id,
                    'news_category_id' => $categories['pengumuman']->id,
                    'excerpt' => 'Contoh draf untuk semakan pentadbir.',
                    'content' => "Ini ialah draf dalaman.\n\nPost ini tidak dipaparkan kepada ahli biasa.",
                    'cover_image_path' => null,
                    'is_published' => false,
                    'published_at' => null,
                ],
            ],
        ];

        $createdPosts = collect($posts)->map(function (array $post) {
            return NewsPost::query()->updateOrCreate(
                $post['lookup'],
                $post['values']
            );
        });

        $memberUsers = User::query()->whereIn('email', ['member@mywap.my', 'member2@mywap.my'])->get();

        foreach ($createdPosts as $post) {
            foreach ($memberUsers as $index => $member) {
                NewsPostReaction::query()->updateOrCreate(
                    [
                        'news_post_id' => $post->id,
                        'user_id' => $member->id,
                    ],
                    [
                        'reaction' => $index % 2 === 0 ? 'like' : 'dislike',
                    ]
                );
            }

            NewsPostComment::query()->updateOrCreate(
                [
                    'news_post_id' => $post->id,
                    'user_id' => $memberUsers->first()?->id ?? $superadmin->id,
                    'content' => 'Terima kasih atas perkongsian. Sangat membantu untuk ahli.',
                ],
                [
                    'is_hidden' => false,
                ]
            );
        }
    }
}

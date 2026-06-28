<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\ArticleComment;
use App\Models\ArticleReaction;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ArticleDemoSeeder extends Seeder
{
    public function run(): void
    {
        $superadmin = User::withoutGlobalScopes()->where('email', 'superadmin@mywap.my')->first();
        if (! $superadmin) {
            $this->command->warn('No superadmin found. Skipping ArticleDemoSeeder.');
            return;
        }

        $pkpim = Organization::where('slug', 'pkpim')->first();
        $abim  = Organization::where('slug', 'abim')->first();
        $wadah = Organization::where('slug', 'wadah')->first();

        $adminPkpim = User::withoutGlobalScopes()->where('email', 'admin-pkpim@mywap.my')->first() ?? $superadmin;
        $adminAbim  = User::withoutGlobalScopes()->where('email', 'admin-abim@mywap.my')->first()  ?? $superadmin;
        $adminWadah = User::withoutGlobalScopes()->where('email', 'admin-wadah@mywap.my')->first() ?? $superadmin;

        $articles = [
            // PKPIM
            [
                'author_id'     => $adminPkpim->id,
                'organization_id' => $pkpim?->id,
                'title'         => 'Kepentingan Tarbiyah dalam Membentuk Generasi Pewaris',
                'excerpt'       => 'Tarbiyah adalah nadi utama dalam melahirkan generasi yang memahami Islam secara syumul.',
                'content'       => "Tarbiyah merupakan proses pembentukan individu muslim yang berterusan.\n\nIa merangkumi aspek rohani, jasmani, dan akli. Melalui usrah dan halaqah, ahli dapat memperdalam kefahaman agama serta memupuk akhlak mulia.\n\nMarilah kita sama-sama menghayati tarbiyah sebagai satu keperluan, bukan sekadar aktiviti sampingan.",
            ],
            [
                'author_id'     => $adminPkpim->id,
                'organization_id' => $pkpim?->id,
                'title'         => 'Program Ihya Ramadhan PKPIM 2026',
                'excerpt'       => 'Sambut Ramadhan dengan program-program yang mendekatkan diri kepada Allah.',
                'content'       => "PKPIM dengan kerjasama beberapa cawangan akan menganjurkan Program Ihya Ramadhan.\n\nAntara aktiviti:\n- Tadarus Al-Quran\n- Kuliah Subuh\n- Iftar Jamaie\n- Qiamullail\n\nSemua ahli dijemput hadir.",
            ],
            // ABIM
            [
                'author_id'     => $adminAbim->id,
                'organization_id' => $abim?->id,
                'title'         => 'Belia Islam dan Cabaran Digitalisasi',
                'excerpt'       => 'Belia perlu bersedia menghadapi cabaran era digital tanpa menggadaikan prinsip Islam.',
                'content'       => "Era digital membawa pelbagai cabaran baru buat belia Islam.\n\nAntaranya:\n1. Maklumat palsu (fake news) yang mudah tersebar\n2. Ketagihan media sosial\n3. Isu privasi dan keselamatan data\n\nABIM komited mendidik belia agar celik digital tanpa melupakan nilai-nilai Islam.",
            ],
            [
                'author_id'     => $adminAbim->id,
                'organization_id' => $abim?->id,
                'title'         => 'Forum Perpaduan Nasional Anjuran ABIM',
                'excerpt'       => 'Forum tahunan yang menghimpunkan pelbagai lapisan masyarakat untuk membincangkan perpaduan negara.',
                'content'       => "ABIM akan menganjurkan Forum Perpaduan Nasional pada tahun ini.\n\nTema: \"Perpaduan Teras Kesejahteraan\"\n\nPanelis terdiri daripada tokoh akademik, aktivis sosial, dan pemimpin belia.\n\nTarikh: 20 Ogos 2026\nTempat: Dewan Serbaguna, Kuala Lumpur",
            ],
            // WADAH
            [
                'author_id'     => $adminWadah->id,
                'organization_id' => $wadah?->id,
                'title'         => 'Keluarga Bahagia: Kunci Masyarakat Sejahtera',
                'excerpt'       => 'WADAH perkasakan institusi keluarga melalui program-program keibubapaan.',
                'content'       => "Keluarga adalah tunjang masyarakat. WADAH komited membantu ahli membina keluarga bahagia berlandaskan Islam.\n\nProgram-program:\n- Bengkel Keibubapaan Efektif\n- Kursus Pengurusan Rumah Tangga\n- Sesi Kaunseling Keluarga\n\nSemua ahli dialu-alukan menyertai program ini.",
            ],
            [
                'author_id'     => $superadmin->id,
                'organization_id' => $wadah?->id,
                'title'         => 'Pengurusan Kewangan Untuk Profesional Islam',
                'excerpt'       => 'Tip pengurusan kewangan patuh Syariah untuk golongan profesional.',
                'content'       => "Pengurusan kewangan yang baik adalah sebahagian daripada tuntutan Islam.\n\nAntara perkongsian:\n- Kepentingan membuat belanjawan bulanan\n- Pelaburan patuh Syariah\n- Tabung kecemasan\n- Perancangan persaraan\n\nWADAH akan mengadakan webinar kewangan pada 15 Julai 2026.",
            ],
        ];

        foreach ($articles as $data) {
            Article::updateOrCreate(
                ['title' => $data['title']],
                [
                    'author_id'       => $data['author_id'],
                    'organization_id'  => $data['organization_id'],
                    'slug'            => Str::slug($data['title']),
                    'excerpt'         => $data['excerpt'],
                    'content'         => $data['content'],
                    'is_published'    => true,
                    'published_at'    => now()->subHours(rand(1, 168)),
                ]
            );
        }

        // Comments & reactions
        $members = User::withoutGlobalScopes()
            ->whereIn('email', ['member-pkpim@mywap.my', 'member-abim@mywap.my', 'member-wadah@mywap.my'])
            ->get();

        $articleAll = Article::all();

        foreach ($articleAll as $article) {
            foreach ($members as $i => $member) {
                ArticleReaction::updateOrCreate(
                    ['article_id' => $article->id, 'user_id' => $member->id],
                    ['reaction' => $i % 2 === 0 ? 'like' : 'dislike']
                );
            }

            ArticleComment::updateOrCreate(
                [
                    'article_id' => $article->id,
                    'user_id'    => $members->random()->id,
                    'content'    => 'Artikel yang sangat bermanfaat. Terima kasih atas perkongsian.',
                ],
            );

            ArticleComment::updateOrCreate(
                [
                    'article_id' => $article->id,
                    'user_id'    => $members->random()->id,
                    'content'    => 'Semoga terus istiqamah menyampaikan ilmu yang bermanfaat.',
                ],
            );
        }

        $this->command->info('✅  '.Article::count().' articles, '.ArticleComment::count().' comments, '.ArticleReaction::count().' reactions seeded.');
    }
}

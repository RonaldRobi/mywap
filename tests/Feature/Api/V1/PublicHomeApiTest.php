<?php

namespace Tests\Feature\Api\V1;

use App\Models\Article;
use App\Models\DashboardBanner;
use App\Models\Infaq;
use App\Models\LibraryItem;
use App\Models\NewsPost;
use App\Models\Organization;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PublicHomeApiTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $this->org = Organization::factory()->create(['name' => 'PKPIM']);

        $this->author = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'member_no' => 'P00001',
        ]);
        $this->author->assignRole('Member');
    }

    public function test_public_home_is_accessible_without_auth(): void
    {
        DashboardBanner::create([
            'title' => 'Banner Utama',
            'image_path' => 'dashboard-banners/x.png',
            'is_active' => true,
            'display_order' => 1,
        ]);

        Article::create([
            'author_id' => $this->author->id,
            'organization_id' => $this->org->id,
            'title' => 'Artikel Awam',
            'slug' => 'artikel-awam',
            'content' => '<p>Kandungan.</p>',
            'is_published' => true,
            'published_at' => now(),
        ]);

        NewsPost::create([
            'author_id' => $this->author->id,
            'organization_id' => $this->org->id,
            'title' => 'Berita Awam',
            'excerpt' => 'Ringkas.',
            'content' => '<p>Kandungan.</p>',
            'is_published' => true,
            'published_at' => now(),
        ]);

        Video::create([
            'organization_id' => $this->org->id,
            'title' => 'Video Awam',
            'youtube_url' => 'https://www.youtube.com/watch?v=abc12345678',
            'youtube_id' => 'abc12345678',
            'is_live' => false,
        ]);

        LibraryItem::create([
            'organization_id' => $this->org->id,
            'title' => 'Buku Awam',
            'file_path' => 'library/buku.pdf',
        ]);

        Infaq::create([
            'organization_id' => $this->org->id,
            'title' => 'Infaq Awam',
            'description' => 'Test',
            'type' => 'one_off',
            'is_active' => true,
            'allow_recurring' => false,
        ]);

        $this->getJson('/api/v1/public/home')
            ->assertOk()
            ->assertJsonPath('data.banners.0.title', 'Banner Utama')
            ->assertJsonPath('data.articles.0.title', 'Artikel Awam')
            ->assertJsonPath('data.news.0.title', 'Berita Awam')
            ->assertJsonPath('data.videos.0.title', 'Video Awam')
            ->assertJsonPath('data.library.0.title', 'Buku Awam')
            ->assertJsonPath('data.infaqs.0.title', 'Infaq Awam');
    }

    public function test_public_home_excludes_inactive_banners_and_infaqs(): void
    {
        DashboardBanner::create([
            'title' => 'Banner Tidak Aktif',
            'image_path' => 'dashboard-banners/y.png',
            'is_active' => false,
        ]);

        Infaq::create([
            'organization_id' => $this->org->id,
            'title' => 'Infaq Tidak Aktif',
            'description' => 'Test',
            'type' => 'one_off',
            'is_active' => false,
            'allow_recurring' => false,
        ]);

        $this->getJson('/api/v1/public/home')
            ->assertOk()
            ->assertJsonCount(0, 'data.banners')
            ->assertJsonCount(0, 'data.infaqs');
    }
}

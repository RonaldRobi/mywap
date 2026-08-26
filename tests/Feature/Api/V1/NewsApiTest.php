<?php

namespace Tests\Feature\Api\V1;

use App\Models\Article;
use App\Models\NewsPost;
use App\Models\Organization;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NewsApiTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private Organization $otherOrg;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $this->org = Organization::factory()->create(['name' => 'PKPIM']);
        $this->otherOrg = Organization::factory()->create(['name' => 'ABIM']);

        $this->member = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
            'member_no' => 'PKPIM-0001',
        ]);
        $this->member->assignRole('Member');
    }

    private function makeNewsPost(?Organization $owner = null): NewsPost
    {
        return NewsPost::create([
            'author_id' => $this->member->id,
            'organization_id' => $owner?->id,
            'title' => 'Berita Terkini Test',
            'excerpt' => 'Sinopsis ringkas.',
            'content' => '<p>Kandungan berita penuh.</p>',
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    private function makeArticle(): Article
    {
        return Article::create([
            'author_id' => $this->member->id,
            'organization_id' => $this->org->id,
            'title' => 'Artikel Test',
            'slug' => 'artikel-test-'.uniqid(),
            'excerpt' => 'Sinopsis artikel.',
            'content' => '<p>Kandungan artikel penuh.</p>',
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    private function makeVideo(?Organization $owner = null): Video
    {
        return Video::create([
            'organization_id' => $owner?->id,
            'title' => 'Video Test',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'youtube_id' => 'dQw4w9WgXcQ',
            'is_live' => false,
        ]);
    }

    public function test_auth_required_returns_401(): void
    {
        $this->getJson('/api/v1/news')->assertStatus(401);
        $this->getJson('/api/v1/articles')->assertStatus(401);
        $this->getJson('/api/v1/videos')->assertStatus(401);
    }

    public function test_member_can_list_news_with_pagination_envelope(): void
    {
        $this->makeNewsPost($this->org);

        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/news')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['id', 'title', 'slug', 'excerpt', 'published_at', 'my_reaction', 'likes_count', 'dislikes_count', 'comments_count'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total', 'categories', 'filters'],
                'links' => ['first', 'last', 'prev', 'next'],
            ])
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.filters.category_id', null)
            ->assertJsonPath('data.0.organization_name', 'PKPIM');
    }

    public function test_member_does_not_see_other_org_news(): void
    {
        $this->makeNewsPost($this->otherOrg);

        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/news')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_member_can_view_news_detail(): void
    {
        $post = $this->makeNewsPost($this->org);

        Sanctum::actingAs($this->member);

        $this->getJson("/api/v1/news/{$post->id}")
            ->assertOk()
            ->assertJsonPath('data.post.id', $post->id)
            ->assertJsonPath('data.post.title', 'Berita Terkini Test')
            ->assertJsonPath('data.post.my_reaction', null)
            ->assertJsonPath('data.comments', []);
    }

    public function test_member_cannot_view_other_org_news(): void
    {
        $post = $this->makeNewsPost($this->otherOrg);

        Sanctum::actingAs($this->member);

        $this->getJson("/api/v1/news/{$post->id}")->assertStatus(404);
    }

    public function test_member_can_react_to_news(): void
    {
        $post = $this->makeNewsPost($this->org);

        Sanctum::actingAs($this->member);

        $this->postJson("/api/v1/news/{$post->id}/react", ['reaction' => 'like'])
            ->assertOk()
            ->assertJsonPath('data.post_id', $post->id)
            ->assertJsonPath('data.reaction', 'like')
            ->assertJsonPath('data.likes_count', 1);

        $this->assertDatabaseHas('news_post_reactions', [
            'news_post_id' => $post->id,
            'user_id' => $this->member->id,
            'reaction' => 'like',
        ]);
    }

    public function test_react_requires_valid_reaction(): void
    {
        $post = $this->makeNewsPost($this->org);

        Sanctum::actingAs($this->member);

        $this->postJson("/api/v1/news/{$post->id}/react", ['reaction' => 'sad'])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_member_can_comment_on_news(): void
    {
        $post = $this->makeNewsPost($this->org);

        Sanctum::actingAs($this->member);

        $this->postJson("/api/v1/news/{$post->id}/comments", ['content' => 'Komen pertama'])
            ->assertStatus(201)
            ->assertJsonPath('data.comment.content', 'Komen pertama')
            ->assertJsonPath('data.comment.user_name', $this->member->name);

        $this->assertDatabaseHas('news_post_comments', [
            'news_post_id' => $post->id,
            'user_id' => $this->member->id,
            'content' => 'Komen pertama',
        ]);
    }

    public function test_comment_requires_content(): void
    {
        $post = $this->makeNewsPost($this->org);

        Sanctum::actingAs($this->member);

        $this->postJson("/api/v1/news/{$post->id}/comments", [])
            ->assertStatus(422);
    }

    public function test_member_can_list_articles(): void
    {
        $this->makeArticle();

        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/articles')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['id', 'title', 'slug', 'excerpt', 'is_featured', 'categories'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total', 'featured', 'categories'],
                'links' => ['first', 'last', 'prev', 'next'],
            ])
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Artikel Test');
    }

    public function test_member_can_view_article_detail(): void
    {
        $article = $this->makeArticle();

        Sanctum::actingAs($this->member);

        $this->getJson("/api/v1/articles/{$article->id}")
            ->assertOk()
            ->assertJsonPath('data.article.id', $article->id)
            ->assertJsonPath('data.article.my_reaction', null)
            ->assertJsonPath('data.comments', []);
    }

    public function test_member_can_react_to_article(): void
    {
        $article = $this->makeArticle();

        Sanctum::actingAs($this->member);

        $this->postJson("/api/v1/articles/{$article->id}/react", ['reaction' => 'dislike'])
            ->assertOk()
            ->assertJsonPath('data.reaction', 'dislike')
            ->assertJsonPath('data.dislikes_count', 1);

        $this->assertDatabaseHas('article_reactions', [
            'article_id' => $article->id,
            'user_id' => $this->member->id,
            'reaction' => 'dislike',
        ]);
    }

    public function test_member_can_comment_on_article(): void
    {
        $article = $this->makeArticle();

        Sanctum::actingAs($this->member);

        $this->postJson("/api/v1/articles/{$article->id}/comments", ['content' => 'Komen artikel'])
            ->assertStatus(201)
            ->assertJsonPath('data.comment.user_name', $this->member->name);

        $this->assertDatabaseHas('article_comments', [
            'article_id' => $article->id,
            'user_id' => $this->member->id,
            'content' => 'Komen artikel',
        ]);
    }

    public function test_member_can_list_videos(): void
    {
        $this->makeVideo($this->org);

        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/videos')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['id', 'title', 'youtube_id', 'thumbnail_url', 'embed_url'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'links' => ['first', 'last', 'prev', 'next'],
            ])
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Video Test');
    }

    public function test_member_does_not_see_other_org_videos(): void
    {
        $this->makeVideo($this->otherOrg);

        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/videos')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }
}

<?php

namespace Tests\Feature\Api\V1;

use App\Models\Article;
use App\Models\ArticleComment;
use App\Models\NewsPost;
use App\Models\NewsPostComment;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ModerationApiTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $member;

    private User $otherMember;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $this->org = Organization::factory()->create(['name' => 'PKPIM']);

        $this->member = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
            'member_no' => 'PKPIM-0001',
        ]);
        $this->member->assignRole('Member');

        $this->otherMember = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
            'member_no' => 'PKPIM-0002',
        ]);
        $this->otherMember->assignRole('Member');
    }

    private function makeNewsPostWithComment(): NewsPostComment
    {
        $post = NewsPost::create([
            'author_id' => $this->member->id,
            'organization_id' => $this->org->id,
            'title' => 'Berita Moderation',
            'excerpt' => 'Ringkas.',
            'content' => '<p>Kandungan.</p>',
            'is_published' => true,
            'published_at' => now(),
        ]);

        return NewsPostComment::create([
            'news_post_id' => $post->id,
            'user_id' => $this->otherMember->id,
            'content' => 'Komen untuk disekat.',
            'is_hidden' => false,
        ]);
    }

    public function test_moderation_endpoints_require_auth(): void
    {
        $this->postJson('/api/v1/reports', [])->assertUnauthorized();
        $this->getJson('/api/v1/blocks')->assertUnauthorized();
        $this->postJson('/api/v1/blocks', [])->assertUnauthorized();
    }

    public function test_member_can_report_a_comment(): void
    {
        $comment = $this->makeNewsPostWithComment();

        Sanctum::actingAs($this->member);

        $this->postJson('/api/v1/reports', [
            'reportable_type' => 'news_comment',
            'reportable_id' => $comment->id,
            'reason' => 'spam',
            'notes' => 'Iklan berulang.',
        ])->assertCreated();

        $this->assertDatabaseHas('content_reports', [
            'reporter_id' => $this->member->id,
            'reportable_type' => NewsPostComment::class,
            'reportable_id' => $comment->id,
            'reason' => 'spam',
        ]);
    }

    public function test_report_rejects_unknown_type(): void
    {
        Sanctum::actingAs($this->member);

        $this->postJson('/api/v1/reports', [
            'reportable_type' => 'unknown',
            'reportable_id' => 1,
            'reason' => 'spam',
        ])->assertStatus(422);
    }

    public function test_blocking_a_user_hides_their_comments(): void
    {
        $comment = $this->makeNewsPostWithComment();

        Sanctum::actingAs($this->member);

        $this->postJson('/api/v1/blocks', [
            'blocked_user_id' => $this->otherMember->id,
        ])->assertOk();

        $this->assertDatabaseHas('user_blocks', [
            'blocker_id' => $this->member->id,
            'blocked_id' => $this->otherMember->id,
        ]);

        $postId = $comment->news_post_id;

        $this->getJson("/api/v1/news/{$postId}")
            ->assertOk()
            ->assertJsonPath('data.post.comments_count', 1)
            ->assertJsonCount(0, 'data.comments');

        $this->getJson('/api/v1/blocks')
            ->assertOk()
            ->assertJsonPath('data.blocked_user_ids.0', $this->otherMember->id);
    }

    public function test_unblock_restores_comments(): void
    {
        $comment = $this->makeNewsPostWithComment();

        Sanctum::actingAs($this->member);

        $this->postJson('/api/v1/blocks', ['blocked_user_id' => $this->otherMember->id])->assertOk();

        $this->deleteJson("/api/v1/blocks/{$this->otherMember->id}")->assertOk();

        $this->getJson("/api/v1/news/{$comment->news_post_id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.comments');
    }

    public function test_member_cannot_block_self(): void
    {
        Sanctum::actingAs($this->member);

        $this->postJson('/api/v1/blocks', ['blocked_user_id' => $this->member->id])
            ->assertStatus(422);
    }

    public function test_member_can_report_article_comment(): void
    {
        $article = Article::create([
            'author_id' => $this->member->id,
            'organization_id' => $this->org->id,
            'title' => 'Artikel Moderation',
            'slug' => 'artikel-moderation-'.uniqid(),
            'excerpt' => 'Ringkas.',
            'content' => '<p>Kandungan.</p>',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $comment = ArticleComment::create([
            'article_id' => $article->id,
            'user_id' => $this->otherMember->id,
            'content' => 'Komen artikel.',
            'is_hidden' => false,
        ]);

        Sanctum::actingAs($this->member);

        $this->postJson('/api/v1/reports', [
            'reportable_type' => 'article_comment',
            'reportable_id' => $comment->id,
            'reason' => 'harassment',
        ])->assertCreated();

        $this->assertDatabaseHas('content_reports', [
            'reporter_id' => $this->member->id,
            'reportable_type' => ArticleComment::class,
            'reportable_id' => $comment->id,
        ]);
    }
}

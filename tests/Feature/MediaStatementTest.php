<?php

namespace Tests\Feature;

use App\Models\MediaStatement;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaStatementTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_api_returns_global_statements_only(): void
    {
        $org = Organization::factory()->create();
        $author = User::factory()->create(['current_organization_id' => $org->id]);
        $global = $this->statement($author, null, 'Umum');
        $this->statement($author, $org, 'Khusus');

        $this->getJson('/api/v1/media-statements')->assertOk()
            ->assertJsonPath('data.0.id', $global->id)
            ->assertJsonMissing(['title' => 'Khusus']);
    }

    public function test_member_api_can_see_global_and_own_organization_statement(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $member = User::factory()->create(['current_organization_id' => $orgA->id]);
        $global = $this->statement($member, null, 'Umum');
        $own = $this->statement($member, $orgA, 'Organisasi A');
        $other = $this->statement($member, $orgB, 'Organisasi B');

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/v1/media-statements')->assertOk();
        $response->assertJsonFragment(['id' => $own->id]);
        $response->assertJsonFragment(['id' => $global->id]);
        $response->assertJsonMissing(['id' => $other->id]);
    }

    public function test_summary_contains_openable_pdf_and_detail_urls(): void
    {
        $author = User::factory()->create();
        $statement = $this->statement($author, null, 'Dengan PDF', ['pdf_path' => '/storage/media-statements/pdf/release.pdf']);

        $this->getJson('/api/v1/media-statements/'.$statement->id)->assertOk()
            ->assertJsonPath('data.pdf_url', asset($statement->pdf_path))
            ->assertJsonPath('data.public_url', route('media-statements.show', $statement))
            ->assertJsonPath('data.detail_url', route('api.v1.media-statements.show', $statement));
    }

    private function statement(User $author, ?Organization $organization, string $title, array $extra = []): MediaStatement
    {
        return MediaStatement::create(array_merge([
            'author_id' => $author->id,
            'organization_id' => $organization?->id,
            'title' => $title,
            'content' => '<p>Kandungan</p>',
            'is_published' => true,
            'published_at' => now(),
            'is_featured' => false,
        ], $extra));
    }
}

<?php

namespace Tests\Feature;

use App\Models\MediaStatement;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
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

    public function test_share_preview_uses_featured_image_for_og_tags(): void
    {
        $statement = $this->statement(User::factory()->create(), null, 'Kenyataan Media Rasmi', [
            'content_type' => 'both',
            'featured_image_path' => '/storage/media-statements/cover.jpg',
            'pdf_path' => '/storage/media-statements/pdf/release.pdf',
        ]);

        $this->get(route('share.media-statement', $statement))
            ->assertOk()
            ->assertSee('property="og:image"', false)
            ->assertSee(rtrim((string) config('app.share_url'), '/').'/storage/media-statements/cover.jpg', false)
            ->assertSee('Kenyataan Media Rasmi');
    }

    public function test_title_rejects_emoji_and_symbols(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.media-statements.store'), [
            'title' => 'Kenyataan 🚀 Media',
            'content_type' => 'text',
            'content' => '<p>Kandungan</p>',
            'is_published' => true,
        ]);

        $response->assertSessionHasErrors('title');
        $this->assertDatabaseCount('media_statements', 0);
    }

    public function test_pdf_only_statement_saves_without_text_content(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin())->post(route('admin.media-statements.store'), [
            'title' => 'Kenyataan Media Rasmi',
            'content_type' => 'pdf',
            'pdf' => UploadedFile::fake()->create('kenyataan.pdf', 120, 'application/pdf'),
            'is_published' => true,
        ]);

        $response->assertSessionHasNoErrors();

        $statement = MediaStatement::firstOrFail();
        $this->assertSame('pdf', $statement->content_type);
        $this->assertNull($statement->content);
        $this->assertNotNull($statement->pdf_path);
    }

    public function test_text_statement_requires_visible_content(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.media-statements.store'), [
            'title' => 'Kenyataan Media Rasmi',
            'content_type' => 'text',
            'content' => '<p><br></p>',
            'is_published' => true,
        ]);

        $response->assertSessionHasErrors('content');
    }

    public function test_both_content_types_require_pdf_and_text(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.media-statements.store'), [
            'title' => 'Kenyataan Media Rasmi',
            'content_type' => 'both',
            'content' => '<p>Kandungan</p>',
            'is_published' => true,
        ]);

        $response->assertSessionHasErrors('pdf');
    }

    private function admin(): User
    {
        Role::findOrCreate('Superadmin', 'web');

        $admin = User::factory()->create([
            'current_organization_id' => null,
            'profile_completed_at' => now(),
        ]);
        $admin->assignRole('Superadmin');

        return $admin;
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

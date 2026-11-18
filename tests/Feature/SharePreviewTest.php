<?php

namespace Tests\Feature;

use App\Models\LibraryItem;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_library_share_preview_uses_cover_image_for_og_tags(): void
    {
        $organization = Organization::factory()->create();

        $item = LibraryItem::create([
            'organization_id' => $organization->id,
            'title' => 'Buku Panduan Ahli',
            'description' => 'Panduan lengkap untuk ahli baharu.',
            'file_path' => '/storage/library/panduan.pdf',
            'cover_image_path' => '/storage/library/covers/panduan.jpg',
            'category' => 'Panduan',
        ]);

        $this->get(route('share.pustaka', $item))
            ->assertOk()
            ->assertSee('property="og:image"', false)
            ->assertSee(
                rtrim(config('app.share_url'), '/').'/storage/library/covers/panduan.jpg',
                false
            )
            ->assertSee('Buku Panduan Ahli');
    }
}

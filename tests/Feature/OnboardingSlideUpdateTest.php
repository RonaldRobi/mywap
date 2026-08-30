<?php

namespace Tests\Feature;

use App\Models\OnboardingSlide;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OnboardingSlideUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $org = Organization::factory()->create();

        $this->admin = User::factory()->create([
            'current_organization_id' => $org->id,
            'profile_completed_at' => now(),
        ]);
        $this->admin->assignRole('Superadmin');
    }

    public function test_slide_update_does_not_500(): void
    {
        $slide = OnboardingSlide::query()->orderBy('slide_order')->first();
        $this->assertNotNull($slide, 'Slide lalai harus wujud selepas migrate');

        $response = $this->actingAs($this->admin)
            ->from(route('superadmin.onboarding.index'))
            ->post(route('superadmin.onboarding.update', $slide->id), [
                '_method' => 'put',
                'title' => 'Tajuk Ujian',
                'body' => 'Penerangan ujian.',
                'button_label' => 'Seterusnya',
                'button_url' => '',
                'background_start' => '#071525',
                'background_end' => '#2F6B32',
                'text_color' => '#FFFFFF',
                'overlay_start_color' => '#071525',
                'overlay_end_color' => '#071525',
                'overlay_start_opacity' => '0',
                'overlay_end_opacity' => '90',
                'overlay_start_position' => '0',
                'overlay_end_position' => '100',
                'is_active' => '1',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('superadmin.onboarding.index'));

        $slide->refresh();
        $this->assertSame('Tajuk Ujian', $slide->title);
    }

    public function test_slide_update_with_media_upload(): void
    {
        $slide = OnboardingSlide::query()->orderBy('slide_order')->first();

        $response = $this->actingAs($this->admin)
            ->post(route('superadmin.onboarding.update', $slide->id), [
                '_method' => 'put',
                'title' => $slide->title,
                'body' => $slide->body,
                'button_label' => $slide->button_label,
                'button_url' => '',
                'background_start' => $slide->background_start,
                'background_end' => $slide->background_end,
                'text_color' => $slide->text_color,
                'overlay_start_color' => $slide->overlay_start_color,
                'overlay_end_color' => $slide->overlay_end_color,
                'overlay_start_opacity' => (string) $slide->overlay_start_opacity,
                'overlay_end_opacity' => (string) $slide->overlay_end_opacity,
                'overlay_start_position' => (string) $slide->overlay_start_position,
                'overlay_end_position' => (string) $slide->overlay_end_position,
                'is_active' => '1',
                'media' => \Illuminate\Http\UploadedFile::fake()->create('slide.png', 100, 'image/png'),
            ]);

        $response->assertSessionHasNoErrors();
        $slide->refresh();
        $this->assertNotNull($slide->media_path);
    }

    public function test_slide_update_with_inertia_formdata_edge_cases(): void
    {
        $slide = OnboardingSlide::query()->orderBy('slide_order')->first();

        // Inertia forceFormData menukar semua nilai kepada string dan
        // `media: null` dihantar sebagai string kosong.
        $response = $this->actingAs($this->admin)
            ->from(route('superadmin.onboarding.index'))
            ->post(route('superadmin.onboarding.update', $slide->id), [
                '_method' => 'put',
                'title' => 'Edge',
                'body' => '',
                'button_label' => '',
                'button_url' => '',
                'background_start' => '#071525',
                'background_end' => '#2F6B32',
                'text_color' => '#FFFFFF',
                'overlay_start_color' => '#071525',
                'overlay_end_color' => '#071525',
                'overlay_start_opacity' => '0',
                'overlay_end_opacity' => '90',
                'overlay_start_position' => '0',
                'overlay_end_position' => '100',
                'is_active' => 'true',
                'media' => '',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('superadmin.onboarding.index'));
    }

    public function test_slide_update_with_reversed_positions_is_not_500(): void
    {
        $slide = OnboardingSlide::query()->orderBy('slide_order')->first();

        $response = $this->actingAs($this->admin)
            ->post(route('superadmin.onboarding.update', $slide->id), [
                '_method' => 'put',
                'title' => $slide->title,
                'body' => $slide->body,
                'button_label' => $slide->button_label,
                'button_url' => '',
                'background_start' => $slide->background_start,
                'background_end' => $slide->background_end,
                'text_color' => $slide->text_color,
                'overlay_start_color' => $slide->overlay_start_color,
                'overlay_end_color' => $slide->overlay_end_color,
                'overlay_start_opacity' => (string) $slide->overlay_start_opacity,
                'overlay_end_opacity' => (string) $slide->overlay_end_opacity,
                'overlay_start_position' => '80',
                'overlay_end_position' => '20',
                'is_active' => 'true',
            ]);

        $response->assertSessionHasErrors('overlay_end_position');
        $response->assertStatus(302);
    }
}

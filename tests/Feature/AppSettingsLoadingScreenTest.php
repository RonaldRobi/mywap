<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AppSettingsLoadingScreenTest extends TestCase
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

    public function test_loading_screen_update_saves_gradient_and_duration(): void
    {
        $response = $this->actingAs($this->admin)
            ->from(route('superadmin.app-settings.index'))
            ->post(route('superadmin.app-settings.loading-screen.update'), [
                'loading_screen_background_start' => '#071525',
                'loading_screen_background_end' => '#2F6B32',
                'loading_screen_duration_ms' => '3000',
                'loading_screen_enabled' => '1',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('superadmin.app-settings.index'));

        $setting = AppSetting::singleton();
        $this->assertSame('#071525', $setting->loading_screen_background_start);
        $this->assertSame('#2F6B32', $setting->loading_screen_background_end);
        $this->assertSame(3000, $setting->loading_screen_duration_ms);
        $this->assertTrue($setting->loading_screen_enabled);
    }

    public function test_loading_screen_update_with_gif_upload(): void
    {
        $response = $this->actingAs($this->admin)
            ->from(route('superadmin.app-settings.index'))
            ->post(route('superadmin.app-settings.loading-screen.update'), [
                'loading_screen_gif' => UploadedFile::fake()->create('loading.gif', 100, 'image/gif'),
                'loading_screen_background_start' => '#0f172a',
                'loading_screen_background_end' => '#123D2A',
                'loading_screen_duration_ms' => '2500',
                'loading_screen_enabled' => '1',
            ]);

        $response->assertSessionHasNoErrors();

        $setting = AppSetting::singleton();
        $this->assertNotNull($setting->loading_screen_gif_path);
        $this->assertStringContainsString('/storage/', $setting->loading_screen_gif_path);
    }

    public function test_loading_screen_rejects_non_gif_file(): void
    {
        $response = $this->actingAs($this->admin)
            ->from(route('superadmin.app-settings.index'))
            ->post(route('superadmin.app-settings.loading-screen.update'), [
                'loading_screen_gif' => UploadedFile::fake()->create('loading.png', 100, 'image/png'),
                'loading_screen_background_start' => '#0f172a',
                'loading_screen_background_end' => '#123D2A',
                'loading_screen_duration_ms' => '2500',
                'loading_screen_enabled' => '1',
            ]);

        $response->assertSessionHasErrors('loading_screen_gif');
    }

    public function test_loading_screen_gif_can_be_removed(): void
    {
        $setting = AppSetting::singleton();
        $setting->update([
            'loading_screen_gif_path' => '/storage/loading-screens/loading.gif',
        ]);

        $response = $this->actingAs($this->admin)
            ->from(route('superadmin.app-settings.index'))
            ->delete(route('superadmin.app-settings.loading-screen.remove'));

        $response->assertSessionHasNoErrors();

        $setting->refresh();
        $this->assertNull($setting->loading_screen_gif_path);
    }

    public function test_public_app_config_returns_loading_screen(): void
    {
        $setting = AppSetting::singleton();
        $setting->update([
            'loading_screen_gif_path' => '/storage/loading-screens/loading.gif',
            'loading_screen_background_start' => '#071525',
            'loading_screen_background_end' => '#2F6B32',
            'loading_screen_duration_ms' => 3000,
            'loading_screen_enabled' => true,
        ]);

        $response = $this->getJson('/api/v1/app-config');

        $response->assertOk();
        $response->assertJsonPath('data.loading_screen.enabled', true);
        $response->assertJsonPath('data.loading_screen.duration_ms', 3000);
        $response->assertJsonPath('data.loading_screen.background_start', '#071525');
        $response->assertJsonPath('data.loading_screen.background_end', '#2F6B32');
        $this->assertStringContainsString('loading.gif', $response->json('data.loading_screen.gif_url'));
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Models\Organization;
use App\Models\User;
use App\Notifications\RegistrationActivated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register_for_free(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'ic_number' => 'A1234567',
            'dob' => '1998-05-12',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'test@example.com')->firstOrFail();

        $this->assertGuest();
        $response->assertRedirect(route('login', absolute: false));
        $this->assertDatabaseMissing('payments', ['user_id' => $user->id]);
        Notification::assertSentTo($user, RegistrationActivated::class);
    }

    public function test_registration_share_preview_has_social_meta(): void
    {
        Organization::factory()->create([
            'name' => 'PKPIM',
            'slug' => 'pkpim',
            'color_theme' => '#6366f1',
        ]);

        $response = $this->get('/share/daftar?org=pkpim');

        $response->assertOk();
        $response->assertSee('og:image', false);
        $response->assertSee('Jemput Ahli PKPIM', false);
    }

    public function test_registration_share_image_is_png(): void
    {
        Organization::factory()->create([
            'name' => 'PKPIM',
            'slug' => 'pkpim',
            'color_theme' => '#6366f1',
        ]);

        $response = $this->get('/share/daftar/imej.png?org=pkpim');

        $response->assertOk();
        $this->assertStringStartsWith('image/png', (string) $response->headers->get('Content-Type'));
    }

    public function test_admin_can_open_share_page_and_download_qr(): void
    {
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);

        $organization = Organization::factory()->create([
            'name' => 'PKPIM',
            'slug' => 'pkpim',
            'color_theme' => '#6366f1',
        ]);

        $admin = User::factory()->create([
            'current_organization_id' => $organization->id,
        ]);
        $admin->assignRole('Admin');

        $this->actingAs($admin)
            ->get('/admin/jemput-ahli')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/RegistrationShare')
                ->has('share.qr_svg')
                ->has('share.share_url'));

        $qr = $this->actingAs($admin)->get('/admin/jemput-ahli/qr.png');

        $qr->assertOk();
        $this->assertStringStartsWith('image/png', (string) $qr->headers->get('Content-Type'));
    }
}

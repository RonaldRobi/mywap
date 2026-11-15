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

    public function test_registration_continues_wadah_member_number_series(): void
    {
        Notification::fake();

        $wadah = Organization::factory()->create([
            'name' => 'WADAH',
            'slug' => 'wadah',
            'min_age' => 30,
            'max_age' => null,
        ]);

        User::factory()->create([
            'dob' => null,
            'current_organization_id' => $wadah->id,
            'member_no' => 'W2550',
            'member_no_sequence' => 2550,
        ]);

        $this->post('/register', [
            'name' => 'Ahli Baru',
            'email' => 'baru@example.com',
            'ic_number' => 'B7654321',
            'dob' => now()->subYears(35)->toDateString(),
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'baru@example.com')->firstOrFail();

        $this->assertSame('W2551', $user->member_no);
        $this->assertSame(2551, $user->member_no_sequence);
        $this->assertSame('W2551', $user->original_member_no);
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

<?php

namespace Tests\Feature\Auth;

use App\Models\Organization;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();
        Role::create(['name' => 'Member']);
        $user->assignRole('Member');

        $response = $this->post('/login', [
            'login_type' => 'admin',
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('member.dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'login_type' => 'admin',
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_inactive_users_are_told_to_contact_admin(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors([
            'email' => 'Akaun anda tidak aktif. Sila hubungi admin untuk mengaktifkannya.',
        ]);
    }

    public function test_member_lookup_returns_organization_for_valid_ic_number(): void
    {
        $organization = Organization::query()->create([
            'name' => 'ABIM',
            'slug' => 'abim',
            'color_theme' => '#10B981',
            'min_age' => 20,
            'max_age' => 29,
            'logo_path' => '/storage/organizations/abim.png',
        ]);

        User::factory()->create([
            'ic_number' => 'A1234567',
            'current_organization_id' => $organization->id,
        ]);

        $response = $this->getJson('/api/check-member?ic_number=a123 4567');

        $response
            ->assertOk()
            ->assertJson([
                'found' => true,
                'organization' => [
                    'name' => 'ABIM',
                    'logo_url' => '/storage/organizations/abim.png',
                ],
            ]);
    }

    public function test_member_lookup_returns_not_found_for_unknown_ic_number(): void
    {
        $response = $this->getJson('/api/check-member?ic_number=UNKNOWN123');

        $response
            ->assertStatus(404)
            ->assertJson([
                'found' => false,
            ]);
    }

    public function test_web_first_login_whatsapp_otp_uses_normalized_number(): void
    {
        Http::fake([
            '*' => Http::response(['code' => 200], 200),
        ]);

        AppSetting::singleton()->update([
            'onesender_url' => 'https://onesender.test/messages',
            'onesender_api_key' => 'test-key',
        ]);

        $user = User::factory()->create([
            'ic_number' => '940729045407',
            'first_login_at' => null,
        ]);

        $this->postJson(route('login.update-and-send-otp'), [
            'ic_number' => $user->ic_number,
            'channel' => 'whatsapp',
            'contact' => '0132716575',
        ])->assertOk()->assertJson([
            'channel' => 'whatsapp',
            'message' => 'Kod OTP telah dihantar melalui WhatsApp.',
        ]);

        Http::assertSent(fn ($request) => $request->url() === 'https://onesender.test/messages'
            && $request['to'] === '60132716575');
    }

    public function test_failed_web_whatsapp_otp_allows_immediate_email_fallback(): void
    {
        Http::fake([
            '*' => Http::response(['code' => 500], 500),
        ]);
        Queue::fake();
        Notification::fake();

        AppSetting::singleton()->update([
            'onesender_url' => 'https://onesender.test/messages',
            'onesender_api_key' => 'test-key',
        ]);

        $user = User::factory()->create([
            'ic_number' => '940729045407',
            'first_login_at' => null,
            'email' => 'wan@example.test',
        ]);

        $this->postJson(route('login.update-and-send-otp'), [
            'ic_number' => $user->ic_number,
            'channel' => 'whatsapp',
            'contact' => '0132716575',
        ])->assertStatus(422);

        $this->postJson(route('login.update-and-send-otp'), [
            'ic_number' => $user->ic_number,
            'channel' => 'email',
            'contact' => $user->email,
        ])->assertOk()->assertJsonPath('channel', 'email');
    }

    public function test_members_can_authenticate_with_ic_number(): void
    {
        Role::create(['name' => 'Member']);

        $organization = Organization::query()->create([
            'name' => 'PKPIM',
            'slug' => 'pkpim',
            'color_theme' => '#22C55E',
            'min_age' => 0,
            'max_age' => 19,
        ]);

        $user = User::factory()->create([
            'ic_number' => 'A1234567',
            'current_organization_id' => $organization->id,
        ]);
        $user->assignRole('Member');

        $response = $this->post('/login', [
            'login_type' => 'member',
            'ic_number' => 'a123 4567',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('member.dashboard', absolute: false));
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}

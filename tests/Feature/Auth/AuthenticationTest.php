<?php

namespace Tests\Feature\Auth;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

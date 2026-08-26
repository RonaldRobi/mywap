<?php

namespace Tests\Feature\Api\V1;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $this->org = Organization::factory()->create(['name' => 'PKPIM', 'slug' => 'pkpim']);

        $this->user = User::factory()->create([
            'email' => 'ahli@pkpim.test',
            'ic_number' => 'A1234567',
            'current_organization_id' => $this->org->id,
            'first_login_at' => now(),
            'profile_completed_at' => now(),
        ]);
        $this->user->assignRole('Member');
    }

    public function test_login_with_email_returns_token_and_user(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'token_type',
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'roles',
                    ],
                ],
            ])
            ->assertJsonPath('data.user.email', 'ahli@pkpim.test');
    }

    public function test_login_with_ic_number_returns_token(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'ic_number' => 'a123 4567',
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer');
    }

    public function test_login_with_invalid_password_fails(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    public function test_login_for_inactive_account_hints_first_login(): void
    {
        $inactive = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'first_login_at' => null,
            'password' => Hash::make(Str::random(32)),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $inactive->email,
            'password' => 'password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Akaun ini belum aktif. Sila gunakan pautan "Log Masuk Kali Pertama" di bawah.');
    }

    public function test_login_requires_password(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'a@b.test'])
            ->assertStatus(422);
    }

    public function test_me_returns_authenticated_user(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'ahli@pkpim.test')
            ->assertJsonPath('data.organization.name', 'PKPIM');
    }

    public function test_protected_routes_require_token(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
        $this->getJson('/api/v1/member/dashboard')->assertStatus(401);
        $this->getJson('/api/v1/events')->assertStatus(401);
    }

    public function test_logout_revokes_current_token(): void
    {
        $token = $this->user->createToken('mobile')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}

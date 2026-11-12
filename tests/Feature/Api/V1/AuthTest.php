<?php

namespace Tests\Feature\Api\V1;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
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
    }

    public function test_public_content_routes_do_not_require_token(): void
    {
        $this->getJson('/api/v1/events')->assertOk();
        $this->getJson('/api/v1/news')->assertOk();
        $this->getJson('/api/v1/articles')->assertOk();
        $this->getJson('/api/v1/videos')->assertOk();
    }

    public function test_logout_revokes_current_token(): void
    {
        $token = $this->user->createToken('mobile')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_email_is_placeholder_only_when_local_part_matches_ic(): void
    {
        $this->assertFalse($this->user->emailIsPlaceholder());

        $legacy = User::factory()->create([
            'email' => strtolower('B7654321').'@mywap.my',
            'ic_number' => 'B7654321',
        ]);

        $this->assertTrue($legacy->fresh()->emailIsPlaceholder());
    }

    public function test_send_otp_rejects_placeholder_email_so_member_can_update_it(): void
    {
        Queue::fake();

        $legacy = User::factory()->create([
            'email' => strtolower('B7654321').'@mywap.my',
            'ic_number' => 'B7654321',
            'current_organization_id' => $this->org->id,
            'first_login_at' => null,
        ]);

        $this->postJson('/api/v1/auth/send-otp', ['ic_number' => 'B7654321'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Akaun ini tiada emel berdaftar.');

        $this->postJson('/api/v1/auth/update-and-send-otp', [
            'ic_number' => 'B7654321',
            'email' => 'legacy@real-mail.test',
        ])->assertOk();
    }

    public function test_send_otp_can_be_resent(): void
    {
        Queue::fake();

        $member = User::factory()->create([
            'email' => 'member@real-mail.test',
            'ic_number' => 'C1111222',
            'current_organization_id' => $this->org->id,
            'first_login_at' => null,
        ]);

        $this->postJson('/api/v1/auth/send-otp', ['ic_number' => 'C1111222'])
            ->assertOk();

        $this->postJson('/api/v1/auth/send-otp', ['ic_number' => 'C1111222'])
            ->assertOk();
    }

    public function test_public_traffic_does_not_exhaust_login_rate_limit(): void
    {
        // Dulu semua `throttle:N,M` berkongsi satu bucket domain+IP, jadi
        // melayari kandungan awam boleh menamatkan kuota login (429) walaupun
        // pengguna baru cuba log masuk sekali.
        for ($i = 0; $i < 30; $i++) {
            $this->getJson('/api/v1/events')->assertOk();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'password',
        ])->assertOk();
    }

    public function test_repeated_wrong_password_gets_friendly_message_not_429(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $this->user->email,
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        // Percubaan ke-6: had per-akaun (5/min) patut beri mesej mesra 422,
        // bukan 429 mentah daripada had route.
        $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'wrong-password',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }
}

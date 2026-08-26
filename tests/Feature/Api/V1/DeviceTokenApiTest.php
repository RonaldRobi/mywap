<?php

namespace Tests\Feature\Api\V1;

use App\Models\DeviceToken;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DeviceTokenApiTest extends TestCase
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
            'current_organization_id' => $this->org->id,
            'first_login_at' => now(),
            'profile_completed_at' => now(),
        ]);
        $this->user->assignRole('Member');
    }

    public function test_register_creates_device_token(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/v1/device-tokens', [
            'token' => 'fcm-token-123',
            'platform' => 'android',
            'device_name' => 'Pixel 7',
        ])
            ->assertOk()
            ->assertJsonPath('data.registered', true);

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $this->user->id,
            'token' => 'fcm-token-123',
            'platform' => 'android',
            'device_name' => 'Pixel 7',
        ]);
    }

    public function test_duplicate_register_updates_not_duplicates(): void
    {
        Sanctum::actingAs($this->user);

        $payload = ['token' => 'fcm-token-123', 'platform' => 'android'];

        $this->postJson('/api/v1/device-tokens', $payload)->assertOk();
        $this->postJson('/api/v1/device-tokens', array_merge($payload, ['platform' => 'ios']))->assertOk();

        $this->assertDatabaseCount('device_tokens', 1);
        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $this->user->id,
            'token' => 'fcm-token-123',
            'platform' => 'ios',
        ]);
    }

    public function test_platform_validation_returns_422(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/v1/device-tokens', [
            'token' => 'fcm-token-123',
            'platform' => 'web',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('platform');

        $this->postJson('/api/v1/device-tokens', [
            'platform' => 'android',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('token');

        $this->assertDatabaseCount('device_tokens', 0);
    }

    public function test_unregister_deletes_token(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/v1/device-tokens', [
            'token' => 'fcm-token-123',
            'platform' => 'android',
        ])->assertOk();

        $this->deleteJson('/api/v1/device-tokens', ['token' => 'fcm-token-123'])
            ->assertOk()
            ->assertJsonPath('data.registered', false);

        $this->assertDatabaseCount('device_tokens', 0);
    }

    public function test_routes_require_auth(): void
    {
        $this->postJson('/api/v1/device-tokens', [
            'token' => 'fcm-token-123',
            'platform' => 'android',
        ])->assertStatus(401);

        $this->deleteJson('/api/v1/device-tokens', ['token' => 'fcm-token-123'])->assertStatus(401);
    }

    public function test_max_5_tokens_per_user_cap(): void
    {
        Sanctum::actingAs($this->user);

        for ($i = 1; $i <= 6; $i++) {
            $this->postJson('/api/v1/device-tokens', [
                'token' => "fcm-token-{$i}",
                'platform' => 'android',
            ])->assertOk();
        }

        $this->assertDatabaseCount('device_tokens', 5);

        $remaining = DeviceToken::where('user_id', $this->user->id)->pluck('token')->all();
        $this->assertNotContains('fcm-token-1', $remaining);
        $this->assertContains('fcm-token-6', $remaining);
    }
}

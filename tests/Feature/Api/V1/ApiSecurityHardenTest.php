<?php

namespace Tests\Feature\Api\V1;

use App\Models\OtpCode;
use App\Models\Organization;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression test untuk pengukuhan keselamatan kritikal API:
 *  - token Sanctum ada abilities + expiration (K1)
 *  - route /admin/* disekat di lapisan routing (K1)
 *  - OTP dibakar selepas had percubaan salah (K2)
 *  - tukar password tarik balik token lain (M1)
 */
class ApiSecurityHardenTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'org-admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $this->org = Organization::factory()->create(['name' => 'PKPIM', 'fee_amount' => 50.00]);

        $this->member = User::factory()->create([
            'email' => 'member.security@pkpim.test',
            'ic_number' => 'SEC0001',
            'current_organization_id' => $this->org->id,
            'first_login_at' => now(),
            'profile_completed_at' => now(),
        ]);
        $this->member->assignRole('Member');
    }

    public function test_login_issues_token_with_ability_and_expiration(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $this->member->email,
            'password' => 'password',
        ]);

        $response->assertOk();

        /** @var PersonalAccessToken $record */
        $record = PersonalAccessToken::query()
            ->where('tokenable_id', $this->member->id)
            ->latest()
            ->first();

        $this->assertNotNull($record, 'Token tidak direkodkan.');
        $this->assertTrue($record->can('member'));
        $this->assertNotNull($record->expires_at, 'Token sepatutnya ada tarikh luput.');
    }

    public function test_admin_route_rejects_member_bearer_token(): void
    {
        $token = $this->member->createToken('mobile', $this->member->apiTokenAbilities())->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/admin/dashboard')
            ->assertStatus(403);
    }

    public function test_otp_is_burned_after_max_wrong_attempts(): void
    {
        Notification::fake();

        $firstLogin = User::factory()->create([
            'email' => 'otp.security@pkpim.test',
            'ic_number' => 'OTP0001',
            'current_organization_id' => $this->org->id,
            'first_login_at' => null,
        ]);
        $firstLogin->assignRole('Member');

        $this->postJson('/api/v1/auth/send-otp', ['ic_number' => 'OTP0001'])->assertOk();

        $otp = OtpCode::where('user_id', $firstLogin->id)
            ->where('purpose', 'login')
            ->latest()
            ->first();
        $this->assertNotNull($otp);

        $wrong = $otp->code === '000000' ? '999999' : '000000';

        for ($i = 0; $i < OtpService::MAX_ATTEMPTS; $i++) {
            $this->postJson('/api/v1/auth/verify-otp', [
                'ic_number' => 'OTP0001',
                'code' => $wrong,
            ])->assertStatus(422);
        }

        $otp->refresh();
        $this->assertNotNull($otp->used_at, 'OTP sepatutnya dibakar selepas had percubaan.');
        $this->assertSame(OtpService::MAX_ATTEMPTS, $otp->attempts);

        // Walaupun kod BETUL dihantar, ia sudah tidak diterima.
        $this->postJson('/api/v1/auth/verify-otp', [
            'ic_number' => 'OTP0001',
            'code' => $otp->code,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertStatus(422);
    }

    public function test_password_change_revokes_other_tokens_but_keeps_current(): void
    {
        $tokenA = $this->member->createToken('mobile', ['member'])->plainTextToken;
        $tokenB = $this->member->createToken('mobile', ['member'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->postJson('/api/v1/profile/password', [
                'current_password' => 'password',
                'password' => 'NewPassword1!',
                'password_confirmation' => 'NewPassword1!',
            ])
            ->assertOk();

        $this->assertSame(
            1,
            PersonalAccessToken::where('tokenable_id', $this->member->id)->count(),
            'Token lain sepatutnya dibatalkan.'
        );

        // Token B mesti sudah tiada dalam storan (dibatalkan).
        $this->assertNull(PersonalAccessToken::findToken($tokenB), 'Token yang dibatalkan masih wujud.');
        $this->assertNotNull(PersonalAccessToken::findToken($tokenA), 'Token semasa tidak sepatutnya dibatalkan.');
    }
}

<?php

namespace Tests\Feature\Api\V1;

use App\Models\OtpCode;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Covers the mobile-only endpoints added for full web/Flutter parity:
 * register, forgot-id, forgot-password, first-login OTP flow.
 */
class AuthOnboardingFlowTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $this->org = Organization::factory()->create([
            'name' => 'PKPIM',
            'slug' => 'pkpim',
            'min_age' => 0,
            'max_age' => null,
            'fee_amount' => 50,
        ]);
    }

    public function test_register_creates_member_and_activates_fee(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ahli Baharu',
            'email' => 'ahli.baharu@example.test',
            'ic_number' => '000101-01-0101',
            'phone' => '0123456789',
            'dob' => '2000-01-01',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['member_no', 'message']]);

        $this->assertDatabaseHas('users', [
            'email' => 'ahli.baharu@example.test',
        ]);

        $user = User::where('email', 'ahli.baharu@example.test')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('Member'));
        $this->assertStringStartsWith('P', $user->member_no);
        $this->assertDatabaseHas('membership_fees', [
            'user_id' => $user->id,
            'status' => 'paid',
        ]);
    }

    public function test_register_rejects_duplicate_ic(): void
    {
        User::factory()->create([
            'ic_number' => 'A1234567',
            'current_organization_id' => $this->org->id,
        ]);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Ahli Lain',
            'email' => 'lain@example.test',
            'ic_number' => 'A1234567',
            'dob' => '2000-01-01',
        ])->assertStatus(422)->assertJsonValidationErrors('ic_number');
    }

    public function test_check_member_returns_status_for_existing_user(): void
    {
        $user = User::factory()->create([
            'email' => 'semak@pkpim.test',
            'ic_number' => 'B9876543',
            'current_organization_id' => $this->org->id,
            'first_login_at' => null,
        ]);
        $user->assignRole('Member');

        $response = $this->postJson('/api/v1/auth/check-member', [
            'identifier' => 'B9876543',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.is_first_login', true)
            ->assertJsonPath('data.is_member', true);
    }

    public function test_check_member_not_found(): void
    {
        $this->postJson('/api/v1/auth/check-member', [
            'identifier' => 'TIADA123',
        ])->assertStatus(404);
    }

    public function test_forgot_id_requires_dob_verification(): void
    {
        $user = User::factory()->create([
            'ic_number' => 'C1112223',
            'current_organization_id' => $this->org->id,
            'dob' => '1995-05-05',
        ]);

        $step1 = $this->postJson('/api/v1/auth/forgot-id', [
            'ic_number' => 'C1112223',
        ]);
        $step1->assertOk()->assertJsonPath('data.needs_verification', true);

        $step2Wrong = $this->postJson('/api/v1/auth/forgot-id', [
            'ic_number' => 'C1112223',
            'dob' => '1990-01-01',
        ]);
        $step2Wrong->assertStatus(422);

        $step2 = $this->postJson('/api/v1/auth/forgot-id', [
            'ic_number' => 'C1112223',
            'dob' => '1995-05-05',
        ]);
        $step2->assertOk()
            ->assertJsonPath('data.verified', true)
            ->assertJsonPath('data.member_no', $user->member_no);
    }

    public function test_forgot_password_sends_reset_link_for_ic(): void
    {
        Notification::fake();

        User::factory()->create([
            'ic_number' => 'D5556667',
            'email' => 'resetme@pkpim.test',
            'current_organization_id' => $this->org->id,
        ]);

        $this->postJson('/api/v1/auth/forgot-password', [
            'ic_number' => 'D5556667',
        ])->assertOk()->assertJsonStructure(['data' => ['message', 'masked_email']]);
    }

    public function test_forgot_password_fails_when_no_email(): void
    {
        $user = User::factory()->create([
            'ic_number' => 'E7778889',
            'current_organization_id' => $this->org->id,
        ]);
        $user->forceFill(['email' => ''])->saveQuietly();

        $this->postJson('/api/v1/auth/forgot-password', [
            'ic_number' => 'E7778889',
        ])->assertStatus(422);
    }

    public function test_first_login_otp_flow_issues_token(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'ic_number' => 'F9990001',
            'email' => 'firstlogin@pkpim.test',
            'current_organization_id' => $this->org->id,
            'first_login_at' => null,
        ]);
        $user->assignRole('Member');

        $this->postJson('/api/v1/auth/send-otp', [
            'ic_number' => 'F9990001',
        ])->assertOk();

        $otp = OtpCode::where('user_id', $user->id)->where('purpose', 'login')->latest()->first();
        $this->assertNotNull($otp);

        $verify = $this->postJson('/api/v1/auth/verify-otp', [
            'ic_number' => 'F9990001',
            'code' => $otp->code,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $verify->assertOk()
            ->assertJsonStructure(['data' => ['token', 'token_type', 'user']]);

        $user->refresh();
        $this->assertNotNull($user->first_login_at, 'verify response: '.$verify->getContent());
        $this->assertTrue(Hash::check('newpassword123', $user->password));
    }

    public function test_send_otp_blocked_if_already_first_logged_in(): void
    {
        $user = User::factory()->create([
            'ic_number' => 'G1212121',
            'email' => 'already@pkpim.test',
            'current_organization_id' => $this->org->id,
            'first_login_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/send-otp', [
            'ic_number' => 'G1212121',
        ])->assertStatus(422);
    }

    public function test_verify_otp_rejects_invalid_code(): void
    {
        $user = User::factory()->create([
            'ic_number' => 'H3434343',
            'email' => 'invalidotp@pkpim.test',
            'current_organization_id' => $this->org->id,
            'first_login_at' => null,
        ]);

        $this->postJson('/api/v1/auth/verify-otp', [
            'ic_number' => 'H3434343',
            'code' => '000000',
        ])->assertStatus(422);
    }
}

<?php

namespace Tests\Feature;

use App\Models\MembershipFee;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ResetMembershipFeesTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        config(['membership.fee_start_year' => 2027]);

        Role::create(['name' => 'Member', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);

        $this->org = Organization::factory()->create(['fee_amount' => 50.00]);
    }

    public function test_reset_clears_debts_but_keeps_life_members_and_skips_admins(): void
    {
        $member = $this->member(['name' => 'Ahli Biasa']);
        $inactive = $this->member(['name' => 'Ahli Tidak Aktif', 'is_active' => false]);
        $life = $this->member(['name' => 'Ahli Seumur Hidup']);
        $deceased = $this->member(['name' => 'Ahli Meninggal', 'position' => 'Meninggal Dunia']);

        $admin = User::factory()->create([
            'current_organization_id' => $this->org->id,
        ]);
        $admin->assignRole('Admin');

        MembershipFee::factory()->create([
            'user_id' => $member->id,
            'organization_id' => $this->org->id,
            'year' => 2025,
            'amount' => 50,
            'status' => 'unpaid',
        ]);
        MembershipFee::factory()->create([
            'user_id' => $member->id,
            'organization_id' => $this->org->id,
            'year' => 2026,
            'amount' => 50,
            'status' => 'paid',
        ]);
        MembershipFee::factory()->create([
            'user_id' => $life->id,
            'organization_id' => $this->org->id,
            'year' => 2026,
            'amount' => 0,
            'status' => 'life_member',
            'notes' => 'Ahli seumur hidup',
        ]);
        MembershipFee::factory()->create([
            'user_id' => $deceased->id,
            'organization_id' => $this->org->id,
            'year' => 2026,
            'amount' => 50,
            'status' => 'unpaid',
        ]);
        MembershipFee::factory()->create([
            'user_id' => $admin->id,
            'organization_id' => $this->org->id,
            'year' => 2026,
            'amount' => 50,
            'status' => 'unpaid',
        ]);

        $this->artisan('fees:reset-baseline')->assertSuccessful();

        $this->assertDatabaseHas('membership_fees', [
            'user_id' => $member->id,
            'year' => 2026,
            'amount' => 0,
            'status' => 'paid',
            'payment_id' => null,
        ]);
        $this->assertDatabaseHas('membership_fees', [
            'user_id' => $member->id,
            'year' => 2025,
            'amount' => 0,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('membership_fees', [
            'user_id' => $life->id,
            'status' => 'life_member',
        ]);

        $this->assertDatabaseHas('membership_fees', [
            'user_id' => $deceased->id,
            'year' => 2026,
            'amount' => 0,
            'status' => 'paid',
        ]);
        $this->assertSame('deceased', $deceased->fresh()->membership_status);

        $this->assertDatabaseMissing('membership_fees', [
            'user_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('membership_fees', [
            'user_id' => $inactive->id,
            'year' => 2026,
            'amount' => 0,
            'status' => 'paid',
        ]);
    }

    public function test_dry_run_does_not_change_data(): void
    {
        $member = $this->member();

        MembershipFee::factory()->create([
            'user_id' => $member->id,
            'organization_id' => $this->org->id,
            'year' => 2026,
            'amount' => 50,
            'status' => 'unpaid',
        ]);

        $this->artisan('fees:reset-baseline', ['--dry-run' => true])->assertSuccessful();

        $this->assertDatabaseHas('membership_fees', [
            'user_id' => $member->id,
            'year' => 2026,
            'amount' => 50,
            'status' => 'unpaid',
        ]);
    }

    public function test_reset_is_idempotent(): void
    {
        $member = $this->member();

        $fee = MembershipFee::factory()->create([
            'user_id' => $member->id,
            'organization_id' => $this->org->id,
            'year' => 2026,
            'amount' => 50,
            'status' => 'unpaid',
        ]);

        $this->artisan('fees:reset-baseline')->assertSuccessful();

        $first = DB::table('membership_fees')->where('id', $fee->id)->first();

        $this->artisan('fees:reset-baseline')->assertSuccessful();

        $second = DB::table('membership_fees')->where('id', $fee->id)->first();

        $this->assertSame((string) $first->updated_at, (string) $second->updated_at);
        $this->assertSame('paid', $second->status);
        $this->assertEquals(0.0, (float) $second->amount);
    }

    private function member(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'current_organization_id' => $this->org->id,
        ], $attributes));

        $user->assignRole('Member');

        return $user;
    }
}

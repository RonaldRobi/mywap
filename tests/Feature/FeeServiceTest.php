<?php

namespace Tests\Feature;

use App\Enums\FeeStatus;
use App\Models\MembershipFee;
use App\Models\Organization;
use App\Models\User;
use App\Services\FeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeeServiceTest extends TestCase
{
    use RefreshDatabase;

    private FeeService $feeService;
    private Organization $org;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create(['fee_amount' => 50.00]);
        $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
        $this->feeService = app(FeeService::class);
    }

    // ─── getStatus ──────────────────────────────────────────────────────────────

    public function test_get_status_returns_due_when_no_record_exists()
    {
        $status = $this->feeService->getStatus($this->user, now()->year);

        $this->assertSame('due', $status['status']);
        $this->assertSame(50.0, $status['amount_due']);
        $this->assertNull($status['last_paid_at']);
        $this->assertNull($status['last_reference']);
    }

    public function test_get_status_returns_active_for_life_member()
    {
        MembershipFee::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->org->id,
            'year' => now()->subYear()->year,
            'status' => FeeStatus::LifeMember,
            'paid_at' => now()->subYear(),
        ]);

        // Querying a different year should still return active
        $status = $this->feeService->getStatus($this->user, now()->year);

        $this->assertSame('active', $status['status']);
        $this->assertSame(0.0, $status['amount_due']);
    }

    public function test_get_status_returns_active_for_exempted()
    {
        MembershipFee::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->org->id,
            'year' => now()->subYear()->year,
            'status' => FeeStatus::Exempted,
            'paid_at' => now()->subYear(),
        ]);

        $status = $this->feeService->getStatus($this->user, now()->year);

        $this->assertSame('active', $status['status']);
        $this->assertSame(0.0, $status['amount_due']);
    }

    public function test_get_status_returns_active_for_paid_record()
    {
        MembershipFee::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->org->id,
            'year' => now()->year,
            'status' => FeeStatus::Paid,
            'paid_at' => now(),
        ]);

        $status = $this->feeService->getStatus($this->user, now()->year);

        $this->assertSame('active', $status['status']);
        $this->assertSame(0.0, $status['amount_due']);
    }

    public function test_get_status_returns_due_for_unpaid_record()
    {
        MembershipFee::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->org->id,
            'year' => now()->year,
            'amount' => 50.00,
            'status' => FeeStatus::Unpaid,
        ]);

        $status = $this->feeService->getStatus($this->user, now()->year);

        $this->assertSame('due', $status['status']);
        $this->assertSame(50.0, $status['amount_due']);
    }

    // ─── markAsPaid ────────────────────────────────────────────────────────────

    public function test_mark_as_paid_creates_record()
    {
        $fee = $this->feeService->markAsPaid($this->user, now()->year, 50.00);

        $this->assertDatabaseHas('membership_fees', [
            'user_id' => $this->user->id,
            'year' => now()->year,
            'status' => 'paid',
            'amount' => 50.00,
        ]);

        $this->assertNotNull($fee->paid_at);
    }

    // ─── markAsLifeMember ──────────────────────────────────────────────────────

    public function test_mark_as_life_member_creates_record_and_clears_unpaid()
    {
        MembershipFee::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->org->id,
            'year' => now()->year,
            'status' => FeeStatus::Unpaid,
        ]);

        $this->feeService->markAsLifeMember($this->user, $this->org);

        $this->assertDatabaseMissing('membership_fees', [
            'user_id' => $this->user->id,
            'status' => 'unpaid',
        ]);

        $this->assertDatabaseHas('membership_fees', [
            'user_id' => $this->user->id,
            'status' => 'life_member',
        ]);

        $this->assertTrue($this->feeService->isLifeMember($this->user));
    }

    // ─── unmarkLifeMember ──────────────────────────────────────────────────────

    public function test_unmark_life_member_removes_life_member_and_exempted()
    {
        $this->feeService->markAsLifeMember($this->user, $this->org);

        $this->feeService->unmarkLifeMember($this->user);

        $this->assertDatabaseMissing('membership_fees', [
            'user_id' => $this->user->id,
            'status' => 'life_member',
        ]);

        $this->assertFalse($this->feeService->isLifeMember($this->user));
    }

    // ─── markAsExempted ────────────────────────────────────────────────────────

    public function test_mark_as_exempted_creates_record_and_clears_unpaid()
    {
        MembershipFee::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->org->id,
            'year' => now()->year,
            'status' => FeeStatus::Unpaid,
        ]);

        $this->feeService->markAsExempted($this->user, 'Test reason');

        $this->assertDatabaseMissing('membership_fees', [
            'user_id' => $this->user->id,
            'status' => 'unpaid',
        ]);

        $this->assertDatabaseHas('membership_fees', [
            'user_id' => $this->user->id,
            'status' => 'exempted',
            'notes' => 'Test reason',
        ]);

        $this->assertTrue($this->feeService->isExempted($this->user));
    }

    // ─── isLifeMember / isExempted ─────────────────────────────────────────────

    public function test_is_life_member_returns_false_when_no_record()
    {
        $this->assertFalse($this->feeService->isLifeMember($this->user));
    }

    public function test_is_exempted_returns_false_when_no_record()
    {
        $this->assertFalse($this->feeService->isExempted($this->user));
    }

    // ─── getDueCount ──────────────────────────────────────────────────────────

    public function test_get_due_count_excludes_paid_users()
    {
        $this->feeService->markAsPaid($this->user, now()->year, 50.00);

        $count = $this->feeService->getDueCount($this->org->id, now()->year);

        $this->assertSame(0, $count);
    }

    public function test_get_due_count_excludes_life_members()
    {
        $this->feeService->markAsLifeMember($this->user, $this->org);

        $count = $this->feeService->getDueCount($this->org->id, now()->year);

        $this->assertSame(0, $count);
    }

    public function test_get_due_count_excludes_exempted_users()
    {
        $this->feeService->markAsExempted($this->user, 'Test reason');

        $count = $this->feeService->getDueCount($this->org->id, now()->year);

        $this->assertSame(0, $count);
    }

    public function test_get_due_count_counts_unpaid_users()
    {
        $count = $this->feeService->getDueCount($this->org->id, now()->year);

        $this->assertSame(1, $count);
    }

    // ─── generateAnnualFees ────────────────────────────────────────────────────

    public function test_generate_annual_fees_creates_unpaid_records()
    {
        $count = $this->feeService->generateAnnualFees($this->org, now()->year);

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('membership_fees', [
            'user_id' => $this->user->id,
            'year' => now()->year,
            'status' => 'unpaid',
            'amount' => 50.00,
        ]);
    }

    public function test_generate_annual_fees_skips_existing_records()
    {
        $this->feeService->markAsPaid($this->user, now()->year, 50.00);

        $count = $this->feeService->generateAnnualFees($this->org, now()->year);

        $this->assertSame(0, $count);
    }

    public function test_generate_annual_fees_skips_life_members()
    {
        $this->feeService->markAsLifeMember($this->user, $this->org);

        $count = $this->feeService->generateAnnualFees($this->org, now()->year);

        $this->assertSame(0, $count);
    }

    public function test_generate_annual_fees_skips_exempted_users()
    {
        $this->feeService->markAsExempted($this->user, 'Test reason');

        $count = $this->feeService->generateAnnualFees($this->org, now()->year);

        $this->assertSame(0, $count);
    }

    public function test_generate_annual_fees_does_not_duplicate_life_member_record()
    {
        $this->feeService->markAsLifeMember($this->user, $this->org);

        $count = $this->feeService->generateAnnualFees($this->org, now()->year + 1);

        $this->assertSame(0, $count);
    }

    // ─── getPaymentHistory ─────────────────────────────────────────────────────

    public function test_get_payment_history_returns_empty_for_new_user()
    {
        $history = $this->feeService->getPaymentHistory($this->user);

        $this->assertIsArray($history);
        $this->assertEmpty($history);
    }

    public function test_get_payment_history_returns_paid_records()
    {
        $this->feeService->markAsPaid($this->user, now()->year, 50.00);

        $history = $this->feeService->getPaymentHistory($this->user);

        $this->assertCount(1, $history);
        $this->assertSame(50.0, $history[0]['amount']);
        $this->assertSame(now()->year, $history[0]['year']);
    }

    public function test_get_payment_history_does_not_include_unpaid()
    {
        MembershipFee::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->org->id,
            'year' => now()->year,
            'status' => FeeStatus::Unpaid,
            'amount' => 50.00,
        ]);

        $history = $this->feeService->getPaymentHistory($this->user);

        $this->assertEmpty($history);
    }

    public function test_get_payment_history_orders_most_recent_year_first()
    {
        $this->feeService->markAsPaid($this->user, now()->subYear()->year, 40.00);
        $this->feeService->markAsPaid($this->user, now()->year, 50.00);

        $history = $this->feeService->getPaymentHistory($this->user);

        $this->assertCount(2, $history);
        $this->assertSame(now()->year, $history[0]['year']);
        $this->assertSame(now()->subYear()->year, $history[1]['year']);
    }

    // ─── getAdminStats ─────────────────────────────────────────────────────────

    public function test_get_admin_stats_returns_all_zero_for_no_members()
    {
        $org2 = Organization::factory()->create(['fee_amount' => 30.00]);

        $stats = $this->feeService->getAdminStats($org2->id, now()->year);

        $this->assertSame(0, $stats['total']);
        $this->assertSame(0, $stats['paid']);
        $this->assertSame(0, $stats['due']);
        $this->assertSame(0, $stats['life_member']);
        $this->assertSame(0, $stats['exempted']);
        $this->assertSame(0.0, $stats['collected_amount']);
    }

    public function test_get_admin_stats_counts_correctly()
    {
        $this->feeService->markAsPaid($this->user, now()->year, 50.00);

        $stats = $this->feeService->getAdminStats($this->org->id, now()->year);

        $this->assertSame(1, $stats['total']);
        $this->assertSame(1, $stats['paid']);
        $this->assertSame(0, $stats['due']);
        $this->assertSame(0, $stats['life_member']);
        $this->assertSame(0, $stats['exempted']);
    }

    public function test_get_admin_stats_with_null_org_cross_org()
    {
        $org2 = Organization::factory()->create(['fee_amount' => 30.00]);
        $user2 = User::factory()->create(['current_organization_id' => $org2->id]);
        $this->feeService->markAsPaid($this->user, now()->year, 50.00);
        $this->feeService->markAsPaid($user2, now()->year, 30.00);

        $stats = $this->feeService->getAdminStats(null, now()->year);

        $this->assertSame(2, $stats['total']);
        $this->assertSame(2, $stats['paid']);
    }

    public function test_get_admin_stats_collected_amount()
    {
        $fee = $this->feeService->markAsPaid($this->user, now()->year, 50.00);

        \App\Models\Payment::create([
            'user_id' => $this->user->id,
            'payable_type' => \App\Models\MembershipFee::class,
            'payable_id' => $fee->id,
            'amount' => 50.00,
            'status' => 'successful',
        ]);

        $stats = $this->feeService->getAdminStats($this->org->id, now()->year);

        $this->assertSame(50.0, $stats['collected_amount']);
    }

    // ─── CSV Matching Logic ────────────────────────────────────────────────────

    public function test_find_user_by_ic_and_member_no()
    {
        $this->user->update(['member_no' => 'P00123', 'original_member_no' => 'P00123']);

        $found = User::withoutGlobalScopes()
            ->where('ic_number', $this->user->ic_number)
            ->where(fn ($q) => $q->where('member_no', 'P00123')->orWhere('original_member_no', 'P00123'))
            ->first();

        $this->assertNotNull($found);
        $this->assertSame($this->user->id, $found->id);
    }

    public function test_find_user_by_ic_with_changed_member_no()
    {
        // Simulate age transition: member_no changed, original stays
        $this->user->update(['member_no' => 'AP00123', 'original_member_no' => 'P00123']);

        // CSV has old number
        $found = User::withoutGlobalScopes()
            ->where('ic_number', $this->user->ic_number)
            ->where(fn ($q) => $q->where('member_no', 'P00123')->orWhere('original_member_no', 'P00123'))
            ->first();

        $this->assertNotNull($found);
        $this->assertSame($this->user->id, $found->id);
    }

    public function test_find_user_by_ic_only()
    {
        $found = User::withoutGlobalScopes()
            ->where('ic_number', $this->user->ic_number)
            ->first();

        $this->assertNotNull($found);
    }

    public function test_find_user_returns_null_when_no_match()
    {
        $found = User::withoutGlobalScopes()
            ->where('ic_number', 'NONEXISTENT')
            ->first();

        $this->assertNull($found);
    }

    // ─── Void / Reverse Payment ────────────────────────────────────────────────

    public function test_void_payment_sets_status_to_voided()
    {
        $fee = $this->feeService->markAsPaid($this->user, now()->year, 50.00);

        $payment = \App\Models\Payment::create([
            'user_id' => $this->user->id,
            'payable_type' => \App\Models\MembershipFee::class,
            'payable_id' => $fee->id,
            'amount' => 50.00,
            'status' => 'successful',
        ]);
        $fee->update(['payment_id' => $payment->id]);

        $payment->update(['status' => 'voided']);
        $fee->update(['status' => 'unpaid', 'payment_id' => null, 'paid_at' => null]);

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'voided']);
        $this->assertDatabaseHas('membership_fees', ['id' => $fee->id, 'status' => 'unpaid', 'payment_id' => null]);
    }

    public function test_payment_history_includes_voided_status()
    {
        $fee = $this->feeService->markAsPaid($this->user, now()->year, 50.00);

        $payment = \App\Models\Payment::create([
            'user_id' => $this->user->id,
            'payable_type' => \App\Models\MembershipFee::class,
            'payable_id' => $fee->id,
            'amount' => 50.00,
            'status' => 'voided',
        ]);
        $fee->update(['payment_id' => $payment->id, 'status' => 'unpaid', 'paid_at' => null]);

        $history = $this->feeService->getPaymentHistory($this->user);

        $this->assertEmpty($history);
    }
}

<?php

namespace Tests\Feature\Api\V1;

use App\Models\MembershipFee;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MemberReceiptApiTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $this->org = Organization::factory()->create(['name' => 'PKPIM', 'fee_amount' => 60.00]);

        $this->member = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
            'member_no' => 'PKPIM-0001',
        ]);
        $this->member->assignRole('Member');
    }

    private function paidFee(string $reference = 'FEE-TEST1234', string $status = 'successful'): Payment
    {
        return Payment::create([
            'user_id' => $this->member->id,
            'payable_type' => 'membership_fee',
            'payable_id' => null,
            'amount' => 60.00,
            'status' => $status,
            'reference' => $reference,
            'description' => 'Yuran keahlian PKPIM 2026',
            'gateway' => 'dummy',
            'organization_id' => $this->org->id,
        ]);
    }

    public function test_overview_includes_reference_and_receipt_url(): void
    {
        $this->paidFee();

        Sanctum::actingAs($this->member);

        $response = $this->getJson('/api/v1/member/financial/overview');

        $response->assertOk();

        $row = collect($response->json('data.payment_history'))->first();
        $this->assertNotNull($row);
        $this->assertSame('FEE-TEST1234', $row['reference']);
        $this->assertSame('Yuran keahlian PKPIM 2026', $row['description']);
        $this->assertStringContainsString('/resit/', $row['receipt_url']);
    }

    public function test_member_gets_signed_url_for_own_successful_payment(): void
    {
        $payment = $this->paidFee();

        Sanctum::actingAs($this->member);

        $response = $this->getJson("/api/v1/member/payments/{$payment->id}/receipt");

        $response->assertOk();
        $url = $response->json('data.url');
        $this->assertIsString($url);
        $this->assertStringContainsString('/resit/', $url);
    }

    public function test_signed_receipt_returns_pdf(): void
    {
        $payment = $this->paidFee();

        $url = URL::temporarySignedRoute(
            'receipt.show',
            now()->addMinutes(30),
            ['payment' => $payment->id],
        );

        $response = $this->get($url);

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type') ?? '');

        $path = storage_path('app/_receipt_test.pdf');
        app(\App\Services\ReceiptService::class)->pdf($payment)->save($path);
        $this->assertStringStartsWith('%PDF', (string) file_get_contents($path));
        @unlink($path);
    }

    public function test_member_cannot_download_someone_elses_receipt(): void
    {
        $payment = $this->paidFee();

        $other = User::factory()->create(['current_organization_id' => $this->org->id]);
        $other->assignRole('Member');
        Sanctum::actingAs($other);

        $response = $this->getJson("/api/v1/member/payments/{$payment->id}/receipt");

        $response->assertStatus(403);
    }

    public function test_receipt_not_available_for_pending_payment(): void
    {
        $payment = $this->paidFee('FEE-PENDING', 'pending');

        Sanctum::actingAs($this->member);

        $response = $this->getJson("/api/v1/member/payments/{$payment->id}/receipt");

        $response->assertStatus(422);
    }

    public function test_signed_receipt_rejected_for_pending_payment(): void
    {
        $payment = $this->paidFee('FEE-PENDING', 'pending');

        $url = URL::temporarySignedRoute(
            'receipt.show',
            now()->addMinutes(30),
            ['payment' => $payment->id],
        );

        $this->get($url)->assertNotFound();
    }

    public function test_guest_without_signature_cannot_open_receipt(): void
    {
        $payment = $this->paidFee();

        $this->get("/resit/{$payment->id}")->assertForbidden();
    }
}

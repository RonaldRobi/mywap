<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\Organization;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FacilityBookingNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $member;

    private User $admin;

    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $this->org = Organization::factory()->create(['name' => 'PKPIM']);

        $this->member = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
            'member_no' => 'PKPIM-0001',
            'phone' => '0123456789',
        ]);
        $this->member->assignRole('Member');

        $this->admin = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
            'email_verified_at' => now(),
        ]);
        $this->admin->assignRole('Admin');

        $this->facility = Facility::create([
            'organization_id' => $this->org->id,
            'name' => 'Dewan Serbaguna',
            'description' => 'Dewan untuk aktiviti',
            'location' => 'Kuala Lumpur',
            'type' => 'hourly',
            'price_per_unit' => 50.00,
            'member_price_per_unit' => 30.00,
            'capacity' => 100,
            'is_active' => true,
        ]);
    }

    private function createBooking(string $status = 'pending', string $paymentStatus = 'unpaid'): FacilityBooking
    {
        return FacilityBooking::create([
            'facility_id' => $this->facility->id,
            'user_id' => $this->member->id,
            'contact_name' => $this->member->name,
            'contact_phone' => $this->member->phone,
            'start_datetime' => now()->addDay()->startOfDay(),
            'end_datetime' => now()->addDay()->startOfDay()->addHours(2),
            'total_price' => 60.00,
            'booking_status' => $status,
            'payment_status' => $paymentStatus,
        ]);
    }

    public function test_new_booking_notifies_org_admin_in_app_and_via_push(): void
    {
        $this->mock(PushNotificationService::class, function ($mock) {
            $mock->shouldReceive('sendToUsers')->once();
        });

        Sanctum::actingAs($this->member);

        $this->postJson("/api/v1/facilities/{$this->facility->id}/book", [
            'start_datetime' => now()->addDay()->startOfDay()->toDateTimeString(),
            'end_datetime' => now()->addDay()->startOfDay()->addHours(2)->toDateTimeString(),
        ])->assertStatus(201);

        $notification = $this->admin->notifications()->first();

        $this->assertNotNull($notification);
        $this->assertSame('Tempahan fasiliti baharu', $notification->data['title']);
        $this->assertSame('facility_booking', $notification->data['type']);
        $this->assertSame('booking_created', $notification->data['event']);
    }

    public function test_approved_booking_notifies_member(): void
    {
        $booking = $this->createBooking('pending');

        $this->actingAs($this->admin)->patch(
            route('admin.facility-bookings.update', $booking),
            ['booking_status' => 'approved', 'admin_remarks' => 'OK, boleh guna.']
        );

        $notification = $this->member->notifications()->first();

        $this->assertNotNull($notification);
        $this->assertSame('Tempahan diluluskan', $notification->data['title']);
        $this->assertSame('booking_approved', $notification->data['event']);
        $this->assertStringContainsString('OK, boleh guna.', $notification->data['content']);

        $this->assertDatabaseHas('facility_bookings', [
            'id' => $booking->id,
            'booking_status' => 'approved',
        ]);
    }

    public function test_rejected_booking_notifies_member(): void
    {
        $booking = $this->createBooking('pending');

        $this->actingAs($this->admin)->patch(
            route('admin.facility-bookings.update', $booking),
            ['booking_status' => 'rejected', 'admin_remarks' => 'Bilik telah ditempah lain.']
        );

        $notification = $this->member->notifications()->first();

        $this->assertNotNull($notification);
        $this->assertSame('Tempahan ditolak', $notification->data['title']);
        $this->assertSame('booking_rejected', $notification->data['event']);
    }

    public function test_admin_can_mark_approved_booking_as_paid(): void
    {
        $booking = $this->createBooking('approved');

        $this->actingAs($this->admin)->patch(
            route('admin.facility-bookings.pay', $booking)
        );

        $this->assertDatabaseHas('facility_bookings', [
            'id' => $booking->id,
            'booking_status' => 'approved',
            'payment_status' => 'paid',
        ]);

        $notification = $this->member->notifications()->first();

        $this->assertNotNull($notification);
        $this->assertSame('Bayaran tempahan diterima', $notification->data['title']);
        $this->assertSame('payment_recorded', $notification->data['event']);
    }

    public function test_pending_booking_cannot_be_marked_paid(): void
    {
        $booking = $this->createBooking('pending');

        $response = $this->actingAs($this->admin)->patch(
            route('admin.facility-bookings.pay', $booking)
        );

        $response->assertSessionHasErrors('payment_status');

        $this->assertDatabaseHas('facility_bookings', [
            'id' => $booking->id,
            'payment_status' => 'unpaid',
        ]);

        $this->assertCount(0, $this->member->notifications);
    }

    public function test_other_org_admin_cannot_mark_paid(): void
    {
        $otherOrg = Organization::factory()->create(['name' => 'ABIM']);
        $otherAdmin = User::factory()->create([
            'current_organization_id' => $otherOrg->id,
            'profile_completed_at' => now(),
            'email_verified_at' => now(),
        ]);
        $otherAdmin->assignRole('Admin');

        $booking = $this->createBooking('approved');

        $this->actingAs($otherAdmin)
            ->patch(route('admin.facility-bookings.pay', $booking))
            ->assertForbidden();

        $this->assertDatabaseHas('facility_bookings', [
            'id' => $booking->id,
            'payment_status' => 'unpaid',
        ]);
    }
}

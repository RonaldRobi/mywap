<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PublicFacilityAccessTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $this->org = Organization::factory()->create(['name' => 'PKPIM']);

        $this->facility = Facility::create([
            'organization_id' => $this->org->id,
            'name' => 'Dewan Serbaguna',
            'description' => 'Dewan',
            'location' => 'KL',
            'type' => 'hourly',
            'price_per_unit' => 50.00,
            'member_price_per_unit' => 30.00,
            'capacity' => 100,
            'is_active' => true,
        ]);
    }

    public function test_guest_can_view_public_facilities_index(): void
    {
        $this->get(route('member.facilities.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Facilities/Index')
                ->has('facilities', 1)
                ->where('authUser', null));
    }

    public function test_guest_can_view_public_facility_detail(): void
    {
        $this->get(route('member.facilities.show', $this->facility->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Facilities/Show')
                ->where('facility.name', 'Dewan Serbaguna')
                ->where('authUser', null));
    }

    public function test_guest_can_book_facility_with_contact_details(): void
    {
        $start = now()->addDays(3)->setTime(9, 0)->toDateTimeString();
        $end = now()->addDays(3)->setTime(11, 0)->toDateTimeString();

        $this->post(route('member.facilities.book', $this->facility->id), [
            'start_datetime' => $start,
            'end_datetime' => $end,
            'contact_name' => 'Ali Ahmad',
            'contact_phone' => '0123456789',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('facility_bookings', [
            'facility_id' => $this->facility->id,
            'user_id' => null,
            'contact_name' => 'Ali Ahmad',
            'contact_phone' => '0123456789',
            'booking_status' => 'pending',
            'payment_status' => 'unpaid',
        ]);
    }

    public function test_guest_booking_uses_public_price_not_member_price(): void
    {
        $start = now()->addDays(3)->setTime(9, 0)->toDateTimeString();
        $end = now()->addDays(3)->setTime(11, 0)->toDateTimeString();

        $this->post(route('member.facilities.book', $this->facility->id), [
            'start_datetime' => $start,
            'end_datetime' => $end,
            'contact_name' => 'Ali Ahmad',
            'contact_phone' => '0123456789',
        ])->assertSessionHasNoErrors();

        // 2 jam pada RM50 (harga umum) = RM100, bukan RM30 harga ahli.
        $booking = FacilityBooking::where('facility_id', $this->facility->id)->firstOrFail();
        $this->assertEquals(100.0, (float) $booking->total_price);
    }

    public function test_member_can_book_without_contact_fields(): void
    {
        $member = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
            'name' => 'Ahmad Faiz',
            'phone' => '0198765432',
        ]);
        $member->assignRole('Member');

        $start = now()->addDays(3)->setTime(9, 0)->toDateTimeString();
        $end = now()->addDays(3)->setTime(11, 0)->toDateTimeString();

        $this->actingAs($member)->post(route('member.facilities.book', $this->facility->id), [
            'start_datetime' => $start,
            'end_datetime' => $end,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('facility_bookings', [
            'facility_id' => $this->facility->id,
            'user_id' => $member->id,
            'contact_name' => 'Ahmad Faiz',
            'contact_phone' => '0198765432',
            'booking_status' => 'pending',
        ]);
    }

    public function test_admin_cannot_book_facility(): void
    {
        $admin = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
        ]);
        $admin->assignRole('Admin');

        $start = now()->addDays(3)->setTime(9, 0)->toDateTimeString();
        $end = now()->addDays(3)->setTime(11, 0)->toDateTimeString();

        $this->actingAs($admin)->post(route('member.facilities.book', $this->facility->id), [
            'start_datetime' => $start,
            'end_datetime' => $end,
            'contact_name' => 'Admin',
            'contact_phone' => '0111111111',
        ])->assertForbidden();

        $this->assertDatabaseMissing('facility_bookings', [
            'facility_id' => $this->facility->id,
        ]);
    }

    public function test_inactive_facility_cannot_be_booked(): void
    {
        $inactive = Facility::create([
            'organization_id' => $this->org->id,
            'name' => 'Bilik Tutup',
            'type' => 'hourly',
            'price_per_unit' => 10.00,
            'is_active' => false,
        ]);

        $start = now()->addDays(3)->setTime(9, 0)->toDateTimeString();
        $end = now()->addDays(3)->setTime(11, 0)->toDateTimeString();

        $this->post(route('member.facilities.book', $inactive->id), [
            'start_datetime' => $start,
            'end_datetime' => $end,
            'contact_name' => 'Ali',
            'contact_phone' => '0123456789',
        ])->assertSessionHasErrors('facility');
    }

    public function test_overlapping_booking_is_rejected(): void
    {
        $start = now()->addDays(3)->setTime(9, 0)->toDateTimeString();
        $end = now()->addDays(3)->setTime(11, 0)->toDateTimeString();

        $this->post(route('member.facilities.book', $this->facility->id), [
            'start_datetime' => $start,
            'end_datetime' => $end,
            'contact_name' => 'Ali Ahmad',
            'contact_phone' => '0123456789',
        ])->assertSessionHasNoErrors();

        // Tempahan kedua pada slot bertindih ditolak.
        $this->post(route('member.facilities.book', $this->facility->id), [
            'start_datetime' => now()->addDays(3)->setTime(10, 0)->toDateTimeString(),
            'end_datetime' => now()->addDays(3)->setTime(12, 0)->toDateTimeString(),
            'contact_name' => 'Siti',
            'contact_phone' => '0111111111',
        ])->assertSessionHasErrors('start_datetime');
    }
}

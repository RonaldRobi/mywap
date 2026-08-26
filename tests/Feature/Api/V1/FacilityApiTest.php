<?php

namespace Tests\Feature\Api\V1;

use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FacilityApiTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private Organization $otherOrg;

    private User $member;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $this->org = Organization::factory()->create(['name' => 'PKPIM']);
        $this->otherOrg = Organization::factory()->create(['name' => 'ABIM']);

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
        ]);
        $this->admin->assignRole('Admin');
    }

    private function makeFacility(Organization $owner, array $overrides = []): Facility
    {
        return Facility::create(array_merge([
            'organization_id' => $owner->id,
            'name' => 'Dewan Serbaguna',
            'description' => 'Dewan untuk aktiviti',
            'location' => 'Kuala Lumpur',
            'type' => 'hourly',
            'price_per_unit' => 50.00,
            'member_price_per_unit' => 30.00,
            'capacity' => 100,
            'is_active' => true,
        ], $overrides));
    }

    public function test_member_can_list_facilities(): void
    {
        $this->makeFacility($this->org);

        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/facilities')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'facilities' => [
                        ['id', 'name', 'organization_name', 'price_per_unit', 'media'],
                    ],
                    'myBookings',
                    'isMember',
                ],
            ])
            ->assertJsonPath('data.facilities.0.name', 'Dewan Serbaguna')
            ->assertJsonPath('data.isMember', true);
    }

    public function test_member_can_show_facility(): void
    {
        $facility = $this->makeFacility($this->org);

        Sanctum::actingAs($this->member);

        $this->getJson("/api/v1/facilities/{$facility->id}")
            ->assertOk()
            ->assertJsonPath('data.facility.id', $facility->id)
            ->assertJsonPath('data.facility.is_active', true)
            ->assertJsonPath('data.bookings', [])
            ->assertJsonPath('data.myBookings', []);
    }

    public function test_member_can_create_booking(): void
    {
        $facility = $this->makeFacility($this->org);

        Sanctum::actingAs($this->member);

        $this->postJson("/api/v1/facilities/{$facility->id}/book", [
            'start_datetime' => now()->addDay()->startOfDay()->toDateTimeString(),
            'end_datetime' => now()->addDay()->startOfDay()->addHours(2)->toDateTimeString(),
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.booking_status', 'pending')
            ->assertJsonPath('data.total_price', 60)
            ->assertJsonPath('data.booking.booking_status', 'pending');

        $this->assertDatabaseHas('facility_bookings', [
            'facility_id' => $facility->id,
            'user_id' => $this->member->id,
            'booking_status' => 'pending',
            'payment_status' => 'unpaid',
            'total_price' => 60.00,
        ]);
    }

    public function test_booking_conflict_is_rejected(): void
    {
        $facility = $this->makeFacility($this->org);
        $start = now()->addDay()->startOfDay();
        $end = $start->copy()->addHours(2);

        FacilityBooking::create([
            'facility_id' => $facility->id,
            'user_id' => $this->member->id,
            'contact_name' => $this->member->name,
            'contact_phone' => $this->member->phone,
            'start_datetime' => $start,
            'end_datetime' => $end,
            'total_price' => 60.00,
            'booking_status' => 'pending',
            'payment_status' => 'unpaid',
        ]);

        Sanctum::actingAs($this->member);

        $this->postJson("/api/v1/facilities/{$facility->id}/book", [
            'start_datetime' => $start->copy()->addMinutes(30)->toDateTimeString(),
            'end_datetime' => $end->copy()->addMinutes(30)->toDateTimeString(),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('start_datetime');

        $this->assertDatabaseCount('facility_bookings', 1);
    }

    public function test_admin_cannot_book_facility(): void
    {
        $facility = $this->makeFacility($this->org);

        Sanctum::actingAs($this->admin);

        $this->postJson("/api/v1/facilities/{$facility->id}/book", [
            'start_datetime' => now()->addDay()->startOfDay()->toDateTimeString(),
            'end_datetime' => now()->addDay()->startOfDay()->addHours(2)->toDateTimeString(),
            'contact_name' => 'Admin Test',
            'contact_phone' => '0111111111',
        ])
            ->assertStatus(403);

        $this->assertDatabaseCount('facility_bookings', 0);
    }

    public function test_my_bookings_can_be_filtered_by_history_status(): void
    {
        $facility = $this->makeFacility($this->org);
        $other = $this->makeFacility($this->org, ['name' => 'Bilik Mesyuarat']);

        FacilityBooking::create([
            'facility_id' => $facility->id,
            'user_id' => $this->member->id,
            'contact_name' => $this->member->name,
            'contact_phone' => $this->member->phone,
            'start_datetime' => now()->addDay(),
            'end_datetime' => now()->addDay()->addHour(),
            'total_price' => 30.00,
            'booking_status' => 'pending',
            'payment_status' => 'unpaid',
        ]);
        FacilityBooking::create([
            'facility_id' => $other->id,
            'user_id' => $this->member->id,
            'contact_name' => $this->member->name,
            'contact_phone' => $this->member->phone,
            'start_datetime' => now()->addDays(2),
            'end_datetime' => now()->addDays(2)->addHour(),
            'total_price' => 30.00,
            'booking_status' => 'approved',
            'payment_status' => 'unpaid',
        ]);

        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/facilities?history_status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data.myBookings')
            ->assertJsonPath('data.myBookings.0.booking_status', 'pending');
    }
}

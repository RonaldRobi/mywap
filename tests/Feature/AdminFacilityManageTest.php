<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminFacilityManageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $this->org = Organization::factory()->create(['name' => 'PKPIM']);
        $this->admin = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
            'email_verified_at' => now(),
        ]);
        $this->admin->assignRole('Admin');
    }

    public function test_admin_can_update_facility_with_formdata_payload(): void
    {
        $facility = Facility::create([
            'organization_id' => $this->org->id,
            'name' => 'Dewan Serbaguna',
            'description' => 'Dewan',
            'location' => 'KL',
            'type' => 'hourly',
            'price_per_unit' => 50.00,
            'member_price_per_unit' => 30.00,
            'capacity' => 100,
            'image_path' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->post("/admin/facilities/{$facility->id}", [
            '_method' => 'put',
            'name' => 'Dewan Serbaguna Baru',
            'description' => 'Dewan dikemas kini',
            'location' => 'Shah Alam',
            'type' => 'daily',
            'price_per_unit' => '80',
            'member_price_per_unit' => '0',
            'capacity' => '150',
            'delete_media' => [],
            'is_active' => '1',
        ]);

        $response->assertSessionHasNoErrors();

        $facility->refresh();
        $this->assertSame('Dewan Serbaguna Baru', $facility->name);
        $this->assertSame('daily', $facility->type);
        $this->assertEquals(80, (float) $facility->price_per_unit);
        $this->assertSame(150, (int) $facility->capacity);
    }

    public function test_admin_can_update_facility_unchecking_active(): void
    {
        $facility = Facility::create([
            'organization_id' => $this->org->id,
            'name' => 'Bilik Mesyuarat',
            'type' => 'hourly',
            'price_per_unit' => 20.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->post("/admin/facilities/{$facility->id}", [
            '_method' => 'put',
            'name' => 'Bilik Mesyuarat',
            'type' => 'hourly',
            'price_per_unit' => '20',
            'is_active' => '0',
        ]);

        $response->assertSessionHasNoErrors();

        $facility->refresh();
        $this->assertFalse((bool) $facility->is_active);
    }

    public function test_admin_can_save_halfday_facility(): void
    {
        $facility = Facility::create([
            'organization_id' => $this->org->id,
            'name' => 'Dewan Serbaguna',
            'type' => 'hourly',
            'price_per_unit' => 50.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->post("/admin/facilities/{$facility->id}", [
            '_method' => 'put',
            'name' => 'Dewan Serbaguna',
            'type' => 'halfday',
            'price_per_unit' => '250',
        ]);

        $response->assertSessionHasNoErrors();

        $facility->refresh();
        $this->assertSame('halfday', $facility->type);
        $this->assertEquals(250, (float) $facility->price_per_unit);
    }

    public function test_halfday_price_calculation(): void
    {
        $facility = Facility::create([
            'organization_id' => $this->org->id,
            'name' => 'Dewan Halfday',
            'type' => 'halfday',
            'price_per_unit' => 200.00,
            'is_active' => true,
        ]);

        $this->assertSame(200.0, app(\App\Services\FacilityService::class)->calculateTotalPrice($facility, 720));
        $this->assertSame(400.0, app(\App\Services\FacilityService::class)->calculateTotalPrice($facility, 1440));
        $this->assertSame(400.0, app(\App\Services\FacilityService::class)->calculateTotalPrice($facility, 721));
    }
}

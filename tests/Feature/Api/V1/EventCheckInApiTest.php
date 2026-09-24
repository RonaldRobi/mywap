<?php

namespace Tests\Feature\Api\V1;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Member-facing QR self check-in — mirrors the web
 * AttendanceController::scan "member" flow, exposed as JSON for mobile.
 */
class EventCheckInApiTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $member;

    private Event $event;

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
        ]);
        $this->member->assignRole('Member');

        $this->event = Event::create([
            'organization_id' => $this->org->id,
            'title' => 'Muktamar Nasional Test',
            'description' => 'Test',
            'type' => 'physical',
            'status' => EventStatus::Published->value,
            'category' => EventCategory::Muktamar->value,
            'location_or_link' => 'Kuala Lumpur',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(3),
        ]);
    }

    private function registerMember(): Registration
    {
        return Registration::create([
            'event_id' => $this->event->id,
            'user_id' => $this->member->id,
            'organization_id' => $this->org->id,
            'name' => $this->member->name,
            'email' => $this->member->email,
            'status' => RegistrationStatus::Confirmed,
        ]);
    }

    public function test_member_can_check_in_with_valid_token(): void
    {
        $this->registerMember();
        Sanctum::actingAs($this->member);

        $response = $this->postJson("/api/v1/events/{$this->event->id}/check-in", [
            'token' => $this->event->attendance_token,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.event_id', $this->event->id)
            ->assertJsonPath('data.walk_in', false);

        $this->assertDatabaseHas('attendances', [
            'event_id' => $this->event->id,
            'method' => 'member',
        ]);
    }

    public function test_check_in_rejects_invalid_token(): void
    {
        $this->registerMember();
        Sanctum::actingAs($this->member);

        $this->postJson("/api/v1/events/{$this->event->id}/check-in", [
            'token' => 'invalid-token',
        ])->assertStatus(403);
    }

    public function test_member_without_registration_is_checked_in_as_walk_in(): void
    {
        Sanctum::actingAs($this->member);

        $response = $this->postJson("/api/v1/events/{$this->event->id}/check-in", [
            'token' => $this->event->attendance_token,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.event_id', $this->event->id)
            ->assertJsonPath('data.walk_in', true);

        // Rekod walk-in dicipta untuk ahli ini.
        $this->assertDatabaseHas('registrations', [
            'event_id' => $this->event->id,
            'user_id' => $this->member->id,
            'member_no' => 'PKPIM-0001',
            'status' => RegistrationStatus::WalkIn->value,
        ]);

        $this->assertDatabaseHas('attendances', [
            'event_id' => $this->event->id,
            'method' => 'walkin',
        ]);
    }

    public function test_walk_in_is_idempotent_on_rescan(): void
    {
        Sanctum::actingAs($this->member);

        $this->postJson("/api/v1/events/{$this->event->id}/check-in", [
            'token' => $this->event->attendance_token,
        ])->assertOk();

        // Imbas kali kedua tidak mencipta pendaftaran/kehadiran duplikat.
        $this->postJson("/api/v1/events/{$this->event->id}/check-in", [
            'token' => $this->event->attendance_token,
        ])->assertOk();

        $this->assertSame(
            1,
            Registration::where('event_id', $this->event->id)
                ->where('user_id', $this->member->id)
                ->count()
        );
        $this->assertSame(
            1,
            Attendance::where('event_id', $this->event->id)->count()
        );
    }

    public function test_existing_registration_is_not_replaced_by_walk_in(): void
    {
        $registration = $this->registerMember();
        Sanctum::actingAs($this->member);

        $this->postJson("/api/v1/events/{$this->event->id}/check-in", [
            'token' => $this->event->attendance_token,
        ])->assertOk()->assertJsonPath('data.walk_in', false);

        // Status pendaftaran asal kekal 'confirmed'.
        $this->assertSame(RegistrationStatus::Confirmed, $registration->fresh()->status);
        $this->assertDatabaseHas('attendances', [
            'event_id' => $this->event->id,
            'method' => 'member',
        ]);
    }

    public function test_admin_cannot_check_in_as_participant(): void
    {
        $admin = User::factory()->create(['current_organization_id' => $this->org->id]);
        $admin->assignRole('Admin');
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/events/{$this->event->id}/check-in", [
            'token' => $this->event->attendance_token,
        ])->assertStatus(403);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Walk-in tetamu melalui halaman web kehadiran: peserta yang TIDAK pernah
 * mendaftar mengisi nama + telefon + emel, dan kehadiran direkod terus.
 */
class GuestWalkInTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $this->org = Organization::factory()->create(['name' => 'PKPIM']);
    }

    private function event(): Event
    {
        return Event::create([
            'organization_id' => $this->org->id,
            'title' => 'Seminar Walk-in',
            'description' => 'Test',
            'type' => 'physical',
            'status' => EventStatus::Published->value,
            'category' => EventCategory::Seminar->value,
            'location_or_link' => 'Kuala Lumpur',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(3),
        ]);
    }

    private function walkInUrl(Event $event, ?string $token = null): string
    {
        return route('events.attend.walkin', [
            'id' => $event->id,
            'token' => $token ?? $event->attendance_token,
        ]);
    }

    public function test_guest_walk_in_creates_registration_and_attendance(): void
    {
        $event = $this->event();

        $this->post($this->walkInUrl($event), [
            'name' => 'Siti Aminah',
            'phone' => '0123456789',
            'email' => 'siti@example.com',
        ])->assertOk();

        $this->assertDatabaseHas('registrations', [
            'event_id' => $event->id,
            'name' => 'Siti Aminah',
            'phone' => '0123456789',
            'email' => 'siti@example.com',
            'status' => 'walkin',
            'user_id' => null,
        ]);

        $this->assertDatabaseHas('attendances', [
            'event_id' => $event->id,
            'method' => 'walkin',
        ]);
    }

    public function test_repeat_walk_in_with_same_phone_reuses_registration(): void
    {
        $event = $this->event();

        $payload = ['name' => 'Siti Aminah', 'phone' => '0123456789'];

        $this->post($this->walkInUrl($event), $payload)->assertOk();
        $this->post($this->walkInUrl($event), $payload)->assertOk();

        $this->assertSame(1, Registration::where('event_id', $event->id)->count());
        $this->assertSame(1, Attendance::where('event_id', $event->id)->count());
    }

    public function test_walk_in_rejects_invalid_token(): void
    {
        $event = $this->event();

        $this->post($this->walkInUrl($event, 'invalid-token'), [
            'name' => 'Siti Aminah',
            'phone' => '0123456789',
        ])->assertStatus(403);

        $this->assertSame(0, Registration::where('event_id', $event->id)->count());
    }

    public function test_walk_in_requires_name_and_phone(): void
    {
        $event = $this->event();

        $this->from(route('events.attend', [
            'id' => $event->id,
            'token' => $event->attendance_token,
        ]))
            ->post($this->walkInUrl($event), [])
            ->assertSessionHasErrors(['name', 'phone']);

        $this->assertSame(0, Registration::where('event_id', $event->id)->count());
    }

    public function test_walk_in_does_not_record_attendance_for_cancelled_registration(): void
    {
        $event = $this->event();

        Registration::create([
            'event_id' => $event->id,
            'name' => 'Siti Aminah',
            'phone' => '0123456789',
            'status' => 'cancelled',
        ]);

        $this->post($this->walkInUrl($event), [
            'name' => 'Siti Aminah',
            'phone' => '0123456789',
        ])->assertOk();

        $this->assertSame(0, Attendance::where('event_id', $event->id)->count());
    }
}

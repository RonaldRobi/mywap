<?php

namespace Tests\Feature;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Kitaran hayat kehadiran: tetingkap QR, integrasi profil dan pembetulan manual.
 */
class AttendanceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private User $member;

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
        ]);
        $this->admin->assignRole('Admin');

        $this->member = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
            'member_no' => 'PKPIM-9001',
            'ic_number' => '900101-14-1234',
        ]);
        $this->member->assignRole('Member');
    }

    private function event(array $overrides = []): Event
    {
        return Event::create(array_merge([
            'organization_id' => $this->org->id,
            'title' => 'Program Kehadiran',
            'description' => 'Test',
            'type' => 'physical',
            'status' => EventStatus::Published->value,
            'category' => EventCategory::Seminar->value,
            'location_or_link' => 'Kuala Lumpur',
            'start_time' => now()->subHour(),
            'end_time' => now()->addHours(2),
        ], $overrides));
    }

    private function attendUrl(Event $event): string
    {
        return route('events.attend', ['id' => $event->id, 'token' => $event->attendance_token]);
    }

    private function registration(Event $event, array $overrides = []): Registration
    {
        return Registration::create(array_merge([
            'event_id' => $event->id,
            'organization_id' => $this->org->id,
            'user_id' => $this->member->id,
            'member_no' => $this->member->member_no,
            'name' => $this->member->name,
            'status' => 'confirmed',
        ], $overrides));
    }

    // ─── Tetingkap QR ─────────────────────────────────────────────────────────

    public function test_scan_is_rejected_before_event_starts(): void
    {
        $event = $this->event([
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(3),
        ]);

        $this->actingAs($this->member)
            ->get($this->attendUrl($event))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Events/AttendanceError'));

        $this->assertSame(0, Attendance::where('event_id', $event->id)->count());
    }

    public function test_scan_is_rejected_after_grace_window(): void
    {
        $event = $this->event([
            'start_time' => now()->subHours(30),
            'end_time' => now()->subHours(25),
        ]);

        $this->actingAs($this->member)
            ->get($this->attendUrl($event))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Events/AttendanceError'));

        $this->assertSame(0, Attendance::where('event_id', $event->id)->count());
    }

    public function test_scan_is_allowed_within_grace_window_after_event_ends(): void
    {
        $event = $this->event([
            'start_time' => now()->subHours(26),
            'end_time' => now()->subHours(23),
        ]);

        $this->actingAs($this->member)
            ->get($this->attendUrl($event))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Events/AttendanceSuccess'));

        $this->assertSame(1, Attendance::where('event_id', $event->id)->count());
    }

    public function test_tampered_signed_qr_is_rejected(): void
    {
        $event = $this->event();

        $signed = $event->signedAttendanceUrl();
        $tampered = preg_replace('/sig=[^&]+/', 'sig=deadbeef', $signed);

        $this->actingAs($this->member)
            ->get($tampered)
            ->assertStatus(403);
    }

    public function test_valid_signed_live_qr_is_accepted(): void
    {
        $event = $this->event();

        $this->actingAs($this->member)
            ->get($event->signedAttendanceUrl())
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Events/AttendanceSuccess'));

        $this->assertSame(1, Attendance::where('event_id', $event->id)->count());
    }

    // ─── Integrasi profil ─────────────────────────────────────────────────────

    public function test_manual_attendance_syncs_member_profile_history(): void
    {
        $event = $this->event();
        $registration = $this->registration($event);

        $this->actingAs($this->admin)
            ->post(route('admin.attendance.toggle', [
                'event' => $event->id,
                'registration' => $registration->id,
            ]), ['present' => true])
            ->assertRedirect();

        $this->assertDatabaseHas('attendances', [
            'registration_id' => $registration->id,
            'method' => 'manual',
            'recorded_by' => $this->admin->id,
        ]);

        $this->assertDatabaseHas('event_rsvps', [
            'event_id' => $event->id,
            'user_id' => $this->member->id,
            'status' => 'attended',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'target_id' => $registration->id,
            'action' => 'attendance_manual_set',
        ]);

        $this->get(route('admin.attendance.show', $event->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('audit_trail', 1)
                ->where('audit_trail.0.action', 'attendance_manual_set'));
    }

    public function test_guest_attendance_matches_profile_by_ic(): void
    {
        $event = $this->event();

        $guest = $this->registration($event, [
            'user_id' => null,
            'member_no' => null,
            'name' => 'Tetamu Sebenar',
            'ic_number' => '900101141234',
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.attendance.toggle', [
                'event' => $event->id,
                'registration' => $guest->id,
            ]), ['present' => true])
            ->assertRedirect();

        $guest->refresh();

        $this->assertSame($this->member->id, $guest->user_id);

        $this->assertDatabaseHas('event_rsvps', [
            'event_id' => $event->id,
            'user_id' => $this->member->id,
            'status' => 'attended',
        ]);
    }

    public function test_guest_without_matching_ic_keeps_attendance_without_profile(): void
    {
        $event = $this->event();

        $guest = $this->registration($event, [
            'user_id' => null,
            'member_no' => null,
            'name' => 'Tetamu Tanpa Akaun',
            'ic_number' => '880303-03-4321',
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.attendance.toggle', [
                'event' => $event->id,
                'registration' => $guest->id,
            ]), ['present' => true])
            ->assertRedirect();

        $this->assertSame(1, Attendance::where('registration_id', $guest->id)->count());
        $this->assertNull($guest->refresh()->user_id);
        $this->assertSame(0, EventRsvp::where('event_id', $event->id)->count());
    }

    public function test_non_owning_admin_sees_only_own_registrations_and_no_qr_link(): void
    {
        $otherOrg = Organization::factory()->create(['name' => 'ABIM']);

        $event = $this->event(['organization_id' => $otherOrg->id]);

        $this->registration($event, ['organization_id' => $this->org->id]);

        Registration::create([
            'event_id' => $event->id,
            'organization_id' => $otherOrg->id,
            'name' => 'Peserta Org Lain',
            'status' => 'confirmed',
        ]);

        $this->actingAs($this->admin);

        $this->get(route('admin.attendance.show', ['event' => $event->id, 'attendance' => 'all']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('qr_url', null)
                ->has('registrations.data', 1)
                ->where('registrations.data.0.name', $this->member->name));

        $this->get(route('admin.attendance'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('programs', 1)
                ->where('programs.0.qr_url', null));
    }

    public function test_bulk_marks_multiple_participants_present(): void
    {
        $event = $this->event();

        $memberReg = $this->registration($event);

        $guestReg = $this->registration($event, [
            'user_id' => null,
            'member_no' => null,
            'name' => 'Tetamu Pukal',
            'phone' => '0198887777',
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.attendance.bulk', $event->id), [
                'present' => true,
                'registration_ids' => [$memberReg->id, $guestReg->id],
            ])
            ->assertRedirect();

        $this->assertSame(2, Attendance::where('event_id', $event->id)->where('method', 'manual')->count());

        $this->assertDatabaseHas('event_rsvps', [
            'event_id' => $event->id,
            'user_id' => $this->member->id,
            'status' => 'attended',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'target_type' => Event::class,
            'target_id' => $event->id,
            'action' => 'attendance_bulk_set',
        ]);
    }

    public function test_admin_can_add_manual_participant_and_link_profile_by_ic(): void
    {
        $event = $this->event();

        $this->actingAs($this->admin)
            ->post(route('admin.attendance.participants.store', $event->id), [
                'name' => 'Tetamu Manual',
                'ic_number' => '900101141234',
                'phone' => '0171112222',
                'mark_present' => true,
            ])
            ->assertRedirect();

        $registration = Registration::where('event_id', $event->id)->firstOrFail();

        $this->assertSame($this->member->id, $registration->user_id);
        $this->assertSame('manual', $registration->source);
        $this->assertSame('walkin', $registration->status->value);

        $this->assertDatabaseHas('attendances', [
            'registration_id' => $registration->id,
            'method' => 'manual',
        ]);

        $this->assertDatabaseHas('event_rsvps', [
            'event_id' => $event->id,
            'user_id' => $this->member->id,
            'status' => 'attended',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'target_id' => $registration->id,
            'action' => 'attendance_participant_add',
        ]);
    }

    public function test_add_participant_rejects_duplicate_ic(): void
    {
        $event = $this->event();

        $this->registration($event, ['ic_number' => '900101-14-1234']);

        $before = Registration::where('event_id', $event->id)->count();

        $this->actingAs($this->admin)
            ->post(route('admin.attendance.participants.store', $event->id), [
                'name' => $this->member->name,
                'ic_number' => '900101141234',
                'mark_present' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['ic_number']);

        $this->assertSame($before, Registration::where('event_id', $event->id)->count());
    }

    public function test_add_non_member_participant_requires_member_identifier_or_phone(): void
    {
        $event = $this->event();

        $this->actingAs($this->admin)
            ->post(route('admin.attendance.participants.store', $event->id), [
                'name' => 'Tanpa Identiti',
            ])
            ->assertSessionHasErrors(['ic_number']);

        $this->assertSame(0, Registration::where('event_id', $event->id)->count());
    }

    public function test_add_non_member_participant_without_profile(): void
    {
        $event = $this->event();

        $this->actingAs($this->admin)
            ->post(route('admin.attendance.participants.store', $event->id), [
                'name' => 'Bukan Ahli Manual',
                'phone' => '0163334444',
                'mark_present' => true,
            ])
            ->assertRedirect();

        $registration = Registration::where('event_id', $event->id)->firstOrFail();

        $this->assertNull($registration->user_id);
        $this->assertSame(1, Attendance::where('registration_id', $registration->id)->count());
        $this->assertSame(0, EventRsvp::where('event_id', $event->id)->count());
    }

    public function test_clearing_attendance_removes_profile_history(): void
    {
        $event = $this->event();
        $registration = $this->registration($event);

        $this->actingAs($this->admin)
            ->post(route('admin.attendance.toggle', [
                'event' => $event->id,
                'registration' => $registration->id,
            ]), ['present' => true]);

        $this->actingAs($this->admin)
            ->post(route('admin.attendance.toggle', [
                'event' => $event->id,
                'registration' => $registration->id,
            ]), ['present' => false]);

        $this->assertSame(0, Attendance::where('registration_id', $registration->id)->count());
        $this->assertSame(0, EventRsvp::where('event_id', $event->id)->count());
        $this->assertDatabaseHas('activity_logs', [
            'target_id' => $registration->id,
            'action' => 'attendance_manual_clear',
        ]);
    }
}

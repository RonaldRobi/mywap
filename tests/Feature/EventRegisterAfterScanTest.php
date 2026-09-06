<?php

namespace Tests\Feature;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Form;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Selepas scan QR kehadiran tanpa rekod → papar laluan terus ke pendaftaran
 * (web attendance page), dan QR promosi program.
 */
class EventRegisterAfterScanTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $this->org = Organization::factory()->create(['fee_amount' => 50.00]);
    }

    private function event(?string $status = EventStatus::Published->value): Event
    {
        return Event::create([
            'organization_id' => $this->org->id,
            'title' => 'Seminar Geopolitik',
            'description' => 'Test',
            'type' => 'physical',
            'status' => $status,
            'category' => EventCategory::Seminar->value,
            'location_or_link' => 'Kuala Lumpur',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(3),
        ]);
    }

    private function activeForm(Event $event): Form
    {
        return Form::create([
            'organization_id' => $this->org->id,
            'event_id' => $event->id,
            'title' => 'Borang Pendaftaran Seminar',
            'description' => 'Isi borang ini.',
            'is_active' => true,
            'allow_public' => true,
        ]);
    }

    private function member(): User
    {
        $member = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
        ]);
        $member->assignRole('Member');

        return $member;
    }

    // ─── Ahli: belum daftar → papar laluan daftar (1 borang aktif) ───────────

    public function test_member_not_registered_sees_register_link_on_attendance_error(): void
    {
        $event = $this->event();
        $form = $this->activeForm($event);
        $member = $this->member();

        $this->actingAs($member)
            ->get(route('events.attend', ['id' => $event->id, 'token' => $event->attendance_token]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Events/AttendanceError')
                ->where('registerAction.url', route('events.register', ['event' => $event->slug, 'form' => $form->id])));
    }

    public function test_member_not_registered_gets_no_register_link_when_event_is_draft(): void
    {
        $event = $this->event(EventStatus::Draft->value);
        $this->activeForm($event);
        $member = $this->member();

        $this->actingAs($member)
            ->get(route('events.attend', ['id' => $event->id, 'token' => $event->attendance_token]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Events/AttendanceError')
                ->where('registerAction', null));
    }

    public function test_member_not_registered_gets_no_register_link_when_no_active_form(): void
    {
        $event = $this->event();
        $member = $this->member();

        $this->actingAs($member)
            ->get(route('events.attend', ['id' => $event->id, 'token' => $event->attendance_token]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Events/AttendanceError')
                ->where('registerAction', null));
    }

    public function test_member_not_registered_with_multiple_active_forms_goes_to_overview(): void
    {
        $event = $this->event();
        $this->activeForm($event);
        $this->activeForm($event);
        $member = $this->member();

        $this->actingAs($member)
            ->get(route('events.attend', ['id' => $event->id, 'token' => $event->attendance_token]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Events/AttendanceError')
                ->where('registerAction.url', route('events.show', $event->slug)));
    }

    // ─── Tetamu: tiada rekod semasa identifikasi → papar pautan daftar awam ──

    public function test_guest_no_record_sees_public_register_link_on_identify(): void
    {
        $event = $this->event();
        $form = $this->activeForm($event);

        $this->post(route('events.attend.identify', [
            'id' => $event->id,
            'token' => $event->attendance_token,
        ]), ['identifier' => '0191234567'])
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Events/GuestCheckin')
                ->where('registerAction.url', route('events.register.public', $form->share_token)));
    }

    // ─── QR promosi program ──────────────────────────────────────────────────

    public function test_event_show_has_promo_qr_pointing_to_single_active_form(): void
    {
        $event = $this->event();
        $form = $this->activeForm($event);

        $this->get(route('events.show', $event->slug))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Events/Show')
                ->where('promoUrl', route('events.register.public', $form->share_token, true))
                ->where('promoQrSvg', fn ($v) => is_string($v) && str_contains($v, '<svg')));
    }

    public function test_event_show_has_promo_qr_pointing_to_overview_without_form(): void
    {
        $event = $this->event();

        $this->get(route('events.show', $event->slug))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Events/Show')
                ->where('promoUrl', route('events.show', $event->slug, true))
                ->where('promoQrSvg', fn ($v) => is_string($v) && str_contains($v, '<svg')));
    }

    public function test_promo_qr_png_download(): void
    {
        $event = $this->event();
        $this->activeForm($event);

        $this->get(route('events.share-qr.download', $event->id))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }
}

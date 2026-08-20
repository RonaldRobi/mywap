<?php

namespace Tests\Feature;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormQuestion;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\RegistrationConfirmationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private Organization $otherOrg;

    private User $admin;

    private User $member;

    private User $otherAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $this->org = Organization::factory()->create(['fee_amount' => 50.00]);
        $this->otherOrg = Organization::factory()->create(['fee_amount' => 50.00]);

        $this->admin = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
            'member_no' => 'PKPIM-0001',
        ]);
        $this->admin->assignRole('Admin');

        $this->otherAdmin = User::factory()->create([
            'current_organization_id' => $this->otherOrg->id,
            'profile_completed_at' => now(),
        ]);
        $this->otherAdmin->assignRole('Admin');

        $this->member = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
            'member_no' => 'PKPIM-0002',
        ]);
        $this->member->assignRole('Member');
    }

    private function makePublishedEvent(?Organization $owner = null): Event
    {
        $event = Event::create([
            'organization_id' => $owner?->id,
            'title' => 'Muktamar Nasional Test',
            'description' => 'Test',
            'type' => 'physical',
            'status' => EventStatus::Published->value,
            'category' => EventCategory::Muktamar->value,
            'location_or_link' => 'Kuala Lumpur',
            'start_time' => now()->addMonth(),
            'end_time' => now()->addMonth()->addHours(3),
        ]);
        $event->organizations()->sync([$this->org->id]);

        return $event;
    }

    private function makeForm(Event $event, bool $paid = false): Form
    {
        $form = Form::create([
            'event_id' => $event->id,
            'organization_id' => $this->org->id,
            'title' => 'Borang Pendaftaran '.($paid ? 'Berbayar' : 'Percuma'),
            'price' => $paid ? 50.00 : null,
            'payment_required' => $paid,
            'terms' => 'Syarat test',
            'is_active' => true,
            'allow_public' => true,
        ]);
        FormQuestion::create([
            'form_id' => $form->id,
            'label' => 'Nama Penuh',
            'type' => 'text',
            'required' => true,
            'sort_order' => 0,
        ]);

        return $form;
    }

    // ─── Admin: cipta event + organisasi terlibat ─────────────────────────────

    public function test_org_admin_can_create_published_event_with_pivot(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('events.store'), [
            'title' => 'Kem Test',
            'description' => 'desc',
            'type' => 'physical',
            'status' => 'published',
            'category' => 'kem',
            'location_or_link' => 'Perak',
            'start_time' => now()->addMonth()->toDateTimeString(),
            'end_time' => now()->addMonth()->addHours(2)->toDateTimeString(),
            'organizations' => [$this->org->id],
        ]);

        $response->assertSessionHas('success');

        $event = Event::where('title', 'Kem Test')->firstOrFail();
        $this->assertSame(EventStatus::Published, $event->status);
        $this->assertSame(EventCategory::Kem, $event->category);
        $this->assertSame($this->org->id, (int) $event->organization_id);
        $this->assertTrue($event->organizations->contains($this->org));
    }

    public function test_org_admin_cannot_attach_other_organization_to_pivot(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('events.store'), [
            'title' => 'Kem Test 2',
            'description' => 'desc',
            'type' => 'physical',
            'status' => 'published',
            'category' => 'kem',
            'location_or_link' => 'Perak',
            'start_time' => now()->addMonth()->toDateTimeString(),
            'end_time' => now()->addMonth()->addHours(2)->toDateTimeString(),
            'organizations' => [$this->otherOrg->id],
        ]);

        $event = Event::where('title', 'Kem Test 2')->firstOrFail();
        $this->assertFalse($event->organizations->contains($this->otherOrg));
    }

    // ─── Daftar (Ahli) ────────────────────────────────────────────────────────

    public function test_member_can_register_for_free_event(): void
    {
        $event = $this->makePublishedEvent();
        $form = $this->makeForm($event);

        $this->actingAs($this->member);

        $response = $this->post(route('events.register.store', ['event' => $event->slug, 'form' => $form->id]), [
            'answers' => [$form->questions->first()->id => 'Ahmad Bin Ali'],
        ]);

        $response->assertSessionHas('success');

        $registration = Registration::where('event_id', $event->id)->where('user_id', $this->member->id)->firstOrFail();
        $this->assertSame('confirmed', $registration->status->value);
        $this->assertSame($this->member->member_no, $registration->member_no);
        $this->assertSame($this->org->id, (int) $registration->organization_id);
    }

    public function test_member_registration_stores_answers(): void
    {
        $event = $this->makePublishedEvent();
        $form = $this->makeForm($event);
        $question = $form->questions->first();

        $this->actingAs($this->member);

        $this->post(route('events.register.store', ['event' => $event->slug, 'form' => $form->id]), [
            'answers' => [$question->id => 'Ali Bin Abu'],
        ]);

        $registration = Registration::where('user_id', $this->member->id)->firstOrFail();
        $this->assertSame('Ali Bin Abu', $registration->form->responses()->first()->answers()->first()->value);
    }

    public function test_member_cannot_register_twice(): void
    {
        $event = $this->makePublishedEvent();
        $form = $this->makeForm($event);

        $this->actingAs($this->member);

        $this->post(route('events.register.store', ['event' => $event->slug, 'form' => $form->id]), [
            'answers' => [$form->questions->first()->id => 'Ali'],
        ]);

        $response = $this->get(route('events.register', ['event' => $event->slug, 'form' => $form->id]));
        $response->assertRedirect(route('member.registrations'));
    }

    public function test_member_cannot_register_closed_event(): void
    {
        $event = $this->makePublishedEvent();
        $event->update(['status' => 'closed']);
        $form = $this->makeForm($event);

        $this->actingAs($this->member);

        $this->post(route('events.register.store', ['event' => $event->slug, 'form' => $form->id]), [
            'answers' => [$form->questions->first()->id => 'Ali'],
        ])->assertForbidden();
    }

    // ─── Daftar (Bukan Ahli) ──────────────────────────────────────────────────

    public function test_guest_can_register_publicly_without_login(): void
    {
        $event = $this->makePublishedEvent();
        $form = $this->makeForm($event);

        // Form Builder ialah single source of truth — maklumat peserta diambil
        // daripada jawapan borang (bukan field auto tambahan).
        $response = $this->post(route('events.register.public.store', ['token' => $form->share_token]), [
            'answers' => [$form->questions->first()->id => 'Tetamu Test'],
        ]);

        $response->assertSessionHas('success');

        $registration = Registration::where('event_id', $event->id)
            ->whereNull('user_id')
            ->firstOrFail();
        $this->assertSame('Tetamu Test', $registration->name);
        $this->assertNull($registration->user_id);
    }

    public function test_guest_participant_fields_mapped_from_form_answers(): void
    {
        $event = $this->makePublishedEvent();
        $form = Form::create([
            'event_id' => $event->id,
            'organization_id' => $this->org->id,
            'title' => 'Borang Peserta',
            'is_active' => true,
            'allow_public' => true,
        ]);

        $nama = FormQuestion::create(['form_id' => $form->id, 'label' => 'Nama', 'type' => 'text', 'required' => true, 'sort_order' => 0]);
        $ic = FormQuestion::create(['form_id' => $form->id, 'label' => 'No Kad Pengenalan', 'type' => 'text', 'required' => true, 'sort_order' => 1]);
        $telefon = FormQuestion::create(['form_id' => $form->id, 'label' => 'No Telefon', 'type' => 'phone', 'required' => true, 'sort_order' => 2]);

        $this->post(route('events.register.public.store', ['token' => $form->share_token]), [
            'answers' => [
                $nama->id => 'Ali Bin Abu',
                $ic->id => '990101-14-1234',
                $telefon->id => '0123456789',
            ],
        ])->assertSessionHas('success');

        $registration = Registration::where('event_id', $event->id)->whereNull('user_id')->firstOrFail();
        $this->assertSame('Ali Bin Abu', $registration->name);
        $this->assertSame('990101-14-1234', $registration->ic_number);
        $this->assertSame('0123456789', $registration->phone);
    }

    // ─── Kehadiran (QR) ───────────────────────────────────────────────────────

    public function test_member_scan_records_attendance(): void
    {
        $event = $this->makePublishedEvent();
        $form = $this->makeForm($event);

        $registration = Registration::create([
            'event_id' => $event->id,
            'form_id' => $form->id,
            'user_id' => $this->member->id,
            'organization_id' => $this->org->id,
            'member_no' => $this->member->member_no,
            'name' => $this->member->name,
            'status' => 'confirmed',
        ]);

        $this->actingAs($this->member);

        $this->get(route('events.attend', ['id' => $event->id, 'token' => $event->attendance_token]))
            ->assertOk();

        $attendance = Attendance::where('registration_id', $registration->id)->firstOrFail();
        $this->assertSame('member', $attendance->method);
    }

    public function test_member_scan_without_registration_shows_error(): void
    {
        $event = $this->makePublishedEvent();

        $this->actingAs($this->member);

        $this->get(route('events.attend', ['id' => $event->id, 'token' => $event->attendance_token]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Events/AttendanceError'));
    }

    public function test_guest_identify_records_attendance(): void
    {
        $event = $this->makePublishedEvent();
        $form = $this->makeForm($event);

        $registration = Registration::create([
            'event_id' => $event->id,
            'form_id' => $form->id,
            'name' => 'Tetamu',
            'phone' => '0131234567',
            'status' => 'confirmed',
        ]);

        $this->post(route('events.attend.identify', ['id' => $event->id, 'token' => $event->attendance_token]), [
            'identifier' => '0131234567',
        ])->assertOk();

        $this->assertTrue($registration->refresh()->hasAttended());
        $this->assertSame('guest', $registration->attendance->method);
    }

    public function test_scan_is_idempotent(): void
    {
        $event = $this->makePublishedEvent();
        $form = $this->makeForm($event);

        $registration = Registration::create([
            'event_id' => $event->id,
            'form_id' => $form->id,
            'user_id' => $this->member->id,
            'organization_id' => $this->org->id,
            'name' => $this->member->name,
            'status' => 'confirmed',
        ]);

        $this->actingAs($this->member);

        $this->get(route('events.attend', ['id' => $event->id, 'token' => $event->attendance_token]));
        $this->get(route('events.attend', ['id' => $event->id, 'token' => $event->attendance_token]));

        $this->assertSame(1, Attendance::where('registration_id', $registration->id)->count());
    }

    // ─── Dashboard Kehadiran (Admin) ──────────────────────────────────────────

    public function test_org_admin_sees_only_own_registrations_in_attendance(): void
    {
        $event = $this->makePublishedEvent();
        $form = $this->makeForm($event);

        Registration::create([
            'event_id' => $event->id,
            'form_id' => $form->id,
            'user_id' => $this->member->id,
            'organization_id' => $this->org->id,
            'name' => 'Ahli Org Saya',
            'status' => 'confirmed',
        ]);

        Registration::create([
            'event_id' => $event->id,
            'form_id' => $form->id,
            'organization_id' => $this->otherOrg->id,
            'name' => 'Ahli Org Lain',
            'status' => 'confirmed',
        ]);

        $this->actingAs($this->admin);

        $this->get(route('admin.attendance'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Events/Attendance')
                ->where('stats.total_registered', 1));
    }

    public function test_admin_can_update_registration_status(): void
    {
        $event = $this->makePublishedEvent();
        $form = $this->makeForm($event);

        $registration = Registration::create([
            'event_id' => $event->id,
            'form_id' => $form->id,
            'organization_id' => $this->org->id,
            'name' => 'Ahli',
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin);

        $this->patch(route('admin.events.registrations.update', $registration->id), [
            'status' => 'confirmed',
        ])->assertSessionHas('success');

        $this->assertSame('confirmed', $registration->refresh()->status->value);
    }

    // ─── Pengurusan Event (Admin/Superadmin) ─────────────────────────────────

    public function test_admin_can_access_event_management_page(): void
    {
        $this->makePublishedEvent();

        $this->actingAs($this->admin);

        $this->get(route('admin.events.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Events/Index'));
    }

    public function test_admin_create_event_via_management_redirects_to_index(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('admin.events.store'), [
            'title' => 'Kem via Pengurusan',
            'description' => 'desc',
            'type' => 'physical',
            'status' => 'draft',
            'category' => 'kem',
            'location_or_link' => 'Melaka',
            'start_time' => now()->addMonth()->toDateTimeString(),
            'end_time' => now()->addMonth()->addHours(2)->toDateTimeString(),
            'organizations' => [$this->org->id],
        ]);

        $event = Event::where('title', 'Kem via Pengurusan')->firstOrFail();
        $this->assertSame('draft', $event->status->value);
        $this->assertDatabaseHas('events', ['title' => 'Kem via Pengurusan']);
    }

    public function test_admin_show_event_page_lists_registration_forms(): void
    {
        $event = $this->makePublishedEvent();
        $form = $this->makeForm($event);

        $this->actingAs($this->admin);

        $this->get(route('admin.events.show', $event->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Events/Show')
                ->has('forms', 1));
    }

    public function test_org_admin_cannot_edit_other_org_event(): void
    {
        $event = Event::create([
            'organization_id' => $this->org->id,
            'title' => 'Event Org Lain',
            'description' => 'x',
            'type' => 'physical',
            'status' => 'published',
            'category' => 'seminar',
            'location_or_link' => 'KL',
            'start_time' => now()->addMonth(),
            'end_time' => now()->addMonth()->addHours(2),
        ]);

        $this->actingAs($this->otherAdmin);

        $this->get(route('admin.events.edit', $event->id))->assertForbidden();
    }

    public function test_superadmin_can_attach_multiple_organizations(): void
    {
        $superadmin = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
        ]);
        $superadmin->assignRole('Superadmin');

        $this->actingAs($superadmin);

        $response = $this->post(route('admin.events.store'), [
            'title' => 'Muktamar Gabungan',
            'description' => 'x',
            'type' => 'physical',
            'status' => 'published',
            'category' => 'muktamar',
            'location_or_link' => 'KL',
            'start_time' => now()->addMonth()->toDateTimeString(),
            'end_time' => now()->addMonth()->addHours(2)->toDateTimeString(),
            'organizations' => [$this->org->id, $this->otherOrg->id],
        ]);
        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        $event = Event::withoutGlobalScopes()->where('title', 'Muktamar Gabungan')->firstOrFail();
        $this->assertTrue($event->organizations->contains($this->org));
        $this->assertTrue($event->organizations->contains($this->otherOrg));
    }

    public function test_admin_can_delete_own_event_and_its_forms(): void
    {
        $event = $this->makePublishedEvent();
        $form = $this->makeForm($event);

        $this->actingAs($this->admin);

        $this->delete(route('events.destroy', $event->id))
            ->assertRedirect(route('admin.events.index'));

        $this->assertSoftDeleted('events', ['id' => $event->id]);
        $this->assertSoftDeleted('forms', ['id' => $form->id]);
    }

    public function test_org_admin_cannot_delete_other_org_event(): void
    {
        $event = Event::create([
            'organization_id' => $this->org->id,
            'title' => 'Event Milik Org Lain',
            'description' => 'x',
            'type' => 'physical',
            'status' => 'published',
            'category' => 'seminar',
            'location_or_link' => 'KL',
            'start_time' => now()->addMonth(),
            'end_time' => now()->addMonth()->addHours(2),
        ]);

        $this->actingAs($this->otherAdmin);

        $this->delete(route('events.destroy', $event->id))->assertForbidden();
        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }

    // ─── senangPay gateway ───────────────────────────────────────────────────

    private function makeSenangPayOrg(): Organization
    {
        $org = Organization::factory()->create([
            'payment_gateway' => 'senangpay',
            'senangpay_merchant_id' => '14222653788472',
            'senangpay_secret_key' => '53-784',
            'senangpay_environment' => 'sandbox',
        ]);

        return $org;
    }

    public function test_senangpay_configured_org_uses_senangpay_gateway(): void
    {
        $org = $this->makeSenangPayOrg();
        $this->assertTrue($org->hasSenangPayConfig());
        $this->assertSame('senangpay', $org->activeGateway());
    }

    public function test_registration_payment_redirects_to_senangpay_pay_page(): void
    {
        $org = $this->makeSenangPayOrg();

        $event = Event::create([
            'organization_id' => $org->id,
            'title' => 'Event SenangPay',
            'description' => 'x',
            'type' => 'physical',
            'status' => 'published',
            'category' => 'muktamar',
            'location_or_link' => 'KL',
            'start_time' => now()->addMonth(),
            'end_time' => now()->addMonth()->addHours(2),
        ]);
        $event->organizations()->sync([$org->id]);

        $form = Form::create([
            'event_id' => $event->id,
            'organization_id' => $org->id,
            'title' => 'Borang SenangPay',
            'price' => 50.00,
            'payment_required' => true,
            'is_active' => true,
            'allow_public' => true,
        ]);
        FormQuestion::create([
            'form_id' => $form->id,
            'label' => 'Nama',
            'type' => 'text',
            'required' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($this->member);

        $response = $this->post(route('events.register.store', ['event' => $event->slug, 'form' => $form->id]), [
            'answers' => [$form->questions->first()->id => 'Ali'],
        ]);

        $response->assertRedirect();

        $redirectUrl = $response->headers->get('Location');
        $this->assertStringContainsString('/senangpay/pay/', $redirectUrl);

        $payment = Payment::where('payable_type', Registration::class)->latest()->firstOrFail();
        $this->assertSame('senangpay', $payment->gateway);
        $this->assertSame('pending', $payment->status);

        $registration = $payment->payable;
        $this->assertSame('pending', $registration->status->value);
    }

    public function test_senangpay_callback_confirms_registration_and_sends_email(): void
    {
        Notification::fake();

        $org = $this->makeSenangPayOrg();
        $event = $this->makePublishedEvent();
        $event->update(['organization_id' => $org->id]);

        $form = Form::create([
            'event_id' => $event->id,
            'organization_id' => $org->id,
            'title' => 'Borang SenangPay 2',
            'price' => 50.00,
            'payment_required' => true,
            'is_active' => true,
            'allow_public' => true,
        ]);

        $registration = Registration::create([
            'event_id' => $event->id,
            'form_id' => $form->id,
            'organization_id' => $org->id,
            'name' => 'Ali',
            'email' => 'ali@example.com',
            'status' => 'pending',
        ]);

        $payment = $registration->payments()->create([
            'amount' => 50.00,
            'status' => 'pending',
            'reference' => 'REG-SENANGPAY01',
            'description' => 'Pendaftaran: Event',
            'gateway' => 'senangpay',
            'organization_id' => $org->id,
        ]);

        $str = $org->senangpay_secret_key.'1'.'REG-SENANGPAY01'.'TXN12345'.'Payment_was_successful';
        $hash = hash_hmac('sha256', $str, $org->senangpay_secret_key);

        $this->post(route('senangpay.callback'), [
            'status_id' => '1',
            'order_id' => 'REG-SENANGPAY01',
            'msg' => 'Payment_was_successful',
            'transaction_id' => 'TXN12345',
            'hash' => $hash,
        ])->assertJson(['status' => 'ok']);

        $this->assertSame('successful', $payment->refresh()->status);
        $this->assertSame('confirmed', $registration->refresh()->status->value);

        Notification::assertSentOnDemand(
            RegistrationConfirmationNotification::class
        );
    }

    public function test_register_page_passes_payment_gateway_branding_for_paid_form(): void
    {
        $org = $this->makeSenangPayOrg();
        $event = Event::create([
            'organization_id' => $org->id,
            'title' => 'Event Branding',
            'description' => 'x',
            'type' => 'physical',
            'status' => 'published',
            'category' => 'muktamar',
            'location_or_link' => 'KL',
            'start_time' => now()->addMonth(),
            'end_time' => now()->addMonth()->addHours(2),
        ]);
        $event->organizations()->sync([$org->id]);

        $form = Form::create([
            'event_id' => $event->id,
            'organization_id' => $org->id,
            'title' => 'Borang Berbayar',
            'price' => 50.00,
            'payment_required' => true,
            'is_active' => true,
            'allow_public' => true,
        ]);

        $this->actingAs($this->member);

        $this->get(route('events.register', ['event' => $event->slug, 'form' => $form->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Events/Register')
                ->where('paymentGateway.key', 'senangpay')
                ->where('paymentGateway.tagline', 'Pay Securely with SenangPay'));
    }

    public function test_register_page_has_no_payment_gateway_for_free_form(): void
    {
        $event = $this->makePublishedEvent();
        $form = $this->makeForm($event, paid: false);

        $this->actingAs($this->member);

        $this->get(route('events.register', ['event' => $event->slug, 'form' => $form->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Events/Register')
                ->where('paymentGateway', null));
    }

    // ─── Kongsi / QR (admin sahaja) ───────────────────────────────────────────

    public function test_register_page_can_share_for_admin(): void
    {
        $event = $this->makePublishedEvent();
        $form = $this->makeForm($event);

        $this->actingAs($this->admin);

        $this->get(route('events.register.public', $form->share_token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Events/Register')
                ->where('canShare', true)
                ->whereNotNull('qrSvg'));
    }

    public function test_register_page_hides_share_for_guest(): void
    {
        $event = $this->makePublishedEvent();
        $form = $this->makeForm($event);

        // Tetamu (tidak login) — tiada alat kongsi.
        $this->get(route('events.register.public', $form->share_token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Events/Register')
                ->where('canShare', false)
                ->whereNull('qrSvg'));
    }

    public function test_admin_events_index_includes_form_url(): void
    {
        $event = $this->makePublishedEvent();
        $form = $this->makeForm($event);

        $this->actingAs($this->admin);

        $this->get(route('admin.events.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Events/Index')
                ->has('events.data', 1)
                ->where('events.data.0.forms_count', 1)
                ->where('events.data.0.form_url', route('events.register.public', $form->share_token)));
    }

    public function test_admin_event_hub_forms_include_qr(): void
    {
        $event = $this->makePublishedEvent();
        $this->makeForm($event);

        $this->actingAs($this->admin);

        $this->get(route('admin.events.show', $event->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Events/Show')
                ->has('forms', 1)
                ->whereNotNull('forms.0.qr_svg'));
    }

    public function test_admin_can_update_published_event(): void
    {
        $event = $this->makePublishedEvent();

        $this->actingAs($this->admin);

        $response = $this->put(route('admin.events.update', $event->id), [
            'title' => 'Tajuk Baharu Test',
            'description' => 'desc baru',
            'type' => 'physical',
            'status' => 'published',
            'category' => 'seminar',
            'location_or_link' => 'Kuala Lumpur',
            'start_time' => now()->addMonth()->toDateTimeString(),
            'end_time' => now()->addMonth()->addHours(2)->toDateTimeString(),
            'organizations' => [$this->org->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('events', ['id' => $event->id, 'title' => 'Tajuk Baharu Test']);
        $this->assertSame('seminar', $event->refresh()->category->value);
    }

    public function test_update_event_without_status_and_category_preserves_them(): void
    {
        // Reproduksi bug sebenar: modal edit lama (page Program) tidak hantar
        // status/category — perubahan mesti tetap disimpan.
        $event = $this->makePublishedEvent();

        $this->actingAs($this->admin);

        $response = $this->put(route('events.update', $event->id), [
            'title' => 'Tajuk Tanpa Status',
            'description' => 'desc',
            'type' => 'physical',
            'location_or_link' => 'Melaka',
            'start_time' => now()->addMonth()->toDateTimeString(),
            'end_time' => now()->addMonth()->addHours(2)->toDateTimeString(),
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('events', ['id' => $event->id, 'title' => 'Tajuk Tanpa Status']);
        $this->assertSame('published', $event->refresh()->status->value);
        $this->assertSame('muktamar', $event->refresh()->category->value);
    }

    public function test_create_event_without_status_and_category_defaults(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('events.store'), [
            'title' => 'Program Default',
            'description' => 'desc',
            'type' => 'physical',
            'location_or_link' => 'KL',
            'start_time' => now()->addMonth()->toDateTimeString(),
            'end_time' => now()->addMonth()->addHours(2)->toDateTimeString(),
        ])->assertSessionHasNoErrors();

        $event = Event::where('title', 'Program Default')->firstOrFail();
        $this->assertSame('draft', $event->status->value);
        $this->assertSame('lain', $event->category->value);
    }
}

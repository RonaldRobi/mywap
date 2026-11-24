<?php

namespace Tests\Feature;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\FormAnswer;
use App\Models\Form;
use App\Models\FormQuestion;
use App\Models\FormResponse;
use App\Models\FormItem;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\RegistrationItem;
use App\Models\User;
use App\Services\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Halaman pendaftaran admin: statistik, senarai sediaan item, batal/pulihkan,
 * tetingkap pendaftaran dan export CSV.
 */
class RegistrationAdminTest extends TestCase
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
            'member_no' => 'PKPIM-7001',
        ]);
        $this->member->assignRole('Member');
    }

    private function event(array $overrides = []): Event
    {
        return Event::create(array_merge([
            'organization_id' => $this->org->id,
            'title' => 'Program Pendaftaran',
            'description' => 'Test',
            'type' => 'physical',
            'status' => EventStatus::Published->value,
            'category' => EventCategory::Seminar->value,
            'location_or_link' => 'Kuala Lumpur',
            'start_time' => now()->subHour(),
            'end_time' => now()->addHours(2),
        ], $overrides));
    }

    private function form(Event $event): Form
    {
        return Form::create([
            'event_id' => $event->id,
            'organization_id' => $this->org->id,
            'title' => 'Borang Pendaftaran',
            'is_active' => true,
            'allow_public' => true,
        ]);
    }

    private function registration(Event $event, Form $form, array $overrides = []): Registration
    {
        return Registration::create(array_merge([
            'event_id' => $event->id,
            'form_id' => $form->id,
            'organization_id' => $this->org->id,
            'name' => 'Peserta',
            'status' => 'confirmed',
        ], $overrides));
    }

    public function test_page_shows_stats_and_items_to_prepare(): void
    {
        $event = $this->event();
        $form = $this->form($event);

        $this->registration($event, $form, ['name' => 'Percuma']);

        $withItem = $this->registration($event, $form, [
            'name' => 'Ada Item',
            'addons_amount' => 30,
        ]);

        RegistrationItem::create([
            'registration_id' => $withItem->id,
            'name' => 'Baju',
            'variant_name' => 'M',
            'quantity' => 2,
            'unit_price' => 15,
            'line_total' => 30,
        ]);

        Payment::create([
            'payable_type' => Registration::class,
            'payable_id' => $withItem->id,
            'amount' => 30,
            'status' => 'successful',
            'organization_id' => $this->org->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.events.registrations', $event->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Events/Registrations')
                ->where('stats.total', 2)
                ->where('stats.confirmed', 2)
                ->where('stats.with_items', 1)
                ->has('items_to_prepare', 1)
                ->where('items_to_prepare.0.name', 'Baju')
                ->where('items_to_prepare.0.quantity', 2)
                ->where('event.registration_open', true));
    }

    public function test_cancel_and_restore_registration(): void
    {
        $event = $this->event();
        $form = $this->form($event);

        $free = $this->registration($event, $form);

        $this->actingAs($this->admin)
            ->patch(route('admin.events.registrations.update', $free->id), ['status' => 'cancelled'])
            ->assertRedirect();

        $this->assertSame('cancelled', $free->fresh()->status->value);

        $this->actingAs($this->admin)
            ->patch(route('admin.events.registrations.update', $free->id), ['status' => 'confirmed'])
            ->assertRedirect();

        $this->assertSame('confirmed', $free->fresh()->status->value);

        // Pendaftaran berbayar yang belum dibayar dipulihkan sebagai menunggu bayaran.
        $paid = $this->registration($event, $form, [
            'name' => 'Berbayar',
            'status' => 'cancelled',
            'base_amount' => 50,
        ]);

        $this->actingAs($this->admin)
            ->patch(route('admin.events.registrations.update', $paid->id), ['status' => 'confirmed'])
            ->assertRedirect();

        $this->assertSame('pending', $paid->fresh()->status->value);
    }

    public function test_registration_can_be_opened_early_for_future_event(): void
    {
        $event = $this->event([
            'start_time' => now()->addMonth(),
            'end_time' => now()->addMonth()->addHours(2),
        ]);
        $form = $this->form($event);

        $this->actingAs($this->member)
            ->get(route('events.register', ['event' => $event->slug, 'form' => $form->id]))
            ->assertOk();

        $this->get(route('events.register.public', $form->share_token))
            ->assertOk();
    }

    public function test_registration_window_blocks_after_close(): void
    {
        $event = $this->event([
            'start_time' => now()->subHours(26),
            'end_time' => now()->subHours(25),
        ]);
        $form = $this->form($event);

        $this->actingAs($this->member)
            ->get(route('events.register', ['event' => $event->slug, 'form' => $form->id]))
            ->assertStatus(403);
    }

    public function test_registration_is_open_within_window(): void
    {
        $event = $this->event();
        $form = $this->form($event);

        $this->actingAs($this->member)
            ->get(route('events.register', ['event' => $event->slug, 'form' => $form->id]))
            ->assertOk();
    }

    public function test_has_items_and_payment_filters(): void
    {
        $event = $this->event();
        $form = $this->form($event);

        $free = $this->registration($event, $form, ['name' => 'Percuma']);

        $withItem = $this->registration($event, $form, [
            'name' => 'Ada Item',
            'addons_amount' => 20,
        ]);

        RegistrationItem::create([
            'registration_id' => $withItem->id,
            'name' => 'Buku',
            'quantity' => 1,
            'unit_price' => 20,
            'line_total' => 20,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.events.registrations', ['event' => $event->id, 'has_items' => 1]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('registrations.data', 1)
                ->where('registrations.data.0.name', 'Ada Item'));

        $this->actingAs($this->admin)
            ->get(route('admin.events.registrations', ['event' => $event->id, 'payment' => 'free']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('registrations.data', 1)
                ->where('registrations.data.0.name', 'Percuma'));

        $this->assertNotNull($free);
    }

    public function test_mark_paid_sets_confirmed_and_records_payment(): void
    {
        $event = $this->event();
        $form = $this->form($event);

        $registration = $this->registration($event, $form, [
            'name' => 'Belum Bayar',
            'status' => 'pending',
            'base_amount' => 50,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.events.registrations.mark-paid', $registration->id))
            ->assertRedirect();

        $this->assertSame('confirmed', $registration->fresh()->status->value);
        $this->assertTrue($registration->fresh()->isPaid());

        $this->assertDatabaseHas('payments', [
            'payable_type' => Registration::class,
            'payable_id' => $registration->id,
            'status' => 'successful',
            'gateway' => 'manual',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'target_id' => $registration->id,
            'action' => 'registration_manual_paid',
        ]);

        $fresh = $registration->fresh();
        $this->assertNotNull($fresh->latestPayment?->uploaded_by);

        $serialized = app(RegistrationService::class)->serialize($fresh);
        $this->assertTrue($serialized['payment_is_manual']);
    }

    public function test_non_owner_admin_only_sees_registrations_from_own_organization(): void
    {
        $otherOrg = Organization::factory()->create(['name' => 'ABIM']);
        $event = $this->event(['organization_id' => $otherOrg->id]);
        $form = $this->form($event);

        $own = $this->registration($event, $form, ['name' => 'Org Sendiri']);
        $other = $this->registration($event, $form, [
            'name' => 'Org Lain',
            'organization_id' => $otherOrg->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.events.registrations', $event->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.total', 1)
                ->has('registrations.data', 1)
                ->where('registrations.data.0.name', 'Org Sendiri'));

        $response = $this->actingAs($this->admin)
            ->get(route('admin.events.registrations.export', $event->id))
            ->assertOk();

        $csv = $response->streamedContent();
        $this->assertStringContainsString($own->registration_no, $csv);
        $this->assertStringNotContainsString($other->registration_no, $csv);
    }

    public function test_unpaid_registration_expires_and_releases_stock(): void
    {
        $event = $this->event();
        $form = $this->form($event);

        $item = FormItem::create([
            'form_id' => $form->id,
            'name' => 'Jersi',
            'price' => 15,
            'stock' => 10,
            'is_active' => true,
        ]);

        $registration = $this->registration($event, $form, [
            'status' => 'pending',
            'addons_amount' => 15,
        ]);
        $registration->forceFill(['created_at' => now()->subHours(25)])->saveQuietly();

        RegistrationItem::create([
            'registration_id' => $registration->id,
            'form_item_id' => $item->id,
            'name' => 'Jersi',
            'quantity' => 2,
            'unit_price' => 15,
            'line_total' => 30,
        ]);

        // Stok telah ditolak semasa tempahan (10 - 2).
        $item->update(['stock' => 8]);

        $this->artisan('registrations:expire-unpaid')->assertSuccessful();

        $this->assertSame('cancelled', $registration->fresh()->status->value);
        $this->assertSame(10, $item->fresh()->stock);
        $this->assertSame(0, $registration->items()->count());
    }

    public function test_export_streams_csv(): void
    {
        $event = $this->event();
        $form = $this->form($event);

        $registration = $this->registration($event, $form, ['name' => 'Nama Eksport']);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.events.registrations.export', $event->id))
            ->assertOk();

        $this->assertStringContainsString($registration->registration_no, $response->streamedContent());
    }

    public function test_public_legacy_registration_is_labelled_and_filterable(): void
    {
        $event = $this->event();
        $form = $this->form($event);

        $registration = $this->registration($event, $form, [
            'name' => 'Peserta Awam Lama',
            'user_id' => null,
            'source' => Registration::SOURCE_FORM,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.events.registrations', $event->id))
            ->assertInertia(fn ($page) => $page
                ->where('registrations.data.0.name', $registration->name)
                ->where('registrations.data.0.organization_name', 'Orang awam'));

        $this->actingAs($this->admin)
            ->get(route('admin.events.registrations', ['event' => $event->id, 'org' => 'public']))
            ->assertInertia(fn ($page) => $page
                ->where('stats.total', 1)
                ->where('registrations.data.0.name', $registration->name));
    }

    public function test_event_export_contains_all_form_questions_and_excel_download(): void
    {
        $event = $this->event();
        $form = $this->form($event);
        $question = FormQuestion::create([
            'form_id' => $form->id,
            'label' => 'Nama Syarikat/Agensi/Organisasi/NGO',
            'type' => 'text',
            'sort_order' => 0,
        ]);
        $nameQuestion = FormQuestion::create([
            'form_id' => $form->id,
            'label' => 'Nama Penuh',
            'type' => 'text',
            'sort_order' => 1,
        ]);
        $icQuestion = FormQuestion::create([
            'form_id' => $form->id,
            'label' => 'No. IC',
            'type' => 'text',
            'sort_order' => 2,
        ]);
        $registration = $this->registration($event, $form, [
            'name' => 'Peserta Awam',
            'user_id' => null,
            'source' => Registration::SOURCE_FORM,
        ]);
        $response = FormResponse::create([
            'form_id' => $form->id,
            'registration_id' => $registration->id,
            'respondent_name' => $registration->name,
            'submitted_at' => now(),
        ]);
        FormAnswer::create([
            'form_response_id' => $response->id,
            'form_question_id' => $question->id,
            'value' => 'Universiti Malaya',
        ]);
        FormAnswer::create([
            'form_response_id' => $response->id,
            'form_question_id' => $nameQuestion->id,
            'value' => 'Peserta Awam',
        ]);
        FormAnswer::create([
            'form_response_id' => $response->id,
            'form_question_id' => $icQuestion->id,
            'value' => '900101-01-1234',
        ]);

        $csv = $this->actingAs($this->admin)
            ->get(route('admin.events.registrations.export', $event->id))
            ->assertOk()
            ->streamedContent();

        $header = str_getcsv(str_replace("\xEF\xBB\xBF", '', strtok($csv, "\n")));
        $this->assertContains('Nama Syarikat/Agensi/Organisasi/NGO', $header);
        $this->assertContains('Nama Penuh', $header);
        $this->assertContains('No. IC', $header);
        $this->assertNotContains('Organisasi akaun', $header);
        $this->assertNotContains('No. Ahli', $header);
        $this->assertNotContains('Borang', $header);
        $this->assertNotContains('Kategori', $header);
        $this->assertNotContains('Jumlah (RM)', $header);
        $this->assertNotContains('Item', $header);
        $this->assertNotContains('Bayaran', $header);
        $this->assertSame(1, count(array_filter($header, fn ($column) => $column === 'Nama Penuh')));
        $this->assertSame(1, count(array_filter($header, fn ($column) => $column === 'No. IC')));
        $this->assertStringContainsString('Universiti Malaya', $csv);

        $excel = $this->actingAs($this->admin)
            ->get(route('admin.events.registrations.export', ['event' => $event->id, 'format' => 'xlsx']))
            ->assertOk();

        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $excel->headers->get('Content-Type')
        );
        $this->assertStringContainsString('.xlsx', (string) $excel->headers->get('Content-Disposition'));
    }

    public function test_free_upcoming_event_export_omits_payment_and_attendance_columns(): void
    {
        $event = $this->event(['start_time' => now()->addDay()]);
        $form = $this->form($event);
        FormQuestion::create([
            'form_id' => $form->id,
            'label' => 'Nama Penuh',
            'type' => 'text',
            'sort_order' => 0,
        ]);

        $this->registration($event, $form, ['name' => 'Peserta Masa Depan']);

        $csv = $this->actingAs($this->admin)
            ->get(route('admin.events.registrations.export', $event->id))
            ->assertOk()
            ->streamedContent();

        $header = str_getcsv(str_replace("\xEF\xBB\xBF", '', strtok($csv, "\n")));
        $this->assertContains('Nama Penuh', $header);
        $this->assertNotContains('Jumlah (RM)', $header);
        $this->assertNotContains('Item', $header);
        $this->assertNotContains('Bayaran', $header);
        $this->assertNotContains('Kehadiran', $header);
        $this->assertNotContains('Masa Hadir', $header);
        $this->assertStringNotContainsString('Tidak Hadir', $csv);
    }

    public function test_past_event_export_includes_attendance_status_and_time(): void
    {
        $event = $this->event();
        $form = $this->form($event);

        $attended = $this->registration($event, $form, ['name' => 'Sudah Hadir']);
        $this->registration($event, $form, ['name' => 'Belum Hadir']);

        $attendance = Attendance::create([
            'registration_id' => $attended->id,
            'event_id' => $event->id,
            'attended_at' => now()->subMinutes(30),
            'method' => 'member',
        ]);

        $csv = $this->actingAs($this->admin)
            ->get(route('admin.events.registrations.export', $event->id))
            ->assertOk()
            ->streamedContent();

        $header = str_getcsv(str_replace("\xEF\xBB\xBF", '', strtok($csv, "\n")));
        $this->assertContains('Kehadiran', $header);
        $this->assertContains('Masa Hadir', $header);
        $this->assertStringContainsString('Hadir', $csv);
        $this->assertStringContainsString('Tidak Hadir', $csv);
        $this->assertStringContainsString($attendance->attended_at->format('d/m/Y h:i A'), $csv);
    }
}

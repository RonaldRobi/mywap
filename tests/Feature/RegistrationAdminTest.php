<?php

namespace Tests\Feature;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Form;
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

        $this->registration($event, $form, ['name' => 'Org Sendiri']);
        $this->registration($event, $form, [
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
        $this->assertStringContainsString('Org Sendiri', $csv);
        $this->assertStringNotContainsString('Org Lain', $csv);
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

        $this->registration($event, $form, ['name' => 'Nama Eksport']);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.events.registrations.export', $event->id))
            ->assertOk();

        $this->assertStringContainsString('Nama Eksport', $response->streamedContent());
    }
}

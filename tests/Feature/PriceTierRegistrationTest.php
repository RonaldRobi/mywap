<?php

namespace Tests\Feature;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormQuestion;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PriceTierRegistrationTest extends TestCase
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

        $this->org = Organization::factory()->create(['fee_amount' => 50.00]);

        $this->admin = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
        ]);
        $this->admin->assignRole('Admin');

        $this->member = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
            'member_no' => 'PKPIM-0001',
        ]);
        $this->member->assignRole('Member');
    }

    private function makeTieredEvent(): array
    {
        $event = Event::create([
            'organization_id' => $this->org->id,
            'title' => 'Muktamar Harga Tier',
            'description' => 'x',
            'type' => 'physical',
            'status' => EventStatus::Published->value,
            'category' => EventCategory::Muktamar->value,
            'location_or_link' => 'KL',
            'start_time' => now()->addMonth(),
            'end_time' => now()->addMonth()->addHours(3),
        ]);

        $form = Form::create([
            'event_id' => $event->id,
            'organization_id' => $this->org->id,
            'title' => 'Borang Ber-Tier',
            'price' => 50,
            'payment_required' => true,
            'is_active' => true,
            'allow_public' => true,
            'price_tiers' => [
                ['label' => 'Pelajar', 'price' => 20, 'is_default' => true, 'requires_document' => true, 'description' => null],
                ['label' => 'Orang Awam', 'price' => 50, 'is_default' => false, 'requires_document' => false, 'description' => null],
            ],
        ]);
        FormQuestion::create([
            'form_id' => $form->id,
            'label' => 'Nama Penuh',
            'type' => 'text',
            'required' => true,
            'sort_order' => 0,
        ]);

        return [$event, $form];
    }

    private function registerPayload(array $overrides = []): array
    {
        return array_merge([
            'answers' => ['1' => 'Ali Bin Abu'],
            'ticket_type' => 'Pelajar',
            'payment_method' => 'fpx',
        ], $overrides);
    }

    // ─── Admin Builder menyimpan price_tiers ────────────────────────────────

    public function test_admin_can_save_price_tiers_via_builder(): void
    {
        $event = Event::create([
            'organization_id' => $this->org->id,
            'title' => 'Event Tier',
            'description' => 'x',
            'type' => 'physical',
            'status' => 'published',
            'category' => 'muktamar',
            'location_or_link' => 'KL',
            'start_time' => now()->addMonth(),
            'end_time' => now()->addMonth()->addHours(3),
        ]);

        $this->actingAs($this->admin);

        $this->post(route('admin.forms.store'), [
            'title' => 'Borang Tier',
            'payment_required' => true,
            'price' => 50,
            'price_tiers' => [
                ['label' => 'Pelajar', 'price' => 20, 'is_default' => true, 'requires_document' => true],
                ['label' => 'Orang Awam', 'price' => 50, 'is_default' => false, 'requires_document' => false],
            ],
            'event_id' => $event->id,
            'questions' => [['label' => 'Nama', 'type' => 'text', 'required' => true]],
        ])->assertSessionHas('success');

        $form = Form::where('title', 'Borang Tier')->firstOrFail();

        $this->assertSame('Pelajar', $form->tiers()[0]['label']);
        $this->assertSame(20.0, $form->priceForTier('Pelajar'));
        $this->assertSame(50.0, $form->priceForTier('Orang Awam'));
        $this->assertTrue($form->tierRequiresDocument('Pelajar'));
        // `price` = harga tier default untuk back-compat.
        $this->assertSame('20.00', (string) $form->price);
    }

    public function test_paid_form_without_price_is_rejected(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('admin.forms.store'), [
            'title' => 'Borang Kosong',
            'payment_required' => true,
            'questions' => [['label' => 'Nama', 'type' => 'text', 'required' => true]],
        ])->assertStatus(422);
    }

    // ─── Pendaftaran ikut tier ──────────────────────────────────────────────

    public function test_student_tier_requires_document_upload(): void
    {
        [$event, $form] = $this->makeTieredEvent();

        $this->actingAs($this->member);

        $this->post(route('events.register.store', ['event' => $event->slug, 'form' => $form->id]), [
            'answers' => ['1' => 'Ali'],
            'ticket_type' => 'Pelajar',
        ])->assertSessionHasErrors('document');
    }

    public function test_student_tier_pays_student_price_and_stores_document(): void
    {
        [$event, $form] = $this->makeTieredEvent();

        $this->actingAs($this->member);

        $this->post(route('events.register.store', ['event' => $event->slug, 'form' => $form->id]), [
            'answers' => ['1' => 'Ali'],
            'ticket_type' => 'Pelajar',
            'document' => UploadedFile::fake()->create('kad-pelajar.pdf', 100),
        ])->assertRedirect();

        $registration = Registration::where('event_id', $event->id)->where('user_id', $this->member->id)->firstOrFail();
        $this->assertSame('Pelajar', $registration->ticket_type);
        $this->assertNotNull($registration->document_path);

        $payment = $registration->latestPayment;
        $this->assertNotNull($payment);
        $this->assertSame(20.0, (float) $payment->amount);
    }

    public function test_public_tier_pays_public_price_without_document(): void
    {
        [$event, $form] = $this->makeTieredEvent();

        $this->actingAs($this->member);

        $this->post(route('events.register.store', ['event' => $event->slug, 'form' => $form->id]), [
            'answers' => ['1' => 'Ali'],
            'ticket_type' => 'Orang Awam',
        ])->assertRedirect();

        $registration = Registration::where('event_id', $event->id)->where('user_id', $this->member->id)->firstOrFail();
        $this->assertSame('Orang Awam', $registration->ticket_type);
        $this->assertNull($registration->document_path);

        $payment = $registration->latestPayment;
        $this->assertNotNull($payment);
        $this->assertSame(50.0, (float) $payment->amount);
    }

    public function test_invalid_ticket_type_is_rejected(): void
    {
        [$event, $form] = $this->makeTieredEvent();

        $this->actingAs($this->member);

        $this->post(route('events.register.store', ['event' => $event->slug, 'form' => $form->id]), [
            'answers' => ['1' => 'Ali'],
            'ticket_type' => 'VIP Sahaja',
        ])->assertSessionHasErrors('ticket_type');

        $this->assertSame(0, Registration::count());
    }

    public function test_guest_public_store_honours_tier(): void
    {
        [$event, $form] = $this->makeTieredEvent();

        $this->post(route('events.register.public.store', ['token' => $form->share_token]), [
            'answers' => ['1' => 'Tetamu'],
            'ticket_type' => 'Orang Awam',
        ])->assertRedirect();

        $registration = Registration::where('event_id', $event->id)->whereNull('user_id')->firstOrFail();
        $this->assertSame('Orang Awam', $registration->ticket_type);
        $this->assertSame(50.0, (float) $registration->latestPayment->amount);
    }

    // ─── API: borang membawa price_tiers ────────────────────────────────────

    public function test_api_form_payload_includes_price_tiers(): void
    {
        [$event, $form] = $this->makeTieredEvent();

        $this->getJson('/api/v1/forms/'.$form->share_token)
            ->assertOk()
            ->assertJsonPath('data.form.price_tiers.0.label', 'Pelajar')
            ->assertJsonPath('data.form.price_tiers.0.price', 20)
            ->assertJsonPath('data.form.price_tiers.1.label', 'Orang Awam');
    }
}

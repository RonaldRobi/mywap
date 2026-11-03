<?php

namespace Tests\Feature;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormItem;
use App\Models\FormItemVariant;
use App\Models\FormQuestion;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\RegistrationConfirmationNotification;
use App\Services\ReceiptService;
use App\Services\RegistrationPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Optional add-on / merchandise purchases attached to event registration.
 * Covers free, paid, combined and variant/stock behaviour without a live
 * payment gateway (the dummy fallback records a successful payment).
 */
class EventAddonRegistrationTest extends TestCase
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

    private function makeEvent(string $title = 'ABIM Run 2026'): Event
    {
        return Event::create([
            'organization_id' => $this->org->id,
            'title' => $title,
            'description' => 'Larian amal',
            'type' => 'physical',
            'status' => EventStatus::Published->value,
            'category' => EventCategory::Lain->value,
            'location_or_link' => 'KL',
            'start_time' => now()->subHour(),
            'end_time' => now()->addHours(3),
        ]);
    }

    private function makeForm(Event $event, array $overrides = []): Form
    {
        $form = Form::create(array_merge([
            'event_id' => $event->id,
            'organization_id' => $this->org->id,
            'title' => 'Pendaftaran ABIM Run',
            'price' => null,
            'payment_required' => false,
            'is_active' => true,
            'allow_public' => true,
        ], $overrides));

        FormQuestion::create([
            'form_id' => $form->id,
            'label' => 'Nama Penuh',
            'type' => 'text',
            'required' => true,
            'sort_order' => 0,
        ]);

        return $form;
    }

    /**
     * @return array{0: FormItem, 1: array<string, FormItemVariant>}
     */
    private function makeJerseyItem(Form $form): array
    {
        $item = FormItem::create([
            'form_id' => $form->id,
            'name' => 'Jersi',
            'price' => 35,
            'stock' => null,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $variants = [
            'S' => $item->variants()->create(['name' => 'S', 'price' => null, 'stock' => 10, 'is_active' => true, 'sort_order' => 0]),
            'M' => $item->variants()->create(['name' => 'M', 'price' => null, 'stock' => 10, 'is_active' => true, 'sort_order' => 1]),
            'XL' => $item->variants()->create(['name' => 'XL', 'price' => 40, 'stock' => 5, 'is_active' => true, 'sort_order' => 2]),
        ];

        return [$item, $variants];
    }

    private function registerUrl(Event $event, Form $form): string
    {
        return route('events.register.store', ['event' => $event->slug, 'form' => $form->id]);
    }

    // ─── Free + optional add-ons ────────────────────────────────────────────

    public function test_free_event_without_addons_completes_without_payment(): void
    {
        $event = $this->makeEvent();
        $form = $this->makeForm($event);

        $this->actingAs($this->member)
            ->post($this->registerUrl($event, $form), [
                'answers' => ['1' => 'Ali'],
            ])
            ->assertRedirect(route('registrations.success', Registration::first()));

        $registration = Registration::firstOrFail();
        $this->assertSame('confirmed', $registration->status->value);
        $this->assertSame(0, Registration::first()->latestPayment()->count());
        $this->assertSame(0.0, $registration->totalAmount());
        $this->assertTrue($registration->isFreeRegistration());
    }

    public function test_free_registration_stays_free_when_optional_merchandise_is_not_bought(): void
    {
        $event = $this->makeEvent();
        $form = $this->makeForm($event);
        $this->makeJerseyItem($form); // optional merchandise is available

        $this->actingAs($this->member)
            ->post($this->registerUrl($event, $form), [
                'answers' => ['1' => 'Ali'],
            ])
            ->assertRedirect();

        $registration = Registration::firstOrFail();
        $this->assertTrue($registration->isFreeRegistration());
        $this->assertSame(0, $registration->items()->count());
        $this->assertNull($registration->latestPayment);
    }

    public function test_addon_image_is_uploaded_and_exposed_in_public_payload(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin);

        $this->post(route('admin.forms.store'), [
            'title' => 'Borang Barang Bergambar',
            'payment_required' => false,
            'questions' => [['label' => 'Nama', 'type' => 'text', 'required' => true]],
            'items' => [
                [
                    'name' => 'Baju',
                    'price' => 35,
                    'is_active' => true,
                    'image' => UploadedFile::fake()->image('baju.png'),
                    'variants' => [
                        ['name' => 'M', 'price' => null, 'stock' => 10, 'is_active' => true],
                    ],
                ],
            ],
        ])->assertSessionHas('success');

        $form = Form::where('title', 'Borang Barang Bergambar')->firstOrFail();
        $item = $form->items()->firstOrFail();

        $this->assertNotNull($item->image);
        Storage::disk('public')->assertExists($item->image);

        $this->getJson('/api/v1/forms/'.$form->share_token)
            ->assertOk()
            ->assertJsonPath('data.form.items.0.name', 'Baju')
            ->assertJsonPath('data.form.items.0.image_url', asset('storage/'.$item->image))
            ->assertJsonPath('data.form.items.0.has_variants', true)
            ->assertJsonPath('data.form.items.0.variants.0.name', 'M');
    }

    public function test_free_event_with_optional_addon_triggers_payment_and_stores_items(): void
    {
        $event = $this->makeEvent();
        $form = $this->makeForm($event, ['payment_required' => false]);
        [$item, $variants] = $this->makeJerseyItem($form);

        $this->actingAs($this->member)
            ->post($this->registerUrl($event, $form), [
                'answers' => ['1' => 'Ali'],
                'items' => [
                    ['form_item_id' => $item->id, 'variant_id' => $variants['M']->id, 'quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $registration = Registration::firstOrFail();
        $this->assertSame('confirmed', $registration->status->value);
        $this->assertSame(0.0, (float) $registration->base_amount);
        $this->assertSame(35.0, (float) $registration->addons_amount);
        $this->assertFalse($registration->isFreeRegistration());

        $payment = $registration->latestPayment;
        $this->assertNotNull($payment);
        $this->assertSame('successful', $payment->status);
        $this->assertSame(35.0, (float) $payment->amount);

        $line = $registration->items()->firstOrFail();
        $this->assertSame('Jersi', $line->name);
        $this->assertSame('M', $line->variant_name);
        $this->assertSame(1, (int) $line->quantity);
        $this->assertSame(35.0, (float) $line->unit_price);
        $this->assertSame(35.0, (float) $line->line_total);
    }

    public function test_variant_override_price_is_used_and_stock_decremented(): void
    {
        $event = $this->makeEvent();
        $form = $this->makeForm($event);
        [$item, $variants] = $this->makeJerseyItem($form);

        $this->actingAs($this->member)
            ->post($this->registerUrl($event, $form), [
                'answers' => ['1' => 'Ali'],
                'items' => [
                    ['form_item_id' => $item->id, 'variant_id' => $variants['XL']->id, 'quantity' => 2],
                ],
            ])
            ->assertRedirect();

        $registration = Registration::firstOrFail();
        $this->assertSame(80.0, (float) $registration->addons_amount);
        $this->assertSame(3, (int) $variants['XL']->fresh()->stock);
    }

    // ─── Paid + add-ons (single combined payment) ───────────────────────────

    public function test_paid_event_with_addon_charges_single_combined_payment(): void
    {
        $event = $this->makeEvent();
        $form = $this->makeForm($event, ['payment_required' => true, 'price' => 50]);
        [$item, $variants] = $this->makeJerseyItem($form);

        $this->actingAs($this->member)
            ->post($this->registerUrl($event, $form), [
                'answers' => ['1' => 'Ali'],
                'items' => [
                    ['form_item_id' => $item->id, 'variant_id' => $variants['XL']->id, 'quantity' => 2],
                ],
            ])
            ->assertRedirect();

        $registration = Registration::firstOrFail();
        $this->assertSame(50.0, (float) $registration->base_amount);
        $this->assertSame(80.0, (float) $registration->addons_amount);

        $payments = $registration->payments()->get();
        $this->assertCount(1, $payments);
        $this->assertSame(130.0, (float) $payments->first()->amount);
    }

    public function test_multiple_addons_and_quantities_are_summed(): void
    {
        $event = $this->makeEvent();
        $form = $this->makeForm($event);
        [$jersey, $variants] = $this->makeJerseyItem($form);

        $cap = FormItem::create([
            'form_id' => $form->id,
            'name' => 'Topi',
            'price' => 20,
            'stock' => 10,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($this->member)
            ->post($this->registerUrl($event, $form), [
                'answers' => ['1' => 'Ali'],
                'items' => [
                    ['form_item_id' => $jersey->id, 'variant_id' => $variants['S']->id, 'quantity' => 2],
                    ['form_item_id' => $cap->id, 'quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $registration = Registration::firstOrFail();
        $this->assertSame(90.0, (float) $registration->addons_amount);
        $this->assertCount(2, $registration->items()->get());
        $this->assertSame(9, (int) $cap->fresh()->stock);
    }

    // ─── Validation & stock integrity ───────────────────────────────────────

    public function test_out_of_stock_addon_is_rejected_and_no_registration_created(): void
    {
        $event = $this->makeEvent();
        $form = $this->makeForm($event);
        [$item, $variants] = $this->makeJerseyItem($form);
        $variants['XL']->update(['stock' => 1]);

        $this->actingAs($this->member)
            ->post($this->registerUrl($event, $form), [
                'answers' => ['1' => 'Ali'],
                'items' => [
                    ['form_item_id' => $item->id, 'variant_id' => $variants['XL']->id, 'quantity' => 2],
                ],
            ])
            ->assertSessionHasErrors('items');

        $this->assertSame(0, Registration::count());
        $this->assertSame(1, (int) $variants['XL']->fresh()->stock);
    }

    public function test_addon_from_another_form_is_rejected(): void
    {
        $event = $this->makeEvent();
        $form = $this->makeForm($event);

        $otherEvent = $this->makeEvent('Program Lain');
        $otherForm = $this->makeForm($otherEvent, ['title' => 'Borang Lain']);
        $foreignItem = FormItem::create([
            'form_id' => $otherForm->id,
            'name' => 'Barang Asing',
            'price' => 10,
            'is_active' => true,
        ]);

        $this->actingAs($this->member)
            ->post($this->registerUrl($event, $form), [
                'answers' => ['1' => 'Ali'],
                'items' => [
                    ['form_item_id' => $foreignItem->id, 'quantity' => 1],
                ],
            ])
            ->assertSessionHasErrors('items');

        $this->assertSame(0, Registration::count());
    }

    public function test_missing_variant_for_variant_item_is_rejected(): void
    {
        $event = $this->makeEvent();
        $form = $this->makeForm($event);
        [$item] = $this->makeJerseyItem($form);

        $this->actingAs($this->member)
            ->post($this->registerUrl($event, $form), [
                'answers' => ['1' => 'Ali'],
                'items' => [
                    ['form_item_id' => $item->id, 'quantity' => 1],
                ],
            ])
            ->assertSessionHasErrors('items');

        $this->assertSame(0, Registration::count());
    }

    public function test_quantity_is_capped_by_validation(): void
    {
        $event = $this->makeEvent();
        $form = $this->makeForm($event);
        [$item, $variants] = $this->makeJerseyItem($form);

        $this->actingAs($this->member)
            ->post($this->registerUrl($event, $form), [
                'answers' => ['1' => 'Ali'],
                'items' => [
                    ['form_item_id' => $item->id, 'variant_id' => $variants['M']->id, 'quantity' => 500],
                ],
            ])
            ->assertSessionHasErrors('items.0.quantity');
    }

    public function test_required_addon_is_automatically_included(): void
    {
        $event = $this->makeEvent();
        $form = $this->makeForm($event);

        $meal = FormItem::create([
            'form_id' => $form->id,
            'name' => 'Bayaran Makan',
            'price' => 10,
            'is_active' => true,
            'is_required' => true,
            'sort_order' => 0,
        ]);

        // Client does not send any items — a mandatory fee must still apply.
        $this->actingAs($this->member)
            ->post($this->registerUrl($event, $form), [
                'answers' => ['1' => 'Ali'],
            ])
            ->assertRedirect();

        $registration = Registration::firstOrFail();
        $this->assertSame(10.0, (float) $registration->addons_amount);
        $this->assertSame(10.0, (float) $registration->latestPayment->amount);

        $line = $registration->items()->firstOrFail();
        $this->assertSame($meal->id, $line->form_item_id);
        $this->assertSame(1, (int) $line->quantity);
    }

    public function test_required_addon_with_variant_must_be_selected(): void
    {
        $event = $this->makeEvent();
        $form = $this->makeForm($event);

        $meal = FormItem::create([
            'form_id' => $form->id,
            'name' => 'Bayaran Makan',
            'price' => 10,
            'is_active' => true,
            'is_required' => true,
            'sort_order' => 0,
        ]);
        $meal->variants()->create(['name' => 'Ayam', 'price' => 10, 'is_active' => true]);
        $meal->variants()->create(['name' => 'Vegetarian', 'price' => 10, 'is_active' => true]);

        $this->actingAs($this->member)
            ->post($this->registerUrl($event, $form), [
                'answers' => ['1' => 'Ali'],
            ])
            ->assertSessionHasErrors('items');

        $this->assertSame(0, Registration::count());
    }

    public function test_required_addon_with_selected_variant_is_charged(): void
    {
        $event = $this->makeEvent();
        $form = $this->makeForm($event);

        $meal = FormItem::create([
            'form_id' => $form->id,
            'name' => 'Bayaran Makan',
            'price' => 10,
            'is_active' => true,
            'is_required' => true,
            'sort_order' => 0,
        ]);
        $ayam = $meal->variants()->create(['name' => 'Ayam', 'price' => 10, 'is_active' => true]);

        $this->actingAs($this->member)
            ->post($this->registerUrl($event, $form), [
                'answers' => ['1' => 'Ali'],
                'items' => [
                    ['form_item_id' => $meal->id, 'variant_id' => $ayam->id, 'quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $registration = Registration::firstOrFail();
        $this->assertSame(10.0, (float) $registration->addons_amount);
        $this->assertSame('Ayam', $registration->items()->firstOrFail()->variant_name);
    }

    // ─── Admin builder persistence ──────────────────────────────────────────

    public function test_admin_builder_saves_items_and_variants(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('admin.forms.store'), [
            'title' => 'Borang dengan Jersi',
            'payment_required' => false,
            'questions' => [['label' => 'Nama', 'type' => 'text', 'required' => true]],
            'items' => [
                [
                    'name' => 'Jersi',
                    'price' => 35,
                    'is_active' => true,
                    'variants' => [
                        ['name' => 'S', 'price' => null, 'stock' => 10, 'is_active' => true],
                        ['name' => 'XL', 'price' => 40, 'stock' => 5, 'is_active' => true],
                    ],
                ],
            ],
        ])->assertSessionHas('success');

        $form = Form::where('title', 'Borang dengan Jersi')->firstOrFail();
        $this->assertCount(1, $form->items()->get());

        $item = $form->items()->first();
        $this->assertSame('Jersi', $item->name);
        $this->assertSame(2, $item->variants()->count());
        $this->assertSame(40.0, (float) $item->variants()->where('name', 'XL')->first()->price);
    }

    public function test_updating_form_without_items_payload_keeps_existing_addons(): void
    {
        $this->actingAs($this->admin);

        $event = $this->makeEvent();
        $form = $this->makeForm($event);
        $item = FormItem::create([
            'form_id' => $form->id,
            'name' => 'Jersi',
            'price' => 35,
            'is_active' => true,
        ]);
        $question = $form->questions()->first();

        $this->post(route('admin.forms.update', $form->id), [
            '_method' => 'PUT',
            'title' => 'Tajuk Baharu',
            'payment_required' => false,
            'questions' => [
                ['id' => $question->id, 'label' => 'Nama', 'type' => 'text', 'required' => true],
            ],
            // no "items" key — a stale tab must not wipe the add-ons
        ])->assertSessionHas('success');

        $this->assertSame(1, $form->fresh()->items()->count());
        $this->assertSame('Jersi', $form->fresh()->items()->first()->name);
    }

    // ─── Payment idempotency & receipts ─────────────────────────────────────

    public function test_confirming_same_payment_twice_is_idempotent(): void
    {
        Notification::fake();

        $event = $this->makeEvent();
        $form = $this->makeForm($event);
        [$item, $variants] = $this->makeJerseyItem($form);

        $this->actingAs($this->member)
            ->post($this->registerUrl($event, $form), [
                'answers' => ['1' => 'Ali'],
                'items' => [
                    ['form_item_id' => $item->id, 'variant_id' => $variants['M']->id, 'quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $registration = Registration::firstOrFail();
        $payment = $registration->latestPayment;
        $this->assertNotNull($payment);

        $service = app(RegistrationPaymentService::class);
        $service->confirmRegistration($payment);
        $service->confirmRegistration($payment);

        $registration->refresh();
        $this->assertSame('confirmed', $registration->status->value);
        Notification::assertSentOnDemand(RegistrationConfirmationNotification::class, 1);
    }

    public function test_receipt_lists_base_fee_and_addons(): void
    {
        $event = $this->makeEvent();
        $form = $this->makeForm($event, ['payment_required' => true, 'price' => 50]);
        [$item, $variants] = $this->makeJerseyItem($form);

        $this->actingAs($this->member)
            ->post($this->registerUrl($event, $form), [
                'answers' => ['1' => 'Ali'],
                'items' => [
                    ['form_item_id' => $item->id, 'variant_id' => $variants['XL']->id, 'quantity' => 2],
                ],
            ])
            ->assertRedirect();

        $payment = Payment::where('payable_type', Registration::class)->firstOrFail();
        $context = app(ReceiptService::class)->context($payment);

        $labels = collect($context['items'])->pluck('label')->implode(' | ');
        $this->assertStringContainsString('Yuran Pendaftaran', $labels);
        $this->assertStringContainsString('Jersi', $labels);
        $this->assertTrue($context['items_have_qty']);
    }
}

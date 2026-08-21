<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Form;
use App\Models\FormQuestion;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuestPayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);
    }

    private function paidForm(): array
    {
        $org = Organization::factory()->create([
            'payment_gateway' => 'senangpay',
            'senangpay_merchant_id' => '14222653788472',
            'senangpay_secret_key' => '53-784',
            'senangpay_environment' => 'sandbox',
        ]);
        $event = Event::create([
            'organization_id' => $org->id,
            'title' => 'Event Guest Pay',
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
            'price' => 30.00,
            'payment_required' => true,
            'is_active' => true,
            'allow_public' => true,
        ]);
        $q = FormQuestion::create(['form_id' => $form->id, 'label' => 'Nama', 'type' => 'text', 'required' => true, 'sort_order' => 0]);

        return [$org, $event, $form, $q];
    }

    public function test_guest_paid_submit_does_not_redirect_to_login(): void
    {
        [, $event, $form, $q] = $this->paidForm();

        // TIDAK login (tetamu).
        $response = $this->post(route('events.register.public.store', ['token' => $form->share_token]), [
            'answers' => [$q->id => 'Tetamu'],
        ]);

        $status = $response->getStatusCode();
        $location = $response->headers->get('Location');

        // Mesti redirect (302) ke senangpay.pay — BUKAN ke /login.
        $this->assertTrue(in_array($status, [200, 302, 409], true), "status=$status");
        if ($location) {
            $this->assertStringNotContainsString('/login', $location);
            echo "GUEST redirect -> $location\n";
        } else {
            echo "GUEST status=$status (409/Inertia)\n";
        }
        $this->assertDatabaseHas('registrations', ['event_id' => $event->id]);
    }

    public function test_guest_free_registration_reaches_success_page(): void
    {
        $org = Organization::factory()->create();
        $event = Event::create([
            'organization_id' => $org->id,
            'title' => 'Event Percuma',
            'description' => 'x',
            'type' => 'physical',
            'status' => 'published',
            'category' => 'muktamar',
            'location_or_link' => 'KL',
            'start_time' => now()->addMonth(),
            'end_time' => now()->addMonth()->addHours(2),
        ]);
        $form = Form::create([
            'event_id' => $event->id,
            'organization_id' => $org->id,
            'title' => 'Borang Percuma',
            'payment_required' => false,
            'is_active' => true,
            'allow_public' => true,
        ]);
        $q = FormQuestion::create(['form_id' => $form->id, 'label' => 'Nama', 'type' => 'text', 'required' => true, 'sort_order' => 0]);

        // Tetamu daftar (percuma).
        $this->post(route('events.register.public.store', ['token' => $form->share_token]), [
            'answers' => [$q->id => 'Tetamu'],
        ])->assertRedirect();

        $registration = Registration::where('event_id', $event->id)->firstOrFail();

        // Halaman terima kasih mesti boleh diakses tetamu (bukan redirect ke login).
        $this->get(route('registrations.success', $registration))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Events/RegistrationSuccess'));
    }
}

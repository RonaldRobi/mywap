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
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Field No. KP / Passport dalam form builder + validation semasa pendaftaran.
 */
class FormIcFieldTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $this->org = Organization::factory()->create();
        $this->admin = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
        ]);
        $this->admin->assignRole('Admin');
    }

    private function makeEvent(): Event
    {
        return Event::create([
            'organization_id' => $this->org->id,
            'title' => 'Program IC',
            'description' => 'Test',
            'type' => 'physical',
            'status' => EventStatus::Published->value,
            'category' => EventCategory::Seminar->value,
            'location_or_link' => 'Kuala Lumpur',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDays(2),
        ]);
    }

    public function test_builder_stores_identity_type(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.forms.store'), [
                'title' => 'Borang Identiti',
                'is_active' => true,
                'allow_public' => true,
                'questions' => [
                    ['label' => 'No. KP / Passport', 'type' => 'ic', 'identity_type' => 'passport', 'required' => true, 'options' => [], 'placeholder' => '', 'help_text' => ''],
                ],
            ])
            ->assertSessionHasNoErrors();

        $form = Form::where('title', 'Borang Identiti')->firstOrFail();
        $this->assertSame('passport', $form->questions->first()->identity_type);
    }

    public function test_guest_registration_rejects_invalid_ic(): void
    {
        $event = $this->makeEvent();
        $form = Form::create([
            'event_id' => $event->id,
            'organization_id' => $this->org->id,
            'title' => 'Borang IC',
            'is_active' => true,
            'allow_public' => true,
        ]);
        $question = FormQuestion::create([
            'form_id' => $form->id,
            'label' => 'No. KP',
            'type' => 'ic',
            'identity_type' => 'ic',
            'required' => true,
            'sort_order' => 0,
        ]);

        $this->post(route('events.register.public.store', ['token' => $form->share_token]), [
            'answers' => [$question->id => '3852598'],
        ])->assertSessionHasErrors("answers.{$question->id}");

        $this->assertDatabaseCount('registrations', 0);
    }

    public function test_guest_registration_accepts_valid_ic_and_maps_to_ic_number(): void
    {
        $event = $this->makeEvent();
        $form = Form::create([
            'event_id' => $event->id,
            'organization_id' => $this->org->id,
            'title' => 'Borang IC Sah',
            'is_active' => true,
            'allow_public' => true,
        ]);
        $question = FormQuestion::create([
            'form_id' => $form->id,
            'label' => 'No. KP',
            'type' => 'ic',
            'identity_type' => 'ic',
            'required' => true,
            'sort_order' => 0,
        ]);

        $this->post(route('events.register.public.store', ['token' => $form->share_token]), [
            'answers' => [$question->id => '900101-01-1234'],
        ])->assertSessionHasNoErrors();

        $registration = Registration::where('event_id', $event->id)->whereNull('user_id')->firstOrFail();
        $this->assertSame('900101-01-1234', $registration->ic_number);
        $this->assertSame('900101011234', $registration->ic_normalized);
    }

    public function test_guest_registration_accepts_passport_without_mapping_to_ic(): void
    {
        $event = $this->makeEvent();
        $form = Form::create([
            'event_id' => $event->id,
            'organization_id' => $this->org->id,
            'title' => 'Borang Passport',
            'is_active' => true,
            'allow_public' => true,
        ]);
        $question = FormQuestion::create([
            'form_id' => $form->id,
            'label' => 'Passport',
            'type' => 'ic',
            'identity_type' => 'passport',
            'required' => true,
            'sort_order' => 0,
        ]);

        $this->post(route('events.register.public.store', ['token' => $form->share_token]), [
            'answers' => [$question->id => 'A1234567'],
        ])->assertSessionHasNoErrors();

        $registration = Registration::where('event_id', $event->id)->whereNull('user_id')->firstOrFail();
        $this->assertNull($registration->ic_number);
    }
}

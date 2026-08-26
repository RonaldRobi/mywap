<?php

namespace Tests\Feature\Api\V1;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormQuestion;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FormApiTest extends TestCase
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

    private function makeForm(): Form
    {
        $form = Form::create([
            'organization_id' => $this->org->id,
            'title' => 'Borang Keahlian',
            'description' => 'Borang ujian',
            'is_active' => true,
            'allow_public' => true,
        ]);

        FormQuestion::create([
            'form_id' => $form->id,
            'label' => 'Nama Penuh',
            'type' => 'text',
            'required' => true,
            'placeholder' => 'Nama seperti IC',
            'help_text' => 'Nama penuh anda',
            'sort_order' => 0,
        ]);

        FormQuestion::create([
            'form_id' => $form->id,
            'label' => 'Jantina',
            'type' => 'select',
            'options' => ['Lelaki', 'Perempuan'],
            'required' => false,
            'sort_order' => 1,
        ]);

        return $form;
    }

    private function makeEventForm(): Form
    {
        $event = Event::create([
            'organization_id' => $this->org->id,
            'title' => 'Program Ujian',
            'description' => 'x',
            'type' => 'physical',
            'status' => EventStatus::Published->value,
            'category' => EventCategory::Muktamar->value,
            'location_or_link' => 'KL',
            'start_time' => now()->addMonth(),
            'end_time' => now()->addMonth()->addHours(2),
        ]);
        $event->organizations()->sync([$this->org->id]);

        return Form::create([
            'event_id' => $event->id,
            'organization_id' => $this->org->id,
            'title' => 'Borang Event',
            'is_active' => true,
            'allow_public' => true,
        ]);
    }

    public function test_public_can_fetch_form_by_token(): void
    {
        $form = $this->makeForm();

        $this->getJson("/api/v1/forms/{$form->share_token}")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'form' => [
                        'id', 'title', 'description', 'share_token', 'event_id',
                        'questions' => [
                            ['id', 'label', 'type', 'options', 'required', 'placeholder', 'help_text'],
                        ],
                    ],
                ],
            ])
            ->assertJsonPath('data.form.id', $form->id)
            ->assertJsonPath('data.form.questions.0.label', 'Nama Penuh')
            ->assertJsonPath('data.form.questions.0.required', true)
            ->assertJsonPath('data.form.questions.0.placeholder', 'Nama seperti IC')
            ->assertJsonPath('data.form.questions.0.help_text', 'Nama penuh anda');
    }

    public function test_public_form_for_event_includes_redirect_to(): void
    {
        $form = $this->makeEventForm();

        $this->getJson("/api/v1/forms/{$form->share_token}")
            ->assertOk()
            ->assertJsonPath('data.form.event_id', $form->event_id)
            ->assertJsonPath(
                'data.form.redirect_to',
                route('events.register', ['event' => $form->event->slug, 'form' => $form->id])
            );
    }

    public function test_public_can_submit_form_and_answers_are_stored(): void
    {
        $form = $this->makeForm();
        $q1 = $form->questions()->where('type', 'text')->first();

        $this->postJson("/api/v1/forms/{$form->share_token}/submit", [
            'respondent_name' => 'Ali',
            'respondent_email' => 'ali@example.com',
            'answers' => [
                $q1->id => 'Ali bin Abu',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.response_id', fn ($id) => is_int($id));

        $this->assertDatabaseHas('form_responses', [
            'form_id' => $form->id,
            'respondent_name' => 'Ali',
            'respondent_email' => 'ali@example.com',
        ]);

        $this->assertDatabaseHas('form_answers', [
            'form_question_id' => $q1->id,
            'value' => 'Ali bin Abu',
        ]);
    }

    public function test_public_form_submit_requires_required_questions(): void
    {
        $form = $this->makeForm();
        $q1 = $form->questions()->where('type', 'text')->first();

        $this->postJson("/api/v1/forms/{$form->share_token}/submit", [
            'answers' => [
                $q1->id => '',
            ],
        ])->assertStatus(422);
    }

    public function test_inactive_form_returns_404(): void
    {
        $form = Form::create([
            'organization_id' => $this->org->id,
            'title' => 'Borang Tidak Aktif',
            'is_active' => false,
            'allow_public' => true,
        ]);

        $this->getJson("/api/v1/forms/{$form->share_token}")->assertStatus(404);
    }
}

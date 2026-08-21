<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormQuestion;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrgAdminFormTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;

    private Organization $orgB;

    private User $adminA;

    private User $adminB;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $this->orgA = Organization::factory()->create();
        $this->orgB = Organization::factory()->create();

        $this->adminA = User::factory()->create(['current_organization_id' => $this->orgA->id, 'profile_completed_at' => now()]);
        $this->adminA->assignRole('Admin');
        $this->adminB = User::factory()->create(['current_organization_id' => $this->orgB->id, 'profile_completed_at' => now()]);
        $this->adminB->assignRole('Admin');
    }

    public function test_org_admin_can_access_create_form_page(): void
    {
        $this->actingAs($this->adminA)
            ->get(route('admin.forms.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Forms/Builder'));
    }

    public function test_org_admin_can_create_form_for_own_org(): void
    {
        $this->actingAs($this->adminA);

        $response = $this->post(route('admin.forms.store'), [
            'title' => 'Borang Org A',
            'is_active' => true,
            'allow_public' => true,
            'questions' => [
                ['label' => 'Nama', 'type' => 'text', 'required' => true, 'options' => [], 'placeholder' => '', 'help_text' => ''],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $form = Form::where('title', 'Borang Org A')->firstOrFail();
        $this->assertEquals($this->orgA->id, $form->organization_id);
    }

    public function test_org_admin_can_edit_own_form(): void
    {
        $form = Form::create([
            'organization_id' => $this->orgA->id,
            'title' => 'Borang A',
            'is_active' => true,
            'allow_public' => true,
        ]);
        FormQuestion::create(['form_id' => $form->id, 'label' => 'Soalan', 'type' => 'text', 'required' => false, 'sort_order' => 0]);

        $this->actingAs($this->adminA)
            ->get(route('admin.forms.edit', $form->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Forms/Builder'));
    }

    public function test_org_admin_can_update_own_form(): void
    {
        $form = Form::create([
            'organization_id' => $this->orgA->id,
            'title' => 'Borang A',
            'is_active' => true,
            'allow_public' => true,
        ]);
        FormQuestion::create(['form_id' => $form->id, 'label' => 'Soalan', 'type' => 'text', 'required' => false, 'sort_order' => 0]);

        $this->actingAs($this->adminA)
            ->put(route('admin.forms.update', $form->id), [
                'title' => 'Borang A Dikemas Kini',
                'is_active' => true,
                'allow_public' => true,
                'questions' => [
                    ['id' => $form->questions->first()->id, 'label' => 'Soalan Baru', 'type' => 'text', 'required' => false, 'options' => [], 'placeholder' => '', 'help_text' => ''],
                ],
            ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('forms', ['id' => $form->id, 'title' => 'Borang A Dikemas Kini']);
    }

    public function test_org_admin_cannot_edit_other_org_form(): void
    {
        $form = Form::create([
            'organization_id' => $this->orgB->id,
            'title' => 'Borang B',
            'is_active' => true,
            'allow_public' => true,
        ]);

        $this->actingAs($this->adminA)
            ->get(route('admin.forms.edit', $form->id))
            ->assertForbidden();
    }

    public function test_org_admin_index_shows_only_own_forms(): void
    {
        Form::create(['organization_id' => $this->orgA->id, 'title' => 'Borang A Saya', 'is_active' => true, 'allow_public' => true]);
        Form::create(['organization_id' => $this->orgB->id, 'title' => 'Borang B Lain', 'is_active' => true, 'allow_public' => true]);

        $this->actingAs($this->adminA)
            ->get(route('admin.forms.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Forms/Index')
                ->has('forms.data', 1)
                ->where('forms.data.0.title', 'Borang A Saya'));
    }

    public function test_org_admin_builder_lists_involved_events(): void
    {
        // Event gabungan: owner null, org A dalam pivot — org admin A patut nampak.
        $event = Event::create([
            'organization_id' => null,
            'title' => 'Muktamar Gabungan',
            'description' => 'x',
            'type' => 'physical',
            'status' => 'published',
            'category' => 'muktamar',
            'location_or_link' => 'KL',
            'start_time' => now()->addMonth(),
            'end_time' => now()->addMonth()->addHours(2),
        ]);
        $event->organizations()->sync([$this->orgA->id]);

        $this->actingAs($this->adminA)
            ->get(route('admin.forms.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Forms/Builder')
                ->has('events', 1)
                ->where('events.0.id', $event->id));
    }

    public function test_branch_question_options_come_from_org_branches(): void
    {
        Branch::create(['organization_id' => $this->orgA->id, 'name' => 'Johor Utara', 'state' => 'Johor', 'is_active' => true]);
        Branch::create(['organization_id' => $this->orgA->id, 'name' => 'Johor Selatan', 'state' => 'Johor', 'is_active' => true]);
        Branch::create(['organization_id' => $this->orgA->id, 'name' => 'Selangor Utara', 'state' => 'Selangor', 'is_active' => true]);
        // Cawangan org lain tidak sepatutnya muncul.
        Branch::create(['organization_id' => $this->orgB->id, 'name' => 'Terengganu Pantai', 'state' => 'Terengganu', 'is_active' => true]);

        $event = Event::create([
            'organization_id' => $this->orgA->id,
            'title' => 'Program Cawangan',
            'description' => 'x',
            'type' => 'physical',
            'status' => 'published',
            'category' => 'muktamar',
            'location_or_link' => 'KL',
            'start_time' => now()->addMonth(),
            'end_time' => now()->addMonth()->addHours(2),
        ]);
        $form = Form::create(['event_id' => $event->id, 'organization_id' => $this->orgA->id, 'title' => 'Borang Cawangan', 'is_active' => true, 'allow_public' => true]);
        FormQuestion::create(['form_id' => $form->id, 'label' => 'Negeri Organisasi', 'type' => 'branch', 'required' => true, 'sort_order' => 0]);

        $this->get(route('events.register.public', $form->share_token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Events/Register')
                ->has('form.branch_options', 2)
                ->where('form.branch_options.0.state', 'Johor')
                ->where('form.branch_options.0.branches.0.name', 'Johor Selatan')
                ->where('form.branch_options.1.state', 'Selangor'));
    }

    public function test_branch_answer_is_stored(): void
    {
        Branch::create(['organization_id' => $this->orgA->id, 'name' => 'Johor Utara', 'state' => 'Johor', 'is_active' => true]);

        $event = Event::create([
            'organization_id' => $this->orgA->id,
            'title' => 'Program Cawangan 2',
            'description' => 'x',
            'type' => 'physical',
            'status' => 'published',
            'category' => 'muktamar',
            'location_or_link' => 'KL',
            'start_time' => now()->addMonth(),
            'end_time' => now()->addMonth()->addHours(2),
        ]);
        $form = Form::create(['event_id' => $event->id, 'organization_id' => $this->orgA->id, 'title' => 'Borang Cawangan 2', 'is_active' => true, 'allow_public' => true]);
        $q = FormQuestion::create(['form_id' => $form->id, 'label' => 'Negeri Organisasi', 'type' => 'branch', 'required' => true, 'sort_order' => 0]);

        $this->post(route('events.register.public.store', ['token' => $form->share_token]), [
            'answers' => [$q->id => 'Johor - Johor Utara'],
        ])->assertSessionHas('success');

        $registration = Registration::where('event_id', $event->id)->firstOrFail();
        $answer = $registration->form->responses()->first()->answers()->where('form_question_id', $q->id)->firstOrFail();
        $this->assertSame('Johor - Johor Utara', $answer->value);
    }
}

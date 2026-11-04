<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormQuestion;
use App\Models\FormResponse;
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
            'start_time' => now()->subHour(),
            'end_time' => now()->addHours(2),
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
            'start_time' => now()->subHour(),
            'end_time' => now()->addHours(2),
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
            'start_time' => now()->subHour(),
            'end_time' => now()->addHours(2),
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

    public function test_event_form_public_link_redirects_to_register_page(): void
    {
        $event = Event::create([
            'organization_id' => $this->orgA->id,
            'title' => 'Program Redirect',
            'description' => 'x',
            'type' => 'physical',
            'status' => 'published',
            'category' => 'muktamar',
            'location_or_link' => 'KL',
            'start_time' => now()->subHour(),
            'end_time' => now()->addHours(2),
        ]);
        $form = Form::create(['event_id' => $event->id, 'organization_id' => $this->orgA->id, 'title' => 'Borang Event', 'is_active' => true, 'allow_public' => true]);

        // Link generik /borang/{token} untuk borang event → terus ke halaman daftar.
        $this->get(route('forms.public', $form->share_token))
            ->assertRedirect(route('events.register.public', $form->share_token));
    }

    public function test_branch_options_fall_back_to_event_org_when_form_org_null(): void
    {
        Branch::create(['organization_id' => $this->orgA->id, 'name' => 'Johor Utara', 'state' => 'Johor', 'is_active' => true]);

        $event = Event::create([
            'organization_id' => $this->orgA->id,
            'title' => 'Program Org A',
            'description' => 'x',
            'type' => 'physical',
            'status' => 'published',
            'category' => 'muktamar',
            'location_or_link' => 'KL',
            'start_time' => now()->subHour(),
            'end_time' => now()->addHours(2),
        ]);
        // Form tiada org — patut fallback ke org event.
        $form = Form::create(['event_id' => $event->id, 'organization_id' => null, 'title' => 'Borang No Org', 'is_active' => true, 'allow_public' => true]);
        FormQuestion::create(['form_id' => $form->id, 'label' => 'Negeri', 'type' => 'branch', 'required' => true, 'sort_order' => 0]);

        $this->get(route('events.register.public', $form->share_token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Events/Register')
                ->has('form.branch_options', 1)
                ->where('form.branch_options.0.state', 'Johor'));
    }

    public function test_update_does_not_delete_questions_omitted_from_payload(): void
    {
        $form = Form::create([
            'organization_id' => $this->orgA->id,
            'title' => 'Borang A',
            'is_active' => true,
            'allow_public' => true,
        ]);
        $kept = FormQuestion::create(['form_id' => $form->id, 'label' => 'Nama', 'type' => 'text', 'required' => true, 'sort_order' => 0]);
        $newer = FormQuestion::create(['form_id' => $form->id, 'label' => 'Checkbox Baharu', 'type' => 'checkbox', 'options' => ['Ya'], 'required' => false, 'sort_order' => 1]);

        $this->actingAs($this->adminA)
            ->put(route('admin.forms.update', $form->id), [
                'title' => 'Borang A',
                'is_active' => true,
                'allow_public' => true,
                'questions' => [
                    ['id' => $kept->id, 'label' => 'Nama', 'type' => 'text', 'required' => true, 'options' => [], 'placeholder' => '', 'help_text' => ''],
                ],
            ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('form_questions', ['id' => $newer->id, 'label' => 'Checkbox Baharu']);
    }

    public function test_update_deletes_question_explicitly_marked_deleted(): void
    {
        $form = Form::create([
            'organization_id' => $this->orgA->id,
            'title' => 'Borang A',
            'is_active' => true,
            'allow_public' => true,
        ]);
        $kept = FormQuestion::create(['form_id' => $form->id, 'label' => 'Nama', 'type' => 'text', 'required' => true, 'sort_order' => 0]);
        $deleted = FormQuestion::create(['form_id' => $form->id, 'label' => 'Buang', 'type' => 'text', 'required' => false, 'sort_order' => 1]);

        $this->actingAs($this->adminA)
            ->put(route('admin.forms.update', $form->id), [
                'title' => 'Borang A',
                'is_active' => true,
                'allow_public' => true,
                'deleted_question_ids' => [$deleted->id],
                'questions' => [
                    ['id' => $kept->id, 'label' => 'Nama', 'type' => 'text', 'required' => true, 'options' => [], 'placeholder' => '', 'help_text' => ''],
                ],
            ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('form_questions', ['id' => $deleted->id]);
        $this->assertDatabaseHas('form_questions', ['id' => $kept->id]);
    }

    public function test_update_accepts_long_question_label_and_help_text(): void
    {
        $form = Form::create([
            'organization_id' => $this->orgA->id,
            'title' => 'Borang Persetujuan',
            'is_active' => true,
            'allow_public' => true,
        ]);
        $question = FormQuestion::create(['form_id' => $form->id, 'label' => 'Persetujuan', 'type' => 'checkbox', 'options' => ['Ya'], 'required' => true, 'sort_order' => 0]);
        $longLabel = rtrim(str_repeat('PENGESAHAN MAKLUMAT DAN PERSETUJUAN ', 20));
        $longHelp = rtrim(str_repeat('Saya mengesahkan semua maklumat adalah benar. ', 20));

        $this->actingAs($this->adminA)
            ->put(route('admin.forms.update', $form->id), [
                'title' => 'Borang Persetujuan',
                'is_active' => true,
                'allow_public' => true,
                'questions' => [
                    ['id' => $question->id, 'label' => $longLabel, 'type' => 'checkbox', 'options' => ['Ya'], 'required' => true, 'placeholder' => '', 'help_text' => $longHelp],
                ],
            ])->assertSessionHasNoErrors();

        $question->refresh();
        $this->assertSame($longLabel, $question->label);
        $this->assertSame($longHelp, $question->help_text);
    }

    public function test_export_csv_only_contains_timestamp_and_form_field_columns_without_duplicate_respondent_columns(): void
    {
        $form = Form::create([
            'organization_id' => $this->orgA->id,
            'title' => 'Borang Pendaftaran Kursus',
            'is_active' => true,
            'allow_public' => true,
        ]);

        $qNama = FormQuestion::create(['form_id' => $form->id, 'label' => 'Nama Penuh', 'type' => 'text', 'sort_order' => 0]);
        $qTel = FormQuestion::create(['form_id' => $form->id, 'label' => 'No Telefon', 'type' => 'phone', 'sort_order' => 1]);
        $qEmail = FormQuestion::create(['form_id' => $form->id, 'label' => 'Emel', 'type' => 'email', 'sort_order' => 2]);
        $qAlamat = FormQuestion::create(['form_id' => $form->id, 'label' => 'Alamat Rumah', 'type' => 'textarea', 'sort_order' => 3]);

        $response = FormResponse::create([
            'form_id' => $form->id,
            'respondent_name' => 'Ahmad Albab',
            'respondent_email' => 'ahmad@example.com',
            'respondent_phone' => '0123456789',
            'submitted_at' => now(),
        ]);

        FormAnswer::create(['form_response_id' => $response->id, 'form_question_id' => $qNama->id, 'value' => 'Ahmad Albab']);
        FormAnswer::create(['form_response_id' => $response->id, 'form_question_id' => $qTel->id, 'value' => '0123456789']);
        FormAnswer::create(['form_response_id' => $response->id, 'form_question_id' => $qEmail->id, 'value' => 'ahmad@example.com']);
        FormAnswer::create(['form_response_id' => $response->id, 'form_question_id' => $qAlamat->id, 'value' => 'Kuala Lumpur']);

        $res = $this->actingAs($this->adminA)
            ->get(route('admin.forms.export', $form->id));

        $res->assertOk();
        $this->assertStringContainsString('text/csv', (string) $res->headers->get('Content-Type'));

        $content = $res->streamedContent();

        // Remove UTF-8 BOM if present
        $clean = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = array_filter(explode("\n", trim($clean)));

        $this->assertGreaterThanOrEqual(2, count($lines));

        $header = str_getcsv($lines[0]);
        // Seharusnya seperti Google Form: Timestamp, diikuti medan borang yang ada
        $this->assertSame(['Timestamp', 'Nama Penuh', 'No Telefon', 'Emel', 'Alamat Rumah'], $header);

        $row1 = str_getcsv($lines[1]);
        $this->assertCount(5, $row1);
        $this->assertSame('Ahmad Albab', $row1[1]);
        $this->assertSame('0123456789', $row1[2]);
        $this->assertSame('ahmad@example.com', $row1[3]);
        $this->assertSame('Kuala Lumpur', $row1[4]);
    }

    public function test_export_excel_returns_xlsx_download(): void
    {
        $form = Form::create([
            'organization_id' => $this->orgA->id,
            'title' => 'Borang Seminar',
            'is_active' => true,
            'allow_public' => true,
        ]);

        FormQuestion::create(['form_id' => $form->id, 'label' => 'Nama', 'type' => 'text', 'sort_order' => 0]);

        $res = $this->actingAs($this->adminA)
            ->get(route('admin.forms.export', ['form' => $form->id, 'format' => 'xlsx']));

        $res->assertOk();
        $this->assertStringContainsString('.xlsx', (string) $res->headers->get('Content-Disposition'));
    }

    public function test_export_deduplicates_duplicate_question_labels_in_database(): void
    {
        $form = Form::create([
            'organization_id' => $this->orgA->id,
            'title' => 'Borang Bertindih',
            'is_active' => true,
            'allow_public' => true,
        ]);

        // Dua soalan dengan label yang sama dalam pangkalan data
        $qOld = FormQuestion::create(['form_id' => $form->id, 'label' => 'Nama Penuh', 'type' => 'text', 'sort_order' => 0]);
        $qNew = FormQuestion::create(['form_id' => $form->id, 'label' => 'Nama Penuh', 'type' => 'text', 'sort_order' => 1]);

        $r1 = FormResponse::create(['form_id' => $form->id, 'submitted_at' => now()->subDay()]);
        FormAnswer::create(['form_response_id' => $r1->id, 'form_question_id' => $qOld->id, 'value' => 'Responden Lama']);

        $r2 = FormResponse::create(['form_id' => $form->id, 'submitted_at' => now()]);
        FormAnswer::create(['form_response_id' => $r2->id, 'form_question_id' => $qNew->id, 'value' => 'Responden Baru']);

        $res = $this->actingAs($this->adminA)
            ->get(route('admin.forms.export', $form->id));

        $res->assertOk();
        $clean = preg_replace('/^\xEF\xBB\xBF/', '', $res->streamedContent());
        $lines = array_filter(explode("\n", trim($clean)));

        $header = str_getcsv($lines[0]);
        // Kolum "Nama Penuh" cuma muncul sekali sahaja
        $this->assertSame(['Timestamp', 'Nama Penuh'], $header);

        $row1 = str_getcsv($lines[1]);
        $row2 = str_getcsv($lines[2]);

        $values = [$row1[1], $row2[1]];
        $this->assertContains('Responden Baru', $values);
        $this->assertContains('Responden Lama', $values);
    }

    public function test_export_standardizes_ic_number_format_without_scientific_notation(): void
    {
        $form = Form::create([
            'organization_id' => $this->orgA->id,
            'title' => 'Borang Keahlian',
            'is_active' => true,
            'allow_public' => true,
        ]);

        $qIc = FormQuestion::create(['form_id' => $form->id, 'label' => 'No. Kad Pengenalan', 'type' => 'text', 'sort_order' => 0]);

        // Responden 1 masukkan IC tanpa sengkang (biasanya menjadi 9.70218E+11 dalam Excel jika tidak distandardkan)
        $r1 = FormResponse::create(['form_id' => $form->id, 'submitted_at' => now()->subHour()]);
        FormAnswer::create(['form_response_id' => $r1->id, 'form_question_id' => $qIc->id, 'value' => '970218105432']);

        // Responden 2 masukkan IC dengan sengkang
        $r2 = FormResponse::create(['form_id' => $form->id, 'submitted_at' => now()]);
        FormAnswer::create(['form_response_id' => $r2->id, 'form_question_id' => $qIc->id, 'value' => '970218-10-5432']);

        $res = $this->actingAs($this->adminA)
            ->get(route('admin.forms.export', $form->id));

        $res->assertOk();
        $clean = preg_replace('/^\xEF\xBB\xBF/', '', $res->streamedContent());
        $lines = array_filter(explode("\n", trim($clean)));

        $row1 = str_getcsv($lines[1]);
        $row2 = str_getcsv($lines[2]);

        // Kedua-dua baris mestilah distandardkan kepada format XXXXXX-XX-XXXX
        $this->assertSame('970218-10-5432', $row1[1]);
        $this->assertSame('970218-10-5432', $row2[1]);
        $this->assertStringNotContainsString('E+', $clean);

        // Uji juga untuk muat turun Excel (.xlsx)
        $resExcel = $this->actingAs($this->adminA)
            ->get(route('admin.forms.export', ['form' => $form->id, 'format' => 'xlsx']));
        $resExcel->assertOk();
    }
}

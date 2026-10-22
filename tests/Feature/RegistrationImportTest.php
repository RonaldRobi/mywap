<?php

namespace Tests\Feature;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormQuestion;
use App\Models\FormResponse;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\RegistrationImport;
use App\Models\User;
use App\Services\RegistrationDedupeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegistrationImportTest extends TestCase
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

        $this->org = Organization::factory()->create(['name' => 'PKPIM']);

        $this->admin = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
        ]);
        $this->admin->assignRole('Admin');
    }

    private function event(): Event
    {
        return Event::create([
            'organization_id' => $this->org->id,
            'title' => 'Program Import Test',
            'description' => 'Test',
            'type' => 'physical',
            'status' => EventStatus::Published->value,
            'category' => EventCategory::Seminar->value,
            'location_or_link' => 'Kuala Lumpur',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(3),
        ]);
    }

    private function csvFile(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('responses.csv', $content);
    }

    private function mapping(): array
    {
        // Column order: Timestamp, Nama Penuh, No. IC, No. Telefon, Emel
        return [
            'name' => 1,
            'ic_number' => 2,
            'phone' => 3,
            'email' => 4,
            'member_no' => null,
        ];
    }

    public function test_preview_detects_google_form_columns(): void
    {
        $event = $this->event();
        $csv = "Timestamp,Nama Penuh,No. IC,No. Telefon,Emel\n"
            ."2026-10-19 10:00:00,Ahmad Ali,980101-01-1234,012-3456789,ahmad@example.com\n";

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.events.registrations.import.preview', $event->id), [
                'file' => $this->csvFile($csv),
            ]);

        $response->assertOk()
            ->assertJsonPath('mapping.name', 1)
            ->assertJsonPath('mapping.ic_number', 2)
            ->assertJsonPath('mapping.phone', 3)
            ->assertJsonPath('mapping.email', 4)
            ->assertJsonPath('total_rows', 1);
    }

    public function test_repeated_import_updates_existing_and_creates_new_without_duplicates(): void
    {
        $event = $this->event();

        $first = "Timestamp,Nama Penuh,No. IC,No. Telefon,Emel\n"
            ."2026-10-19 10:00:00,Ahmad Ali,980101-01-1234,012-3456789,ahmad@example.com\n"
            ."2026-10-19 10:01:00,Siti Aminah,900202-02-5678,0123456780,siti@example.com\n";

        $this->actingAs($this->admin)
            ->postJson(route('admin.events.registrations.import', $event->id), [
                'file' => $this->csvFile($first),
                'mapping' => $this->mapping(),
            ])
            ->assertOk()
            ->assertJsonPath('summary.created', 2)
            ->assertJsonPath('summary.updated', 0);

        $second = "Timestamp,Nama Penuh,No. IC,No. Telefon,Emel\n"
            ."2026-10-19 11:00:00,Ahmad Ali bin Abdullah,980101011234,012-9999999,ahmad@example.com\n"
            ."2026-10-19 11:01:00,Siti Aminah,900202025678,0123456780,siti@example.com\n"
            ."2026-10-19 11:02:00,Nur Izzah,010203-03-4567,017-1112222,nur@example.com\n";

        $this->actingAs($this->admin)
            ->postJson(route('admin.events.registrations.import', $event->id), [
                'file' => $this->csvFile($second),
                'mapping' => $this->mapping(),
            ])
            ->assertOk()
            ->assertJsonPath('summary.created', 1)
            ->assertJsonPath('summary.updated', 2)
            ->assertJsonPath('summary.skipped', 0);

        $this->assertSame(3, Registration::where('event_id', $event->id)->count());
        $this->assertSame(
            'Ahmad Ali bin Abdullah',
            Registration::where('event_id', $event->id)->where('ic_normalized', '980101011234')->value('name'),
        );
        $this->assertSame(
            '0129999999',
            Registration::where('event_id', $event->id)->where('ic_normalized', '980101011234')->value('phone_normalized'),
        );
        $this->assertSame(2, RegistrationImport::where('event_id', $event->id)->count());
    }

    public function test_imported_participant_can_check_in_with_digits_only_ic(): void
    {
        $event = $this->event();

        $csv = "Timestamp,Nama Penuh,No. IC,No. Telefon,Emel\n"
            ."2026-10-19 10:00:00,Ahmad Ali,980101-01-1234,012-3456789,ahmad@example.com\n";

        $this->actingAs($this->admin)
            ->postJson(route('admin.events.registrations.import', $event->id), [
                'file' => $this->csvFile($csv),
                'mapping' => $this->mapping(),
            ])
            ->assertOk();

        $registration = Registration::where('event_id', $event->id)->firstOrFail();
        $this->assertSame('980101011234', $registration->ic_normalized);

        $this->post(route('events.attend.identify', [
            'id' => $event->id,
            'token' => $event->attendance_token,
        ]), [
            'identifier' => '980101011234',
        ])->assertOk();

        $this->assertDatabaseHas('attendances', [
            'event_id' => $event->id,
            'registration_id' => $registration->id,
            'method' => 'guest',
        ]);
    }

    public function test_identity_columns_are_normalised_on_create(): void
    {
        $event = $this->event();

        $registration = Registration::create([
            'event_id' => $event->id,
            'name' => 'Ujian Normalisasi',
            'ic_number' => '900202-02-5678',
            'phone' => '+60 12-345 6789',
            'status' => 'confirmed',
        ]);

        $this->assertSame('900202025678', $registration->ic_normalized);
        $this->assertSame('0123456789', $registration->phone_normalized);
    }

    public function test_import_preserves_every_google_form_column(): void
    {
        $event = $this->event();

        $csv = "Timestamp,Nama Penuh,No. IC,No. Telefon,Emel,Jantina,Negeri\n"
            ."2026-10-19 10:00:00,Ahmad Ali,980101-01-1234,012-3456789,ahmad@example.com,Lelaki,Selangor\n";

        $this->actingAs($this->admin)
            ->postJson(route('admin.events.registrations.import', $event->id), [
                'file' => $this->csvFile($csv),
                'mapping' => $this->mapping(),
            ])
            ->assertOk();

        $registration = Registration::where('event_id', $event->id)->firstOrFail();

        $this->assertSame('Lelaki', $registration->raw_data['Jantina']);
        $this->assertSame('Selangor', $registration->raw_data['Negeri']);
        $this->assertSame('2026-10-19 10:00:00', $registration->raw_data['Timestamp']);

        $import = RegistrationImport::where('event_id', $event->id)->firstOrFail();
        $this->assertSame(
            ['Timestamp', 'Nama Penuh', 'No. IC', 'No. Telefon', 'Emel', 'Jantina', 'Negeri'],
            $import->headers,
        );
    }

    public function test_registrations_page_exposes_every_imported_column(): void
    {
        $event = $this->event();

        $csv = "Timestamp,Nama Penuh,No. IC,No. Telefon,Emel,Jantina,Negeri\n"
            ."2026-10-19 10:00:00,Ahmad Ali,980101-01-1234,012-3456789,ahmad@example.com,Lelaki,Selangor\n";

        $this->actingAs($this->admin)
            ->postJson(route('admin.events.registrations.import', $event->id), [
                'file' => $this->csvFile($csv),
                'mapping' => $this->mapping(),
            ])
            ->assertOk();

        $this->actingAs($this->admin)
            ->get(route('admin.events.registrations', $event->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('extraColumns', [
                    'Timestamp', 'Nama Penuh', 'No. IC', 'No. Telefon', 'Emel', 'Jantina', 'Negeri',
                ])
                ->where('registrations.data.0.raw_data.Jantina', 'Lelaki')
                ->where('registrations.data.0.raw_data.Negeri', 'Selangor'));
    }

    public function test_export_includes_answers_from_mywap_registration_form(): void
    {
        $event = $this->event();

        $form = Form::create([
            'event_id' => $event->id,
            'organization_id' => $this->org->id,
            'title' => 'Borang Pendaftaran',
            'is_active' => true,
            'allow_public' => true,
        ]);

        $nama = FormQuestion::create([
            'form_id' => $form->id,
            'label' => 'Nama',
            'type' => 'text',
            'required' => true,
            'sort_order' => 0,
        ]);
        $jawatan = FormQuestion::create([
            'form_id' => $form->id,
            'label' => 'Jawatan',
            'type' => 'text',
            'required' => false,
            'sort_order' => 1,
        ]);

        $this->post(route('events.register.public.store', ['token' => $form->share_token]), [
            'answers' => [
                $nama->id => 'Ali Bin Abu',
                $jawatan->id => 'Setiausaha',
            ],
        ])->assertSessionHas('success');

        $registration = Registration::where('event_id', $event->id)->firstOrFail();
        $this->assertSame($registration->id, $registration->formResponse?->registration_id);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.events.registrations.export', $event->id));

        $response->assertOk();

        $content = ltrim($response->streamedContent(), "\xEF\xBB\xBF");
        $lines = preg_split('/\r\n|\n|\r/', trim($content));
        $headers = str_getcsv($lines[0]);
        $row = str_getcsv($lines[1]);

        $this->assertContains('Jawatan', $headers);
        $this->assertSame('Setiausaha', $row[array_search('Jawatan', $headers, true)]);
    }

    public function test_export_merges_csv_and_form_name_columns_and_keeps_all_rows(): void
    {
        $event = $this->event();

        $csv = "Timestamp,Nama Penuh,No. IC,No. Telefon,Emel\n"
            ."2026-10-19 10:00:00,Ahmad Ali,980101-01-1234,012-3456789,ahmad@example.com\n";

        $this->actingAs($this->admin)
            ->postJson(route('admin.events.registrations.import', $event->id), [
                'file' => $this->csvFile($csv),
                'mapping' => $this->mapping(),
            ])
            ->assertOk();

        $form = Form::create([
            'event_id' => $event->id,
            'organization_id' => $this->org->id,
            'title' => 'Borang Pendaftaran',
            'is_active' => true,
            'allow_public' => true,
        ]);

        $nama = FormQuestion::create([
            'form_id' => $form->id,
            'label' => 'Nama',
            'type' => 'text',
            'required' => true,
            'sort_order' => 0,
        ]);
        $jawatan = FormQuestion::create([
            'form_id' => $form->id,
            'label' => 'Jawatan',
            'type' => 'text',
            'required' => false,
            'sort_order' => 1,
        ]);

        $this->post(route('events.register.public.store', ['token' => $form->share_token]), [
            'answers' => [
                $nama->id => 'Ali Form',
                $jawatan->id => 'Setiausaha',
            ],
        ])->assertSessionHas('success');

        $response = $this->actingAs($this->admin)
            ->get(route('admin.events.registrations.export', $event->id));

        $response->assertOk();

        $content = ltrim($response->streamedContent(), "\xEF\xBB\xBF");
        $lines = preg_split('/\r\n|\n|\r/', trim($content));
        $headers = str_getcsv($lines[0]);

        // "Nama" from the myWAP form must reuse the CSV "Nama Penuh" column.
        $this->assertNotContains('Nama', $headers);
        $this->assertContains('Nama Penuh', $headers);
        $this->assertContains('Jawatan', $headers);
        $this->assertCount(3, $lines);

        $nameIndex = array_search('Nama Penuh', $headers, true);
        $jawatanIndex = array_search('Jawatan', $headers, true);
        $names = [];
        $jawatanValues = [];

        foreach (array_slice($lines, 1) as $line) {
            $row = str_getcsv($line);
            $names[] = $row[$nameIndex];
            $jawatanValues[] = $row[$jawatanIndex];
        }

        $this->assertContains('Ahmad Ali', $names);
        $this->assertContains('Ali Form', $names);
        $this->assertContains('Setiausaha', $jawatanValues);
    }

    public function test_export_streams_every_registration_beyond_one_chunk(): void
    {
        $event = $this->event();
        $now = now();
        $rows = [];

        for ($i = 1; $i <= 501; $i++) {
            $rows[] = [
                'event_id' => $event->id,
                'organization_id' => $this->org->id,
                'registration_no' => 'REG-CHUNK-'.$i,
                'name' => 'Peserta '.$i,
                'status' => 'confirmed',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        Registration::insert($rows);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.events.registrations.export', $event->id));

        $response->assertOk();

        $content = ltrim($response->streamedContent(), "\xEF\xBB\xBF");
        $lines = preg_split('/\r\n|\n|\r/', trim($content));

        $this->assertCount(502, $lines);
    }

    public function test_attendance_excel_export_includes_form_answers(): void
    {
        $event = $this->event();

        $form = Form::create([
            'event_id' => $event->id,
            'organization_id' => $this->org->id,
            'title' => 'Borang Kehadiran',
            'is_active' => true,
            'allow_public' => true,
        ]);

        $nama = FormQuestion::create([
            'form_id' => $form->id,
            'label' => 'Nama',
            'type' => 'text',
            'required' => true,
            'sort_order' => 0,
        ]);
        $jawatan = FormQuestion::create([
            'form_id' => $form->id,
            'label' => 'Jawatan',
            'type' => 'text',
            'required' => false,
            'sort_order' => 1,
        ]);

        $csv = "Timestamp,Nama Penuh,No. IC,No. Telefon,Emel,Jawatan\n"
            ."2026-10-19 10:00:00,Ahmad CSV,980101-01-1234,012-3456789,ahmad@example.com,Bendahari\n";

        $this->actingAs($this->admin)
            ->postJson(route('admin.events.registrations.import', $event->id), [
                'file' => $this->csvFile($csv),
                'mapping' => $this->mapping(),
            ])
            ->assertOk();

        $this->post(route('events.register.public.store', ['token' => $form->share_token]), [
            'answers' => [
                $nama->id => 'Ali Bin Abu',
                $jawatan->id => 'Setiausaha',
            ],
        ])->assertSessionHas('success');

        $response = $this->actingAs($this->admin)
            ->get(route('admin.attendance.export.excel', ['event_id' => $event->id]));

        $response->assertOk();

        $spreadsheet = IOFactory::load(
            $response->getFile()->getPathname(),
        );
        $sheet = $spreadsheet->getActiveSheet();
        $lastColumn = $sheet->getHighestColumn();

        $headings = $sheet->rangeToArray('A1:'.$lastColumn.'1')[0];
        $rows = $sheet->rangeToArray('A2:'.$lastColumn.$sheet->getHighestRow());

        $this->assertContains('Jawatan', $headings);

        $nameIndex = array_search('Nama', $headings, true);
        $jawatanIndex = array_search('Jawatan', $headings, true);
        $jawatanByParticipant = [];

        foreach ($rows as $row) {
            $jawatanByParticipant[$row[$nameIndex]] = $row[$jawatanIndex];
        }

        $this->assertSame('Setiausaha', $jawatanByParticipant['Ali Bin Abu']);
        $this->assertSame('Bendahari', $jawatanByParticipant['Ahmad CSV']);

        $pdf = $this->actingAs($this->admin)
            ->get(route('admin.attendance.export.pdf', ['event_id' => $event->id]));

        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('content-type'));
    }

    public function test_export_finds_form_answers_when_response_has_no_registration_link(): void
    {
        $event = $this->event();

        $form = Form::create([
            'event_id' => $event->id,
            'organization_id' => $this->org->id,
            'title' => 'Borang Lama',
            'is_active' => true,
            'allow_public' => true,
        ]);

        $question = FormQuestion::create([
            'form_id' => $form->id,
            'label' => 'Jawatan',
            'type' => 'text',
            'required' => false,
            'sort_order' => 0,
        ]);

        $registration = Registration::create([
            'event_id' => $event->id,
            'form_id' => $form->id,
            'organization_id' => $this->org->id,
            'name' => 'Peserta Lama',
            'status' => 'confirmed',
        ]);

        $response = FormResponse::create([
            'form_id' => $form->id,
            'registration_id' => null,
            'respondent_name' => $registration->name,
            'submitted_at' => now(),
        ]);

        FormAnswer::create([
            'form_response_id' => $response->id,
            'form_question_id' => $question->id,
            'value' => 'Setiausaha',
        ]);

        $export = $this->actingAs($this->admin)
            ->get(route('admin.events.registrations.export', $event->id));

        $export->assertOk();

        $content = ltrim($export->streamedContent(), "\xEF\xBB\xBF");
        $lines = preg_split('/\r\n|\n|\r/', trim($content));
        $headers = str_getcsv($lines[0]);
        $row = str_getcsv($lines[1]);

        $this->assertContains('Jawatan', $headers);
        $this->assertSame('Setiausaha', $row[array_search('Jawatan', $headers, true)]);
    }

    public function test_company_question_is_not_replaced_by_mywap_organization(): void
    {
        $this->org->update(['name' => 'Wadah']);

        $event = $this->event();

        $form = Form::create([
            'event_id' => $event->id,
            'organization_id' => $this->org->id,
            'title' => 'Borang Syarikat',
            'is_active' => true,
            'allow_public' => true,
        ]);

        $nama = FormQuestion::create([
            'form_id' => $form->id,
            'label' => 'NAMA PENUH',
            'type' => 'text',
            'required' => true,
            'sort_order' => 0,
        ]);
        $company = FormQuestion::create([
            'form_id' => $form->id,
            'label' => 'NAMA SYARIKAT/AGENSI/ORGANISASI/NGO',
            'type' => 'text',
            'required' => true,
            'sort_order' => 1,
        ]);

        $this->post(route('events.register.public.store', ['token' => $form->share_token]), [
            'answers' => [
                $nama->id => 'Ali Bin Abu',
                $company->id => 'Syarikat ABC',
            ],
        ])->assertSessionHas('success');

        $export = $this->actingAs($this->admin)
            ->get(route('admin.events.registrations.export', $event->id));

        $export->assertOk();

        $content = ltrim($export->streamedContent(), "\xEF\xBB\xBF");
        $lines = preg_split('/\r\n|\n|\r/', trim($content));
        $headers = str_getcsv($lines[0]);
        $row = str_getcsv($lines[1]);

        $companyIndex = array_search('NAMA SYARIKAT/AGENSI/ORGANISASI/NGO', $headers, true);
        $orgIndex = array_search('Organisasi myWAP', $headers, true);

        $this->assertNotFalse($companyIndex);
        $this->assertSame('Syarikat ABC', $row[$companyIndex]);
        $this->assertSame('Wadah', $row[$orgIndex]);

        $attendance = $this->actingAs($this->admin)
            ->get(route('admin.attendance.export.excel', ['event_id' => $event->id]));

        $attendance->assertOk();

        $spreadsheet = IOFactory::load(
            $attendance->getFile()->getPathname(),
        );
        $sheet = $spreadsheet->getActiveSheet();
        $lastColumn = $sheet->getHighestColumn();
        $attendanceHeaders = $sheet->rangeToArray('A1:'.$lastColumn.'1')[0];
        $attendanceRow = $sheet->rangeToArray('A2:'.$lastColumn.'2')[0];

        $this->assertSame(
            'Syarikat ABC',
            $attendanceRow[array_search('NAMA SYARIKAT/AGENSI/ORGANISASI/NGO', $attendanceHeaders, true)],
        );
    }

    public function test_company_answers_match_different_csv_and_form_wording(): void
    {
        $event = $this->event();

        $form = Form::create([
            'event_id' => $event->id,
            'organization_id' => $this->org->id,
            'title' => 'Borang Syarikat',
            'is_active' => true,
            'allow_public' => true,
        ]);

        FormQuestion::create([
            'form_id' => $form->id,
            'label' => 'NAMA SYARIKAT/AGENSI/ORGANISASI/NGO',
            'type' => 'text',
            'required' => false,
            'sort_order' => 0,
        ]);
        FormQuestion::create([
            'form_id' => $form->id,
            'label' => 'JAWATAN',
            'type' => 'text',
            'required' => false,
            'sort_order' => 1,
        ]);

        $csv = "Timestamp,Nama Penuh,No. IC,No. Telefon,Emel,ORGANISASI/SYARIKAT,JAWATAN\n"
            ."2026-10-19 10:00:00,Ahmad Ali,980101-01-1234,012-3456789,ahmad@example.com,Syarikat CSV,Pengerusi\n";

        $this->actingAs($this->admin)
            ->postJson(route('admin.events.registrations.import', $event->id), [
                'file' => $this->csvFile($csv),
                'mapping' => $this->mapping(),
            ])
            ->assertOk();

        $export = $this->actingAs($this->admin)
            ->get(route('admin.events.registrations.export', $event->id));

        $export->assertOk();

        $content = ltrim($export->streamedContent(), "\xEF\xBB\xBF");
        $lines = preg_split('/\r\n|\n|\r/', trim($content));
        $headers = str_getcsv($lines[0]);
        $row = str_getcsv($lines[1]);

        $this->assertSame(
            'Syarikat CSV',
            $row[array_search('ORGANISASI/SYARIKAT', $headers, true)],
        );

        $attendance = $this->actingAs($this->admin)
            ->get(route('admin.attendance.export.excel', ['event_id' => $event->id]));

        $attendance->assertOk();

        $spreadsheet = IOFactory::load(
            $attendance->getFile()->getPathname(),
        );
        $sheet = $spreadsheet->getActiveSheet();
        $lastColumn = $sheet->getHighestColumn();
        $attendanceHeaders = $sheet->rangeToArray('A1:'.$lastColumn.'1')[0];
        $attendanceRow = $sheet->rangeToArray('A2:'.$lastColumn.'2')[0];

        $this->assertSame(
            'Syarikat CSV',
            $attendanceRow[array_search('NAMA SYARIKAT/AGENSI/ORGANISASI/NGO', $attendanceHeaders, true)],
        );
    }

    public function test_scientific_ic_does_not_merge_different_people(): void
    {
        $event = $this->event();

        $csv = "Nama Penuh,No. IC,No. Telefon,Emel\n"
            ."Orang A,6.00627E+11,0111111111,a@example.com\n"
            ."Orang B,6.00627E+11,0222222222,b@example.com\n";

        $this->actingAs($this->admin)
            ->postJson(route('admin.events.registrations.import', $event->id), [
                'file' => $this->csvFile($csv),
                'mapping' => ['name' => 0, 'ic_number' => 1, 'phone' => 2, 'email' => 3, 'member_no' => null],
            ])
            ->assertOk()
            ->assertJsonPath('summary.created', 2)
            ->assertJsonPath('summary.skipped', 0);

        $this->assertSame(2, Registration::where('event_id', $event->id)->count());

        $registration = Registration::where('event_id', $event->id)
            ->where('name', 'Orang A')
            ->firstOrFail();

        $this->assertSame('6.00627E+11', $registration->ic_number);
        $this->assertNull($registration->ic_normalized);
    }

    public function test_shared_phone_does_not_merge_different_people(): void
    {
        $event = $this->event();

        $csv = "Nama Penuh,No. IC,No. Telefon,Emel\n"
            ."Mohamad Amran bin Ariffin,6.20724E+11,0122955277,amran@example.com\n"
            ."Suzana binti Osman,6.60212E+11,0122955277,suzana@example.com\n";

        $this->actingAs($this->admin)
            ->postJson(route('admin.events.registrations.import', $event->id), [
                'file' => $this->csvFile($csv),
                'mapping' => ['name' => 0, 'ic_number' => 1, 'phone' => 2, 'email' => 3, 'member_no' => null],
            ])
            ->assertOk()
            ->assertJsonPath('summary.created', 2);

        $this->assertSame(2, Registration::where('event_id', $event->id)->count());
    }

    public function test_duplicate_submission_with_name_variant_is_merged(): void
    {
        $event = $this->event();

        $csv = "Nama Penuh,No. IC,No. Telefon,Emel\n"
            ."Khairani Che Ibrahim,6.5053E+11,0163383279,ummiawiz@gmail.com\n"
            ."Khairani binti Che Ibrahim,6.5053E+11,0163383279,ummiawiz@gmail.com\n";

        $this->actingAs($this->admin)
            ->postJson(route('admin.events.registrations.import', $event->id), [
                'file' => $this->csvFile($csv),
                'mapping' => ['name' => 0, 'ic_number' => 1, 'phone' => 2, 'email' => 3, 'member_no' => null],
            ])
            ->assertOk()
            ->assertJsonPath('summary.created', 1)
            ->assertJsonPath('summary.skipped', 1);

        $this->assertSame(1, Registration::where('event_id', $event->id)->count());
    }

    public function test_preview_prefers_emel_over_google_email_address(): void
    {
        $event = $this->event();

        $csv = "Timestamp,Email Address,NAMA PENUH:,NO. KAD PENGENALAN:,NO. TELEFON:,EMEL:\n"
            ."10/5/2026,google@example.com,Ahmad Ali,900101-01-1234,0123456789,peserta@example.com\n";

        $this->actingAs($this->admin)
            ->postJson(route('admin.events.registrations.import.preview', $event->id), [
                'file' => $this->csvFile($csv),
            ])
            ->assertOk()
            ->assertJsonPath('mapping.email', 5)
            ->assertJsonPath('stats.unique_identities', 1);
    }

    public function test_preview_reports_duplicates_and_invalid_ics(): void
    {
        $event = $this->event();

        $csv = "Nama Penuh,No. IC,No. Telefon,Emel\n"
            ."Orang A,6.00627E+11,0111111111,a@example.com\n"
            ."Orang A,6.00627E+11,0111111111,a@example.com\n"
            ."Orang B,6.00627E+11,0222222222,b@example.com\n";

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.events.registrations.import.preview', $event->id), [
                'file' => $this->csvFile($csv),
            ]);

        $response->assertOk()
            ->assertJsonPath('stats.total_rows', 3)
            ->assertJsonPath('stats.unique_identities', 2)
            ->assertJsonPath('stats.duplicate_rows', 1)
            ->assertJsonPath('stats.invalid_ic_rows', 3);
    }

    public function test_check_in_accepts_phone_with_or_without_leading_zero(): void
    {
        $event = $this->event();

        $this->assertSame('0123456789', Registration::normalizePhone('123456789'));
        $this->assertSame('0123456789', Registration::normalizePhone('0123456789'));
        $this->assertSame('0123456789', Registration::normalizePhone('60123456789'));
        $this->assertSame('0123456789', Registration::normalizePhone('+60 12-345 6789'));

        $csv = "Nama Penuh,No. IC,No. Telefon,Emel\n"
            ."Ahmad Tanpa Sifar,900101011234,123456789,ahmad@example.com\n"
            ."Siti Dengan Sifar,900202025678,0129998888,siti@example.com\n";

        $this->actingAs($this->admin)
            ->postJson(route('admin.events.registrations.import', $event->id), [
                'file' => $this->csvFile($csv),
                'mapping' => ['name' => 0, 'ic_number' => 1, 'phone' => 2, 'email' => 3, 'member_no' => null],
            ])
            ->assertOk()
            ->assertJsonPath('summary.created', 2);

        $this->assertSame(
            '0123456789',
            Registration::where('event_id', $event->id)->where('name', 'Ahmad Tanpa Sifar')->value('phone_normalized'),
        );

        // CSV stored "123456789"; participant types the full local number.
        $this->post(route('events.attend.identify', [
            'id' => $event->id,
            'token' => $event->attendance_token,
        ]), ['identifier' => '0123456789'])->assertOk();

        // CSV stored "0129998888"; participant omits the leading zero.
        $this->post(route('events.attend.identify', [
            'id' => $event->id,
            'token' => $event->attendance_token,
        ]), ['identifier' => '129998888'])->assertOk();

        $this->assertDatabaseCount('attendances', 2);
    }

    public function test_duplicate_registrations_are_merged_and_history_is_kept(): void
    {
        $event = $this->event();

        $keeper = Registration::create([
            'event_id' => $event->id,
            'organization_id' => $this->org->id,
            'name' => 'Ahmad Ali',
            'ic_number' => '900101-01-1234',
            'phone' => '0123456789',
            'email' => 'ahmad@example.com',
            'status' => 'confirmed',
        ]);

        $duplicate = Registration::create([
            'event_id' => $event->id,
            'organization_id' => $this->org->id,
            'name' => 'Ahmad Ali',
            'ic_number' => '900101011234',
            'phone' => '0123456789',
            'email' => 'ahmad2@example.com',
            'status' => 'confirmed',
            'raw_data' => ['JAWATAN' => 'Pengerusi'],
        ]);

        // Same phone but a different person: must not be merged.
        $spouse = Registration::create([
            'event_id' => $event->id,
            'organization_id' => $this->org->id,
            'name' => 'Siti Aminah',
            'ic_number' => '910202-02-5678',
            'phone' => '0123456789',
            'email' => 'siti@example.com',
            'status' => 'confirmed',
        ]);

        Attendance::create([
            'event_id' => $event->id,
            'registration_id' => $keeper->id,
            'attended_at' => now(),
            'method' => 'scan',
        ]);

        $payment = Payment::create([
            'payable_type' => Registration::class,
            'payable_id' => $duplicate->id,
            'amount' => 50,
            'status' => 'successful',
            'reference' => 'PAY-DUPLICATE',
        ]);

        $service = app(RegistrationDedupeService::class);
        $summary = $service->summary($event);

        $this->assertSame(3, $summary['total_rows']);
        $this->assertSame(2, $summary['unique_identities']);
        $this->assertSame(1, $summary['duplicate_rows']);
        $this->assertSame(1, $summary['groups_count']);

        $result = $service->merge($event);

        $this->assertSame(1, $result['deleted']);
        $this->assertSame(2, $result['remaining']);
        $this->assertSame(2, Registration::where('event_id', $event->id)->count());
        $this->assertNull(Registration::find($duplicate->id));
        $this->assertNotNull(Registration::find($keeper->id));
        $this->assertNotNull(Registration::find($spouse->id));

        $this->assertSame(
            $keeper->id,
            Attendance::where('event_id', $event->id)->value('registration_id'),
        );
        $this->assertSame(
            $keeper->id,
            Payment::where('reference', 'PAY-DUPLICATE')->value('payable_id'),
        );
        $this->assertSame('Pengerusi', Registration::find($keeper->id)->raw_data['JAWATAN']);
    }

    public function test_repeated_public_form_submission_updates_existing_registration(): void
    {
        $event = $this->event();

        $form = Form::create([
            'event_id' => $event->id,
            'organization_id' => $this->org->id,
            'title' => 'Borang Pendaftaran',
            'is_active' => true,
            'allow_public' => true,
        ]);

        $nama = FormQuestion::create(['form_id' => $form->id, 'label' => 'Nama', 'type' => 'text', 'required' => true, 'sort_order' => 0]);
        $ic = FormQuestion::create(['form_id' => $form->id, 'label' => 'No IC', 'type' => 'text', 'required' => true, 'sort_order' => 1]);
        $phone = FormQuestion::create(['form_id' => $form->id, 'label' => 'No Telefon', 'type' => 'phone', 'required' => true, 'sort_order' => 2]);
        $email = FormQuestion::create(['form_id' => $form->id, 'label' => 'Emel', 'type' => 'email', 'required' => false, 'sort_order' => 3]);

        $answers = [
            $nama->id => 'Ali Bin Abu',
            $ic->id => '900101-01-1234',
            $phone->id => '0123456789',
            $email->id => 'ali@example.com',
        ];

        $this->post(route('events.register.public.store', ['token' => $form->share_token]), [
            'answers' => $answers,
        ])->assertSessionHas('success');

        $first = Registration::where('event_id', $event->id)->firstOrFail();
        $this->assertSame('form', $first->source);

        // Same person submits again (e.g. refreshed the form).
        $this->post(route('events.register.public.store', ['token' => $form->share_token]), [
            'answers' => $answers,
        ])->assertSessionHas('success');

        $this->assertSame(1, Registration::where('event_id', $event->id)->count());
        $this->assertSame($first->id, Registration::where('event_id', $event->id)->firstOrFail()->id);
        $this->assertSame(2, FormResponse::where('registration_id', $first->id)->count());

        // A different person sharing the same phone must still get a new record.
        $this->post(route('events.register.public.store', ['token' => $form->share_token]), [
            'answers' => [
                $nama->id => 'Siti Aminah',
                $ic->id => '910202-02-5678',
                $phone->id => '0123456789',
                $email->id => 'siti@example.com',
            ],
        ])->assertSessionHas('success');

        $this->assertSame(2, Registration::where('event_id', $event->id)->count());
    }

    public function test_new_import_replaces_previous_import_but_keeps_mywap_and_walkins(): void
    {
        $event = $this->event();

        $first = "Nama Penuh,No. IC,No. Telefon,Emel\n"
            ."Import Lama Satu,900101011234,0111111111,old1@example.com\n"
            ."Import Lama Dua,900202025678,0122222222,old2@example.com\n";

        $this->actingAs($this->admin)
            ->postJson(route('admin.events.registrations.import', $event->id), [
                'file' => $this->csvFile($first),
                'mapping' => ['name' => 0, 'ic_number' => 1, 'phone' => 2, 'email' => 3, 'member_no' => null],
            ])
            ->assertOk()
            ->assertJsonPath('summary.created', 2);

        $formRegistration = Registration::create([
            'event_id' => $event->id,
            'name' => 'Peserta myWAP',
            'ic_number' => '900303-03-9999',
            'phone' => '0133333333',
            'status' => 'confirmed',
            'source' => Registration::SOURCE_FORM,
        ]);

        $walkIn = Registration::create([
            'event_id' => $event->id,
            'name' => 'Peserta Walk In',
            'ic_number' => '900404-04-8888',
            'phone' => '0144444444',
            'status' => 'walkin',
            'source' => Registration::SOURCE_WALKIN,
        ]);

        $second = "Nama Penuh,No. IC,No. Telefon,Emel\n"
            ."Import Terbaru,900505055555,0155555555,new@example.com\n";

        $this->actingAs($this->admin)
            ->postJson(route('admin.events.registrations.import', $event->id), [
                'file' => $this->csvFile($second),
                'mapping' => ['name' => 0, 'ic_number' => 1, 'phone' => 2, 'email' => 3, 'member_no' => null],
            ])
            ->assertOk()
            ->assertJsonPath('summary.created', 1)
            ->assertJsonPath('summary.removed', 2);

        $this->assertDatabaseMissing('registrations', ['name' => 'Import Lama Satu']);
        $this->assertDatabaseMissing('registrations', ['name' => 'Import Lama Dua']);
        $this->assertDatabaseHas('registrations', ['name' => 'Import Terbaru']);
        $this->assertDatabaseHas('registrations', ['id' => $formRegistration->id, 'source' => 'form']);
        $this->assertDatabaseHas('registrations', ['id' => $walkIn->id, 'source' => 'walkin']);
        $this->assertSame(3, Registration::where('event_id', $event->id)->count());
    }

    public function test_new_import_keeps_old_imported_participant_who_already_attended(): void
    {
        $event = $this->event();

        $this->actingAs($this->admin)
            ->postJson(route('admin.events.registrations.import', $event->id), [
                'file' => $this->csvFile("Nama Penuh,No. IC,No. Telefon,Emel\n"
                    ."Sudah Hadir,900101011234,0111111111,hadir@example.com\n"
                    ."Belum Hadir,900202025678,0122222222,belum@example.com\n"),
                'mapping' => ['name' => 0, 'ic_number' => 1, 'phone' => 2, 'email' => 3, 'member_no' => null],
            ])
            ->assertOk();

        $attended = Registration::where('event_id', $event->id)->where('name', 'Sudah Hadir')->firstOrFail();
        $notAttended = Registration::where('event_id', $event->id)->where('name', 'Belum Hadir')->firstOrFail();

        Attendance::create([
            'event_id' => $event->id,
            'registration_id' => $attended->id,
            'attended_at' => now(),
            'method' => 'scan',
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.events.registrations.import', $event->id), [
                'file' => $this->csvFile("Nama Penuh,No. IC,No. Telefon,Emel\n"
                    ."Peserta Baharu,900505055555,0155555555,baharu@example.com\n"),
                'mapping' => ['name' => 0, 'ic_number' => 1, 'phone' => 2, 'email' => 3, 'member_no' => null],
            ])
            ->assertOk()
            ->assertJsonPath('summary.removed', 1);

        $this->assertDatabaseHas('registrations', ['id' => $attended->id]);
        $this->assertDatabaseMissing('registrations', ['id' => $notAttended->id]);
        $this->assertSame(2, Registration::where('event_id', $event->id)->count());
    }

    public function test_partial_reimport_does_not_drop_earlier_columns(): void
    {
        $event = $this->event();

        $full = "Timestamp,Nama Penuh,No. IC,No. Telefon,Emel,Jantina,Negeri\n"
            ."2026-10-19 10:00:00,Ahmad Ali,980101-01-1234,012-3456789,ahmad@example.com,Lelaki,Selangor\n";

        $this->actingAs($this->admin)
            ->postJson(route('admin.events.registrations.import', $event->id), [
                'file' => $this->csvFile($full),
                'mapping' => $this->mapping(),
            ])
            ->assertOk();

        // A filtered/partial sheet that only contains the core columns.
        $partial = "Timestamp,Nama Penuh,No. IC,No. Telefon,Emel\n"
            ."2026-10-20 11:00:00,Ahmad Ali,980101011234,012-3456789,ahmad@example.com\n";

        $this->actingAs($this->admin)
            ->postJson(route('admin.events.registrations.import', $event->id), [
                'file' => $this->csvFile($partial),
                'mapping' => $this->mapping(),
            ])
            ->assertOk()
            ->assertJsonPath('summary.updated', 1);

        $registration = Registration::where('event_id', $event->id)->firstOrFail();

        $this->assertSame('Lelaki', $registration->raw_data['Jantina']);
        $this->assertSame('Selangor', $registration->raw_data['Negeri']);
    }

    public function test_export_keeps_original_columns_and_can_be_imported_back(): void
    {
        $event = $this->event();

        $csv = "Timestamp,Nama Penuh,No. IC,No. Telefon,Emel,Jantina,Negeri\n"
            ."2026-10-19 10:00:00,Ahmad Ali,980101-01-1234,012-3456789,ahmad@example.com,Lelaki,Selangor\n";

        $this->actingAs($this->admin)
            ->postJson(route('admin.events.registrations.import', $event->id), [
                'file' => $this->csvFile($csv),
                'mapping' => $this->mapping(),
            ])
            ->assertOk();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.events.registrations.export', $event->id));

        $response->assertOk();

        $content = ltrim($response->streamedContent(), "\xEF\xBB\xBF");
        $lines = preg_split('/\r\n|\n|\r/', trim($content));
        $headers = str_getcsv($lines[0]);

        $this->assertContains('Timestamp', $headers);
        $this->assertContains('Nama Penuh', $headers);
        $this->assertContains('Jantina', $headers);
        $this->assertContains('Negeri', $headers);
        $this->assertNotContains('No. Pendaftaran', $headers);

        $exportedRow = str_getcsv($lines[1]);
        $this->assertSame('Lelaki', $exportedRow[array_search('Jantina', $headers, true)]);
        $this->assertSame('Selangor', $exportedRow[array_search('Negeri', $headers, true)]);

        $mapping = [
            'name' => array_search('Nama Penuh', $headers, true),
            'ic_number' => array_search('No. IC', $headers, true),
            'phone' => array_search('No. Telefon', $headers, true),
            'email' => array_search('Emel', $headers, true),
            'member_no' => null,
        ];

        $this->actingAs($this->admin)
            ->postJson(route('admin.events.registrations.import', $event->id), [
                'file' => $this->csvFile($content),
                'mapping' => $mapping,
            ])
            ->assertOk()
            ->assertJsonPath('summary.created', 0)
            ->assertJsonPath('summary.updated', 1);

        $this->assertSame(1, Registration::where('event_id', $event->id)->count());
    }

    public function test_other_organization_admin_cannot_import(): void
    {
        $otherOrg = Organization::factory()->create(['name' => 'ABIM']);
        $otherAdmin = User::factory()->create([
            'current_organization_id' => $otherOrg->id,
            'profile_completed_at' => now(),
        ]);
        $otherAdmin->assignRole('Admin');

        $event = $this->event();

        $this->actingAs($otherAdmin)
            ->postJson(route('admin.events.registrations.import.preview', $event->id), [
                'file' => $this->csvFile("Nama\nAli\n"),
            ])
            ->assertForbidden();
    }

    public function test_clear_imported_deletes_imported_registrations_and_import_log(): void
    {
        $event = $this->event();

        $csv = "Nama Penuh,No. IC,No. Telefon,Emel\n"
            ."Ahmad Ali,900101-01-1234,0123456789,ahmad@example.com\n";

        $this->actingAs($this->admin)
            ->postJson(route('admin.events.registrations.import', $event->id), [
                'file' => $this->csvFile($csv),
                'mapping' => ['name' => 0, 'ic_number' => 1, 'phone' => 2, 'email' => 3, 'member_no' => null],
            ])
            ->assertOk()
            ->assertJsonPath('summary.created', 1);

        $imported = Registration::where('event_id', $event->id)
            ->where('source', Registration::SOURCE_IMPORT)
            ->firstOrFail();

        Attendance::create([
            'event_id' => $event->id,
            'registration_id' => $imported->id,
            'attended_at' => now(),
            'method' => 'manual',
        ]);

        $formRegistration = Registration::create([
            'event_id' => $event->id,
            'organization_id' => $this->org->id,
            'name' => 'Peserta Borang myWAP',
            'status' => 'confirmed',
            'source' => Registration::SOURCE_FORM,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.events.registrations.clear-imported', $event->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('registrations', ['id' => $imported->id]);
        $this->assertDatabaseMissing('attendances', ['registration_id' => $imported->id]);
        $this->assertDatabaseHas('registrations', ['id' => $formRegistration->id]);
        $this->assertDatabaseMissing('registration_imports', ['event_id' => $event->id]);
    }

    public function test_other_organization_admin_cannot_clear_imported(): void
    {
        $otherOrg = Organization::factory()->create(['name' => 'ABIM']);
        $otherAdmin = User::factory()->create([
            'current_organization_id' => $otherOrg->id,
            'profile_completed_at' => now(),
        ]);
        $otherAdmin->assignRole('Admin');

        $event = $this->event();

        $this->actingAs($otherAdmin)
            ->delete(route('admin.events.registrations.clear-imported', $event->id))
            ->assertForbidden();
    }
}

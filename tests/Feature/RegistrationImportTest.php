<?php

namespace Tests\Feature;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\RegistrationImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
        $this->assertContains('No. Pendaftaran', $headers);

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
}

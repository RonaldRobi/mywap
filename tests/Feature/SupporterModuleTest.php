<?php

namespace Tests\Feature;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormQuestion;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\Supporter;
use App\Models\User;
use App\Notifications\SupporterInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupporterModuleTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private Organization $otherOrg;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $this->org = Organization::factory()->create(['name' => 'WADAH']);
        $this->otherOrg = Organization::factory()->create(['name' => 'ABIM']);

        $this->admin = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
        ]);
        $this->admin->assignRole('Admin');
    }

    private function program(array $overrides = []): Event
    {
        return Event::create(array_merge([
            'organization_id' => $this->org->id,
            'title' => 'Program Komuniti',
            'description' => 'Test',
            'type' => 'physical',
            'status' => EventStatus::Published->value,
            'category' => EventCategory::Seminar->value,
            'location_or_link' => 'Kuala Lumpur',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(2),
        ], $overrides));
    }

    public function test_manual_non_member_participant_is_saved_as_supporter_with_program_link(): void
    {
        $event = $this->program();

        $this->actingAs($this->admin)
            ->post(route('admin.attendance.participants.store', $event), [
                'name' => 'Aminah Binti Ali',
                'ic_number' => '900101-14-1234',
                'phone' => '0123456789',
                'email' => 'aminah@example.com',
                'mark_present' => true,
            ])
            ->assertSessionHas('success');

        $supporter = Supporter::where('email', 'aminah@example.com')->firstOrFail();

        $this->assertSame($this->org->id, (int) $supporter->organization_id);
        $this->assertSame('900101-14-1234', $supporter->ic_number);
        $this->assertSame('900101141234', $supporter->ic_normalized);
        $this->assertSame('0123456789', $supporter->phone_normalized);
        $this->assertSame('program', $supporter->source);
        $this->assertDatabaseHas('event_supporter', [
            'event_id' => $event->id,
            'supporter_id' => $supporter->id,
        ]);
    }

    public function test_member_participant_is_not_saved_as_supporter(): void
    {
        $event = $this->program();

        $member = User::factory()->create([
            'email' => 'ahli@example.com',
            'ic_number' => '880101-14-5678',
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
        ]);
        $member->assignRole('Member');

        $this->actingAs($this->admin)
            ->post(route('admin.attendance.participants.store', $event), [
                'name' => $member->name,
                'ic_number' => '880101-14-5678',
                'phone' => '0198887777',
                'mark_present' => true,
            ])
            ->assertSessionHas('success');

        $this->assertSame(0, Supporter::query()->count());
    }

    public function test_guest_walk_in_is_saved_as_supporter(): void
    {
        $event = $this->program();

        $this->post(route('events.attend.walkin', [
            'id' => $event->id,
            'token' => $event->attendance_token,
        ]), [
            'name' => 'Siti Aminah',
            'phone' => '0129998888',
            'email' => 'siti@example.com',
        ])->assertOk();

        $supporter = Supporter::where('email', 'siti@example.com')->firstOrFail();

        $this->assertSame($this->org->id, (int) $supporter->organization_id);
        $this->assertTrue($supporter->events->contains($event));
    }

    public function test_public_form_guest_is_saved_as_supporter(): void
    {
        $event = $this->program();

        $form = Form::create([
            'event_id' => $event->id,
            'organization_id' => $this->org->id,
            'title' => 'Borang Program',
            'is_active' => true,
            'allow_public' => true,
        ]);

        $name = FormQuestion::create([
            'form_id' => $form->id,
            'label' => 'Nama',
            'type' => 'text',
            'required' => true,
            'sort_order' => 0,
        ]);
        $ic = FormQuestion::create([
            'form_id' => $form->id,
            'label' => 'No Kad Pengenalan',
            'type' => 'text',
            'required' => true,
            'sort_order' => 1,
        ]);
        $phone = FormQuestion::create([
            'form_id' => $form->id,
            'label' => 'No Telefon',
            'type' => 'phone',
            'required' => true,
            'sort_order' => 2,
        ]);

        $this->post(route('events.register.public.store', ['token' => $form->share_token]), [
            'answers' => [
                $name->id => 'Ali Bin Abu',
                $ic->id => '990101-14-4321',
                $phone->id => '01122223333',
            ],
        ])->assertSessionHas('success');

        $supporter = Supporter::where('name', 'Ali Bin Abu')->firstOrFail();

        $this->assertSame('990101144321', $supporter->ic_normalized);
        $this->assertSame('01122223333', $supporter->phone_normalized);
        $this->assertTrue($supporter->events->contains($event));
    }

    public function test_supporter_index_only_returns_supporters_for_admin_organisation(): void
    {
        Supporter::create([
            'organization_id' => $this->org->id,
            'name' => 'Pendukung WADAH',
            'email' => 'wadah@example.com',
        ]);
        Supporter::create([
            'organization_id' => $this->otherOrg->id,
            'name' => 'Pendukung ABIM',
            'email' => 'abim@example.com',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.supporters.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Supporters')
                ->has('supporters.data', 1)
                ->where('supporters.data.0.email', 'wadah@example.com')
                ->where('counts.total', 1));
    }

    public function test_excel_export_only_contains_supporters_from_admin_organisation(): void
    {
        Supporter::create([
            'organization_id' => $this->org->id,
            'name' => 'Pendukung WADAH',
            'email' => 'wadah@example.com',
        ]);
        Supporter::create([
            'organization_id' => $this->otherOrg->id,
            'name' => 'Pendukung ABIM',
            'email' => 'abim@example.com',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.supporters.export.excel'));

        $response->assertOk();

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($response->getFile()->getPathname());
        $rows = $spreadsheet->getActiveSheet()->toArray();
        $emails = array_column(array_slice($rows, 1), 2);

        $this->assertContains('wadah@example.com', $emails);
        $this->assertNotContains('abim@example.com', $emails);
    }

    public function test_pdf_export_returns_a_pdf_download(): void
    {
        Supporter::create([
            'organization_id' => $this->org->id,
            'name' => 'Pendukung PDF',
            'email' => 'pdf@example.com',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.supporters.export.pdf'));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('.pdf', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_invite_sends_email_and_marks_last_invited_at(): void
    {
        Notification::fake();

        $eligible = Supporter::create([
            'organization_id' => $this->org->id,
            'name' => 'Pendukung Layak',
            'email' => 'layak@example.com',
        ]);
        $converted = Supporter::create([
            'organization_id' => $this->org->id,
            'name' => 'Sudah Ahli',
            'email' => 'ahli@example.com',
            'converted_at' => now(),
        ]);
        $noEmail = Supporter::create([
            'organization_id' => $this->org->id,
            'name' => 'Tiada Emel',
            'phone' => '0123456789',
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.supporters.invite'), [
                'ids' => [$eligible->id, $converted->id, $noEmail->id],
            ])
            ->assertSessionHas('success');

        Notification::assertSentOnDemand(
            SupporterInvitationNotification::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'layak@example.com',
        );

        $this->assertNotNull($eligible->refresh()->last_invited_at);
        $this->assertNull($converted->refresh()->last_invited_at);
        $this->assertNull($noEmail->refresh()->last_invited_at);
    }

    public function test_creating_member_marks_supporter_as_converted(): void
    {
        $supporter = Supporter::create([
            'organization_id' => $this->org->id,
            'name' => 'Bakal Ahli',
            'email' => 'bakal@example.com',
            'ic_number' => '910101-14-1111',
        ]);

        $user = User::factory()->create([
            'name' => 'Bakal Ahli',
            'email' => 'bakal@example.com',
            'ic_number' => '910101-14-1111',
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
        ]);

        $supporter->refresh();

        $this->assertNotNull($supporter->converted_at);
        $this->assertSame($user->id, (int) $supporter->converted_user_id);
    }

    public function test_backfill_command_links_existing_non_member_registration(): void
    {
        $event = $this->program();

        Registration::withoutEvents(fn () => Registration::create([
            'event_id' => $event->id,
            'organization_id' => $this->org->id,
            'registration_no' => 'REG-BACKFILL1',
            'name' => 'Peserta Lama',
            'email' => 'lama@example.com',
            'phone' => '0144445555',
            'status' => 'walkin',
            'source' => 'walkin',
        ]));

        $this->assertSame(0, Supporter::query()->count());

        $this->artisan('app:backfill-supporters')->assertSuccessful();

        $supporter = Supporter::where('email', 'lama@example.com')->firstOrFail();

        $this->assertTrue($supporter->events->contains($event));
    }

    public function test_import_accepts_ic_and_phone_without_email(): void
    {
        $csv = "name,ic,phone\n"
            .'Peserta Tanpa Emel,920202-14-2222,0133334444';

        $file = UploadedFile::fake()->createWithContent('supporters.csv', $csv);

        $this->actingAs($this->admin)
            ->post(route('admin.supporters.import'), ['file' => $file])
            ->assertRedirect();

        $supporter = Supporter::where('ic_normalized', '920202142222')->firstOrFail();

        $this->assertNull($supporter->email);
        $this->assertSame('Peserta Tanpa Emel', $supporter->name);
        $this->assertSame('0133334444', $supporter->phone_normalized);
    }
}

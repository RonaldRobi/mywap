<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\BranchAdmin;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProgramReportTest extends TestCase
{
    use RefreshDatabase;

    private Organization $abim;

    private Organization $wadah;

    private Branch $perak;

    private Branch $johor;

    private User $superadmin;

    private User $orgAdmin;

    private User $branchAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Superadmin', 'Admin', 'org-admin', 'Admin Cawangan', 'Member'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }

        $this->abim = Organization::factory()->create(['name' => 'ABIM', 'slug' => 'abim']);
        $this->wadah = Organization::factory()->create(['name' => 'WADAH', 'slug' => 'wadah']);

        $this->perak = Branch::create([
            'organization_id' => $this->abim->id,
            'name' => 'Perak',
            'state' => 'Perak',
            'is_active' => true,
        ]);
        $this->johor = Branch::create([
            'organization_id' => $this->abim->id,
            'name' => 'Johor',
            'state' => 'Johor',
            'is_active' => true,
        ]);

        $this->superadmin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'super@mywap.my',
            'current_organization_id' => $this->abim->id,
            'profile_completed_at' => now(),
        ]);
        $this->superadmin->assignRole('Superadmin');

        $this->orgAdmin = User::factory()->create([
            'name' => 'Admin ABIM',
            'email' => 'admin-abim@mywap.my',
            'current_organization_id' => $this->abim->id,
            'profile_completed_at' => now(),
        ]);
        $this->orgAdmin->assignRole('Admin');

        $this->branchAdmin = User::factory()->create([
            'name' => 'Admin ABIM Perak',
            'email' => 'admin-perak@mywap.my',
            'current_organization_id' => $this->abim->id,
            'branch_id' => $this->perak->id,
            'profile_completed_at' => now(),
        ]);
        $this->branchAdmin->assignRole('Admin Cawangan');
        BranchAdmin::create([
            'branch_id' => $this->perak->id,
            'user_id' => $this->branchAdmin->id,
            'role' => BranchAdmin::ROLE_MANAGER,
        ]);
    }

    private function event(
        string $title,
        ?Organization $organization,
        ?Branch $branch = null,
        array $overrides = [],
    ): Event {
        return Event::create(array_merge([
            'organization_id' => $organization?->id,
            'branch_id' => $branch?->id,
            'visibility' => $branch ? 'branch_only' : 'open',
            'title' => $title,
            'type' => 'physical',
            'status' => 'published',
            'category' => 'seminar',
            'start_time' => now()->subDays(10),
            'end_time' => now()->subDays(10)->addHours(2),
        ], $overrides));
    }

    private function register(Event $event, string $name, string $ic): Registration
    {
        return Registration::create([
            'event_id' => $event->id,
            'name' => $name,
            'ic_number' => $ic,
            'status' => 'confirmed',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function reportProps(User $user, array $query = []): array
    {
        $response = $this->actingAs($user)->get(route('admin.program-report.index', $query));
        $response->assertOk();

        return $response->viewData('page')['props'];
    }

    public function test_superadmin_sees_all_past_programs_only(): void
    {
        $this->event('Program ABIM', $this->abim, $this->perak);
        $this->event('Program WADAH', $this->wadah);
        $this->event('Program Akan Datang', $this->abim, $this->perak, [
            'start_time' => now()->addDays(5),
            'end_time' => now()->addDays(5)->addHours(2),
        ]);

        $props = $this->reportProps($this->superadmin, ['year' => 'all']);
        $titles = collect($props['events']['data'])->pluck('title');

        $this->assertTrue($titles->contains('Program ABIM'));
        $this->assertTrue($titles->contains('Program WADAH'));
        $this->assertFalse($titles->contains('Program Akan Datang'));
    }

    public function test_organisation_admin_only_sees_programs_for_own_organisation(): void
    {
        $this->event('Program ABIM', $this->abim, $this->perak);
        $this->event('Program WADAH', $this->wadah);

        $props = $this->reportProps($this->orgAdmin, ['year' => 'all']);
        $titles = collect($props['events']['data'])->pluck('title');

        $this->assertTrue($titles->contains('Program ABIM'));
        $this->assertFalse($titles->contains('Program WADAH'));
    }

    public function test_organisation_admin_sees_programs_where_their_organisation_is_involved(): void
    {
        $event = $this->event('Program Bersama', $this->wadah);
        $event->organizations()->attach($this->abim->id);

        $props = $this->reportProps($this->orgAdmin, ['year' => 'all']);
        $titles = collect($props['events']['data'])->pluck('title');

        $this->assertTrue($titles->contains('Program Bersama'));
    }

    public function test_branch_admin_only_sees_programs_for_managed_branch(): void
    {
        $this->event('Program Perak', $this->abim, $this->perak);
        $this->event('Program Johor', $this->abim, $this->johor);
        $this->event('Program Organisasi', $this->abim);

        $props = $this->reportProps($this->branchAdmin, ['year' => 'all']);
        $titles = collect($props['events']['data'])->pluck('title');

        $this->assertTrue($titles->contains('Program Perak'));
        $this->assertFalse($titles->contains('Program Johor'));
        $this->assertFalse($titles->contains('Program Organisasi'));
        $this->assertSame($this->perak->id, (int) $props['filters']['branch_id']);
    }

    public function test_report_filters_by_year_and_month(): void
    {
        $inside = now()->subMonthsNoOverflow(2)->startOfMonth()->addDays(9)->setTime(10, 0);
        $outside = now()->subMonthsNoOverflow(4)->startOfMonth()->addDays(9)->setTime(10, 0);

        $this->event('Program Dalam Bulan', $this->abim, $this->perak, [
            'start_time' => $inside,
            'end_time' => $inside->copy()->addHours(2),
        ]);
        $this->event('Program Luar Bulan', $this->abim, $this->perak, [
            'start_time' => $outside,
            'end_time' => $outside->copy()->addHours(2),
        ]);

        $props = $this->reportProps($this->orgAdmin, [
            'year' => (string) $inside->year,
            'month' => (string) $inside->month,
        ]);
        $titles = collect($props['events']['data'])->pluck('title');

        $this->assertTrue($titles->contains('Program Dalam Bulan'));
        $this->assertFalse($titles->contains('Program Luar Bulan'));
    }

    public function test_report_counts_registrations_and_attendance(): void
    {
        $event = $this->event('Program Kehadiran', $this->abim, $this->perak);

        $first = $this->register($event, 'Peserta Satu', '900101015551');
        $this->register($event, 'Peserta Dua', '900101015552');

        Attendance::create([
            'event_id' => $event->id,
            'registration_id' => $first->id,
            'attended_at' => now(),
            'method' => 'manual',
        ]);

        $props = $this->reportProps($this->orgAdmin, ['year' => 'all']);
        $row = collect($props['events']['data'])->firstWhere('title', 'Program Kehadiran');

        $this->assertSame(2, $row['registrations_count']);
        $this->assertSame(2, $row['unique_participants_count']);
        $this->assertSame(1, $row['attended_count']);
        $this->assertSame(50.0, (float) $row['attendance_rate']);
    }

    public function test_csv_export_contains_filtered_programs(): void
    {
        $this->event('Program CSV ABIM', $this->abim, $this->perak);
        $this->event('Program CSV WADAH', $this->wadah);

        $response = $this->actingAs($this->orgAdmin)
            ->get(route('admin.program-report.export.csv', ['year' => 'all']));

        $response->assertOk();

        $content = $response->streamedContent();

        $this->assertStringContainsString('Laporan Program', $content);
        $this->assertStringContainsString('Program CSV ABIM', $content);
        $this->assertStringNotContainsString('Program CSV WADAH', $content);
    }
}

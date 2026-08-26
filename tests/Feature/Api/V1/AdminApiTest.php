<?php

namespace Tests\Feature\Api\V1;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Models\Attendance;
use App\Models\BroadcastMessage;
use App\Models\Event;
use App\Models\MembershipFee;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\User;
use App\Models\UsrahGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private Organization $otherOrg;

    private User $admin;

    private User $superadmin;

    private User $orgAdmin;

    private User $member;

    private User $pendingMember;

    private User $otherMember;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'org-admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $this->org = Organization::factory()->create(['name' => 'PKPIM']);
        $this->otherOrg = Organization::factory()->create(['name' => 'ABIM']);

        $this->admin = User::factory()->create([
            'name' => 'Admin Test',
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
            'member_no' => 'ADMIN-001',
        ]);
        $this->admin->assignRole('Admin');

        $this->superadmin = User::factory()->create([
            'name' => 'Super Test',
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
        ]);
        $this->superadmin->assignRole('Superadmin');

        $this->orgAdmin = User::factory()->create([
            'name' => 'Org Admin Test',
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
        ]);
        $this->orgAdmin->assignRole('org-admin');

        $this->member = User::factory()->create([
            'name' => 'Ali Member',
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
            'member_no' => 'M-0001',
        ]);
        $this->member->assignRole('Member');

        $this->pendingMember = User::factory()->create([
            'name' => 'Budi Pending',
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => null,
            'member_no' => 'M-0002',
        ]);
        $this->pendingMember->assignRole('Member');

        $this->otherMember = User::factory()->create([
            'name' => 'Zaid Lain',
            'current_organization_id' => $this->otherOrg->id,
            'profile_completed_at' => now(),
            'member_no' => 'ABIM-001',
        ]);
        $this->otherMember->assignRole('Member');
    }

    private function makeEvent(?Organization $owner = null, bool $upcoming = true): Event
    {
        return Event::create([
            'organization_id' => $owner?->id,
            'title' => 'Program Test',
            'description' => 'Test',
            'type' => 'physical',
            'status' => EventStatus::Published->value,
            'category' => EventCategory::Seminar->value,
            'location_or_link' => 'KL',
            'start_time' => $upcoming ? now()->addWeek() : now()->subWeek(),
            'end_time' => $upcoming ? now()->addWeek()->addHours(2) : now()->subWeek()->addHours(2),
        ]);
    }

    private function makeRegistration(Event $event, User $user, bool $attended = false): Registration
    {
        $registration = Registration::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'organization_id' => $user->current_organization_id,
            'member_no' => $user->member_no,
            'name' => $user->name,
            'status' => 'confirmed',
        ]);

        if ($attended) {
            Attendance::create([
                'registration_id' => $registration->id,
                'event_id' => $event->id,
                'attended_at' => now(),
                'method' => 'manual',
            ]);
        }

        return $registration;
    }

    // ─── Dashboard ───────────────────────────────────────────────────────────

    public function test_admin_dashboard_returns_stats(): void
    {
        $this->makeEvent($this->org, upcoming: true);
        $this->makeEvent($this->org, upcoming: false);
        $this->makeEvent($this->otherOrg, upcoming: true);

        Payment::create(['user_id' => $this->member->id, 'payable_type' => MembershipFee::class, 'amount' => 100, 'status' => 'successful']);
        Payment::create(['user_id' => $this->member->id, 'payable_type' => MembershipFee::class, 'amount' => 150, 'status' => 'successful']);
        Payment::create(['user_id' => $this->member->id, 'payable_type' => MembershipFee::class, 'amount' => 50, 'status' => 'pending']);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'stats' => [
                        'total_members',
                        'active_members',
                        'pending_members',
                        'total_events',
                        'upcoming_events',
                        'total_revenue',
                        'pending_payments',
                    ],
                    'recent_activities' => [['id', 'type', 'title', 'description', 'created_at']],
                    'revenue_by_month' => ['labels', 'values'],
                ],
            ])
            ->assertJsonPath('data.stats.total_members', 5)
            ->assertJsonPath('data.stats.active_members', 4)
            ->assertJsonPath('data.stats.pending_members', 1)
            ->assertJsonPath('data.stats.total_events', 2)
            ->assertJsonPath('data.stats.upcoming_events', 1)
            ->assertJsonPath('data.stats.total_revenue', 250)
            ->assertJsonPath('data.stats.pending_payments', 1)
            ->assertJsonCount(6, 'data.revenue_by_month.labels')
            ->assertJsonCount(6, 'data.revenue_by_month.values')
            ->assertJsonPath('data.revenue_by_month.values.5', 250);
    }

    public function test_org_admin_can_access_dashboard(): void
    {
        Sanctum::actingAs($this->orgAdmin);

        $this->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.stats.total_members', 5);
    }

    public function test_member_gets_403_on_dashboard(): void
    {
        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/admin/dashboard')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Tiada kebenaran.');
    }

    // ─── Ahli ────────────────────────────────────────────────────────────────

    public function test_admin_members_list_respects_org_scoping(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/admin/members')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'member_no', 'email', 'phone', 'ic_number', 'branch_name', 'organization', 'status', 'created_at', 'profile_completed_at']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'links' => ['first', 'last', 'prev', 'next'],
            ])
            ->assertJsonPath('meta.total', 5);

        // Ahli dari organisasi lain tidak kelihatan.
        $this->getJson('/api/v1/admin/members?search=Zaid')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_superadmin_sees_members_across_orgs(): void
    {
        Sanctum::actingAs($this->superadmin);

        $this->getJson('/api/v1/admin/members')
            ->assertOk()
            ->assertJsonPath('meta.total', 6);
    }

    public function test_members_search_and_status_filter(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/admin/members?search=Ali')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Ali Member');

        $this->getJson('/api/v1/admin/members?status=pending')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Budi Pending')
            ->assertJsonPath('data.0.status', 'pending');
    }

    // ─── Yuran ───────────────────────────────────────────────────────────────

    public function test_fees_returns_summary_and_fees(): void
    {
        $fee = MembershipFee::factory()->create([
            'user_id' => $this->member->id,
            'organization_id' => $this->org->id,
            'year' => now()->year,
            'amount' => 50,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        Payment::create([
            'user_id' => $this->member->id,
            'payable_type' => MembershipFee::class,
            'payable_id' => $fee->id,
            'amount' => 50,
            'status' => 'successful',
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/admin/fees?search=Ali Member')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'summary' => ['total_members', 'paid_count', 'pending_count', 'revenue'],
                    'fees' => [['id', 'user_id', 'name', 'member_no', 'year', 'amount', 'status', 'paid_at']],
                ],
            ])
            ->assertJsonPath('data.summary.total_members', 5)
            ->assertJsonPath('data.summary.paid_count', 1)
            ->assertJsonPath('data.summary.pending_count', 4)
            ->assertJsonPath('data.summary.revenue', 50)
            ->assertJsonPath('data.fees.0.user_id', $this->member->id)
            ->assertJsonPath('data.fees.0.status', 'paid');
    }

    // ─── Kehadiran ───────────────────────────────────────────────────────────

    public function test_attendance_registrations_returns_event_stats(): void
    {
        $event = $this->makeEvent($this->org);
        $this->makeRegistration($event, $this->member, attended: true);
        $this->makeRegistration($event, $this->pendingMember, attended: false);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/admin/attendance/registrations?event_id='.$event->id)
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'event' => ['id', 'title', 'start_time'],
                    'stats' => ['total_registered', 'attended_count'],
                    'registrations' => [['id', 'name', 'member_no', 'status', 'attended', 'attended_at']],
                ],
            ])
            ->assertJsonPath('data.event.id', $event->id)
            ->assertJsonPath('data.stats.total_registered', 2)
            ->assertJsonPath('data.stats.attended_count', 1)
            ->assertJsonCount(2, 'data.registrations');
    }

    public function test_attendance_registrations_requires_event_id(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/admin/attendance/registrations')
            ->assertStatus(422);
    }

    // ─── Scan ────────────────────────────────────────────────────────────────

    public function test_scan_records_attendance_ok(): void
    {
        $event = $this->makeEvent($this->org);
        $registration = $this->makeRegistration($event, $this->member);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/admin/attendance/scan', [
            'event_id' => $event->id,
            'identifier' => 'M-0001',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.registration.id', $registration->id)
            ->assertJsonPath('data.registration.member_no', 'M-0001');

        $this->assertNotNull($response->json('data.registration.attended_at'));

        $this->assertDatabaseHas('attendances', [
            'registration_id' => $registration->id,
            'event_id' => $event->id,
            'method' => 'manual',
        ]);
    }

    public function test_scan_unknown_identifier_returns_422(): void
    {
        $event = $this->makeEvent($this->org);

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/admin/attendance/scan', [
            'event_id' => $event->id,
            'identifier' => 'TIDAK-WUJUD',
        ])
            ->assertStatus(422)
            ->assertJsonPath('data.status', 'error')
            ->assertJsonPath('data.registration', null);
    }

    public function test_scan_already_attended_returns_422(): void
    {
        $event = $this->makeEvent($this->org);
        $this->makeRegistration($event, $this->member, attended: true);

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/admin/attendance/scan', [
            'event_id' => $event->id,
            'identifier' => 'M-0001',
        ])
            ->assertStatus(422)
            ->assertJsonPath('data.status', 'error');

        $this->assertDatabaseCount('attendances', 1);
    }

    // ─── Siaran ──────────────────────────────────────────────────────────────

    public function test_broadcast_creates_broadcast_message(): void
    {
        Queue::fake();

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/admin/broadcast', [
            'title' => 'Makluman Penting',
            'message' => 'Sila ambil perhatian.',
            'audience' => 'members',
        ])
            ->assertOk()
            ->assertJsonPath('data.success', true);

        $message = BroadcastMessage::withoutGlobalScopes()->latest('id')->first();
        $this->assertNotNull($message);
        $this->assertSame('Makluman Penting', $message->title);
        $this->assertSame('Sila ambil perhatian.', $message->content);
        $this->assertSame('organization', $message->target_criteria);
        $this->assertSame($this->org->id, (int) $message->target_organization_id);

        Queue::assertPushed(\App\Jobs\SendBroadcastJob::class);
    }

    public function test_broadcast_usrah_audience_resolves_member_ids(): void
    {
        Queue::fake();

        $group = UsrahGroup::factory()->create(['organization_id' => $this->org->id]);
        $group->members()->attach($this->member->id);

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/admin/broadcast', [
            'title' => 'Usrah Update',
            'message' => 'Jumpa minggu ini.',
            'audience' => 'usrah',
        ])->assertOk();

        $message = BroadcastMessage::withoutGlobalScopes()->latest('id')->first();
        $this->assertSame('specific_members', $message->target_criteria);
        $this->assertSame([$this->member->id], $message->recipient_ids);
    }

    public function test_member_cannot_broadcast(): void
    {
        Sanctum::actingAs($this->member);

        $this->postJson('/api/v1/admin/broadcast', [
            'title' => 'Haram',
            'message' => 'x',
            'audience' => 'all',
        ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Tiada kebenaran.');
    }

    public function test_admin_only_enforcement_for_members_endpoint(): void
    {
        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/admin/members')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Tiada kebenaran.');
    }
}

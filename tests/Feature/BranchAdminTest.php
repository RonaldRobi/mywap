<?php

namespace Tests\Feature;

use App\Enums\EventVisibility;
use App\Models\Announcement;
use App\Models\Article;
use App\Models\Branch;
use App\Models\BranchAdmin;
use App\Models\BranchChangeRequest;
use App\Models\BroadcastMessage;
use App\Models\Category;
use App\Models\Event;
use App\Models\Facility;
use App\Models\Form;
use App\Models\Infaq;
use App\Models\LibraryItem;
use App\Models\Organization;
use App\Models\Product;
use App\Models\UsrahGroup;
use App\Models\User;
use App\Models\Video;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Notifications\BranchChangeRequestSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BranchAdminTest extends TestCase
{
    use RefreshDatabase;

    private Organization $abim;

    private Organization $wadah;

    private Branch $perak;

    private Branch $johor;

    private Branch $wadahPerak;

    private User $branchAdmin;

    private User $orgAdmin;

    private User $memberPerak;

    private User $memberJohor;

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
        $this->wadahPerak = Branch::create([
            'organization_id' => $this->wadah->id,
            'name' => 'Perak',
            'state' => 'Perak',
            'is_active' => true,
        ]);

        $this->branchAdmin = User::factory()->create([
            'name' => 'Admin ABIM Perak',
            'email' => 'abim-perak@mywap.my',
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

        $this->orgAdmin = User::factory()->create([
            'name' => 'Admin ABIM',
            'email' => 'admin-abim@mywap.my',
            'current_organization_id' => $this->abim->id,
            'profile_completed_at' => now(),
        ]);
        $this->orgAdmin->assignRole('Admin');

        $this->memberPerak = $this->member('Ahli Perak', $this->abim, $this->perak, '900101015551');
        $this->memberJohor = $this->member('Ahli Johor', $this->abim, $this->johor, '900101015552');
    }

    private function member(string $name, Organization $org, Branch $branch, string $ic): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
            'ic_number' => $ic,
            'current_organization_id' => $org->id,
            'branch_id' => $branch->id,
            'profile_completed_at' => now(),
        ]);
        $user->assignRole('Member');

        return $user;
    }

    private function branchEvent(Branch $branch, string $title, array $overrides = []): Event
    {
        return Event::create(array_merge([
            'organization_id' => $branch->organization_id,
            'branch_id' => $branch->id,
            'visibility' => EventVisibility::BranchOnly->value,
            'title' => $title,
            'type' => 'physical',
            'status' => 'published',
            'category' => 'lain',
            'start_time' => now()->addDays(7),
            'end_time' => now()->addDays(7)->addHours(2),
        ], $overrides));
    }

    public function test_branch_admin_only_sees_events_for_own_branch(): void
    {
        $this->branchEvent($this->perak, 'Program Perak');
        $this->branchEvent($this->johor, 'Program Johor');
        Event::create([
            'organization_id' => $this->abim->id,
            'visibility' => EventVisibility::Open->value,
            'title' => 'Program Organisasi',
            'type' => 'physical',
            'status' => 'published',
            'category' => 'lain',
            'start_time' => now()->addDays(9),
            'end_time' => now()->addDays(9)->addHours(2),
        ]);

        $response = $this->actingAs($this->branchAdmin)->get(route('admin.events.index'));
        $response->assertOk();

        $titles = collect($response->viewData('page')['props']['events']['data'])->pluck('title');

        $this->assertTrue($titles->contains('Program Perak'));
        $this->assertFalse($titles->contains('Program Johor'));
        $this->assertFalse($titles->contains('Program Organisasi'));
    }

    public function test_branch_admin_creating_event_forces_own_org_and_branch(): void
    {
        $this->actingAs($this->branchAdmin)->post(route('admin.events.store'), [
            'title' => 'Program Baharu Perak',
            'description' => 'Ujian',
            'type' => 'physical',
            'status' => 'published',
            'category' => 'lain',
            'location_or_link' => 'Ipoh',
            'start_time' => now()->addDays(3)->toDateTimeString(),
            'end_time' => now()->addDays(3)->addHours(2)->toDateTimeString(),
            'organization_id' => $this->wadah->id,
            'visibility' => EventVisibility::BranchOnly->value,
            'organizations' => [$this->wadah->id],
        ])->assertRedirect();

        $event = Event::where('title', 'Program Baharu Perak')->firstOrFail();

        $this->assertSame($this->abim->id, (int) $event->organization_id);
        $this->assertSame($this->perak->id, (int) $event->branch_id);
        $this->assertSame(EventVisibility::BranchOnly, $event->visibility);
    }

    public function test_branch_admin_cannot_open_org_only_pages(): void
    {
        $this->actingAs($this->branchAdmin)->get(route('admin.news.manage'))->assertForbidden();
    }

    public function test_branch_admin_can_open_allowed_pages(): void
    {
        $this->actingAs($this->branchAdmin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($this->branchAdmin)->get(route('admin.attendance'))->assertOk();
        $this->actingAs($this->branchAdmin)->get(route('admin.fees.members'))->assertOk();
        $this->actingAs($this->branchAdmin)->get(route('admin.hub.manage'))->assertOk();
        $this->actingAs($this->branchAdmin)->get(route('admin.branch-report.index'))->assertOk();
    }

    public function test_fee_page_only_lists_branch_members(): void
    {
        $response = $this->actingAs($this->branchAdmin)->get(route('admin.fees.members'));
        $response->assertOk();

        $names = collect($response->viewData('page')['props']['members']['data'])->pluck('name');

        $this->assertTrue($names->contains('Ahli Perak'));
        $this->assertFalse($names->contains('Ahli Johor'));
    }

    public function test_org_admin_can_create_branch_manager_account_only_once(): void
    {
        $payload = [
            'name' => 'Admin Baharu Perak',
            'email' => 'admin-baharu-perak@mywap.my',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $this->actingAs($this->orgAdmin)
            ->post(route('branches.admin-account.store', $this->johor), $payload)
            ->assertRedirect()
            ->assertSessionHas('success');

        $manager = User::withoutGlobalScopes()->where('email', 'admin-baharu-perak@mywap.my')->firstOrFail();
        $this->assertTrue($manager->hasRole('Admin Cawangan'));
        $this->assertSame($this->johor->id, (int) $manager->branch_id);
        $this->assertDatabaseHas('branch_admins', [
            'branch_id' => $this->johor->id,
            'user_id' => $manager->id,
            'role' => BranchAdmin::ROLE_MANAGER,
        ]);

        $this->actingAs($this->orgAdmin)
            ->post(route('branches.admin-account.store', $this->johor), [
                ...$payload,
                'email' => 'admin-kedua@mywap.my',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_branch_admin_cannot_create_manager_account_for_another_branch(): void
    {
        $this->actingAs($this->branchAdmin)
            ->post(route('branches.admin-account.store', $this->johor), [
                'name' => 'Sesiapa',
                'email' => 'sesiapa@mywap.my',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertForbidden();
    }

    public function test_org_admin_can_appoint_max_three_secretaries(): void
    {
        $members = collect(range(1, 4))->map(function (int $index) {
            $member = $this->member(
                'Setiausaha '.$index,
                $this->abim,
                $this->perak,
                '91010101000'.$index,
            );

            return $member;
        });

        foreach ($members->take(3) as $member) {
            $this->actingAs($this->orgAdmin)
                ->post(route('branches.secretaries.store', $this->perak), ['user_id' => $member->id])
                ->assertRedirect()
                ->assertSessionHas('success');
        }

        $this->assertSame(3, BranchAdmin::where('branch_id', $this->perak->id)
            ->where('role', BranchAdmin::ROLE_SETIAUSAHA)
            ->count());

        $this->actingAs($this->orgAdmin)
            ->post(route('branches.secretaries.store', $this->perak), ['user_id' => $members->last()->id])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(3, BranchAdmin::where('branch_id', $this->perak->id)
            ->where('role', BranchAdmin::ROLE_SETIAUSAHA)
            ->count());
    }

    public function test_branch_change_request_notifies_destination_secretaries_only(): void
    {
        Notification::fake();

        $destinationSecretary = $this->member('Setiausaha Johor', $this->abim, $this->johor, '920101015553');
        BranchAdmin::create([
            'branch_id' => $this->johor->id,
            'user_id' => $destinationSecretary->id,
            'role' => BranchAdmin::ROLE_SETIAUSAHA,
        ]);

        $sourceSecretary = $this->member('Setiausaha Perak', $this->abim, $this->perak, '920101015554');
        BranchAdmin::create([
            'branch_id' => $this->perak->id,
            'user_id' => $sourceSecretary->id,
            'role' => BranchAdmin::ROLE_SETIAUSAHA,
        ]);

        $this->actingAs($this->memberPerak)
            ->post(route('profile.branch-change'), ['branch_id' => $this->johor->id])
            ->assertRedirect(route('profile.edit'));

        Notification::assertSentTo($destinationSecretary, BranchChangeRequestSubmitted::class);
        Notification::assertNotSentTo($sourceSecretary, BranchChangeRequestSubmitted::class);

        $this->assertDatabaseHas('branch_change_requests', [
            'user_id' => $this->memberPerak->id,
            'to_branch_id' => $this->johor->id,
            'status' => 'pending',
        ]);
    }

    public function test_branch_only_event_registration_rejects_other_branch_and_guest(): void
    {
        $event = $this->branchEvent($this->perak, 'Program Khusus Perak', [
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(2),
        ]);

        $form = Form::create([
            'organization_id' => $this->abim->id,
            'event_id' => $event->id,
            'title' => 'Borang Program Perak',
            'is_active' => true,
        ]);

        $this->actingAs($this->memberPerak)
            ->get(route('events.register', ['event' => $event->slug, 'form' => $form->id]))
            ->assertOk();

        $this->actingAs($this->memberJohor)
            ->get(route('events.register', ['event' => $event->slug, 'form' => $form->id]))
            ->assertForbidden();

        $this->get(route('events.register.public', $form->share_token))->assertForbidden();
    }

    public function test_branch_admin_can_only_add_participants_from_own_branch(): void
    {
        $event = $this->branchEvent($this->perak, 'Program Kehadiran Perak');

        $this->actingAs($this->branchAdmin)
            ->post(route('admin.attendance.participants.store', $event), [
                'name' => 'Ahli Johor',
                'ic_number' => $this->memberJohor->ic_number,
                'mark_present' => true,
            ])
            ->assertSessionHasErrors('ic_number');

        $this->actingAs($this->branchAdmin)
            ->post(route('admin.attendance.participants.store', $event), [
                'name' => 'Ahli Perak',
                'ic_number' => $this->memberPerak->ic_number,
                'mark_present' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_member_search_is_scoped_to_managed_branch(): void
    {
        $response = $this->actingAs($this->branchAdmin)
            ->getJson('/api/members/search?q=Ahli');

        $response->assertOk();
        $names = collect($response->json())->pluck('name');

        $this->assertTrue($names->contains('Ahli Perak'));
        $this->assertFalse($names->contains('Ahli Johor'));
    }

    public function test_org_admin_sees_full_member_ic_number(): void
    {
        $response = $this->actingAs($this->orgAdmin)
            ->get('/admin/information-hub/manage');

        $response->assertOk();

        $members = collect($response->viewData('page')['props']['members']['data'] ?? []);
        $perak = $members->firstWhere('id', $this->memberPerak->id);

        $this->assertSame('900101015551', $perak['ic_number']);
    }

    public function test_branch_admin_sees_full_member_ic_number(): void
    {
        $response = $this->actingAs($this->branchAdmin)
            ->get('/admin/information-hub/manage');

        $response->assertOk();

        $members = collect($response->viewData('page')['props']['members']['data'] ?? []);
        $perak = $members->firstWhere('id', $this->memberPerak->id);

        $this->assertSame('900101015551', $perak['ic_number']);
    }

    public function test_branch_report_uses_managers_branch(): void
    {
        $response = $this->actingAs($this->branchAdmin)->get(route('admin.branch-report.index'));
        $response->assertOk();

        $page = $response->viewData('page')['props'];

        $this->assertSame($this->perak->id, $page['branch']['id']);
    }

    public function test_branch_admin_can_create_registration_form_for_own_branch_event(): void
    {
        $event = $this->branchEvent($this->perak, 'Program Borang Perak');

        $this->actingAs($this->branchAdmin)->post(route('admin.forms.store'), [
            'title' => 'Borang Program Perak',
            'event_id' => $event->id,
            'is_active' => true,
            'allow_public' => true,
            'questions' => [
                ['label' => 'Nama penuh', 'type' => 'text', 'required' => true],
            ],
        ])->assertRedirect();

        $form = Form::where('title', 'Borang Program Perak')->firstOrFail();

        $this->assertSame($event->id, (int) $form->event_id);
        $this->assertSame($this->abim->id, (int) $form->organization_id);
    }

    public function test_branch_admin_cannot_create_form_for_other_branch_event(): void
    {
        $event = $this->branchEvent($this->johor, 'Program Borang Johor');

        $this->actingAs($this->branchAdmin)->post(route('admin.forms.store'), [
            'title' => 'Borang Program Johor',
            'event_id' => $event->id,
            'is_active' => true,
            'questions' => [
                ['label' => 'Nama penuh', 'type' => 'text', 'required' => true],
            ],
        ])->assertForbidden();
    }

    public function test_form_list_only_shows_forms_for_own_branch_events(): void
    {
        $perakEvent = $this->branchEvent($this->perak, 'Program Borang Perak');
        $johorEvent = $this->branchEvent($this->johor, 'Program Borang Johor');

        Form::create([
            'organization_id' => $this->abim->id,
            'event_id' => $perakEvent->id,
            'title' => 'Borang Perak',
            'is_active' => true,
        ]);
        Form::create([
            'organization_id' => $this->abim->id,
            'event_id' => $johorEvent->id,
            'title' => 'Borang Johor',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->branchAdmin)->get(route('admin.forms.index'));
        $response->assertOk();

        $titles = collect($response->viewData('page')['props']['forms']['data'])->pluck('title');

        $this->assertTrue($titles->contains('Borang Perak'));
        $this->assertFalse($titles->contains('Borang Johor'));
    }

    public function test_branch_admin_without_branch_is_redirected_to_unassigned_page(): void
    {
        $unassigned = User::factory()->create([
            'current_organization_id' => $this->abim->id,
            'branch_id' => null,
            'profile_completed_at' => now(),
        ]);
        $unassigned->assignRole('Admin Cawangan');

        $this->actingAs($unassigned)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('branch-admin.unassigned'));
    }

    public function test_org_admin_can_assign_unassigned_branch_admin_account(): void
    {
        $unassigned = User::factory()->create([
            'name' => 'Admin Lama',
            'email' => 'admin-lama@mywap.my',
            'current_organization_id' => $this->abim->id,
            'branch_id' => null,
            'profile_completed_at' => now(),
        ]);
        $unassigned->assignRole('Admin Cawangan');

        $this->actingAs($this->orgAdmin)
            ->post(route('branches.admin-account.assign', $this->johor), ['user_id' => $unassigned->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame($this->johor->id, (int) $unassigned->fresh()->branch_id);
        $this->assertDatabaseHas('branch_admins', [
            'branch_id' => $this->johor->id,
            'user_id' => $unassigned->id,
            'role' => BranchAdmin::ROLE_MANAGER,
        ]);
    }

    public function test_branch_admin_can_reset_password_for_own_branch_member_only(): void
    {
        Notification::fake();

        $this->actingAs($this->branchAdmin)
            ->post(route('admin.hub.members.reset-password', $this->memberPerak))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($this->branchAdmin)
            ->post(route('admin.hub.members.reset-password', $this->memberJohor))
            ->assertForbidden();
    }

    public function test_branch_admin_can_access_usrah_facilities_and_broadcasts_for_own_branch(): void
    {
        UsrahGroup::create([
            'organization_id' => $this->abim->id,
            'branch_id' => $this->perak->id,
            'name' => 'Usrah Perak',
        ]);
        UsrahGroup::create([
            'organization_id' => $this->abim->id,
            'branch_id' => $this->johor->id,
            'name' => 'Usrah Johor',
        ]);

        Facility::create([
            'organization_id' => $this->abim->id,
            'branch_id' => $this->perak->id,
            'name' => 'Dewan Perak',
            'type' => 'daily',
            'price_per_unit' => 100,
            'is_active' => true,
        ]);
        Facility::create([
            'organization_id' => $this->abim->id,
            'branch_id' => $this->johor->id,
            'name' => 'Dewan Johor',
            'type' => 'daily',
            'price_per_unit' => 100,
            'is_active' => true,
        ]);

        BroadcastMessage::create([
            'organization_id' => $this->abim->id,
            'branch_id' => $this->perak->id,
            'target_organization_id' => $this->abim->id,
            'title' => 'Siaran Perak',
            'content' => 'Ujian',
            'target_criteria' => 'branch',
            'notification_channels' => ['in_app'],
        ]);
        BroadcastMessage::create([
            'organization_id' => $this->abim->id,
            'branch_id' => $this->johor->id,
            'target_organization_id' => $this->abim->id,
            'title' => 'Siaran Johor',
            'content' => 'Ujian',
            'target_criteria' => 'branch',
            'notification_channels' => ['in_app'],
        ]);

        $usrah = $this->actingAs($this->branchAdmin)->get(route('admin.usrah.index'));
        $usrah->assertOk();
        $groupNames = collect($usrah->viewData('page')['props']['groups'])->pluck('name');
        $this->assertTrue($groupNames->contains('Usrah Perak'));
        $this->assertFalse($groupNames->contains('Usrah Johor'));

        $facilities = $this->actingAs($this->branchAdmin)->get(route('admin.facilities.manage'));
        $facilities->assertOk();
        $facilityNames = collect($facilities->viewData('page')['props']['facilities'])->pluck('name');
        $this->assertTrue($facilityNames->contains('Dewan Perak'));
        $this->assertFalse($facilityNames->contains('Dewan Johor'));

        $broadcasts = $this->actingAs($this->branchAdmin)->get(route('admin.broadcasts.index'));
        $broadcasts->assertOk();
        $broadcastTitles = collect($broadcasts->viewData('page')['props']['recentMessages'])->pluck('title');
        $this->assertTrue($broadcastTitles->contains('Siaran Perak'));
        $this->assertFalse($broadcastTitles->contains('Siaran Johor'));
    }

    public function test_branch_admin_broadcast_is_forced_to_own_branch(): void
    {
        Queue::fake();

        $this->actingAs($this->branchAdmin)->post(route('admin.broadcasts.store'), [
            'title' => 'Siaran Cawangan',
            'content' => 'Mesej cawangan',
            'target_criteria' => 'all',
            'notification_channels' => ['in_app'],
        ])->assertRedirect();

        $message = BroadcastMessage::latest('id')->firstOrFail();

        $this->assertSame('branch', $message->target_criteria);
        $this->assertSame($this->perak->id, (int) $message->branch_id);
        $this->assertSame($this->abim->id, (int) $message->target_organization_id);
    }

    public function test_branch_admin_cannot_generate_or_import_fees(): void
    {
        $this->actingAs($this->branchAdmin)
            ->post(route('admin.fees.members.generate'))
            ->assertForbidden();

        $this->actingAs($this->branchAdmin)
            ->post(route('admin.fees.members.import.preview'))
            ->assertForbidden();
    }

    public function test_audit_log_is_superadmin_only(): void
    {
        $superadmin = User::factory()->create([
            'current_organization_id' => $this->abim->id,
            'profile_completed_at' => now(),
        ]);
        $superadmin->assignRole('Superadmin');

        $this->actingAs($superadmin)->get(route('superadmin.audit-log.index'))->assertOk();
        $this->actingAs($this->orgAdmin)->get(route('superadmin.audit-log.index'))->assertForbidden();
        $this->actingAs($this->branchAdmin)->get(route('superadmin.audit-log.index'))->assertForbidden();
    }

    public function test_branch_report_includes_activity_metrics(): void
    {
        $response = $this->actingAs($this->branchAdmin)->get(route('admin.branch-report.index'));
        $response->assertOk();

        $activity = $response->viewData('page')['props']['activity'];

        $this->assertCount(3, $activity);
        $this->assertSame('registered_program', $activity[0]['key']);
    }

    public function test_branch_admin_can_create_article_scoped_to_branch(): void
    {
        $this->actingAs($this->branchAdmin)->post(route('admin.articles.store'), [
            'title' => 'Artikel Cawangan Perak',
            'content' => 'Kandungan artikel.',
            'is_published' => true,
        ])->assertRedirect();

        $article = Article::where('title', 'Artikel Cawangan Perak')->firstOrFail();

        $this->assertSame($this->perak->id, (int) $article->branch_id);
        $this->assertSame($this->abim->id, (int) $article->organization_id);
    }

    public function test_branch_admin_can_create_video_scoped_to_branch(): void
    {
        $this->actingAs($this->branchAdmin)->post(route('admin.videos.store'), [
            'title' => 'Video Cawangan Perak',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ])->assertRedirect();

        $video = Video::where('title', 'Video Cawangan Perak')->firstOrFail();

        $this->assertSame($this->perak->id, (int) $video->branch_id);
    }

    public function test_branch_admin_can_upload_library_item_scoped_to_branch(): void
    {
        Storage::fake('public');

        $this->actingAs($this->branchAdmin)->post(route('admin.hub.library.store'), [
            'title' => 'Ebook Cawangan Perak',
            'pdf_file' => UploadedFile::fake()->create('ebook.pdf', 120, 'application/pdf'),
        ])->assertRedirect();

        $item = LibraryItem::where('title', 'Ebook Cawangan Perak')->firstOrFail();

        $this->assertSame($this->perak->id, (int) $item->branch_id);
    }

    public function test_branch_admin_can_create_announcement_scoped_to_branch(): void
    {
        Queue::fake();

        $this->actingAs($this->branchAdmin)->post(route('admin.hub.announcements.store'), [
            'title' => 'Pengumuman Cawangan Perak',
            'content' => 'Makluman cawangan.',
            'target_criteria' => 'all',
        ])->assertRedirect();

        $announcement = Announcement::where('title', 'Pengumuman Cawangan Perak')->firstOrFail();

        $this->assertSame($this->perak->id, (int) $announcement->branch_id);
        $this->assertSame($this->abim->id, (int) $announcement->organization_id);
    }

    public function test_branch_admin_can_create_infaq_scoped_to_branch(): void
    {
        $this->actingAs($this->branchAdmin)->post(route('superadmin.infaq.store'), [
            'title' => 'Infaq Cawangan Perak',
            'type' => 'one_off',
            'is_active' => true,
        ])->assertRedirect();

        $infaq = Infaq::where('title', 'Infaq Cawangan Perak')->firstOrFail();

        $this->assertSame($this->perak->id, (int) $infaq->branch_id);
        $this->assertSame($this->abim->id, (int) $infaq->organization_id);
    }

    public function test_branch_admin_can_create_product_scoped_to_branch(): void
    {
        $category = Category::create([
            'name' => 'Kategori ABIM',
            'organization_id' => $this->abim->id,
        ]);

        $this->actingAs($this->branchAdmin)->post(route('products.store'), [
            'name' => 'Produk Cawangan Perak',
            'price' => 25,
            'stock' => 10,
            'category_id' => $category->id,
            'status' => true,
        ])->assertRedirect();

        $product = Product::where('name', 'Produk Cawangan Perak')->firstOrFail();

        $this->assertSame($this->perak->id, (int) $product->branch_id);
        $this->assertSame($this->abim->id, (int) $product->organisasi_id);
    }

    public function test_branch_admin_can_create_standalone_branch_form(): void
    {
        $this->actingAs($this->branchAdmin)->post(route('admin.forms.store'), [
            'title' => 'Borang Cawangan Perak',
            'is_active' => true,
            'allow_public' => true,
            'questions' => [
                ['label' => 'Nama penuh', 'type' => 'text', 'required' => true],
            ],
        ])->assertRedirect();

        $form = Form::where('title', 'Borang Cawangan Perak')->firstOrFail();

        $this->assertNull($form->event_id);
        $this->assertSame($this->perak->id, (int) $form->branch_id);
        $this->assertSame($this->abim->id, (int) $form->organization_id);
    }

    public function test_org_admin_can_attach_existing_member_as_branch_admin(): void
    {
        $this->assertFalse($this->memberJohor->hasRole('Admin Cawangan'));

        $this->actingAs($this->orgAdmin)
            ->post(route('branches.admin-account.assign', $this->johor), ['user_id' => $this->memberJohor->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue($this->memberJohor->fresh()->hasRole('Admin Cawangan'));
        $this->assertDatabaseHas('branch_admins', [
            'branch_id' => $this->johor->id,
            'user_id' => $this->memberJohor->id,
            'role' => BranchAdmin::ROLE_MANAGER,
        ]);
        $this->assertSame($this->johor->id, (int) $this->memberJohor->fresh()->branch_id);
    }

    public function test_existing_admin_account_can_be_assigned_to_additional_branches(): void
    {
        $batuBerendam = Branch::create([
            'organization_id' => $this->abim->id,
            'name' => 'Batu Berendam',
            'state' => 'Melaka',
            'is_active' => true,
        ]);
        $telokMas = Branch::create([
            'organization_id' => $this->abim->id,
            'name' => 'Telok Mas',
            'state' => 'Melaka',
            'is_active' => true,
        ]);

        $this->actingAs($this->orgAdmin)
            ->put(route('branches.admin-account.branches', $this->perak), [
                'branch_ids' => [$batuBerendam->id, $telokMas->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertEqualsCanonicalizing(
            [$this->perak->id, $batuBerendam->id, $telokMas->id],
            $this->branchAdmin->fresh()->managedBranchIds()
        );
        $this->assertDatabaseHas('branch_admins', [
            'branch_id' => $batuBerendam->id,
            'user_id' => $this->branchAdmin->id,
            'role' => BranchAdmin::ROLE_MANAGER,
        ]);
        $this->assertDatabaseHas('branch_admins', [
            'branch_id' => $telokMas->id,
            'user_id' => $this->branchAdmin->id,
            'role' => BranchAdmin::ROLE_MANAGER,
        ]);
    }

    public function test_branch_admin_with_multiple_branches_sees_members_and_events_from_all(): void
    {
        $batuBerendam = Branch::create([
            'organization_id' => $this->abim->id,
            'name' => 'Batu Berendam',
            'state' => 'Melaka',
            'is_active' => true,
        ]);
        BranchAdmin::create([
            'branch_id' => $batuBerendam->id,
            'user_id' => $this->branchAdmin->id,
            'role' => BranchAdmin::ROLE_MANAGER,
        ]);

        $memberBatu = $this->member('Ahli Batu Berendam', $this->abim, $batuBerendam, '900101015599');
        $this->branchEvent($batuBerendam, 'Program Batu Berendam');

        $this->assertTrue($this->branchAdmin->fresh()->canManageBranch($this->perak->id));
        $this->assertTrue($this->branchAdmin->fresh()->canManageBranch($batuBerendam->id));
        $this->assertFalse($this->branchAdmin->fresh()->canManageBranch($this->johor->id));

        $membersResponse = $this->actingAs($this->branchAdmin)->get(route('admin.fees.members'));
        $membersResponse->assertOk();
        $names = collect($membersResponse->viewData('page')['props']['members']['data'])->pluck('name');
        $this->assertTrue($names->contains('Ahli Perak'));
        $this->assertTrue($names->contains($memberBatu->name));

        $eventsResponse = $this->actingAs($this->branchAdmin)->get(route('admin.events.index'));
        $eventsResponse->assertOk();
        $titles = collect($eventsResponse->viewData('page')['props']['events']['data'])->pluck('title');
        $this->assertTrue($titles->contains('Program Batu Berendam'));
    }

    public function test_sync_admin_branches_skips_branch_owned_by_another_admin(): void
    {
        $occupied = Branch::create([
            'organization_id' => $this->abim->id,
            'name' => 'Telok Mas',
            'state' => 'Melaka',
            'is_active' => true,
        ]);
        $otherAdmin = $this->member('Admin Telok Mas', $this->abim, $occupied, '900101015598');
        $otherAdmin->assignRole('Admin Cawangan');
        BranchAdmin::create([
            'branch_id' => $occupied->id,
            'user_id' => $otherAdmin->id,
            'role' => BranchAdmin::ROLE_MANAGER,
        ]);

        $this->actingAs($this->orgAdmin)
            ->put(route('branches.admin-account.branches', $this->perak), [
                'branch_ids' => [$occupied->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('branch_admins', [
            'branch_id' => $occupied->id,
            'user_id' => $this->branchAdmin->id,
        ]);
        $this->assertEqualsCanonicalizing(
            [$this->perak->id],
            $this->branchAdmin->fresh()->managedBranchIds()
        );
    }

    public function test_branch_admin_can_access_content_management_pages(): void
    {
        $this->actingAs($this->branchAdmin)->get(route('admin.articles.index'))->assertOk();
        $this->actingAs($this->branchAdmin)->get(route('admin.videos.manage'))->assertOk();
        $this->actingAs($this->branchAdmin)->get(route('admin.library.manage'))->assertOk();
        $this->actingAs($this->branchAdmin)->get(route('superadmin.infaq.index'))->assertOk();
        $this->actingAs($this->branchAdmin)->get(route('admin.donors.index'))->assertOk();
        $this->actingAs($this->branchAdmin)->get(route('admin.broadcasts.index'))->assertOk();
        $this->actingAs($this->branchAdmin)->get(route('products.index'))->assertOk();
    }
}

<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\UsrahGroup;
use App\Models\UsrahAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Spatie\Permission\Models\Role;

class UsrahTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $admin;
    private User $member;
    private User $naqibUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $this->org = Organization::factory()->create(['fee_amount' => 50.00]);

        $this->admin = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
        ]);
        $this->admin->assignRole('Admin');

        $this->member = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
        ]);
        $this->member->assignRole('Member');

        $this->naqibUser = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
        ]);
        $this->naqibUser->assignRole('Member');
    }

    // ─── Group CRUD ──────────────────────────────────────────────────────────────

    public function test_admin_can_create_group(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.usrah.groups.store'), [
            'name' => 'Usrah Al-Falah',
            'description' => 'Kumpulan usrah mingguan',
            'meeting_day' => 'Jumaat',
            'meeting_time' => '20:00',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('usrah_groups', [
            'name' => 'Usrah Al-Falah',
            'organization_id' => $this->org->id,
        ]);
    }

    public function test_member_cannot_create_group(): void
    {
        $this->actingAs($this->member);

        $response = $this->post(route('admin.usrah.groups.store'), [
            'name' => 'Usrah Haram',
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_update_group(): void
    {
        $this->actingAs($this->admin);
        $group = UsrahGroup::factory()->create([
            'organization_id' => $this->org->id,
            'name' => 'Usrah Lama',
        ]);

        $response = $this->put(route('admin.usrah.groups.update', $group), [
            'name' => 'Usrah Baru',
            'meeting_day' => 'Sabtu',
            'meeting_time' => '09:00',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('usrah_groups', [
            'id' => $group->id,
            'name' => 'Usrah Baru',
            'meeting_day' => 'Sabtu',
        ]);
    }

    public function test_admin_can_delete_group(): void
    {
        $this->actingAs($this->admin);
        $group = UsrahGroup::factory()->create([
            'organization_id' => $this->org->id,
        ]);

        $response = $this->delete(route('admin.usrah.groups.delete', $group));

        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('usrah_groups', ['id' => $group->id]);
    }

    // ─── Assign Members ──────────────────────────────────────────────────────────

    public function test_admin_can_assign_members_to_group(): void
    {
        $this->actingAs($this->admin);
        $group = UsrahGroup::factory()->create([
            'organization_id' => $this->org->id,
        ]);

        $response = $this->post(route('admin.usrah.groups.assign', $group), [
            'members' => [
                ['user_id' => $this->member->id, 'role' => 'member'],
                ['user_id' => $this->naqibUser->id, 'role' => 'leader'],
            ],
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('usrah_group_user', [
            'usrah_group_id' => $group->id,
            'user_id' => $this->member->id,
            'role' => 'member',
        ]);
        $this->assertDatabaseHas('usrah_group_user', [
            'usrah_group_id' => $group->id,
            'user_id' => $this->naqibUser->id,
            'role' => 'leader',
        ]);
    }

    public function test_assign_allows_multiple_leaders(): void
    {
        $this->actingAs($this->admin);
        $group = UsrahGroup::factory()->create([
            'organization_id' => $this->org->id,
        ]);
        $member2 = User::factory()->create(['current_organization_id' => $this->org->id]);

        $response = $this->post(route('admin.usrah.groups.assign', $group), [
            'members' => [
                ['user_id' => $this->member->id, 'role' => 'leader'],
                ['user_id' => $member2->id, 'role' => 'sub_leader'],
            ],
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('usrah_group_user', [
            'usrah_group_id' => $group->id,
            'user_id' => $this->member->id,
            'role' => 'leader',
        ]);
        $this->assertDatabaseHas('usrah_group_user', [
            'usrah_group_id' => $group->id,
            'user_id' => $member2->id,
            'role' => 'sub_leader',
        ]);
    }

    // ─── Attendance ──────────────────────────────────────────────────────────────

    public function test_leader_can_log_attendance(): void
    {
        $this->actingAs($this->admin);
        $group = UsrahGroup::factory()->create([
            'organization_id' => $this->org->id,
        ]);
        $group->members()->attach([
            $this->naqibUser->id => ['role' => 'leader', 'is_naqib' => true, 'joined_at' => now()],
            $this->member->id => ['role' => 'member', 'is_naqib' => false, 'joined_at' => now()],
        ]);

        $this->actingAs($this->naqibUser);

        $response = $this->post(route('member.usrah.attendance.log', $group), [
            'session_date' => now()->format('Y-m-d'),
            'attendances' => [
                ['user_id' => $this->naqibUser->id, 'status' => 'hadir'],
                ['user_id' => $this->member->id, 'status' => 'tidak_hadir'],
            ],
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('usrah_attendances', [
            'usrah_group_id' => $group->id,
            'user_id' => $this->naqibUser->id,
            'status' => 'hadir',
        ]);
        $this->assertDatabaseHas('usrah_attendances', [
            'usrah_group_id' => $group->id,
            'user_id' => $this->member->id,
            'status' => 'tidak_hadir',
        ]);
    }

    public function test_sub_leader_can_log_attendance(): void
    {
        $this->actingAs($this->admin);
        $group = UsrahGroup::factory()->create([
            'organization_id' => $this->org->id,
        ]);
        $group->members()->attach($this->naqibUser->id, ['role' => 'sub_leader', 'is_naqib' => false, 'joined_at' => now()]);

        $this->actingAs($this->naqibUser);

        $response = $this->post(route('member.usrah.attendance.log', $group), [
            'session_date' => now()->format('Y-m-d'),
            'attendances' => [
                ['user_id' => $this->naqibUser->id, 'status' => 'hadir'],
            ],
        ]);

        $response->assertSessionHas('success');
    }

    public function test_non_leader_cannot_log_attendance(): void
    {
        $this->actingAs($this->admin);
        $group = UsrahGroup::factory()->create([
            'organization_id' => $this->org->id,
        ]);
        $group->members()->attach($this->member->id, ['role' => 'member', 'is_naqib' => false, 'joined_at' => now()]);

        $this->actingAs($this->member);

        $response = $this->post(route('member.usrah.attendance.log', $group), [
            'session_date' => now()->format('Y-m-d'),
            'attendances' => [
                ['user_id' => $this->member->id, 'status' => 'hadir'],
            ],
        ]);

        $response->assertForbidden();
    }

    // ─── My Group ────────────────────────────────────────────────────────────────

    public function test_member_can_view_own_groups(): void
    {
        $this->actingAs($this->admin);
        $group = UsrahGroup::factory()->create([
            'organization_id' => $this->org->id,
        ]);
        $group->members()->attach($this->member->id, ['role' => 'member', 'is_naqib' => false, 'joined_at' => now()]);

        $this->actingAs($this->member);
        $response = $this->get(route('member.usrah'));

        $response->assertInertia(fn ($page) => $page
            ->component('Usrah/MyGroup')
            ->has('groups', 1)
        );
    }

    public function test_member_can_view_multiple_groups(): void
    {
        $this->actingAs($this->admin);
        $group1 = UsrahGroup::factory()->create([
            'organization_id' => $this->org->id,
            'name' => 'Group A',
        ]);
        $group2 = UsrahGroup::factory()->create([
            'organization_id' => $this->org->id,
            'name' => 'Group B',
        ]);
        $group1->members()->attach($this->member->id, ['role' => 'member', 'is_naqib' => false, 'joined_at' => now()]);
        $group2->members()->attach($this->member->id, ['role' => 'sub_leader', 'is_naqib' => false, 'joined_at' => now()]);

        $this->actingAs($this->member);
        $response = $this->get(route('member.usrah'));

        $response->assertInertia(fn ($page) => $page
            ->component('Usrah/MyGroup')
            ->has('groups', 2)
        );
    }

    public function test_member_without_group_sees_empty_state(): void
    {
        $this->actingAs($this->member);
        $response = $this->get(route('member.usrah'));

        $response->assertInertia(fn ($page) => $page
            ->component('Usrah/MyGroup')
            ->has('groups', 0)
        );
    }
}

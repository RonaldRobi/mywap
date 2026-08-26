<?php

namespace Tests\Feature\Api\V1;

use App\Models\Organization;
use App\Models\User;
use App\Models\UsrahAttendance;
use App\Models\UsrahGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UsrahApiTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $member;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $this->org = Organization::factory()->create(['name' => 'PKPIM']);

        $this->member = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
            'member_no' => 'PKPIM-0001',
        ]);
        $this->member->assignRole('Member');

        $this->admin = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
        ]);
        $this->admin->assignRole('Admin');
    }

    public function test_member_can_list_own_usrah_groups(): void
    {
        $group = UsrahGroup::factory()->create([
            'organization_id' => $this->org->id,
            'name' => 'Usrah Al-Falah',
        ]);
        $group->members()->attach($this->member->id, ['role' => 'member', 'is_naqib' => false, 'joined_at' => now()]);

        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/usrah')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['groups', 'attendanceHistory'],
            ])
            ->assertJsonPath('data.groups.0.id', $group->id)
            ->assertJsonPath('data.groups.0.name', 'Usrah Al-Falah')
            ->assertJsonPath('data.groups.0.is_leader', false)
            ->assertJsonPath('data.groups.0.members.0.id', $this->member->id)
            ->assertJsonPath('data.groups.0.members.0.role', 'member')
            ->assertJsonCount(1, 'data.groups');
    }

    public function test_member_without_group_sees_empty_state(): void
    {
        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/usrah')
            ->assertOk()
            ->assertJsonCount(0, 'data.groups')
            ->assertJsonCount(0, 'data.attendanceHistory');
    }

    public function test_member_sees_attendance_history(): void
    {
        $group = UsrahGroup::factory()->create([
            'organization_id' => $this->org->id,
        ]);
        $group->members()->attach($this->member->id, ['role' => 'member', 'is_naqib' => false, 'joined_at' => now()]);

        UsrahAttendance::create([
            'usrah_group_id' => $group->id,
            'user_id' => $this->member->id,
            'session_date' => now()->subWeek(),
            'status' => 'hadir',
            'created_by' => $this->member->id,
        ]);

        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/usrah')
            ->assertOk()
            ->assertJsonPath('data.attendanceHistory.0.status', 'hadir')
            ->assertJsonPath('data.attendanceHistory.0.date', now()->subWeek()->format('Y-m-d'));
    }

    public function test_member_does_not_see_groups_of_other_org(): void
    {
        $otherOrg = Organization::factory()->create(['name' => 'ABIM']);
        $group = UsrahGroup::factory()->create([
            'organization_id' => $otherOrg->id,
            'name' => 'Usrah Asing',
        ]);
        $group->members()->attach($this->member->id, ['role' => 'member', 'is_naqib' => false, 'joined_at' => now()]);

        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/usrah')
            ->assertOk()
            ->assertJsonCount(0, 'data.groups');
    }
}

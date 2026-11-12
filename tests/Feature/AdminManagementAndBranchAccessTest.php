<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchAdmin;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminManagementAndBranchAccessTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private Branch $perak;

    private Branch $johor;

    private User $member;

    private User $orgAdmin;

    private User $branchAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        foreach (['Superadmin', 'Admin', 'Admin Cawangan', 'Member'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }

        $this->org = Organization::factory()->create(['name' => 'ABIM', 'slug' => 'abim']);

        $this->perak = Branch::create([
            'organization_id' => $this->org->id,
            'name' => 'PERAK',
            'state' => 'Perak',
            'is_active' => true,
        ]);
        $this->johor = Branch::create([
            'organization_id' => $this->org->id,
            'name' => 'JOHOR',
            'state' => 'Johor',
            'is_active' => true,
        ]);

        $this->member = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'branch_id' => $this->perak->id,
            'profile_completed_at' => now(),
        ]);
        $this->member->assignRole('Member');

        $this->orgAdmin = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
        ]);
        $this->orgAdmin->assignRole('Admin');

        $this->branchAdmin = User::factory()->create([
            'current_organization_id' => $this->org->id,
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

    public function test_regular_member_cannot_access_member_and_branch_management(): void
    {
        $this->actingAs($this->member)->get(route('admin.hub.manage'))->assertForbidden();
        $this->actingAs($this->member)->get(route('branches.index'))->assertForbidden();
        $this->actingAs($this->member)->get(route('admin.admins.index'))->assertForbidden();
    }

    public function test_org_admin_can_open_admin_management_and_appoint_admin(): void
    {
        $this->actingAs($this->orgAdmin)->get(route('admin.admins.index'))->assertOk();

        $this->actingAs($this->orgAdmin)
            ->patch(route('admin.hub.members.role.update', $this->member), ['role' => 'Admin'])
            ->assertRedirect();

        $this->assertTrue($this->member->fresh()->hasRole('Admin'));
    }

    public function test_branch_admin_can_open_branch_page_but_not_admin_management(): void
    {
        $response = $this->actingAs($this->branchAdmin)->get(route('branches.index'));
        $response->assertOk();

        $organizations = $response->viewData('page')['props']['organizations'];
        $this->assertCount(1, $organizations);
        $this->assertCount(1, $organizations[0]['branches']);
        $this->assertSame($this->perak->id, $organizations[0]['branches'][0]['id']);

        $this->actingAs($this->branchAdmin)->get(route('admin.admins.index'))->assertForbidden();
        $this->actingAs($this->branchAdmin)
            ->patch(route('admin.hub.members.role.update', $this->member), ['role' => 'Admin'])
            ->assertForbidden();
    }

    public function test_org_admin_can_open_all_branches_of_own_organization(): void
    {
        $response = $this->actingAs($this->orgAdmin)->get(route('branches.index'));
        $response->assertOk();

        $branches = $response->viewData('page')['props']['organizations'][0]['branches'];
        $this->assertCount(2, $branches);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchAdmin;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
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

    public function test_org_admin_can_open_admin_management(): void
    {
        $this->actingAs($this->orgAdmin)->get(route('admin.admins.index'))->assertOk();
    }

    public function test_superadmin_can_create_org_admin(): void
    {
        $superadmin = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
        ]);
        $superadmin->assignRole('Superadmin');

        $this->actingAs($superadmin)
            ->post(route('admin.admins.store'), [
                'name' => 'Admin Baharu',
                'email' => 'admin.baharu@abim.org.my',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'organization_id' => $this->org->id,
            ])
            ->assertRedirect();

        $admin = User::where('email', 'admin.baharu@abim.org.my')->firstOrFail();

        $this->assertTrue($admin->hasRole('Admin'));
        $this->assertNull($admin->member_no);
        $this->assertFalse((bool) $admin->is_public_in_directory);
        $this->assertNotNull($admin->profile_completed_at);
    }

    public function test_org_admin_cannot_create_org_admin(): void
    {
        $this->actingAs($this->orgAdmin)
            ->post(route('admin.admins.store'), [
                'name' => 'Admin Baharu',
                'email' => 'admin.baharu@abim.org.my',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'organization_id' => $this->org->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'admin.baharu@abim.org.my']);
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
    }

    public function test_org_admin_can_open_all_branches_of_own_organization(): void
    {
        $response = $this->actingAs($this->orgAdmin)->get(route('branches.index'));
        $response->assertOk();

        $branches = $response->viewData('page')['props']['organizations'][0]['branches'];
        $this->assertCount(2, $branches);
    }

    public function test_superadmin_sees_all_admins_across_organizations_and_roles(): void
    {
        $superadmin = $this->makeSuperadmin();
        $otherOrg = Organization::factory()->create(['name' => 'PKPIM', 'slug' => 'pkpim']);
        $otherAdmin = User::factory()->create([
            'name' => 'Admin Lain',
            'current_organization_id' => $otherOrg->id,
        ]);
        $otherAdmin->assignRole('Admin');

        $response = $this->actingAs($superadmin)->get(route('admin.admins.index'));
        $response->assertOk();

        $admins = collect($response->viewData('page')['props']['admins']);

        $this->assertTrue($admins->contains(fn ($admin) => $admin['id'] === $this->orgAdmin->id));
        $this->assertTrue($admins->contains(fn ($admin) => $admin['id'] === $this->branchAdmin->id));
        $this->assertTrue($admins->contains(fn ($admin) => $admin['id'] === $otherAdmin->id));
        $this->assertTrue($admins->contains(fn ($admin) => $admin['id'] === $superadmin->id));
        $this->assertFalse($admins->contains(fn ($admin) => $admin['id'] === $this->member->id));

        $branchAdminRow = $admins->firstWhere('id', $this->branchAdmin->id);
        $this->assertSame('Perak', $branchAdminRow['branch_name']);
        $this->assertTrue($branchAdminRow['is_branch_admin']);
    }

    public function test_org_admin_only_sees_admins_from_own_organization(): void
    {
        $otherOrg = Organization::factory()->create(['name' => 'PKPIM', 'slug' => 'pkpim']);
        $otherAdmin = User::factory()->create([
            'name' => 'Admin Lain',
            'current_organization_id' => $otherOrg->id,
        ]);
        $otherAdmin->assignRole('Admin');

        $response = $this->actingAs($this->orgAdmin)->get(route('admin.admins.index'));
        $response->assertOk();

        $admins = collect($response->viewData('page')['props']['admins']);

        $this->assertTrue($admins->contains(fn ($admin) => $admin['id'] === $this->orgAdmin->id));
        $this->assertFalse($admins->contains(fn ($admin) => $admin['id'] === $otherAdmin->id));
        $this->assertTrue($admins->contains(fn ($admin) => $admin['id'] === $this->branchAdmin->id));

        $orgAdminRow = $admins->firstWhere('id', $this->orgAdmin->id);
        $branchAdminRow = $admins->firstWhere('id', $this->branchAdmin->id);

        $this->assertFalse($orgAdminRow['can_toggle']);
        $this->assertFalse($orgAdminRow['can_reset_password']);
        $this->assertTrue($branchAdminRow['can_toggle']);
        $this->assertTrue($branchAdminRow['can_reset_password']);
    }

    public function test_org_admin_can_manage_branch_admin_but_not_other_org_admin(): void
    {
        $otherOrgAdmin = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
        ]);
        $otherOrgAdmin->assignRole('Admin');

        $this->actingAs($this->orgAdmin)
            ->patch(route('admin.hub.members.toggle-active', $this->branchAdmin))
            ->assertRedirect();

        $this->assertFalse($this->branchAdmin->fresh()->is_active);

        $this->actingAs($this->orgAdmin)
            ->patch(route('admin.hub.members.toggle-active', $otherOrgAdmin))
            ->assertForbidden();

        $this->assertTrue($otherOrgAdmin->fresh()->is_active);
    }

    public function test_superadmin_can_toggle_and_reset_org_admin(): void
    {
        $superadmin = $this->makeSuperadmin();

        $this->actingAs($superadmin)
            ->patch(route('admin.hub.members.toggle-active', $this->orgAdmin))
            ->assertRedirect();
        $this->assertFalse($this->orgAdmin->fresh()->is_active);

        $this->actingAs($superadmin)
            ->post(route('admin.hub.members.reset-password', $this->orgAdmin), [
                'password' => 'password-baharu-123',
                'password_confirmation' => 'password-baharu-123',
            ])
            ->assertRedirect();

        $this->orgAdmin->refresh();
        $this->assertTrue(Hash::check('password-baharu-123', $this->orgAdmin->password));
        $this->assertTrue((bool) $this->orgAdmin->must_change_password);

        $this->actingAs($superadmin)
            ->delete(route('admin.hub.members.destroy', $this->orgAdmin))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted('users', ['id' => $this->orgAdmin->id]);
    }

    public function test_superadmin_cannot_deactivate_self_or_last_superadmin(): void
    {
        $superadmin = $this->makeSuperadmin();

        $this->actingAs($superadmin)
            ->patch(route('admin.hub.members.toggle-active', $superadmin))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertTrue($superadmin->fresh()->is_active);
    }

    public function test_superadmin_dashboard_has_no_overdue_fees_before_fee_start_year(): void
    {
        config(['membership.fee_start_year' => now()->year + 1]);
        Cache::flush();

        $superadmin = $this->makeSuperadmin();

        $response = $this->actingAs($superadmin)->get(route('admin.dashboard'));
        $response->assertOk();

        $overview = $response->viewData('page')['props']['overview'];

        $this->assertSame(0, $overview['fees_due_count']);
    }

    private function makeSuperadmin(): User
    {
        $superadmin = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
        ]);
        $superadmin->assignRole('Superadmin');

        return $superadmin;
    }
}

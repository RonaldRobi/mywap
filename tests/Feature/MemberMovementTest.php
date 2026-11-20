<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchAdmin;
use App\Models\BranchTransitionHistory;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MemberMovementTest extends TestCase
{
    use RefreshDatabase;

    private User $branchAdmin;

    private User $orgAdmin;

    private Branch $managedBranch;

    private Branch $sourceBranch;

    private Branch $otherBranch;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Admin', 'Admin Cawangan', 'Member'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }

        $organization = Organization::factory()->create(['name' => 'ABIM', 'slug' => 'abim']);
        $this->managedBranch = Branch::create(['organization_id' => $organization->id, 'name' => 'Johor', 'state' => 'Johor', 'is_active' => true]);
        $this->sourceBranch = Branch::create(['organization_id' => $organization->id, 'name' => 'Selangor', 'state' => 'Selangor', 'is_active' => true]);
        $this->otherBranch = Branch::create(['organization_id' => $organization->id, 'name' => 'Kedah', 'state' => 'Kedah', 'is_active' => true]);

        $this->branchAdmin = User::factory()->create([
            'current_organization_id' => $organization->id,
            'branch_id' => $this->managedBranch->id,
            'profile_completed_at' => now(),
        ]);
        $this->branchAdmin->assignRole('Admin Cawangan');
        BranchAdmin::create([
            'branch_id' => $this->managedBranch->id,
            'user_id' => $this->branchAdmin->id,
            'role' => BranchAdmin::ROLE_MANAGER,
        ]);

        $this->orgAdmin = User::factory()->create([
            'current_organization_id' => $organization->id,
            'profile_completed_at' => now(),
        ]);
        $this->orgAdmin->assignRole('Admin');
    }

    public function test_branch_admin_sees_only_movements_for_managed_branches(): void
    {
        $incoming = $this->member('Ahli Masuk', $this->managedBranch);
        $outgoing = $this->member('Ahli Keluar', $this->otherBranch);
        $unrelated = $this->member('Ahli Luar Skop', $this->otherBranch);

        $this->movement($incoming, $this->sourceBranch, $this->managedBranch);
        $this->movement($outgoing, $this->managedBranch, $this->otherBranch);
        $this->movement($unrelated, $this->sourceBranch, $this->otherBranch);

        $this->actingAs($this->branchAdmin)
            ->get(route('admin.member-movements.index', ['direction' => 'all']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/MemberMovements')
                ->where('movements.total', 2)
                ->where('movements.data.0.user.name', 'Ahli Keluar')
                ->where('movements.data.0.direction', 'outgoing')
                ->where('movements.data.1.user.name', 'Ahli Masuk')
                ->where('movements.data.1.direction', 'incoming'));
    }

    public function test_page_defaults_to_incoming_and_supports_member_search(): void
    {
        $matched = $this->member('Nur Aisyah', $this->managedBranch);
        $other = $this->member('Ahmad Zaki', $this->managedBranch);

        $this->movement($matched, $this->sourceBranch, $this->managedBranch);
        $this->movement($other, $this->sourceBranch, $this->managedBranch);

        $this->actingAs($this->branchAdmin)
            ->get(route('admin.member-movements.index', ['search' => 'Aisyah']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.direction', 'incoming')
                ->where('movements.total', 1)
                ->where('movements.data.0.user.name', 'Nur Aisyah'));
    }

    public function test_org_admin_cannot_open_branch_admin_movement_page(): void
    {
        $this->actingAs($this->orgAdmin)
            ->get(route('admin.member-movements.index'))
            ->assertForbidden();
    }

    private function member(string $name, Branch $branch): User
    {
        $member = User::factory()->create([
            'name' => $name,
            'current_organization_id' => $branch->organization_id,
            'branch_id' => $branch->id,
            'profile_completed_at' => now(),
        ]);
        $member->assignRole('Member');

        return $member;
    }

    private function movement(User $member, Branch $from, Branch $to): BranchTransitionHistory
    {
        return BranchTransitionHistory::create([
            'user_id' => $member->id,
            'from_branch_id' => $from->id,
            'to_branch_id' => $to->id,
            'changed_by' => $this->orgAdmin->id,
            'change_type' => 'admin_direct',
        ]);
    }
}

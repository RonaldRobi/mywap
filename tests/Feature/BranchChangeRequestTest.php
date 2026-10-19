<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchChangeRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BranchChangeRequestTest extends TestCase
{
    use RefreshDatabase;

    private Organization $abim;

    private Branch $selangor;

    private Branch $johor;

    private Branch $kedah;

    private User $member;

    private BranchChangeRequest $request;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Superadmin', 'Admin', 'org-admin', 'Admin Cawangan', 'Member'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }

        $this->abim = Organization::factory()->create(['name' => 'ABIM', 'slug' => 'abim']);

        $this->selangor = Branch::create(['organization_id' => $this->abim->id, 'name' => 'Selangor', 'state' => 'Selangor', 'is_active' => true]);
        $this->johor = Branch::create(['organization_id' => $this->abim->id, 'name' => 'Johor', 'state' => 'Johor', 'is_active' => true]);
        $this->kedah = Branch::create(['organization_id' => $this->abim->id, 'name' => 'Kedah', 'state' => 'Kedah', 'is_active' => true]);

        $this->member = User::factory()->create([
            'current_organization_id' => $this->abim->id,
            'branch_id' => $this->selangor->id,
            'profile_completed_at' => now(),
        ]);
        $this->member->assignRole('Member');

        $this->request = BranchChangeRequest::create([
            'user_id' => $this->member->id,
            'from_branch_id' => $this->selangor->id,
            'to_branch_id' => $this->johor->id,
            'status' => 'pending',
        ]);
    }

    private function branchAdmin(Branch $branch): User
    {
        $admin = User::factory()->create([
            'current_organization_id' => $this->abim->id,
            'branch_id' => $branch->id,
            'profile_completed_at' => now(),
        ]);
        $admin->assignRole('Admin Cawangan');

        return $admin;
    }

    private function approve(User $actor)
    {
        return $this->actingAs($actor)->post(route('branch-change-requests.approve', $this->request));
    }

    public function test_source_branch_admin_can_approve(): void
    {
        $this->approve($this->branchAdmin($this->selangor))->assertRedirect();

        $this->assertSame('approved', $this->request->fresh()->status);
        $this->assertSame($this->johor->id, $this->member->fresh()->branch_id);
    }

    public function test_destination_branch_admin_can_approve(): void
    {
        $this->approve($this->branchAdmin($this->johor))->assertRedirect();

        $this->assertSame('approved', $this->request->fresh()->status);
        $this->assertSame($this->johor->id, $this->member->fresh()->branch_id);
    }

    public function test_unrelated_branch_admin_cannot_approve(): void
    {
        $this->actingAs($this->branchAdmin($this->kedah))
            ->post(route('branch-change-requests.approve', $this->request))
            ->assertForbidden();

        $this->assertSame('pending', $this->request->fresh()->status);
        $this->assertSame($this->selangor->id, $this->member->fresh()->branch_id);
    }

    public function test_org_admin_can_approve(): void
    {
        $admin = User::factory()->create([
            'current_organization_id' => $this->abim->id,
            'profile_completed_at' => now(),
        ]);
        $admin->assignRole('Admin');

        $this->approve($admin)->assertRedirect();

        $this->assertSame('approved', $this->request->fresh()->status);
    }
}

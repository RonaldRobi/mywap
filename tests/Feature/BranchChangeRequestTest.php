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

    public function test_source_branch_admin_cannot_approve(): void
    {
        $this->approve($this->branchAdmin($this->selangor))->assertForbidden();

        $this->assertSame('pending', $this->request->fresh()->status);
        $this->assertSame($this->selangor->id, $this->member->fresh()->branch_id);
    }

    public function test_destination_branch_admin_cannot_approve(): void
    {
        $this->approve($this->branchAdmin($this->johor))->assertForbidden();

        $this->assertSame('pending', $this->request->fresh()->status);
        $this->assertSame($this->selangor->id, $this->member->fresh()->branch_id);
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

    public function test_admin_can_search_pending_requests_by_member_fields(): void
    {
        $this->member->update([
            'name' => 'Ahmad Firdaus',
            'email' => 'ahmad@example.test',
            'member_no' => 'ABIM-123',
        ]);

        foreach (['Ahmad Firdaus', 'ahmad@example.test', 'ABIM-123'] as $term) {
            $response = $this->actingAs($this->adminUser())
                ->get(route('branch-change-requests.index', ['search' => $term]));

            $response->assertOk();
            $props = $response->viewData('page')['props'] ?? [];
            $ids = collect($props['requests']['data'] ?? [])->pluck('user.id')->all();

            $this->assertContains($this->member->id, $ids);
        }
    }

    public function test_branch_change_requests_are_paginated_and_filters_are_preserved(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)
            ->get(route('branch-change-requests.index', [
                'from_branch_id' => $this->selangor->id,
                'to_branch_id' => $this->johor->id,
                'per_page' => 25,
            ]));

        $response->assertOk();
        $props = $response->viewData('page')['props'] ?? [];

        $this->assertSame($this->selangor->id, (int) ($props['filters']['from_branch_id'] ?? 0));
        $this->assertSame($this->johor->id, (int) ($props['filters']['to_branch_id'] ?? 0));
        $this->assertSame(25, (int) ($props['requests']['per_page'] ?? 0));
    }

    private function adminUser(): User
    {
        $admin = User::factory()->create([
            'current_organization_id' => $this->abim->id,
            'profile_completed_at' => now(),
        ]);
        $admin->assignRole('Admin');

        return $admin;
    }

    public function test_member_can_request_branch_change_from_profile(): void
    {
        $this->actingAs($this->member)
            ->post(route('profile.branch-change'), ['branch_id' => $this->kedah->id])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('branch_change_requests', [
            'user_id' => $this->member->id,
            'from_branch_id' => $this->selangor->id,
            'to_branch_id' => $this->kedah->id,
            'status' => 'pending',
        ]);

        $this->assertSame($this->selangor->id, $this->member->fresh()->branch_id);
    }

    public function test_branch_change_request_rejects_branch_from_other_organization(): void
    {
        $otherOrg = Organization::factory()->create(['slug' => 'other-org']);
        $otherBranch = Branch::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Cawangan Lain',
            'state' => 'Perak',
            'is_active' => true,
        ]);

        $this->actingAs($this->member)
            ->post(route('profile.branch-change'), ['branch_id' => $otherBranch->id])
            ->assertSessionHasErrors('branch_id');
    }

    public function test_branch_change_request_rejects_current_branch(): void
    {
        $this->actingAs($this->member)
            ->post(route('profile.branch-change'), ['branch_id' => $this->selangor->id])
            ->assertSessionHasErrors('branch_id');
    }

    public function test_new_branch_change_request_cancels_previous_pending_request(): void
    {
        $this->actingAs($this->member)
            ->post(route('profile.branch-change'), ['branch_id' => $this->kedah->id])
            ->assertSessionHas('success');

        $this->assertSame('cancelled', $this->request->fresh()->status);
        $this->assertDatabaseHas('branch_change_requests', [
            'user_id' => $this->member->id,
            'to_branch_id' => $this->kedah->id,
            'status' => 'pending',
        ]);
    }

    public function test_org_admin_can_bulk_approve_multiple_requests(): void
    {
        $secondMember = User::factory()->create([
            'current_organization_id' => $this->abim->id,
            'branch_id' => $this->selangor->id,
            'profile_completed_at' => now(),
        ]);
        $secondMember->assignRole('Member');

        $secondRequest = BranchChangeRequest::create([
            'user_id' => $secondMember->id,
            'from_branch_id' => $this->selangor->id,
            'to_branch_id' => $this->kedah->id,
            'status' => 'pending',
        ]);

        $admin = User::factory()->create([
            'current_organization_id' => $this->abim->id,
            'profile_completed_at' => now(),
        ]);
        $admin->assignRole('Admin');

        $this->actingAs($admin)
            ->post(route('branch-change-requests.bulk-approve'), [
                'ids' => [$this->request->id, $secondRequest->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('approved', $this->request->fresh()->status);
        $this->assertSame('approved', $secondRequest->fresh()->status);
        $this->assertSame($this->johor->id, $this->member->fresh()->branch_id);
        $this->assertSame($this->kedah->id, $secondMember->fresh()->branch_id);
    }

    public function test_unrelated_branch_admin_cannot_bulk_approve(): void
    {
        $this->actingAs($this->branchAdmin($this->kedah))
            ->post(route('branch-change-requests.bulk-approve'), [
                'ids' => [$this->request->id],
            ])
            ->assertForbidden();

        $this->assertSame('pending', $this->request->fresh()->status);
        $this->assertSame($this->selangor->id, $this->member->fresh()->branch_id);
    }

    public function test_bulk_approve_skips_already_processed_requests(): void
    {
        $this->request->update(['status' => 'rejected']);

        $admin = User::factory()->create([
            'current_organization_id' => $this->abim->id,
            'profile_completed_at' => now(),
        ]);
        $admin->assignRole('Admin');

        $this->actingAs($admin)
            ->post(route('branch-change-requests.bulk-approve'), [
                'ids' => [$this->request->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('rejected', $this->request->fresh()->status);
        $this->assertSame($this->selangor->id, $this->member->fresh()->branch_id);
    }
}

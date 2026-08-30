<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MoveOrganizationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $pkpim;

    private Organization $abim;

    private Organization $wadah;

    private User $superadmin;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Superadmin', 'Admin', 'Member'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }

        $this->pkpim = Organization::factory()->create(['name' => 'PKPIM', 'slug' => 'pkpim', 'min_age' => 0, 'max_age' => 19]);
        $this->abim = Organization::factory()->create(['name' => 'ABIM', 'slug' => 'abim', 'min_age' => 20, 'max_age' => 29]);
        $this->wadah = Organization::factory()->create(['name' => 'WADAH', 'slug' => 'wadah', 'min_age' => 30, 'max_age' => null]);

        $this->superadmin = User::factory()->create();
        $this->superadmin->assignRole('Superadmin');

        $this->admin = User::factory()->create(['current_organization_id' => $this->abim->id]);
        $this->admin->assignRole('Admin');
    }

    public function test_superadmin_can_move_member_to_another_organization(): void
    {
        $branch = Branch::create([
            'organization_id' => $this->abim->id,
            'name' => 'Cawangan Kuala Lumpur',
            'is_active' => true,
        ]);

        $member = User::factory()->create([
            'current_organization_id' => $this->abim->id,
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($this->superadmin)
            ->post(route('superadmin.members.organization.update', $member), [
                'organization_id' => $this->wadah->id,
            ])
            ->assertRedirect();

        $this->assertSame($this->wadah->id, $member->fresh()->current_organization_id);
        $this->assertNull($member->fresh()->branch_id);

        $this->assertDatabaseHas('user_transition_histories', [
            'user_id' => $member->id,
            'from_organization_id' => $this->abim->id,
            'to_organization_id' => $this->wadah->id,
        ]);
    }

    public function test_admin_cannot_move_member(): void
    {
        $member = User::factory()->create(['current_organization_id' => $this->abim->id]);

        $this->actingAs($this->admin)
            ->post(route('superadmin.members.organization.update', $member), [
                'organization_id' => $this->wadah->id,
            ])
            ->assertForbidden();

        $this->assertSame($this->abim->id, $member->fresh()->current_organization_id);
    }

    public function test_moving_to_same_organization_is_rejected(): void
    {
        $member = User::factory()->create(['current_organization_id' => $this->abim->id]);

        $this->actingAs($this->superadmin)
            ->from('/superadmin/settings')
            ->post(route('superadmin.members.organization.update', $member), [
                'organization_id' => $this->abim->id,
            ])
            ->assertRedirect('/superadmin/settings')
            ->assertSessionHas('error');

        $this->assertSame($this->abim->id, $member->fresh()->current_organization_id);
    }
}

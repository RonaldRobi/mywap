<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PositionBadgeTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $member;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Superadmin', 'Admin', 'Member'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }

        $this->org = Organization::factory()->create(['name' => 'PKPIM', 'slug' => 'pkpim']);

        $this->admin = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
        ]);
        $this->admin->assignRole('Admin');

        $this->member = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
            'member_no' => 'PKPIM-0001',
        ]);
        $this->member->assignRole('Member');
    }

    public function test_admin_can_assign_position_and_profile_exposes_badge(): void
    {
        $position = OrganizationPosition::create([
            'organization_id' => $this->org->id,
            'name' => 'Presiden',
            'color' => '#123456',
            'show_badge' => true,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->patch(route('admin.members.update', $this->member), [
                'name' => $this->member->name,
                'email' => $this->member->email,
                'position' => 'Presiden',
            ])
            ->assertRedirect();

        $this->assertSame($position->id, $this->member->fresh()->position_id);

        $this->member->refresh();
        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.profileUser.position_badge.name', 'Presiden')
            ->assertJsonPath('data.profileUser.position_badge.color', '#123456');
    }

    public function test_ordinary_member_position_does_not_render_badge(): void
    {
        $position = OrganizationPosition::create([
            'organization_id' => $this->org->id,
            'name' => 'Ahli Biasa',
            'show_badge' => false,
            'is_active' => true,
        ]);

        $this->member->update([
            'position_id' => $position->id,
            'position' => 'Ahli Biasa',
        ]);

        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.profileUser.position_badge', null);
    }

    public function test_inactive_position_does_not_render_badge(): void
    {
        $position = OrganizationPosition::create([
            'organization_id' => $this->org->id,
            'name' => 'Setiausaha',
            'show_badge' => true,
            'is_active' => false,
        ]);

        $this->member->update(['position_id' => $position->id]);

        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.profileUser.position_badge', null);
    }

    public function test_member_card_includes_position_badge(): void
    {
        $position = OrganizationPosition::create([
            'organization_id' => $this->org->id,
            'name' => 'Bendahari',
            'color' => '#abcdef',
            'show_badge' => true,
            'is_active' => true,
        ]);

        $this->member->update([
            'position_id' => $position->id,
            'position' => 'Bendahari',
        ]);

        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/member/card')
            ->assertOk()
            ->assertJsonPath('data.card.position_badge.name', 'Bendahari')
            ->assertJsonPath('data.card.position_badge.color', '#abcdef');
    }

    public function test_member_cannot_self_assign_position(): void
    {
        OrganizationPosition::create([
            'organization_id' => $this->org->id,
            'name' => 'Presiden',
            'show_badge' => true,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->member);

        $this->putJson('/api/v1/profile', [
            'name' => $this->member->name,
            'email' => $this->member->email,
            'position' => 'Presiden',
        ])->assertOk();

        $this->assertNull($this->member->fresh()->position_id);
        $this->assertNull($this->member->fresh()->position);
    }
}

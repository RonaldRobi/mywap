<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Admin Organisasi (role Admin / org-admin) mesti boleh melihat & mengurus
 * SEMUA ahli dalam organisasinya (merentas cawangan/negeri) tetapi TIDAK
 * boleh melihat atau mengurus ahli organisasi lain.
 */
class OrgAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    private Organization $abim;

    private Organization $wadah;

    private Branch $selangor;

    private Branch $johor;

    private User $admin;

    private User $orgAdmin;

    private User $memberSelangor;

    private User $memberJohor;

    private User $memberWadah;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Superadmin', 'Admin', 'org-admin', 'Admin Cawangan', 'Member'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }

        $this->abim = Organization::factory()->create(['name' => 'ABIM', 'slug' => 'abim']);
        $this->wadah = Organization::factory()->create(['name' => 'WADAH', 'slug' => 'wadah']);

        $this->selangor = Branch::create(['organization_id' => $this->abim->id, 'name' => 'Selangor', 'state' => 'Selangor', 'is_active' => true]);
        $this->johor = Branch::create(['organization_id' => $this->abim->id, 'name' => 'Johor', 'state' => 'Johor', 'is_active' => true]);
        Branch::create(['organization_id' => $this->wadah->id, 'name' => 'Selangor', 'state' => 'Selangor', 'is_active' => true]);

        $this->admin = User::factory()->create([
            'name' => 'Admin ABIM Selangor',
            'current_organization_id' => $this->abim->id,
            'branch_id' => $this->selangor->id,
            'profile_completed_at' => now(),
        ]);
        $this->admin->assignRole('Admin');

        $this->orgAdmin = User::factory()->create([
            'current_organization_id' => $this->abim->id,
            'profile_completed_at' => now(),
        ]);
        $this->orgAdmin->assignRole('org-admin');

        $this->memberSelangor = $this->makeMember('Ahli Selangor', $this->abim, $this->selangor);
        $this->memberJohor = $this->makeMember('Ahli Johor', $this->abim, $this->johor);
        $this->memberWadah = $this->makeMember('Ahli Wadah', $this->wadah, null);
    }

    private function makeMember(string $name, Organization $org, ?Branch $branch): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'current_organization_id' => $org->id,
            'branch_id' => $branch?->id,
            'is_active' => true,
            'profile_completed_at' => now(),
        ]);
        $user->assignRole('Member');

        return $user;
    }

    private function memberNames(User $actor): array
    {
        $response = $this->actingAs($actor)->get('/admin/information-hub/manage');
        $response->assertOk();

        $props = $response->viewData('page')['props'] ?? [];

        return collect($props['members']['data'] ?? [])->pluck('name')->all();
    }

    public function test_admin_sees_members_across_all_states_of_own_org(): void
    {
        $names = $this->memberNames($this->admin);

        $this->assertContains('Ahli Selangor', $names);
        $this->assertContains('Ahli Johor', $names);
        $this->assertNotContains('Ahli Wadah', $names);
    }

    public function test_org_admin_role_can_open_admin_panel(): void
    {
        $names = $this->memberNames($this->orgAdmin);

        $this->assertContains('Ahli Selangor', $names);
        $this->assertNotContains('Ahli Wadah', $names);
    }

    public function test_admin_can_open_create_program_and_push_notification(): void
    {
        $this->actingAs($this->admin)->get('/admin/events/create')->assertOk();
        $this->actingAs($this->admin)->get('/admin/broadcasts')->assertOk();
    }

    public function test_admin_cannot_manage_members_of_other_organization(): void
    {
        $response = $this->actingAs($this->admin)
            ->patch("/admin/information-hub/members/{$this->memberWadah->id}/toggle-active");

        $this->assertContains($response->getStatusCode(), [403, 404]);
        $this->assertTrue((bool) $this->memberWadah->fresh()->is_active);
    }
}

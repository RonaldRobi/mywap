<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MemberSearchScopingTest extends TestCase
{
    use RefreshDatabase;

    private Organization $pkpim;

    private Organization $abim;

    private User $admin;

    private User $superadmin;

    private User $memberPkpim;

    private User $memberAbim;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'org-admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $this->pkpim = Organization::factory()->create(['name' => 'PKPIM', 'slug' => 'pkpim']);
        $this->abim = Organization::factory()->create(['name' => 'ABIM', 'slug' => 'abim']);

        $this->admin = User::factory()->create([
            'name' => 'Pegawai PKPIM',
            'current_organization_id' => $this->pkpim->id,
        ]);
        $this->admin->assignRole('Admin');

        $this->superadmin = User::factory()->create([
            'name' => 'Super Admin',
            'current_organization_id' => $this->pkpim->id,
        ]);
        $this->superadmin->assignRole('Superadmin');

        $this->memberPkpim = User::factory()->create([
            'name' => 'Ahmad Firdaus',
            'member_no' => 'P050101',
            'ic_number' => '901012019001',
            'current_organization_id' => $this->pkpim->id,
        ]);

        $this->memberAbim = User::factory()->create([
            'name' => 'Ahmad Faiz',
            'member_no' => 'A060202',
            'current_organization_id' => $this->abim->id,
        ]);
    }

    public function test_org_admin_search_is_scoped_to_own_organization(): void
    {
        $response = $this->actingAs($this->admin, 'web')
            ->get('/api/members/search?q=Ahmad');

        $response->assertOk();

        $ids = collect($response->json())->pluck('id')->all();
        $this->assertContains($this->memberPkpim->id, $ids);
        $this->assertNotContains($this->memberAbim->id, $ids);
    }

    public function test_superadmin_search_sees_all_organizations(): void
    {
        $response = $this->actingAs($this->superadmin, 'web')
            ->get('/api/members/search?q=Ahmad');

        $response->assertOk();

        $ids = collect($response->json())->pluck('id')->all();
        $this->assertContains($this->memberPkpim->id, $ids);
        $this->assertContains($this->memberAbim->id, $ids);
    }

    public function test_superadmin_can_filter_by_organization_id(): void
    {
        $response = $this->actingAs($this->superadmin, 'web')
            ->get('/api/members/search?q=Ahmad&organization_id='.$this->abim->id);

        $response->assertOk();

        $ids = collect($response->json())->pluck('id')->all();
        $this->assertNotContains($this->memberPkpim->id, $ids);
        $this->assertContains($this->memberAbim->id, $ids);
    }

    public function test_admin_can_search_by_ic_number(): void
    {
        $response = $this->actingAs($this->admin, 'web')
            ->get('/api/members/search?q=019001');

        $response->assertOk();

        $ids = collect($response->json())->pluck('id')->all();
        $this->assertContains($this->memberPkpim->id, $ids);
    }

    public function test_guest_search_only_matches_name_or_member_no(): void
    {
        // '019001' tidak sepatutnya ditemui oleh tetamu (padanan sensitif dilarang).
        $this->get('/api/members/search?q=019001')->assertOk()->assertJsonCount(0);

        $response = $this->get('/api/members/search?q=Ahmad');
        $response->assertOk();

        $ids = collect($response->json())->pluck('id')->all();
        $this->assertContains($this->memberPkpim->id, $ids);
        $this->assertContains($this->memberAbim->id, $ids);
    }
}

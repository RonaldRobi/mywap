<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExportMembersTest extends TestCase
{
    use RefreshDatabase;

    private Organization $abim;

    private Organization $wadah;

    private Branch $selangor;

    private Branch $johor;

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
        $wadahBranch = Branch::create(['organization_id' => $this->wadah->id, 'name' => 'Wadah Selangor', 'state' => 'Selangor', 'is_active' => true]);

        $this->memberSelangor = $this->makeUser('Ahli Selangor', $this->abim, $this->selangor);
        $this->memberJohor = $this->makeUser('Ahli Johor', $this->abim, $this->johor);
        $this->memberWadah = $this->makeUser('Ahli Wadah', $this->wadah, $wadahBranch);
    }

    private function makeUser(string $name, Organization $org, ?Branch $branch, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'name' => $name,
            'current_organization_id' => $org->id,
            'branch_id' => $branch?->id,
            'is_active' => true,
            'profile_completed_at' => now(),
        ], $attributes));
    }

    private function export(User $actor, string $query = 'type=simple'): string
    {
        $response = $this->actingAs($actor)->get('/admin/members/export?'.$query);

        $response->assertOk();

        return $response->streamedContent();
    }

    public function test_full_export_contains_address_columns(): void
    {
        $superadmin = $this->makeUser('Super Admin', $this->wadah, null, [
            'address_1' => 'No 1 Jalan Satu',
            'address_2' => 'Taman Test',
            'postcode' => '43000',
            'city' => 'Kajang',
            'state' => 'Selangor',
        ]);
        $superadmin->assignRole('Superadmin');

        $content = $this->export($superadmin, 'type=full');

        $this->assertStringContainsString('Alamat 1', $content);
        $this->assertStringContainsString('No 1 Jalan Satu', $content);
    }

    public function test_branch_admin_exports_only_own_branch(): void
    {
        $branchAdmin = $this->makeUser('Admin Cawangan Selangor', $this->abim, $this->selangor);
        $branchAdmin->assignRole('Admin Cawangan');

        $content = $this->export($branchAdmin);

        $this->assertStringContainsString('Ahli Selangor', $content);
        $this->assertStringNotContainsString('Ahli Johor', $content);
        $this->assertStringNotContainsString('Ahli Wadah', $content);
    }

    public function test_branch_admin_cannot_override_scope_with_branch_filter(): void
    {
        $branchAdmin = $this->makeUser('Admin Cawangan Selangor', $this->abim, $this->selangor);
        $branchAdmin->assignRole('Admin Cawangan');

        $content = $this->export($branchAdmin, 'type=simple&branch_id='.$this->johor->id);

        $this->assertStringContainsString('Ahli Selangor', $content);
        $this->assertStringNotContainsString('Ahli Johor', $content);
    }

    public function test_org_admin_can_filter_export_by_branch(): void
    {
        $admin = $this->makeUser('Admin ABIM', $this->abim, $this->selangor);
        $admin->assignRole('Admin');

        $content = $this->export($admin, 'type=simple&branch_id='.$this->johor->id);

        $this->assertStringNotContainsString('Ahli Selangor', $content);
        $this->assertStringContainsString('Ahli Johor', $content);
        $this->assertStringNotContainsString('Ahli Wadah', $content);
    }

    public function test_org_admin_cannot_export_other_organization_members(): void
    {
        $admin = $this->makeUser('Admin ABIM', $this->abim, null);
        $admin->assignRole('Admin');

        $content = $this->export($admin);

        $this->assertStringContainsString('Ahli Selangor', $content);
        $this->assertStringContainsString('Ahli Johor', $content);
        $this->assertStringNotContainsString('Ahli Wadah', $content);
    }

    public function test_member_cannot_export_members(): void
    {
        $member = $this->makeUser('Ahli Biasa', $this->abim, $this->selangor);
        $member->assignRole('Member');

        $this->actingAs($member)->get('/admin/members/export')->assertForbidden();
    }
}

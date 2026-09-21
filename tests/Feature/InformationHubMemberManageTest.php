<?php

namespace Tests\Feature;

use App\Models\MembershipFee;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InformationHubMemberManageTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $superadmin;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $this->org = Organization::factory()->create(['name' => 'PKPIM']);

        $this->superadmin = User::factory()->create([
            'name' => 'Super Admin',
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
        ]);
        $this->superadmin->assignRole('Superadmin');

        $this->admin = User::factory()->create([
            'name' => 'Admin Biasa',
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
        ]);
        $this->admin->assignRole('Admin');
    }

    private function makeMember(array $attributes = []): User
    {
        $member = User::factory()->create(array_merge([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
        ], $attributes));
        $member->assignRole('Member');

        return $member;
    }

    private function searchNames(string $search): array
    {
        $response = $this->actingAs($this->superadmin)
            ->get('/admin/information-hub/manage?search='.urlencode($search));

        $response->assertOk();

        $props = $response->viewData('page')['props'] ?? [];

        return collect($props['members']['data'] ?? [])->pluck('name')->all();
    }

    // ─── Carian ──────────────────────────────────────────────────────────────

    public function test_full_name_search_matches_nbsp_and_multiple_spaces(): void
    {
        $this->makeMember(['name' => "Ahmad\u{00A0}Firdaus  bin Ali"]);

        $names = $this->searchNames('Ahmad Firdaus bin Ali');

        $this->assertSame(["Ahmad\u{00A0}Firdaus  bin Ali"], $names);
    }

    public function test_full_name_search_matches_when_extra_word_is_typed(): void
    {
        $this->makeMember(['name' => 'Ahmad Firdaus']);

        $names = $this->searchNames('Ahmad Firdaus bin Abdullah');

        $this->assertContains('Ahmad Firdaus', $names);
    }

    public function test_name_search_is_case_insensitive(): void
    {
        $this->makeMember(['name' => 'Nurul Izzah']);

        $names = $this->searchNames('nurul izzah');

        $this->assertContains('Nurul Izzah', $names);
    }

    public function test_name_search_relevance_puts_full_match_first(): void
    {
        $this->makeMember(['name' => 'Ahmad Zaki']);
        $this->makeMember(['name' => 'Ahmad Firdaus']);

        $names = $this->searchNames('Ahmad Firdaus');

        $this->assertSame('Ahmad Firdaus', $names[0]);
    }

    // ─── Status aktif ────────────────────────────────────────────────────────

    public function test_web_toggle_member_active_persists(): void
    {
        $member = $this->makeMember(['name' => 'Ahli Toggle']);
        $member->update(['is_active' => true]);

        $this->actingAs($this->admin)
            ->patch(route('admin.hub.members.toggle-active', $member->id))
            ->assertRedirect();

        $this->assertFalse((bool) $member->fresh()->is_active);
    }

    public function test_active_status_filter(): void
    {
        $this->makeMember(['name' => 'Ahli Aktif', 'is_active' => true]);
        $this->makeMember(['name' => 'Ahli Mati', 'is_active' => false]);

        $response = $this->actingAs($this->superadmin)
            ->get('/admin/information-hub/manage?active=inactive');

        $response->assertOk();
        $props = $response->viewData('page')['props'] ?? [];
        $names = collect($props['members']['data'] ?? [])->pluck('name')->all();

        $this->assertContains('Ahli Mati', $names);
        $this->assertNotContains('Ahli Aktif', $names);
    }

    // ─── Padam ahli ──────────────────────────────────────────────────────────

    public function test_superadmin_can_soft_delete_member_and_records_survive(): void
    {
        $member = $this->makeMember(['name' => 'Ahli Buang', 'member_no' => 'M-9999']);

        MembershipFee::factory()->create([
            'user_id' => $member->id,
            'organization_id' => $this->org->id,
            'year' => now()->year,
        ]);

        $this->actingAs($this->superadmin)
            ->delete(route('admin.hub.members.destroy', $member->id))
            ->assertRedirect();

        $this->assertSoftDeleted('users', ['id' => $member->id]);
        // Rekod kewangan kekal (tidak cascade).
        $this->assertDatabaseHas('membership_fees', ['user_id' => $member->id]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'delete_member',
            'target_id' => $member->id,
        ]);
    }

    public function test_member_is_hidden_from_list_after_soft_delete(): void
    {
        $member = $this->makeMember(['name' => 'Ahli Sorok', 'member_no' => 'M-7777']);

        $this->actingAs($this->superadmin)
            ->delete(route('admin.hub.members.destroy', $member->id));

        $names = $this->searchNames('Ahli Sorok');
        $this->assertNotContains('Ahli Sorok', $names);

        $response = $this->actingAs($this->superadmin)
            ->get('/admin/information-hub/manage?trashed=1&search='.urlencode('Ahli Sorok'));
        $response->assertOk();
        $props = $response->viewData('page')['props'] ?? [];
        $this->assertContains('Ahli Sorok', collect($props['members']['data'] ?? [])->pluck('name')->all());
    }

    public function test_superadmin_can_restore_member(): void
    {
        $member = $this->makeMember(['name' => 'Ahli Pulih', 'member_no' => 'M-6666']);
        $member->delete();

        $this->actingAs($this->superadmin)
            ->patch(route('admin.hub.members.restore', $member->id))
            ->assertRedirect();

        $this->assertNotSoftDeleted('users', ['id' => $member->id]);
        $this->assertContains('Ahli Pulih', $this->searchNames('Ahli Pulih'));
    }

    public function test_superadmin_can_force_delete_member(): void
    {
        $member = $this->makeMember(['name' => 'Ahli Kekal', 'member_no' => 'M-5555']);
        $member->delete();

        $this->actingAs($this->superadmin)
            ->delete(route('admin.hub.members.force-delete', $member->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $member->id]);
    }

    public function test_bulk_soft_delete_members(): void
    {
        $a = $this->makeMember(['name' => 'Bulk Satu']);
        $b = $this->makeMember(['name' => 'Bulk Dua']);

        $this->actingAs($this->superadmin)
            ->post(route('admin.hub.members.bulk-destroy'), ['ids' => [$a->id, $b->id]])
            ->assertRedirect();

        $this->assertSoftDeleted('users', ['id' => $a->id]);
        $this->assertSoftDeleted('users', ['id' => $b->id]);
    }

    public function test_regular_admin_can_soft_delete_own_org_member_but_not_restore(): void
    {
        $member = $this->makeMember(['name' => 'Ahli Org']);

        $this->actingAs($this->admin)
            ->delete(route('admin.hub.members.destroy', $member->id))
            ->assertRedirect();

        $this->assertSoftDeleted('users', ['id' => $member->id]);

        $this->actingAs($this->admin)
            ->patch(route('admin.hub.members.restore', $member->id))
            ->assertForbidden();
    }

    public function test_superadmin_cannot_delete_themselves_or_other_superadmin(): void
    {
        $this->actingAs($this->superadmin)
            ->delete(route('admin.hub.members.destroy', $this->superadmin->id))
            ->assertRedirect();

        $this->assertNotSoftDeleted('users', ['id' => $this->superadmin->id]);

        $otherSuperadmin = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
        ]);
        $otherSuperadmin->assignRole('Superadmin');

        $this->actingAs($this->superadmin)
            ->delete(route('admin.hub.members.destroy', $otherSuperadmin->id))
            ->assertRedirect();

        $this->assertNotSoftDeleted('users', ['id' => $otherSuperadmin->id]);
    }
}

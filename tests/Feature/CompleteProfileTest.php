<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CompleteProfileTest extends TestCase
{
    use RefreshDatabase;

    private Organization $abim;

    private Branch $selangor;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $this->abim = Organization::factory()->create(['name' => 'ABIM', 'slug' => 'abim']);
        $this->selangor = Branch::create([
            'organization_id' => $this->abim->id,
            'name' => 'Selangor',
            'state' => 'Selangor',
            'is_active' => true,
        ]);

        $this->member = User::factory()->create([
            'current_organization_id' => $this->abim->id,
            'branch_id' => null,
            'profile_completed_at' => null,
            'email_verified_at' => now(),
        ]);
        $this->member->assignRole('Member');
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'education_level' => 'Ijazah',
            'current_profession' => 'Guru',
            'phone' => '0131234567',
            'branch_id' => $this->selangor->id,
        ], $overrides);
    }

    public function test_complete_profile_page_lists_branches(): void
    {
        $this->actingAs($this->member)
            ->get('/member/complete-profile')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Member/CompleteProfile')
                ->where('currentBranchId', null)
                ->has('branches', 1)
                ->where('branches.0.name', 'Selangor'));
    }

    public function test_branch_is_required_to_complete_profile(): void
    {
        $payload = $this->validPayload();
        unset($payload['branch_id']);

        $this->actingAs($this->member)
            ->post('/member/complete-profile', $payload)
            ->assertSessionHasErrors('branch_id');

        $this->assertNull($this->member->fresh()->profile_completed_at);
    }

    public function test_branch_must_belong_to_members_organization(): void
    {
        $other = Organization::factory()->create(['name' => 'WADAH', 'slug' => 'wadah']);
        $foreignBranch = Branch::create([
            'organization_id' => $other->id,
            'name' => 'Wadah Selangor',
            'state' => 'Selangor',
            'is_active' => true,
        ]);

        $this->actingAs($this->member)
            ->post('/member/complete-profile', $this->validPayload([
                'branch_id' => $foreignBranch->id,
            ]))
            ->assertSessionHasErrors('branch_id');

        $this->assertNull($this->member->fresh()->profile_completed_at);
    }

    public function test_valid_branch_completes_profile_and_records_history(): void
    {
        $this->actingAs($this->member)
            ->post('/member/complete-profile', $this->validPayload())
            ->assertRedirect(route('dashboard'));

        $this->member->refresh();

        $this->assertNotNull($this->member->profile_completed_at);
        $this->assertSame($this->selangor->id, $this->member->branch_id);
        $this->assertDatabaseHas('branch_transition_histories', [
            'user_id' => $this->member->id,
            'from_branch_id' => null,
            'to_branch_id' => $this->selangor->id,
            'change_type' => 'profile_completion',
        ]);
    }
}

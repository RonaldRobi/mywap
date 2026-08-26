<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Organization;
use App\Models\OrganizationPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $this->org = Organization::factory()->create(['name' => 'PKPIM']);

        $this->member = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
            'member_no' => 'PKPIM-0001',
            'phone' => '0123456789',
            'education_level' => 'Ijazah Sarjana Muda',
            'current_profession' => 'Juruaudit',
        ]);
        $this->member->assignRole('Member');
    }

    public function test_member_can_get_profile_with_web_matching_shape(): void
    {
        $branch = Branch::create([
            'organization_id' => $this->org->id,
            'name' => 'Cawangan Kuala Lumpur',
            'state' => 'Wilayah Persekutuan',
            'is_active' => true,
        ]);
        $this->member->update(['branch_id' => $branch->id]);

        $event = Event::create([
            'organization_id' => $this->org->id,
            'title' => 'Muktamar Nasional',
            'type' => 'physical',
            'status' => 'published',
            'category' => 'muktamar',
            'start_time' => now()->subMonth(),
            'end_time' => now()->subMonth()->addHours(3),
        ]);
        EventRsvp::create([
            'event_id' => $event->id,
            'user_id' => $this->member->id,
            'status' => 'attended',
            'attended_at' => now()->subMonth(),
        ]);

        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'profileUser' => [
                        'id',
                        'member_no',
                        'name',
                        'email',
                        'phone',
                        'ic_number',
                        'roles',
                        'dob',
                        'age',
                        'gender',
                        'marital_status',
                        'education_level',
                        'current_profession',
                        'industry',
                        'expertise',
                        'topics',
                        'position',
                        'branch_name',
                        'locality',
                        'address_1',
                        'address_2',
                        'postcode',
                        'city',
                        'state',
                        'emergency_contact_name',
                        'emergency_contact_phone',
                        'organization',
                        'feeStatus',
                    ],
                    'history',
                    'attendedPrograms',
                ],
            ])
            ->assertJsonPath('data.profileUser.id', $this->member->id)
            ->assertJsonPath('data.profileUser.branch_name', 'Cawangan Kuala Lumpur')
            ->assertJsonPath('data.profileUser.organization.name', 'PKPIM')
            ->assertJsonPath('data.attendedPrograms.0.event.title', 'Muktamar Nasional');
    }

    public function test_member_can_get_complete_profile_meta(): void
    {
        $this->member->update(['ic_number' => '921231105233']);

        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/profile/complete')
            ->assertOk()
            ->assertJsonStructure(['data' => ['parsedDob', 'parsedGender']])
            ->assertJsonPath('data.parsedDob', '1992-12-31')
            ->assertJsonPath('data.parsedGender', 'lelaki');
    }

    public function test_complete_profile_stores_data_and_autofills_from_ic(): void
    {
        $user = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'ic_number' => '921231105233',
            'member_no' => 'PKPIM-0002',
        ]);
        $user->assignRole('Member');

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/profile/complete', [
            'education_level' => 'Ijazah',
            'current_profession' => 'Guru',
            'phone' => '0131234567',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', $user->name);

        $user->refresh();
        $this->assertNotNull($user->profile_completed_at);
        $this->assertSame('Ijazah', $user->education_level);
        $this->assertSame('Guru', $user->current_profession);
        $this->assertSame('1992-12-31', $user->dob?->format('Y-m-d'));
        $this->assertSame('lelaki', $user->gender);
    }

    public function test_complete_profile_validates_required_fields(): void
    {
        Sanctum::actingAs($this->member);

        $this->postJson('/api/v1/profile/complete', [])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['education_level', 'current_profession', 'phone']]);
    }

    public function test_member_can_update_profile(): void
    {
        Sanctum::actingAs($this->member);

        $this->putJson('/api/v1/profile', [
            'name' => 'Nama Baharu',
            'email' => $this->member->email,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nama Baharu');

        $this->assertDatabaseHas('users', [
            'id' => $this->member->id,
            'name' => 'Nama Baharu',
        ]);
    }

    public function test_update_profile_validates(): void
    {
        Sanctum::actingAs($this->member);

        $this->putJson('/api/v1/profile', [
            'email' => 'invalid-email',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['name', 'email']]);
    }

    public function test_member_branch_change_creates_pending_request_via_update(): void
    {
        $current = Branch::create([
            'organization_id' => $this->org->id,
            'name' => 'Cawangan A',
            'state' => 'Selangor',
            'is_active' => true,
        ]);
        $target = Branch::create([
            'organization_id' => $this->org->id,
            'name' => 'Cawangan B',
            'state' => 'Johor',
            'is_active' => true,
        ]);
        $this->member->update(['branch_id' => $current->id]);

        Sanctum::actingAs($this->member);

        $this->putJson('/api/v1/profile', [
            'name' => $this->member->name,
            'email' => $this->member->email,
            'branch_id' => $target->id,
        ])
            ->assertOk();

        $this->assertDatabaseHas('branch_change_requests', [
            'user_id' => $this->member->id,
            'from_branch_id' => $current->id,
            'to_branch_id' => $target->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->member->id,
            'branch_id' => $current->id,
        ]);
    }

    public function test_member_can_get_edit_meta(): void
    {
        $branch = Branch::create([
            'organization_id' => $this->org->id,
            'name' => 'Cawangan KL',
            'state' => 'Wilayah Persekutuan',
            'is_active' => true,
        ]);
        OrganizationPosition::create([
            'organization_id' => $this->org->id,
            'name' => 'Pengerusi',
            'display_order' => 1,
        ]);

        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/profile/edit-meta')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'branches',
                    'orgPositions',
                    'canEditIcNumber',
                    'pendingBranchRequest',
                ],
            ])
            ->assertJsonPath('data.branches.0.name', 'Cawangan KL')
            ->assertJsonPath('data.orgPositions.0.name', 'Pengerusi')
            ->assertJsonPath('data.canEditIcNumber', false)
            ->assertJsonPath('data.pendingBranchRequest', null);
    }

    public function test_profile_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/profile')->assertStatus(401);
        $this->getJson('/api/v1/profile/complete')->assertStatus(401);
        $this->getJson('/api/v1/profile/edit-meta')->assertStatus(401);
        $this->postJson('/api/v1/profile/complete')->assertStatus(401);
        $this->putJson('/api/v1/profile')->assertStatus(401);
    }
}

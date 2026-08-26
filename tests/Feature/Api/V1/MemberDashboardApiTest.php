<?php

namespace Tests\Feature\Api\V1;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MemberDashboardApiTest extends TestCase
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
            'branch_name' => 'Cawangan KL',
        ]);
        $this->member->assignRole('Member');
    }

    public function test_member_can_fetch_dashboard(): void
    {
        Event::create([
            'organization_id' => $this->org->id,
            'title' => 'Kem Kepimpinan',
            'description' => 'x',
            'type' => 'physical',
            'status' => EventStatus::Published->value,
            'category' => EventCategory::Kem->value,
            'location_or_link' => 'Perak',
            'start_time' => now()->addMonth(),
            'end_time' => now()->addMonth()->addHours(2),
        ]);

        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/member/dashboard')
            ->assertOk()
            ->assertJsonPath('data.member.name', $this->member->name)
            ->assertJsonPath('data.member.organization.name', 'PKPIM')
            ->assertJsonPath('data.upcomingEvents.0.title', 'Kem Kepimpinan')
            ->assertJsonStructure([
                'data' => [
                    'member',
                    'feeStatus',
                    'upcomingEvents',
                    'banners',
                    'infaqItems',
                    'latestNews',
                ],
            ]);
    }
}

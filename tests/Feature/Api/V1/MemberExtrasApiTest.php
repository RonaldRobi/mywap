<?php

namespace Tests\Feature\Api\V1;

use App\Models\Campaign;
use App\Models\Organization;
use App\Models\OrganizationChartMember;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Covers the referral, financial-overview and organization-info endpoints
 * added for full web/Flutter member parity.
 */
class MemberExtrasApiTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $this->org = Organization::factory()->create([
            'name' => 'PKPIM',
            'slug' => 'pkpim',
        ]);

        $this->user = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'member_no' => 'P00001',
            'first_login_at' => now(),
        ]);
        $this->user->assignRole('Member');
    }

    public function test_referral_returns_link_qr_and_referred_members(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $referred = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'referred_by_user_id' => $this->user->id,
            'profile_completed_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/member/referral');

        $response->assertOk()
            ->assertJsonPath('data.member_no', 'P00001')
            ->assertJsonPath('data.stats.total', 1)
            ->assertJsonPath('data.stats.active', 1)
            ->assertJsonPath('data.referred_members.0.id', $referred->id)
            ->assertJsonStructure(['data' => ['referral_link', 'qr_svg', 'stats', 'referred_members']]);
    }

    public function test_financial_overview_returns_campaigns_and_payment_history(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        Campaign::create([
            'organization_id' => $this->org->id,
            'title' => 'Tabung Ramadan',
            'slug' => 'tabung-ramadan',
            'target_amount' => 1000,
            'current_amount' => 250,
            'status' => 'active',
        ]);

        Payment::create([
            'user_id' => $this->user->id,
            'payable_type' => 'membership_fee',
            'amount' => 50,
            'status' => 'successful',
            'reference' => 'REF-001',
        ]);

        $response = $this->getJson('/api/v1/member/financial/overview');

        $response->assertOk()
            ->assertJsonPath('data.campaigns.0.title', 'Tabung Ramadan')
            ->assertJsonPath('data.campaigns.0.progress_percent', 25)
            ->assertJsonPath('data.payment_history.0.reference', null) // reference not exposed by design
            ->assertJsonStructure(['data' => ['campaigns', 'fee_status', 'payment_history']]);
    }

    public function test_organization_info_returns_org_and_chart_members(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        OrganizationChartMember::create([
            'organization_id' => $this->org->id,
            'name' => 'Ahmad Firdaus',
            'position' => 'Presiden',
            'display_order' => 1,
        ]);

        $response = $this->getJson('/api/v1/organization/info');

        $response->assertOk()
            ->assertJsonPath('data.organization.name', 'PKPIM')
            ->assertJsonPath('data.chart_members.0.name', 'Ahmad Firdaus');
    }

    public function test_organization_info_requires_organization(): void
    {
        $userWithoutOrg = User::factory()->create(['current_organization_id' => null]);
        Sanctum::actingAs($userWithoutOrg, ['*']);

        $this->getJson('/api/v1/organization/info')->assertStatus(404);
    }
}

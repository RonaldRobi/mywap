<?php

namespace Tests\Feature\Api\V1;

use App\Models\Infaq;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InfaqApiTest extends TestCase
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
        ]);
        $this->member->assignRole('Member');
    }

    private function makeInfaq(array $overrides = []): Infaq
    {
        return Infaq::create(array_merge([
            'organization_id' => $this->org->id,
            'title' => 'Infaq Test',
            'description' => 'Test',
            'type' => 'one_off',
            'is_active' => true,
            'allow_recurring' => false,
        ], $overrides));
    }

    public function test_public_can_list_infaqs(): void
    {
        $this->makeInfaq(['title' => 'Infaq A']);
        $this->makeInfaq(['title' => 'Infaq B']);

        $this->getJson('/api/v1/infaq')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'infaqs' => [
                        ['id', 'title', 'slug', 'type', 'organization_id', 'organization_name', 'organization_slug', 'public_url', 'days_running'],
                    ],
                    'organizations' => [
                        ['id', 'name', 'slug'],
                    ],
                    'hasGlobal',
                ],
            ])
            ->assertJsonCount(2, 'data.infaqs')
            ->assertJsonPath('data.organizations.0.name', 'PKPIM')
            ->assertJsonPath('data.hasGlobal', false)
            ->assertJsonPath('data.infaqs.0.days_running', 1);
    }

    public function test_public_list_excludes_inactive_infaqs(): void
    {
        $this->makeInfaq(['title' => 'Aktif']);
        $this->makeInfaq(['title' => 'Tak Aktif', 'is_active' => false]);

        $this->getJson('/api/v1/infaq')
            ->assertOk()
            ->assertJsonCount(1, 'data.infaqs')
            ->assertJsonPath('data.infaqs.0.title', 'Aktif');
    }

    public function test_public_can_view_infaq_detail(): void
    {
        $infaq = $this->makeInfaq(['title' => 'Detail Test']);

        $this->getJson("/api/v1/infaq/{$infaq->slug}")
            ->assertOk()
            ->assertJsonPath('data.infaq.id', $infaq->id)
            ->assertJsonPath('data.infaq.slug', $infaq->slug)
            ->assertJsonPath('data.infaq.organization_name', 'PKPIM')
            ->assertJsonStructure([
                'data' => [
                    'infaq' => ['id', 'slug', 'title', 'organization_name', 'total_donors', 'days_running', 'public_url'],
                    'recentDonations',
                    'relatedInfaqs',
                ],
            ]);
    }

    public function test_public_inactive_infaq_detail_is_404(): void
    {
        $infaq = $this->makeInfaq(['is_active' => false]);

        $this->getJson("/api/v1/infaq/{$infaq->slug}")
            ->assertNotFound();
    }

    public function test_member_can_donate_without_gateway_and_gets_success(): void
    {
        $infaq = $this->makeInfaq(['title' => 'Donasi A']);

        Sanctum::actingAs($this->member);

        $this->postJson("/api/v1/infaq/{$infaq->slug}/donate", [
            'amount' => 50,
            'donor_name' => 'Ali',
            'donor_phone' => '0123456789',
            'donor_email' => 'ali@example.com',
            'prayer_message' => 'Berkat',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'success')
            ->assertJsonPath('data.donation.amount', 50)
            ->assertJsonStructure([
                'data' => [
                    'status',
                    'donation' => ['id', 'reference', 'amount', 'status', 'infaq'],
                ],
            ]);

        $this->assertDatabaseHas('infaq_donations', [
            'infaq_id' => $infaq->id,
            'user_id' => $this->member->id,
            'amount' => 50.00,
            'status' => 'confirmed',
            'donor_email' => 'ali@example.com',
        ]);

        $this->assertDatabaseHas('payments', [
            'payable_type' => 'infaq_donation',
            'payable_id' => $infaq->donations()->first()->id,
            'amount' => 50.00,
            'status' => 'successful',
            'gateway' => 'dummy',
            'organization_id' => $this->org->id,
        ]);

        $this->assertDatabaseHas('infaq', [
            'id' => $infaq->id,
            'collected_amount' => 50.00,
        ]);

        $this->assertDatabaseHas('donors', [
            'user_id' => $this->member->id,
            'email' => 'ali@example.com',
            'total_donated' => 50.00,
            'donation_count' => 1,
        ]);
    }

    public function test_recurring_donation_without_bayarcash_gateway_is_rejected(): void
    {
        $infaq = $this->makeInfaq(['title' => 'Berkala', 'allow_recurring' => true]);

        Sanctum::actingAs($this->member);

        $this->postJson("/api/v1/infaq/{$infaq->slug}/donate", [
            'amount' => 50,
            'donor_name' => 'Ali',
            'donor_phone' => '0123456789',
            'donor_email' => 'ali@example.com',
            'is_recurring' => true,
            'frequency' => 'monthly',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Sumbangan berkala hanya tersedia untuk gateway BayarCash. Sila pilih sumbangan sekali sahaja.');

        $this->assertDatabaseCount('infaq_donations', 0);
    }

    public function test_donate_requires_auth(): void
    {
        $infaq = $this->makeInfaq(['title' => 'Auth Test']);

        $this->postJson("/api/v1/infaq/{$infaq->slug}/donate", [
            'amount' => 50,
            'donor_name' => 'Ali',
            'donor_phone' => '0123456789',
            'donor_email' => 'ali@example.com',
        ])
            ->assertUnauthorized();

        $this->assertDatabaseCount('infaq_donations', 0);
        $this->assertDatabaseCount('payments', 0);
    }
}

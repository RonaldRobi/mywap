<?php

namespace Tests\Feature\Api\V1;

use App\Models\Announcement;
use App\Models\LibraryItem;
use App\Models\MembershipFee;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MemberCoreApiTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private Organization $otherOrg;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $this->org = Organization::factory()->create(['name' => 'PKPIM', 'fee_amount' => 60.00]);
        $this->otherOrg = Organization::factory()->create(['name' => 'ABIM']);

        $this->member = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
            'member_no' => 'PKPIM-0001',
            'branch_name' => 'Cawangan KL',
            'current_profession' => 'Pensyarah',
            'industry' => 'Pendidikan',
        ]);
        $this->member->assignRole('Member');
    }

    private function makeAnnouncement(Organization $org, string $title = 'Pengumuman Ujian'): Announcement
    {
        return Announcement::create([
            'organization_id' => $org->id,
            'title' => $title,
            'content' => 'Kandungan pengumuman.',
            'is_pinned' => false,
            'published_at' => now(),
        ]);
    }

    public function test_member_can_get_member_card(): void
    {
        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/member/card')
            ->assertOk()
            ->assertJsonPath('data.card.name', $this->member->name)
            ->assertJsonPath('data.card.member_no', 'PKPIM-0001')
            ->assertJsonPath('data.card.organization.name', 'PKPIM')
            ->assertJsonPath('data.card.qr_value', route('public.card', ['memberNo' => 'PKPIM-0001']))
            ->assertJsonStructure([
                'data' => [
                    'card' => ['id', 'name', 'email', 'phone', 'member_no', 'organization', 'qr_value'],
                    'qrPrivate',
                    'qrPublic',
                ],
            ])
            ->assertJsonPath('data.qrPrivate', fn (string $svg) => str_contains($svg, '<svg'));
    }

    public function test_member_can_get_fee_status(): void
    {
        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/member/fee-status')
            ->assertOk()
            ->assertJsonPath('data.status.status', 'due')
            ->assertJsonPath('data.fee_amount', 60);
    }

    public function test_member_with_paid_fee_gets_active_status(): void
    {
        MembershipFee::create([
            'user_id' => $this->member->id,
            'organization_id' => $this->org->id,
            'year' => now()->year,
            'amount' => 60.00,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/member/fee-status')
            ->assertOk()
            ->assertJsonPath('data.status.status', 'active')
            ->assertJsonPath('data.fee_amount', 60);
    }

    public function test_member_can_list_announcements(): void
    {
        $announcement = $this->makeAnnouncement($this->org);

        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/member/announcements')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $announcement->id)
            ->assertJsonPath('data.0.user_reaction', null)
            ->assertJsonPath('data.0.is_read', false)
            ->assertJsonStructure([
                'data' => [
                    ['id', 'title', 'content', 'is_pinned', 'published_at', 'likes_count', 'reads_count', 'user_reaction', 'is_read', 'images'],
                ],
            ]);
    }

    public function test_member_does_not_see_other_org_announcements(): void
    {
        $this->makeAnnouncement($this->otherOrg);

        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/member/announcements')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_member_can_react_to_announcement(): void
    {
        $announcement = $this->makeAnnouncement($this->org);

        Sanctum::actingAs($this->member);

        $this->postJson("/api/v1/member/announcements/{$announcement->id}/react")
            ->assertOk()
            ->assertJsonPath('data.reaction', 'like');

        $this->assertDatabaseHas('announcement_reactions', [
            'announcement_id' => $announcement->id,
            'user_id' => $this->member->id,
            'reaction' => 'like',
        ]);

        $this->postJson("/api/v1/member/announcements/{$announcement->id}/react")
            ->assertOk()
            ->assertJsonPath('data.reaction', null);

        $this->assertDatabaseMissing('announcement_reactions', [
            'announcement_id' => $announcement->id,
            'user_id' => $this->member->id,
        ]);
    }

    public function test_member_can_mark_announcement_as_read(): void
    {
        $announcement = $this->makeAnnouncement($this->org);

        Sanctum::actingAs($this->member);

        $this->postJson("/api/v1/member/announcements/{$announcement->id}/read")
            ->assertOk()
            ->assertJsonPath('data.is_read', true);

        $this->assertDatabaseHas('announcement_reads', [
            'announcement_id' => $announcement->id,
            'user_id' => $this->member->id,
        ]);
    }

    public function test_member_can_list_library(): void
    {
        $item = LibraryItem::create([
            'organization_id' => $this->org->id,
            'title' => 'Buku Panduan',
            'description' => 'Panduan keahlian',
            'file_path' => '/storage/library/panduan.pdf',
            'cover_image_path' => '/storage/library/cover.jpg',
            'category' => 'buku',
        ]);

        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/member/library')
            ->assertOk()
            ->assertJsonPath('data.0.id', $item->id)
            ->assertJsonPath('data.0.title', 'Buku Panduan')
            ->assertJsonStructure([
                'data' => [
                    ['id', 'title', 'description', 'file_path', 'cover_image_path', 'category'],
                ],
            ]);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/member/card')->assertStatus(401);
        $this->getJson('/api/v1/member/fee-status')->assertStatus(401);
        $this->getJson('/api/v1/member/announcements')->assertStatus(401);
        $this->getJson('/api/v1/member/library')->assertStatus(401);

        $announcement = $this->makeAnnouncement($this->org);
        $this->postJson("/api/v1/member/announcements/{$announcement->id}/react")->assertStatus(401);
        $this->postJson("/api/v1/member/announcements/{$announcement->id}/read")->assertStatus(401);
    }
}

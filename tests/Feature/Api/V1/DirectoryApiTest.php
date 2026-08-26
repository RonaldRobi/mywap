<?php

namespace Tests\Feature\Api\V1;

use App\Models\KnowledgeArticle;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DirectoryApiTest extends TestCase
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

    private function makePublicUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'current_organization_id' => $this->org->id,
            'is_public_in_directory' => true,
            'name' => 'Ali Ahmad',
            'industry' => 'Perubatan',
            'expertise' => 'Doktor',
        ], $attributes));
    }

    public function test_directory_requires_authentication(): void
    {
        $this->getJson('/api/v1/directory')->assertUnauthorized();
    }

    public function test_member_can_search_directory(): void
    {
        $this->makePublicUser(['member_no' => 'PKPIM-0100']);
        $this->makePublicUser([
            'name' => 'Siti Aminah',
            'industry' => 'Pendidikan',
            'member_no' => 'PKPIM-0101',
        ]);
        $this->makePublicUser(['is_public_in_directory' => false, 'member_no' => 'PKPIM-0102']);

        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/directory?search=Ali')
            ->assertOk()
            ->assertJsonPath('data.filters.search', 'Ali')
            ->assertJsonCount(1, 'data.users')
            ->assertJsonPath('data.users.0.name', 'Ali Ahmad')
            ->assertJsonPath('data.users.0.organization.name', 'PKPIM')
            ->assertJsonPath('data.industries.0', 'Pendidikan')
            ->assertJsonPath('data.industries.1', 'Perubatan');
    }

    public function test_member_can_filter_directory_by_industry(): void
    {
        $this->makePublicUser(['member_no' => 'PKPIM-0200']);
        $this->makePublicUser([
            'name' => 'Siti Aminah',
            'industry' => 'Pendidikan',
            'member_no' => 'PKPIM-0201',
        ]);

        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/directory?industry=Pendidikan')
            ->assertOk()
            ->assertJsonCount(1, 'data.users')
            ->assertJsonPath('data.users.0.name', 'Siti Aminah')
            ->assertJsonPath('data.filters.industry', 'Pendidikan');
    }

    public function test_public_card_is_accessible_without_auth(): void
    {
        $this->makePublicUser([
            'member_no' => 'PKPIM-0300',
            'profile_photo_path' => null,
        ]);

        $this->getJson('/api/v1/card/PKPIM-0300')
            ->assertOk()
            ->assertJsonPath('data.card.name', 'Ali Ahmad')
            ->assertJsonPath('data.card.member_no', 'PKPIM-0300')
            ->assertJsonPath('data.card.organization.name', 'PKPIM');
    }

    public function test_public_card_returns_404_for_unknown_member(): void
    {
        $this->getJson('/api/v1/card/PKPIM-9999')->assertNotFound();
    }

    public function test_chat_returns_fallback_reply_when_no_gemini_key(): void
    {
        KnowledgeArticle::create([
            'question' => 'Bagaimana bayar yuran?',
            'answer' => 'Guna portal pembayaran.',
            'keywords' => 'yuran bayaran',
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->member);

        $this->postJson('/api/v1/chat', ['message' => 'bayar yuran'])
            ->assertStatus(503)
            ->assertJsonPath('data.reply', 'AI chatbot belum dikonfigurasi. Sila hubungi admin.');
    }

    public function test_chat_requires_authentication(): void
    {
        $this->postJson('/api/v1/chat', ['message' => 'hello'])->assertUnauthorized();
    }

    public function test_member_can_list_notifications(): void
    {
        $this->member->notify(new class extends Notification
        {
            public function via(object $notifiable): array
            {
                return ['database'];
            }

            public function toDatabase(object $notifiable): array
            {
                return ['title' => 'Pengumuman', 'content' => 'Test'];
            }
        });

        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data.notifications')
            ->assertJsonPath('data.notifications.0.data.title', 'Pengumuman');
    }

    public function test_member_can_mark_all_notifications_read(): void
    {
        $this->member->notify(new class extends Notification
        {
            public function via(object $notifiable): array
            {
                return ['database'];
            }

            public function toDatabase(object $notifiable): array
            {
                return ['title' => 'Pengumuman', 'content' => 'Test'];
            }
        });

        Sanctum::actingAs($this->member);

        $this->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.success', true);

        $this->assertSame(0, $this->member->fresh()->unreadNotifications->count());
    }

    public function test_notifications_require_authentication(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
        $this->postJson('/api/v1/notifications/read-all')->assertUnauthorized();
    }
}

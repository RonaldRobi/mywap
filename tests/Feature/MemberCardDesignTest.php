<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MemberCardDesignTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $superadmin;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        foreach (['Superadmin', 'Admin', 'Member'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }

        $this->org = Organization::factory()->create(['name' => 'ABIM', 'slug' => 'abim']);

        $this->superadmin = User::factory()->create();
        $this->superadmin->assignRole('Superadmin');

        $this->member = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
            'member_no' => 'ABIM-0001',
        ]);
        $this->member->assignRole('Member');
    }

    public function test_superadmin_can_upload_front_design_per_organization(): void
    {
        $this->actingAs($this->superadmin)
            ->post(route('superadmin.organizations.card-front.update', $this->org), [
                'card_front' => UploadedFile::fake()->image('depan.jpg', 638, 1004),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $path = $this->org->fresh()->card_front_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $path));
    }

    public function test_superadmin_can_upload_shared_back_design(): void
    {
        $this->actingAs($this->superadmin)
            ->post(route('superadmin.organizations.card-back.update'), [
                'card_back' => UploadedFile::fake()->image('belakang.jpg', 638, 1004),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNotNull(AppSetting::singleton()->member_card_back_path);
    }

    public function test_member_cannot_upload_designs(): void
    {
        $this->actingAs($this->member)
            ->post(route('superadmin.organizations.card-front.update', $this->org), [
                'card_front' => UploadedFile::fake()->image('depan.jpg', 638, 1004),
            ])
            ->assertForbidden();

        $this->assertNull($this->org->fresh()->card_front_path);
    }

    public function test_superadmin_can_remove_front_design(): void
    {
        $this->org->update(['card_front_path' => '/storage/member-cards/front/x.jpg']);

        $this->actingAs($this->superadmin)
            ->delete(route('superadmin.organizations.card-front.destroy', $this->org))
            ->assertRedirect();

        $this->assertNull($this->org->fresh()->card_front_path);
    }

    public function test_card_api_returns_front_and_back_designs(): void
    {
        $this->org->update(['card_front_path' => '/storage/member-cards/front/abim.jpg']);
        AppSetting::singleton()->update(['member_card_back_path' => '/storage/member-cards/back/all.jpg']);

        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/member/card')
            ->assertOk()
            ->assertJsonPath('data.card.card_front_path', '/storage/member-cards/front/abim.jpg')
            ->assertJsonPath('data.card.card_back_path', '/storage/member-cards/back/all.jpg');
    }
}

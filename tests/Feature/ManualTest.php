<?php

namespace Tests\Feature;

use App\Models\Manual;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManualTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $superadmin;

    private User $admin;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $this->org = Organization::factory()->create();

        $this->superadmin = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
        ]);
        $this->superadmin->assignRole('Superadmin');

        $this->admin = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
        ]);
        $this->admin->assignRole('Admin');

        $this->member = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
        ]);
        $this->member->assignRole('Member');
    }

    private function manual(string $audience, string $title): Manual
    {
        return Manual::create([
            'title' => $title,
            'audience' => $audience,
            'file_path' => '/storage/manuals/'.$audience.'.pdf',
            'is_active' => true,
        ]);
    }

    public function test_member_only_sees_user_and_all_manuals(): void
    {
        $this->manual('user', 'Manual Ahli');
        $this->manual('admin', 'Manual Admin');
        $this->manual('all', 'Manual Untuk Semua');

        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/manuals')
            ->assertOk()
            ->assertJsonPath('data.audience', 'user')
            ->assertJsonCount(2, 'data.manuals')
            ->assertJsonPath('data.manuals.0.title', 'Manual Ahli')
            ->assertJsonPath('data.manuals.1.title', 'Manual Untuk Semua');
    }

    public function test_admin_only_sees_admin_and_all_manuals(): void
    {
        $this->manual('user', 'Manual Ahli');
        $this->manual('admin', 'Manual Admin');
        $this->manual('all', 'Manual Untuk Semua');

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/manuals')
            ->assertOk()
            ->assertJsonPath('data.audience', 'admin')
            ->assertJsonCount(2, 'data.manuals')
            ->assertJsonPath('data.manuals.0.title', 'Manual Admin')
            ->assertJsonPath('data.manuals.1.title', 'Manual Untuk Semua');
    }

    public function test_inactive_manuals_are_hidden(): void
    {
        $active = $this->manual('user', 'Aktif');
        $inactive = $this->manual('user', 'Tidak Aktif');
        $inactive->update(['is_active' => false]);

        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/manuals')
            ->assertOk()
            ->assertJsonCount(1, 'data.manuals')
            ->assertJsonPath('data.manuals.0.id', $active->id);
    }

    public function test_guest_cannot_access_manuals(): void
    {
        $this->getJson('/api/v1/manuals')->assertUnauthorized();
    }

    public function test_superadmin_can_upload_manual(): void
    {
        Storage::fake('public');

        $this->actingAs($this->superadmin)
            ->post(route('admin.manuals.store'), [
                'title' => 'Manual Pengguna v1',
                'audience' => 'user',
                'sort_order' => 1,
                'is_active' => true,
                'document' => UploadedFile::fake()->create('manual.pdf', 200, 'application/pdf'),
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('manuals', [
            'title' => 'Manual Pengguna v1',
            'audience' => 'user',
        ]);
    }

    public function test_org_admin_cannot_access_superadmin_manual_manager(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.manuals.index'))
            ->assertForbidden();
    }
}

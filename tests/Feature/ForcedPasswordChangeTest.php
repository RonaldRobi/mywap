<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ForcedPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_branch_admin_can_change_temporary_password_and_leave_profile(): void
    {
        Role::findOrCreate('Admin Cawangan');

        $org = Organization::factory()->create();
        $admin = User::factory()->create([
            'current_organization_id' => $org->id,
            'password' => Hash::make('temp-password'),
            'must_change_password' => true,
            'profile_completed_at' => now(),
        ]);
        $admin->assignRole('Admin Cawangan');

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertRedirect('/profile/change-password');

        $this->actingAs($admin)
            ->get('/profile')
            ->assertRedirect('/profile/change-password');

        $this->actingAs($admin)->get('/profile/change-password')->assertOk();

        $this->actingAs($admin)
            ->put('/password', [
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertRedirect('/dashboard');

        $this->assertFalse((bool) $admin->fresh()->must_change_password);
        $this->assertTrue(Hash::check('new-password-123', $admin->fresh()->password));

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertRedirect(route('branch-admin.unassigned'));
    }
}

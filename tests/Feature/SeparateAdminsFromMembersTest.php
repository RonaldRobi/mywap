<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeparateAdminsFromMembersTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_account_promoted_to_admin_is_returned_to_member(): void
    {
        foreach (['Superadmin', 'Admin', 'Admin Cawangan', 'Member'] as $role) {
            Role::findOrCreate($role);
        }

        $org = Organization::factory()->create(['slug' => 'abim']);

        $promoted = User::factory()->create([
            'current_organization_id' => $org->id,
            'member_no' => 'A00001',
            'member_no_sequence' => 1,
            'is_public_in_directory' => true,
        ]);
        $promoted->assignRole('Admin');

        $pureAdmin = User::factory()->create([
            'current_organization_id' => $org->id,
            'member_no' => null,
            'ic_number' => '700101011111',
            'is_public_in_directory' => true,
            'profile_completed_at' => null,
        ]);
        $pureAdmin->assignRole('Admin');

        $this->artisan('admins:separate-from-members', ['--apply' => true])
            ->assertExitCode(0);

        $promoted->refresh();
        $pureAdmin->refresh();

        $this->assertTrue($promoted->hasRole('Member'));
        $this->assertFalse($promoted->hasRole('Admin'));
        $this->assertSame('A00001', $promoted->member_no);
        $this->assertFalse((bool) $promoted->is_public_in_directory);

        $this->assertTrue($pureAdmin->hasRole('Admin'));
        $this->assertNull($pureAdmin->ic_number);
        $this->assertFalse((bool) $pureAdmin->is_public_in_directory);
        $this->assertNotNull($pureAdmin->profile_completed_at);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\AbimStateAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AbimStateAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    private Organization $abim;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $this->abim = Organization::factory()->create(['name' => 'ABIM', 'slug' => 'abim']);

        foreach (AbimStateAdminSeeder::STATES as $state) {
            Branch::create([
                'organization_id' => $this->abim->id,
                'name' => $state,
                'state' => $state,
                'is_active' => true,
            ]);
        }
    }

    public function test_it_creates_one_admin_per_state(): void
    {
        $this->seed(AbimStateAdminSeeder::class);

        $this->assertSame(14, User::withoutGlobalScopes()->whereNotNull('email')->where('email', 'like', 'abim-%@mywap.my')->count());

        foreach (AbimStateAdminSeeder::STATES as $state) {
            $email = 'abim-'.Str::slug($state).'@mywap.my';
            $user = User::withoutGlobalScopes()->where('email', $email)->first();

            $this->assertNotNull($user, "Missing admin for {$state}");
            $this->assertSame($this->abim->id, (int) $user->current_organization_id);
            $this->assertTrue($user->hasRole('Admin'), "{$email} is not an Admin");
            $this->assertNotNull($user->branch_id, "{$email} has no branch");
            $this->assertTrue(Hash::check(AbimStateAdminSeeder::PASSWORD, $user->password));
        }
    }

    public function test_it_is_idempotent_and_does_not_reset_changed_password(): void
    {
        $this->seed(AbimStateAdminSeeder::class);

        $email = 'abim-selangor@mywap.my';
        $user = User::withoutGlobalScopes()->where('email', $email)->firstOrFail();
        $user->update(['password' => 'changed-by-admin']);

        $this->seed(AbimStateAdminSeeder::class);

        $this->assertSame(14, User::withoutGlobalScopes()->where('email', 'like', 'abim-%@mywap.my')->count());
        $this->assertTrue(Hash::check('changed-by-admin', $user->fresh()->password));
    }
}

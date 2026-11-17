<?php

namespace Tests\Feature;

use App\Console\Commands\ProcessAgeTransitions;
use App\Models\AppSetting;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProcessAgeTransitionsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $pkpim;

    private Organization $abim;

    private Organization $wadah;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pkpim = Organization::factory()->create(['name' => 'PKPIM', 'slug' => 'pkpim', 'min_age' => 0, 'max_age' => 19]);
        $this->abim = Organization::factory()->create(['name' => 'ABIM', 'slug' => 'abim', 'min_age' => 20, 'max_age' => 29]);
        $this->wadah = Organization::factory()->create(['name' => 'WADAH', 'slug' => 'wadah', 'min_age' => 30, 'max_age' => null]);

        Role::findOrCreate('Member');
        Role::findOrCreate('Admin Cawangan');
    }

    public function test_engine_is_frozen_when_disabled(): void
    {
        AppSetting::singleton()->update(['age_transition_enabled' => false]);

        $user = User::factory()->create([
            'dob' => now()->subYears(35),
            'current_organization_id' => $this->pkpim->id,
        ]);

        $this->artisan(ProcessAgeTransitions::class)
            ->expectsOutputToContain('DIBEKUKAN')
            ->assertExitCode(0);

        $this->assertSame($this->pkpim->id, $user->fresh()->current_organization_id);
    }

    public function test_engine_transitions_when_enabled(): void
    {
        AppSetting::singleton()->update(['age_transition_enabled' => true]);

        $user = User::factory()->create([
            'dob' => now()->subYears(35),
            'current_organization_id' => $this->pkpim->id,
        ]);

        $this->artisan(ProcessAgeTransitions::class)->assertExitCode(0);

        $this->assertSame($this->wadah->id, $user->fresh()->current_organization_id);
    }

    public function test_branch_admin_is_not_transitioned_or_demoted(): void
    {
        AppSetting::singleton()->update(['age_transition_enabled' => true]);

        $admin = User::factory()->create([
            'dob' => now()->subYears(35),
            'current_organization_id' => $this->pkpim->id,
            'member_no' => 'P00001',
        ]);
        $admin->assignRole('Admin Cawangan');

        $this->artisan(ProcessAgeTransitions::class)->assertExitCode(0);

        $admin->refresh();

        $this->assertSame($this->pkpim->id, $admin->current_organization_id);
        $this->assertTrue($admin->hasRole('Admin Cawangan'));
        $this->assertFalse($admin->hasRole('Member'));
    }

    public function test_transition_assigns_next_number_in_target_series(): void
    {
        AppSetting::singleton()->update(['age_transition_enabled' => true]);

        User::factory()->create([
            'dob' => null,
            'current_organization_id' => $this->wadah->id,
            'member_no' => 'W2550',
            'member_no_sequence' => 2550,
        ]);

        $user = User::factory()->create([
            'dob' => now()->subYears(35),
            'current_organization_id' => $this->abim->id,
            'member_no' => 'A01234',
            'member_no_sequence' => 1234,
            'original_member_no' => 'A01234',
        ]);

        $this->artisan(ProcessAgeTransitions::class)->assertExitCode(0);

        $user->refresh();

        $this->assertSame('W2551', $user->member_no);
        $this->assertSame(2551, $user->member_no_sequence);
        $this->assertSame('A01234', $user->original_member_no);

        $history = $user->transitionHistory()->first();

        $this->assertNotNull($history);
        $this->assertSame('A01234', $history->from_member_no);
        $this->assertSame('W2551', $history->to_member_no);
    }
}

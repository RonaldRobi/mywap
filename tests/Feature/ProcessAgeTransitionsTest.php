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

        $this->pkpim = Organization::factory()->create(['name' => 'PKPIM', 'min_age' => 0, 'max_age' => 19]);
        $this->abim = Organization::factory()->create(['name' => 'ABIM', 'min_age' => 20, 'max_age' => 29]);
        $this->wadah = Organization::factory()->create(['name' => 'WADAH', 'min_age' => 30, 'max_age' => null]);

        Role::findOrCreate('Member');
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
}

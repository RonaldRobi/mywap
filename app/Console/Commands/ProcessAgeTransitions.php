<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * ProcessAgeTransitions — The Age Transition Engine
 *
 * Scheduled to run daily at midnight.  For every active user whose
 * current_organization_id no longer matches their age tier, the command:
 *   1. Updates their current_organization_id.
 *   2. Syncs their Spatie role to the new tier.
 *   3. Fires UserOrganizationTransitioned, which queues logging + notification.
 *
 * Memory efficiency: chunkById(500) processes users in pages of 500, so even a
 * database with hundreds of thousands of members never exhausts PHP memory.
 *
 * Safety: withoutGlobalScopes() bypasses OrganizationScope so the command sees
 * ALL users regardless of the authenticated session (there is none in CLI).
 */
class ProcessAgeTransitions extends Command
{
    protected $signature = 'app:process-age-transitions
                            {--dry-run : Preview transitions without persisting any changes}';

    protected $description = 'Automatically migrate members to the correct NGO tier based on their age.';

    public function handle(): int
    {
        $isDryRun    = $this->option('dry-run');
        $transitioned = 0;
        $skipped      = 0;

        $this->info($isDryRun ? '🔍  Dry-run mode — no changes will be saved.' : '⚙️   Processing age transitions...');

        // Preload all three organizations to avoid N+1 inside the chunk loop.
        $organizations = \App\Models\Organization::all()->keyBy('id');

        \App\Models\User::withoutGlobalScopes()
            ->whereNotNull('dob')
            ->with('organization')
            ->chunkById(500, function ($users) use (
                $organizations,
                $isDryRun,
                &$transitioned,
                &$skipped
            ) {
                foreach ($users as $user) {
                    // Administrative accounts are management-only and are not
                    // part of member age lifecycle transitions.
                    if ($user->hasRole(['Admin', 'Superadmin'])) {
                        $skipped++;
                        continue;
                    }

                    $age            = $user->dob->age;
                    $correctOrg     = \App\Models\Organization::forAge($age);

                    if (! $correctOrg) {
                        $this->warn("  ⚠  No organization matched for age {$age} (user #{$user->id})");
                        $skipped++;
                        continue;
                    }

                    // Member is already in the correct tier — nothing to do.
                    if ($user->current_organization_id === $correctOrg->id) {
                        $skipped++;
                        continue;
                    }

                    $fromOrgId = $user->current_organization_id;
                    $toOrgName = $correctOrg->name;

                    $this->line("  ↪  User #{$user->id} ({$user->name}) age {$age} → {$toOrgName}");

                    if (! $isDryRun) {
                        // 1. Update the user's active organization.
                        $user->current_organization_id = $correctOrg->id;
                        $user->save();

                        // 2. Sync the Spatie role to reflect the new tier.
                        //    Each org tier gets the 'Member' base role; Admin/Superadmin
                        //    roles are NOT touched to preserve elevated permissions.
                        if (! $user->hasRole(['Admin', 'Superadmin'])) {
                            $user->syncRoles(['Member']);
                        }

                        // 3. Fire event — listener handles logging & notification asynchronously.
                        \App\Events\UserOrganizationTransitioned::dispatch(
                            $user,
                            $fromOrgId,
                            $correctOrg->id,
                        );
                    }

                    $transitioned++;
                }
            });

        $this->newLine();
        $this->info("✅  Done. Transitioned: {$transitioned}  |  Skipped (already correct): {$skipped}");

        return self::SUCCESS;
    }
}

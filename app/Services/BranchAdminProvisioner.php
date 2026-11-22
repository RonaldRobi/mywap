<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\BranchAdmin;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BranchAdminProvisioner
{
    /**
     * WADAH keeps its existing manual account flow. ABIM and PKPIM have
     * enough branches that each branch needs a ready manager account.
     */
    public function supports(Branch $branch): bool
    {
        return in_array($branch->organization?->slug, ['abim', 'pkpim'], true);
    }

    public function provision(Branch $branch, ?int $createdBy = null): ?User
    {
        if (! $this->supports($branch)) {
            return null;
        }

        return DB::transaction(function () use ($branch, $createdBy) {
            $existing = BranchAdmin::query()
                ->where('branch_id', $branch->id)
                ->where('role', BranchAdmin::ROLE_MANAGER)
                ->with('user')
                ->lockForUpdate()
                ->first();

            if ($existing?->user) {
                return $existing->user;
            }

            $organization = $branch->organization;

            // Older deployments may have the role and branch_id but not the
            // pivot row. Reuse that account instead of creating a duplicate.
            $legacyUser = User::withoutGlobalScopes()
                ->where('current_organization_id', $branch->organization_id)
                ->where('branch_id', $branch->id)
                ->whereHas('roles', fn ($query) => $query->where('name', 'Admin Cawangan'))
                ->first();

            if ($legacyUser) {
                BranchAdmin::create([
                    'branch_id' => $branch->id,
                    'user_id' => $legacyUser->id,
                    'created_by' => $createdBy,
                    'role' => BranchAdmin::ROLE_MANAGER,
                ]);

                return $legacyUser;
            }

            $email = $this->uniqueLoginEmail($organization?->slug ?? 'org', $branch);
            $temporaryPassword = Str::password(16, symbols: false);

            $attributes = [
                'name' => 'Admin '.($organization?->name ?? '').' '.$branch->name,
                'email' => $email,
                'password' => $temporaryPassword,
                'current_organization_id' => $branch->organization_id,
                'branch_id' => $branch->id,
                'is_active' => true,
                'is_public_in_directory' => false,
                'email_verified_at' => now(),
                'profile_completed_at' => now(),
            ];

            if (Schema::hasColumn('users', 'must_change_password')) {
                $attributes['must_change_password'] = true;
            }

            $user = User::withoutGlobalScopes()->create($attributes);
            $user->assignRole('Admin Cawangan');

            BranchAdmin::create([
                'branch_id' => $branch->id,
                'user_id' => $user->id,
                'created_by' => $createdBy,
                'role' => BranchAdmin::ROLE_MANAGER,
            ]);

            if ($createdBy) {
                ActivityLog::create([
                    'user_id' => $createdBy,
                    'organization_id' => $branch->organization_id,
                    'target_type' => User::class,
                    'target_id' => $user->id,
                    'action' => 'branch_admin_account_auto_created',
                    'description' => "Akaun admin cawangan/negeri {$branch->name} dicipta secara automatik.",
                ]);
            }

            return $user;
        });
    }

    private function uniqueLoginEmail(string $organizationSlug, Branch $branch): string
    {
        $base = Str::slug($organizationSlug.'-'.$branch->name) ?: $organizationSlug.'-branch-'.$branch->id;
        $email = $base.'@mywap.my';

        if (! User::withoutGlobalScopes()->where('email', $email)->exists()) {
            return $email;
        }

        return $base.'-'.$branch->id.'@mywap.my';
    }
}

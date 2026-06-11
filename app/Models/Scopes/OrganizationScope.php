<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * OrganizationScope — Multi-Tenancy Global Scope
 *
 * Architectural decision:
 * Rather than using a separate tenant table or sub-domains, MyMarhalah implements
 * "soft" multi-tenancy via a global scope on the User model.  Every Eloquent
 * query that targets users is automatically restricted to those sharing the
 * same current_organization_id as the currently authenticated user.
 *
 * Superadmin bypass:
 * Users with the 'Superadmin' role (managed by Spatie\Permission) see the full
 * ecosystem across all three NGO tiers — necessary for reporting and transition
 * oversight.
 *
 * CLI / Queue safety:
 * When there is no authenticated user (e.g. in Artisan commands or queued jobs)
 * the scope is skipped entirely so that background tasks such as the Age
 * Transition Scheduler can operate across all organizations without stubbing Auth.
 */
class OrganizationScope implements Scope
{
    /** @var array<string, array<string, bool>> */
    protected static array $columnCache = [];

    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $guard = Auth::guard();

        // During initial auth hydration (e.g., login/session restore), the guard
        // may not yet hold a user instance. Skipping here prevents recursive
        // user-provider queries that can exhaust memory.
        if (! method_exists($guard, 'hasUser') || ! $guard->hasUser()) {
            return;
        }

        // We also skip the scope during login requests to prevent "locked-out"
        // scenarios where an existing session for a different organization
        // prevents a new user from being found during Auth::attempt().
        if (request()->is('login*')) {
            return;
        }

        /** @var \App\Models\User|null $user */
        $user = $guard->user();

        if (! $user) {
            return;
        }

        // Superadmins have a cross-organization view of the entire ecosystem.
        if ($user->hasRole('Superadmin')) {
            return;
        }

        if (! $user->current_organization_id) {
            return;
        }

        $table = $model->getTable();

        if (! isset(self::$columnCache[$table])) {
            $columns = DB::connection($model->getConnectionName())
                ->getSchemaBuilder()
                ->getColumnListing($table);

            self::$columnCache[$table] = [
                'current_organization_id' => in_array('current_organization_id', $columns, true),
                'organization_id' => in_array('organization_id', $columns, true),
            ];
        }

        $orgColumn = self::$columnCache[$table]['current_organization_id']
            ? 'current_organization_id'
            : (self::$columnCache[$table]['organization_id'] ? 'organization_id' : null);

        if (! $orgColumn) {
            return;
        }

        $builder->where($table . '.' . $orgColumn, $user->current_organization_id);
    }
}

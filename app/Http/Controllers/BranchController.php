<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchAdmin;
use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    /**
     * Display the branch management page.
     *
     * Superadmin: sees all 3 organisations with their branches.
     * Admin:      sees only their own organisation's branches.
     */
    public function index(): Response
    {
        $user = Auth::user();
        $isSuperadmin = $user->hasRole('Superadmin');
        $isBranchAdmin = $user->isBranchAdmin();
        $managedBranchId = $isBranchAdmin ? $user->managedBranchId() : null;
        $managedBranchIds = $isBranchAdmin ? $user->managedBranchIds() : [];

        $organizations = Organization::query()
            ->when(! $isSuperadmin, fn ($q) => $q->where('id', $user->current_organization_id))
            ->with(['branches' => fn ($q) => $q->withCount('members')->with(['admins', 'secretaries'])->when($isBranchAdmin, fn ($query) => $query->whereKey($managedBranchIds))->orderBy('state')])
            ->orderBy('sort_order')
            ->orderBy('min_age')
            ->get()
            ->map(fn (Organization $org) => [
                'id' => $org->id,
                'name' => $org->name,
                'slug' => $org->slug,
                'color_theme' => $org->color_theme,
                'logo_path' => $org->logo_path,
                'branches' => $org->branches->map(fn (Branch $b) => [
                    'id' => $b->id,
                    'organization_id' => $b->organization_id,
                    'name' => $b->name,
                    'state' => $b->state,
                    'address' => $b->address,
                    'phone' => $b->phone,
                    'email' => $b->email,
                    'logo_path' => $b->logo_path,
                    'is_active' => $b->is_active,
                    'member_count' => $b->members_count,
                    'admins' => $b->admins->map(fn ($admin) => [
                        'id' => $admin->id,
                        'name' => $admin->name,
                        'email' => $admin->email,
                        'member_no' => $admin->member_no,
                        'last_login_at' => $admin->first_login_at?->toISOString(),
                        'managed_branch_ids' => $admin->managedBranchIds(),
                    ])->values(),
                    'manager' => $b->admins->first() ? [
                        'id' => $b->admins->first()->id,
                        'name' => $b->admins->first()->name,
                        'email' => $b->admins->first()->email,
                        'managed_branch_ids' => $b->admins->first()->managedBranchIds(),
                    ] : null,
                    'secretaries' => $b->secretaries->map(fn ($secretary) => [
                        'id' => $secretary->id,
                        'name' => $secretary->name,
                        'email' => $secretary->email,
                        'member_no' => $secretary->member_no,
                    ])->values(),
                    'secretary_limit' => BranchAdmin::MAX_SETIAUSAHA,
                ])->values(),
            ]);

        return Inertia::render('Admin/BranchManage', [
            'organizations' => $organizations,
            'isBranchAdmin' => $isBranchAdmin,
            'managedBranchId' => $managedBranchId,
            'managedBranchIds' => $managedBranchIds,
            'unassignedAdmins' => $isBranchAdmin ? [] : User::query()
                ->role('Admin Cawangan')
                ->whereDoesntHave('branchAdminAssignments', fn ($q) => $q->where('role', BranchAdmin::ROLE_MANAGER))
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'current_organization_id', 'branch_id']),
        ]);
    }

    /**
     * Store a new branch.
     * Admin may only create branches for their own organisation.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        abort_if($user->isBranchAdmin(), 403);
        $isSuperadmin = $user->hasRole('Superadmin');

        $data = $request->validate([
            'organization_id' => ['required', 'exists:organizations,id'],
            'name' => ['required', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:120'],
            'is_active' => ['boolean'],
        ]);

        // Admins can only add branches under their own organisation
        if (! $isSuperadmin && (int) $data['organization_id'] !== (int) $user->current_organization_id) {
            abort(403, 'Anda tidak dibenarkan menambah cawangan organisasi lain.');
        }

        Branch::create($data);

        return back()->with('success', 'Cawangan berjaya ditambah!');
    }

    /**
     * Update an existing branch.
     */
    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $user = Auth::user();
        abort_if($user->isBranchAdmin(), 403);
        $isSuperadmin = $user->hasRole('Superadmin');

        if (! $isSuperadmin && $branch->organization_id !== $user->current_organization_id) {
            abort(403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:120'],
            'is_active' => ['boolean'],
        ]);

        $branch->update($data);

        return back()->with('success', 'Cawangan berjaya dikemaskini!');
    }

    /**
     * Upload a logo for a specific branch.
     * Falls back to org logo if none uploaded.
     */
    public function updateLogo(Request $request, Branch $branch): RedirectResponse
    {
        $user = Auth::user();
        abort_if($user->isBranchAdmin(), 403);
        $isSuperadmin = $user->hasRole('Superadmin');

        if (! $isSuperadmin && $branch->organization_id !== $user->current_organization_id) {
            abort(403);
        }

        $request->validate([
            'branch_logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
        ]);

        if ($branch->logo_path) {
            $oldPath = ltrim(str_replace('/storage/', '', parse_url((string) $branch->logo_path, PHP_URL_PATH) ?? ''), '/');
            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $stored = $request->file('branch_logo')->store('logos/branches', 'public');
        $branch->update(['logo_path' => '/storage/'.ltrim($stored, '/')]);

        return back()->with('success', 'Logo cawangan berjaya dikemaskini!');
    }

    /**
     * Delete the logo for a specific branch.
     */
    public function deleteLogo(Branch $branch): RedirectResponse
    {
        $user = Auth::user();
        $isSuperadmin = $user->hasRole('Superadmin');

        if (! $isSuperadmin && $branch->organization_id !== $user->current_organization_id) {
            abort(403);
        }

        if ($branch->logo_path) {
            $oldPath = ltrim(str_replace('/storage/', '', parse_url((string) $branch->logo_path, PHP_URL_PATH) ?? ''), '/');
            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $branch->update(['logo_path' => null]);

        return back()->with('success', 'Logo cawangan berjaya dipadam!');
    }

    /**
     * Delete a branch (only if it has no active members).
     */
    public function destroy(Branch $branch): RedirectResponse
    {
        $user = Auth::user();
        abort_if($user->isBranchAdmin(), 403);
        $isSuperadmin = $user->hasRole('Superadmin');

        if (! $isSuperadmin && $branch->organization_id !== $user->current_organization_id) {
            abort(403);
        }

        if ($branch->members()->count() > 0) {
            return back()->with('error', 'Cawangan ini masih mempunyai ahli. Pindahkan ahli terlebih dahulu sebelum memadam cawangan.');
        }

        $branch->delete();

        return back()->with('success', 'Cawangan berjaya dipadam!');
    }

    /**
     * Create the shared login account for a branch.
     */
    public function storeAdminAccount(Request $request, Branch $branch): RedirectResponse
    {
        $this->authorizeBranchManagement($branch);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $created = DB::transaction(function () use ($branch, $data) {
            $existing = BranchAdmin::query()
                ->where('branch_id', $branch->id)
                ->where('role', BranchAdmin::ROLE_MANAGER)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return null;
            }

            $user = User::withoutGlobalScopes()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'current_organization_id' => $branch->organization_id,
                'branch_id' => $branch->id,
                'is_active' => true,
                'is_public_in_directory' => false,
                'email_verified_at' => now(),
                'profile_completed_at' => now(),
            ]);

            $user->assignRole('Admin Cawangan');

            BranchAdmin::create([
                'branch_id' => $branch->id,
                'user_id' => $user->id,
                'created_by' => Auth::id(),
                'role' => BranchAdmin::ROLE_MANAGER,
            ]);

            ActivityLog::create([
                'user_id' => Auth::id(),
                'organization_id' => $branch->organization_id,
                'target_type' => User::class,
                'target_id' => $user->id,
                'action' => 'branch_admin_account_created',
                'description' => "Akaun admin cawangan {$branch->name} dicipta.",
            ]);

            return $user;
        });

        if (! $created) {
            return back()->with('error', 'Cawangan ini sudah mempunyai akaun admin. Gunakan pilihan tukar kata laluan.');
        }

        return back()->with('success', 'Akaun admin cawangan berjaya dicipta.');
    }

    /**
     * Attach an existing unassigned Admin Cawangan account to this branch.
     */
    public function assignAdminAccount(Request $request, Branch $branch): RedirectResponse
    {
        $this->authorizeBranchManagement($branch);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $result = DB::transaction(function () use ($branch, $data) {
            $managerExists = BranchAdmin::query()
                ->where('branch_id', $branch->id)
                ->where('role', BranchAdmin::ROLE_MANAGER)
                ->lockForUpdate()
                ->exists();

            if ($managerExists) {
                return ['error' => 'Cawangan ini sudah mempunyai akaun admin.'];
            }

            $target = User::withoutGlobalScopes()->findOrFail($data['user_id']);

            if ($target->trashed() || ! $target->is_active) {
                return ['error' => 'Akaun tersebut tidak aktif.'];
            }

            if ($target->current_organization_id && (int) $target->current_organization_id !== (int) $branch->organization_id) {
                return ['error' => 'Akaun tersebut milik organisasi lain.'];
            }

            if ($target->branchAdminAssignments()
                ->where('branch_id', $branch->id)
                ->where('role', BranchAdmin::ROLE_MANAGER)
                ->exists()) {
                return ['error' => 'Akaun tersebut sudah menjadi admin cawangan ini.'];
            }

            if (! $target->hasRole('Admin Cawangan')) {
                $target->assignRole('Admin Cawangan');
            }

            $target->forceFill([
                'current_organization_id' => $branch->organization_id,
                'branch_id' => $target->branch_id ?? $branch->id,
                'is_active' => true,
                'email_verified_at' => $target->email_verified_at ?? now(),
                'profile_completed_at' => $target->profile_completed_at ?? now(),
            ])->save();

            BranchAdmin::create([
                'branch_id' => $branch->id,
                'user_id' => $target->id,
                'created_by' => Auth::id(),
                'role' => BranchAdmin::ROLE_MANAGER,
            ]);

            ActivityLog::create([
                'user_id' => Auth::id(),
                'organization_id' => $branch->organization_id,
                'target_type' => User::class,
                'target_id' => $target->id,
                'action' => 'branch_admin_account_assigned',
                'description' => "Akaun admin sedia ada ditugaskan ke cawangan {$branch->name}.",
            ]);

            return ['success' => 'Akaun admin berjaya ditugaskan ke cawangan ini.'];
        });

        if (isset($result['error'])) {
            return back()->with('error', $result['error']);
        }

        return back()->with('success', $result['success']);
    }

    /**
     * Tetapkan cawangan tambahan yang diurus oleh admin cawangan ini.
     *
     * Satu akaun boleh mengurus beberapa cawangan dalam organisasi yang sama.
     * Cawangan yang sudah mempunyai admin lain tidak akan diambil alih.
     */
    public function syncAdminBranches(Request $request, Branch $branch): RedirectResponse
    {
        $this->authorizeBranchManagement($branch);

        $data = $request->validate([
            'branch_ids' => ['array'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
        ]);

        $manager = BranchAdmin::query()
            ->where('branch_id', $branch->id)
            ->where('role', BranchAdmin::ROLE_MANAGER)
            ->with('user')
            ->first();

        if (! $manager?->user) {
            return back()->with('error', 'Cawangan ini belum mempunyai akaun admin.');
        }

        $requested = collect($data['branch_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->push((int) $branch->id)
            ->unique()
            ->values();

        $branches = Branch::query()
            ->whereIn('id', $requested)
            ->where('organization_id', $branch->organization_id)
            ->get()
            ->keyBy('id');

        if ($branches->count() !== $requested->count()) {
            return back()->with('error', 'Ada cawangan yang tidak sah untuk organisasi ini.');
        }

        $skipped = [];
        $assigned = [];

        DB::transaction(function () use ($branches, $branch, $manager, &$skipped, &$assigned) {
            foreach ($branches as $target) {
                $existing = BranchAdmin::query()
                    ->where('branch_id', $target->id)
                    ->where('role', BranchAdmin::ROLE_MANAGER)
                    ->lockForUpdate()
                    ->first();

                if ($existing && (int) $existing->user_id !== (int) $manager->user_id) {
                    $skipped[] = $target->name;

                    continue;
                }

                BranchAdmin::updateOrCreate(
                    ['branch_id' => $target->id, 'user_id' => $manager->user_id],
                    ['role' => BranchAdmin::ROLE_MANAGER, 'created_by' => Auth::id()]
                );

                $assigned[] = $target->name;
            }

            BranchAdmin::query()
                ->where('user_id', $manager->user_id)
                ->where('role', BranchAdmin::ROLE_MANAGER)
                ->where('branch_id', '!=', $branch->id)
                ->whereNotIn('branch_id', $branches->keys())
                ->delete();
        });

        ActivityLog::create([
            'user_id' => Auth::id(),
            'organization_id' => $branch->organization_id,
            'target_type' => User::class,
            'target_id' => $manager->user_id,
            'action' => 'branch_admin_branches_synced',
            'description' => 'Skop cawangan admin dikemas kini: '.implode(', ', $assigned).'.',
        ]);

        if ($skipped !== []) {
            return back()->with(
                'success',
                'Skop cawangan dikemas kini. Cawangan berikut sudah mempunyai admin lain: '.implode(', ', $skipped).'.'
            );
        }

        return back()->with('success', 'Skop cawangan admin berjaya dikemas kini.');
    }

    /**
     * Reset the shared branch admin password.
     */
    public function updateAdminPassword(Request $request, Branch $branch): RedirectResponse
    {
        $this->authorizeBranchManagement($branch);

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $manager = BranchAdmin::query()
            ->where('branch_id', $branch->id)
            ->where('role', BranchAdmin::ROLE_MANAGER)
            ->with('user')
            ->first();

        if (! $manager?->user) {
            return back()->with('error', 'Cawangan ini belum mempunyai akaun admin.');
        }

        $manager->user->update(['password' => $data['password']]);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'organization_id' => $branch->organization_id,
            'target_type' => User::class,
            'target_id' => $manager->user_id,
            'action' => 'branch_admin_password_reset',
            'description' => "Kata laluan admin cawangan {$branch->name} ditukar.",
        ]);

        return back()->with('success', 'Kata laluan admin cawangan berjaya dikemas kini.');
    }

    /**
     * Revoke the shared branch admin account.
     */
    public function destroyAdminAccount(Branch $branch): RedirectResponse
    {
        $this->authorizeBranchManagement($branch);

        $manager = BranchAdmin::query()
            ->where('branch_id', $branch->id)
            ->where('role', BranchAdmin::ROLE_MANAGER)
            ->with('user')
            ->first();

        if (! $manager?->user) {
            return back()->with('error', 'Cawangan ini belum mempunyai akaun admin.');
        }

        $manager->user->removeRole('Admin Cawangan');
        $manager->user->update(['is_active' => false]);
        $manager->delete();

        ActivityLog::create([
            'user_id' => Auth::id(),
            'organization_id' => $branch->organization_id,
            'target_type' => User::class,
            'target_id' => $manager->user_id,
            'action' => 'branch_admin_account_revoked',
            'description' => "Akses admin cawangan {$branch->name} dibatalkan.",
        ]);

        return back()->with('success', 'Akses admin cawangan telah dibatalkan.');
    }

    /**
     * Appoint a member as setiausaha for this branch (maximum 3).
     */
    public function storeSecretary(Request $request, Branch $branch): RedirectResponse
    {
        $this->authorizeBranchManagement($branch);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $result = DB::transaction(function () use ($branch, $data) {
            $target = User::withoutGlobalScopes()->findOrFail($data['user_id']);

            if ($target->trashed() || ! $target->is_active) {
                return ['error' => 'Ahli tersebut tidak aktif.'];
            }

            if ((int) $target->current_organization_id !== (int) $branch->organization_id) {
                return ['error' => 'Ahli tersebut bukan dalam organisasi cawangan ini.'];
            }

            if ((int) $target->branch_id !== (int) $branch->id) {
                return ['error' => 'Ahli tersebut bukan dalam cawangan ini.'];
            }

            if (! $target->email || $target->emailIsPlaceholder()) {
                return ['error' => 'Ahli ini tiada emel sebenar. Kemas kini emel sebelum melantik setiausaha.'];
            }

            $existing = BranchAdmin::query()
                ->where('branch_id', $branch->id)
                ->where('user_id', $target->id)
                ->first();

            if ($existing) {
                return ['error' => 'Ahli ini sudah dilantik untuk cawangan ini.'];
            }

            $count = BranchAdmin::query()
                ->where('branch_id', $branch->id)
                ->where('role', BranchAdmin::ROLE_SETIAUSAHA)
                ->lockForUpdate()
                ->count();

            if ($count >= BranchAdmin::MAX_SETIAUSAHA) {
                return ['error' => 'Cawangan ini sudah mempunyai '.BranchAdmin::MAX_SETIAUSAHA.' setiausaha.'];
            }

            BranchAdmin::create([
                'branch_id' => $branch->id,
                'user_id' => $target->id,
                'created_by' => Auth::id(),
                'role' => BranchAdmin::ROLE_SETIAUSAHA,
            ]);

            ActivityLog::create([
                'user_id' => Auth::id(),
                'organization_id' => $branch->organization_id,
                'target_type' => User::class,
                'target_id' => $target->id,
                'action' => 'branch_secretary_appointed',
                'description' => "{$target->name} dilantik sebagai setiausaha cawangan {$branch->name}.",
            ]);

            return ['success' => 'Setiausaha cawangan berjaya dilantik.'];
        });

        if (isset($result['error'])) {
            return back()->with('error', $result['error']);
        }

        return back()->with('success', $result['success']);
    }

    /**
     * Remove a setiausaha from this branch.
     */
    public function destroySecretary(Branch $branch, User $user): RedirectResponse
    {
        $this->authorizeBranchManagement($branch);

        $assignment = BranchAdmin::query()
            ->where('branch_id', $branch->id)
            ->where('user_id', $user->id)
            ->where('role', BranchAdmin::ROLE_SETIAUSAHA)
            ->first();

        if (! $assignment) {
            return back()->with('error', 'Ahli tersebut bukan setiausaha cawangan ini.');
        }

        $assignment->delete();

        ActivityLog::create([
            'user_id' => Auth::id(),
            'organization_id' => $branch->organization_id,
            'target_type' => User::class,
            'target_id' => $user->id,
            'action' => 'branch_secretary_removed',
            'description' => "{$user->name} dibuang sebagai setiausaha cawangan {$branch->name}.",
        ]);

        return back()->with('success', 'Setiausaha cawangan berjaya dibuang.');
    }

    private function authorizeBranchManagement(Branch $branch): void
    {
        $user = Auth::user();
        $isSuperadmin = $user->hasRole('Superadmin');

        if (! $isSuperadmin && (int) $branch->organization_id !== (int) $user->current_organization_id) {
            abort(403);
        }

        if ($user->isBranchAdmin()) {
            abort_unless($user->canManageBranch($branch->id), 403);

            return;
        }

        if (! $isSuperadmin && ! $user->isOrgAdmin()) {
            abort(403);
        }
    }
}

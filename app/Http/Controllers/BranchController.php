<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        $organizations = Organization::query()
            ->when(! $isSuperadmin, fn ($q) => $q->where('id', $user->current_organization_id))
            ->with(['branches' => fn ($q) => $q->withCount('members')->orderBy('state')])
            ->orderBy('sort_order')
            ->orderBy('min_age')
            ->get()
            ->map(fn (Organization $org) => [
                'id'          => $org->id,
                'name'        => $org->name,
                'slug'        => $org->slug,
                'color_theme' => $org->color_theme,
                'logo_path'   => $org->logo_path,
                'branches'    => $org->branches->map(fn (Branch $b) => [
                    'id'           => $b->id,
                    'name'         => $b->name,
                    'state'        => $b->state,
                    'address'      => $b->address,
                    'phone'        => $b->phone,
                    'email'        => $b->email,
                    'logo_path'    => $b->logo_path,
                    'is_active'    => $b->is_active,
                    'member_count' => $b->members_count,
                ])->values(),
            ]);

        return Inertia::render('Admin/BranchManage', [
            'organizations' => $organizations,
        ]);
    }

    /**
     * Store a new branch.
     * Admin may only create branches for their own organisation.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $isSuperadmin = $user->hasRole('Superadmin');

        $data = $request->validate([
            'organization_id' => ['required', 'exists:organizations,id'],
            'name'            => ['required', 'string', 'max:120'],
            'state'           => ['nullable', 'string', 'max:80'],
            'address'         => ['nullable', 'string', 'max:500'],
            'phone'           => ['nullable', 'string', 'max:30'],
            'email'           => ['nullable', 'email', 'max:120'],
            'is_active'       => ['boolean'],
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
        $isSuperadmin = $user->hasRole('Superadmin');

        if (! $isSuperadmin && $branch->organization_id !== $user->current_organization_id) {
            abort(403);
        }

        $data = $request->validate([
            'name'      => ['required', 'string', 'max:120'],
            'state'     => ['nullable', 'string', 'max:80'],
            'address'   => ['nullable', 'string', 'max:500'],
            'phone'     => ['nullable', 'string', 'max:30'],
            'email'     => ['nullable', 'email', 'max:120'],
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
        $branch->update(['logo_path' => '/storage/' . ltrim($stored, '/')]);

        return back()->with('success', 'Logo cawangan berjaya dikemaskini!');
    }

    /**
     * Delete a branch (only if it has no active members).
     */
    public function destroy(Branch $branch): RedirectResponse
    {
        $user = Auth::user();
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
}

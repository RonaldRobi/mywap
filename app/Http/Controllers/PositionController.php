<?php

namespace App\Http\Controllers;

use App\Models\OrganizationPosition;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PositionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $orgId = $user->current_organization_id;

        $positionCounts = User::where('current_organization_id', $orgId)
            ->whereNotNull('position_id')
            ->selectRaw('position_id, COUNT(*) as count')
            ->groupBy('position_id')
            ->pluck('count', 'position_id');

        $positions = OrganizationPosition::where('organization_id', $orgId)
            ->orderBy('display_order')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'description' => $p->description,
                'category' => $p->category,
                'parent_id' => $p->parent_id,
                'display_order' => $p->display_order,
                'is_active' => $p->is_active,
                'show_badge' => $p->show_badge,
                'color' => $p->color,
                'member_count' => $positionCounts[$p->id] ?? 0,
            ]);

        $categories = OrganizationPosition::where('organization_id', $orgId)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->values();

        return Inertia::render('Admin/Positions/Index', [
            'positions' => $positions,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['nullable', 'string', 'max:100'],
            'parent_id' => ['nullable', 'exists:organization_positions,id'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'color' => ['nullable', 'string', 'max:7'],
            'show_badge' => ['nullable', 'boolean'],
        ]);

        OrganizationPosition::create([
            'organization_id' => $request->user()->current_organization_id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
            'display_order' => $data['display_order'] ?? 0,
            'color' => $data['color'] ?? null,
            'show_badge' => $data['show_badge'] ?? true,
        ]);

        return back()->with('success', 'Jawatan berjaya ditambah.');
    }

    public function update(Request $request, OrganizationPosition $position): RedirectResponse
    {
        if ($position->organization_id !== $request->user()->current_organization_id) {
            abort(403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['nullable', 'string', 'max:100'],
            'parent_id' => ['nullable', 'exists:organization_positions,id'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'color' => ['nullable', 'string', 'max:7'],
            'show_badge' => ['nullable', 'boolean'],
        ]);

        $position->update($data);

        if ($position->wasChanged('name')) {
            User::withoutGlobalScope(\App\Models\Scopes\OrganizationScope::class)
                ->where('position_id', $position->id)
                ->update(['position' => $position->name]);
        }

        return back()->with('success', 'Jawatan berjaya dikemas kini.');
    }

    public function destroy(Request $request, OrganizationPosition $position): RedirectResponse
    {
        if ($position->organization_id !== $request->user()->current_organization_id) {
            abort(403);
        }

        OrganizationPosition::where('parent_id', $position->id)
            ->update(['parent_id' => null]);

        $position->delete();

        return back()->with('success', 'Jawatan berjaya dipadam.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'positions' => ['required', 'array'],
            'positions.*.id' => ['required', 'exists:organization_positions,id'],
            'positions.*.display_order' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($data['positions'] as $item) {
            OrganizationPosition::where('id', $item['id'])
                ->where('organization_id', $request->user()->current_organization_id)
                ->update(['display_order' => $item['display_order']]);
        }

        return back()->with('success', 'Turutan jawatan berjaya dikemas kini.');
    }

    public function toggleActive(Request $request, OrganizationPosition $position): RedirectResponse
    {
        if ($position->organization_id !== $request->user()->current_organization_id) {
            abort(403);
        }

        $position->update(['is_active' => ! $position->is_active]);

        $status = $position->is_active ? 'diaktifkan' : 'dinyahaktifkan';

        return back()->with('success', "Jawatan berjaya {$status}.");
    }

    public function categories(Request $request): JsonResponse
    {
        $categories = OrganizationPosition::where('organization_id', $request->user()->current_organization_id)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->values();

        return response()->json($categories);
    }

    public function members(Request $request, OrganizationPosition $position): JsonResponse
    {
        $members = User::withoutGlobalScope(\App\Models\Scopes\OrganizationScope::class)
            ->where('current_organization_id', $request->user()->current_organization_id)
            ->where('position_id', $position->id)
            ->select('id', 'name', 'email', 'phone', 'branch_name')
            ->orderBy('name')
            ->get();

        return response()->json(['position' => $position->name, 'members' => $members]);
    }
}

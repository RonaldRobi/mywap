<?php

namespace App\Http\Controllers;

use App\Models\OrganizationPosition;
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

        $positions = OrganizationPosition::where('organization_id', $orgId)
            ->orderBy('display_order')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'display_order' => $p->display_order,
            ]);

        return Inertia::render('Admin/Positions/Index', [
            'positions' => $positions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ]);

        OrganizationPosition::create([
            'organization_id' => $request->user()->current_organization_id,
            'name' => $data['name'],
            'display_order' => $data['display_order'] ?? 0,
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
            'display_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $position->update($data);

        return back()->with('success', 'Jawatan berjaya dikemas kini.');
    }

    public function destroy(Request $request, OrganizationPosition $position): RedirectResponse
    {
        if ($position->organization_id !== $request->user()->current_organization_id) {
            abort(403);
        }

        $position->delete();

        return back()->with('success', 'Jawatan berjaya dipadam.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationChartMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * OrganizationInfoController
 *
 * Renders the per-organisation "Info" page (Maklumat + Carta Organisasi)
 * scoped to the currently authenticated user's organisation.
 * Only Admin / Superadmin users may manage the org chart entries.
 */
class OrganizationInfoController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $currentOrganization = $user->organization;

        $organizations = Organization::query()
            ->withCount(['members', 'chartMembers'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($organizations->isEmpty()) {
            return redirect()->route('dashboard')
                ->with('error', 'Tiada organisasi untuk dipaparkan.');
        }

        $requestedSlug = $request->query('org');
        $selected = $requestedSlug
            ? $organizations->firstWhere('slug', $requestedSlug)
            : null;

        $organization = $selected ?? $currentOrganization ?? $organizations->first();

        $chartMembers = $organization->chartMembers()->get()->map(fn (OrganizationChartMember $member) => [
            'id' => $member->id,
            'name' => $member->name,
            'position' => $member->position,
            'email' => $member->email,
            'image_path' => $member->image_path,
            'display_order' => $member->display_order,
        ]);

        $isOwnOrganization = $currentOrganization?->id === $organization->id;

        return Inertia::render('Org/Info', [
            'organization' => $this->organizationPayload($organization, $currentOrganization),
            'organizations' => $organizations->map(fn (Organization $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'slug' => $item->slug,
                'description' => $item->description,
                'logo_path' => $item->logo_path,
                'color_theme' => $item->color_theme,
                'min_age' => $item->min_age,
                'max_age' => $item->max_age,
                'member_count' => $item->members_count,
                'chart_member_count' => $item->chart_members_count,
                'is_current' => $currentOrganization?->id === $item->id,
            ])->values(),
            'chartMembers' => $chartMembers,
            'canManage' => $isOwnOrganization && $user->isAdminPanelUser(),
            'isOwnOrganization' => $isOwnOrganization,
        ]);
    }

    public function updateDescription(Request $request): RedirectResponse
    {
        $organization = $this->guardOrganization($request);

        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        $organization->update(['description' => $data['description'] ?? null]);

        return back()->with('success', 'Maklumat organisasi berjaya dikemas kini.');
    }

    public function storeChartMember(Request $request): RedirectResponse
    {
        $organization = $this->guardOrganization($request);

        $data = $this->validateChartMember($request);

        $imagePath = null;
        if (! empty($data['image'])) {
            $imagePath = $data['image']->store("org-chart/{$organization->slug}", 'public');
        }

        $organization->chartMembers()->create([
            'name' => $data['name'],
            'position' => $data['position'],
            'email' => $data['email'] ?? null,
            'image_path' => $imagePath ? '/storage/'.ltrim($imagePath, '/') : null,
            'display_order' => $data['display_order'] ?? 0,
        ]);

        return back()->with('success', 'Entri carta organisasi berjaya ditambah.');
    }

    public function updateChartMember(Request $request, OrganizationChartMember $member): RedirectResponse
    {
        $organization = $this->guardOrganization($request);

        if ($member->organization_id !== $organization->id) {
            abort(403);
        }

        $data = $this->validateChartMember($request);

        $payload = [
            'name' => $data['name'],
            'position' => $data['position'],
            'email' => $data['email'] ?? null,
            'display_order' => $data['display_order'] ?? 0,
        ];

        if (! empty($data['image'])) {
            $this->deleteStoredImage($member->image_path);

            $imagePath = $data['image']->store("org-chart/{$organization->slug}", 'public');
            $payload['image_path'] = '/storage/'.ltrim($imagePath, '/');
        }

        $member->update($payload);

        return back()->with('success', 'Entri carta organisasi berjaya dikemas kini.');
    }

    public function destroyChartMember(Request $request, OrganizationChartMember $member): RedirectResponse
    {
        $organization = $this->guardOrganization($request);

        if ($member->organization_id !== $organization->id) {
            abort(403);
        }

        $this->deleteStoredImage($member->image_path);
        $member->delete();

        return back()->with('success', 'Entri carta organisasi berjaya dipadam.');
    }

    private function validateChartMember(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function deleteStoredImage(?string $imagePath): void
    {
        if (! $imagePath) {
            return;
        }

        $storedPath = ltrim(str_replace('/storage/', '', parse_url((string) $imagePath, PHP_URL_PATH) ?? ''), '/');

        if ($storedPath !== '' && Storage::disk('public')->exists($storedPath)) {
            Storage::disk('public')->delete($storedPath);
        }
    }

    private function guardOrganization(Request $request): Organization
    {
        $organization = $request->user()->organization;

        if (! $organization) {
            abort(403, 'Tiada organisasi.');
        }

        return $organization;
    }

    private function organizationPayload(Organization $organization, ?Organization $currentOrganization): array
    {
        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'slug' => $organization->slug,
            'description' => $organization->description,
            'logo_path' => $organization->logo_path,
            'color_theme' => $organization->color_theme,
            'min_age' => $organization->min_age,
            'max_age' => $organization->max_age,
            'website_url' => $organization->website_url,
            'facebook_url' => $organization->facebook_url,
            'instagram_url' => $organization->instagram_url,
            'twitter_url' => $organization->twitter_url,
            'youtube_url' => $organization->youtube_url,
            'tiktok_url' => $organization->tiktok_url,
            'is_current' => $currentOrganization?->id === $organization->id,
        ];
    }
}

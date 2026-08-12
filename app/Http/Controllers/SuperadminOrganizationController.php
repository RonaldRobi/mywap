<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationChartMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SuperadminOrganizationController extends Controller
{
    public function index(): Response
    {
        $hasLogoColumn = Schema::hasColumn('organizations', 'logo_path');
        $hasSortOrderColumn = Schema::hasColumn('organizations', 'sort_order');

        $organizations = Organization::query()
            ->withCount('members')
            ->with('chartMembers')
            ->orderBy($hasSortOrderColumn ? 'sort_order' : 'min_age')
            ->orderBy('min_age')
            ->get()
            ->map(fn (Organization $organization) => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'color_theme' => $organization->color_theme,
                'description' => $organization->description,
                'min_age' => $organization->min_age,
                'max_age' => $organization->max_age,
                'logo_path' => $hasLogoColumn ? $this->normalizeStorageUrl($organization->logo_path) : null,
                'sort_order' => $hasSortOrderColumn ? $organization->sort_order : null,
                'member_count' => $organization->members_count,
                'chart_members' => $organization->chartMembers->map(fn ($m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'position' => $m->position,
                    'email' => $m->email,
                    'image_path' => $m->image_path,
                    'display_order' => $m->display_order,
                ]),
                'payment_gateway' => $organization->payment_gateway,
                'active_gateway' => $organization->activeGateway(),
                'bayarcash_api_token' => $organization->bayarcash_api_token,
                'bayarcash_portal_key' => $organization->bayarcash_portal_key,
                'bayarcash_secret_key' => $organization->bayarcash_secret_key,
                'bayarcash_environment' => $organization->bayarcash_environment,
                'doku_client_id' => $organization->doku_client_id,
                'doku_api_key' => $organization->doku_api_key,
                'doku_secret_key' => $organization->doku_secret_key,
                'doku_environment' => $organization->doku_environment,
                'website_url' => $organization->website_url,
                'facebook_url' => $organization->facebook_url,
                'instagram_url' => $organization->instagram_url,
                'twitter_url' => $organization->twitter_url,
                'youtube_url' => $organization->youtube_url,
                'tiktok_url' => $organization->tiktok_url,
            ])
            ->values();

        return Inertia::render('Superadmin/OrganizationManage', [
            'organizations' => $organizations,
            'capabilities' => [
                'logo' => $hasLogoColumn,
                'sort_order' => $hasSortOrderColumn,
            ],
        ]);
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $hasSortOrderColumn = Schema::hasColumn('organizations', 'sort_order');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'color_theme' => ['nullable', 'string', 'max:20'],
            'min_age' => ['required', 'integer', 'min:0', 'max:120'],
            'max_age' => ['nullable', 'integer', 'min:0', 'max:120', 'gte:min_age'],
            'sort_order' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'bayarcash_api_token' => ['nullable', 'string', 'max:255'],
            'bayarcash_portal_key' => ['nullable', 'string', 'max:255'],
            'bayarcash_secret_key' => ['nullable', 'string', 'max:255'],
            'bayarcash_environment' => ['nullable', 'in:sandbox,live'],
            'payment_gateway' => ['nullable', 'in:bayarcash,doku'],
            'doku_client_id' => ['nullable', 'string', 'max:255'],
            'doku_api_key' => ['nullable', 'string', 'max:500'],
            'doku_secret_key' => ['nullable', 'string', 'max:500'],
            'doku_environment' => ['nullable', 'in:sandbox,production'],
            'website_url' => ['nullable', 'string', 'max:500', 'url'],
            'facebook_url' => ['nullable', 'string', 'max:500', 'url'],
            'instagram_url' => ['nullable', 'string', 'max:500', 'url'],
            'twitter_url' => ['nullable', 'string', 'max:500', 'url'],
            'youtube_url' => ['nullable', 'string', 'max:500', 'url'],
            'tiktok_url' => ['nullable', 'string', 'max:500', 'url'],
        ]);

        $payload = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'color_theme' => $data['color_theme'] ?? null,
            'min_age' => (int) $data['min_age'],
            'max_age' => $data['max_age'] !== null ? (int) $data['max_age'] : null,
            'bayarcash_api_token' => $data['bayarcash_api_token'] ?? null,
            'bayarcash_portal_key' => $data['bayarcash_portal_key'] ?? null,
            'bayarcash_secret_key' => $data['bayarcash_secret_key'] ?? null,
            'bayarcash_environment' => $data['bayarcash_environment'] ?? 'sandbox',
            'payment_gateway' => $data['payment_gateway'] ?? null,
            'doku_client_id' => $data['doku_client_id'] ?? null,
            'doku_api_key' => $data['doku_api_key'] ?? null,
            'doku_secret_key' => $data['doku_secret_key'] ?? null,
            'doku_environment' => $data['doku_environment'] ?? 'sandbox',
            'website_url' => $data['website_url'] ?? null,
            'facebook_url' => $data['facebook_url'] ?? null,
            'instagram_url' => $data['instagram_url'] ?? null,
            'twitter_url' => $data['twitter_url'] ?? null,
            'youtube_url' => $data['youtube_url'] ?? null,
            'tiktok_url' => $data['tiktok_url'] ?? null,
        ];

        if ($hasSortOrderColumn) {
            $payload['sort_order'] = $data['sort_order'] ?? null;
        }

        $organization->update($payload);

        return back()->with('success', "Tetapan organisasi {$organization->name} berjaya dikemas kini.");
    }

    public function updateLogo(Request $request, Organization $organization): RedirectResponse
    {
        if (! Schema::hasColumn('organizations', 'logo_path')) {
            return back()->with('error', 'Sila jalankan migration terlebih dahulu untuk logo organisasi.');
        }

        $data = $request->validate([
            'organization_logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
        ]);

        if ($organization->logo_path) {
            $oldPath = ltrim(str_replace('/storage/', '', parse_url((string) $organization->logo_path, PHP_URL_PATH) ?? ''), '/');
            if ($oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $storedPath = $data['organization_logo']->store('logos/organizations', 'public');
        $organization->update([
            'logo_path' => '/storage/'.ltrim($storedPath, '/'),
        ]);

        return back()->with('success', "Logo {$organization->name} berjaya dikemas kini.");
    }

    private function normalizeStorageUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $parsedPath = parse_url($url, PHP_URL_PATH);

        if (is_string($parsedPath) && str_starts_with($parsedPath, '/storage/')) {
            return $parsedPath;
        }

        return $url;
    }

    // ─── Carta Organisasi (Superadmin) ──────────────────────────────────────

    public function storeChartMember(Request $request, Organization $organization): RedirectResponse
    {
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

        return back()->with('success', "Entri carta {$organization->name} berjaya ditambah.");
    }

    public function updateChartMember(Request $request, Organization $organization, OrganizationChartMember $member): RedirectResponse
    {
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
            $this->deleteChartImage($member->image_path);

            $imagePath = $data['image']->store("org-chart/{$organization->slug}", 'public');
            $payload['image_path'] = '/storage/'.ltrim($imagePath, '/');
        }

        $member->update($payload);

        return back()->with('success', "Entri carta {$organization->name} berjaya dikemas kini.");
    }

    public function destroyChartMember(Request $request, Organization $organization, OrganizationChartMember $member): RedirectResponse
    {
        if ($member->organization_id !== $organization->id) {
            abort(403);
        }

        $this->deleteChartImage($member->image_path);
        $member->delete();

        return back()->with('success', "Entri carta {$organization->name} berjaya dipadam.");
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

    private function deleteChartImage(?string $imagePath): void
    {
        if (! $imagePath) {
            return;
        }

        $storedPath = ltrim(str_replace('/storage/', '', parse_url((string) $imagePath, PHP_URL_PATH) ?? ''), '/');

        if ($storedPath !== '' && Storage::disk('public')->exists($storedPath)) {
            Storage::disk('public')->delete($storedPath);
        }
    }
}

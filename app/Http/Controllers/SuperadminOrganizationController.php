<?php

namespace App\Http\Controllers;

use App\Models\Organization;
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
            ->orderBy($hasSortOrderColumn ? 'sort_order' : 'min_age')
            ->orderBy('min_age')
            ->get($hasLogoColumn && $hasSortOrderColumn
                ? ['id', 'name', 'slug', 'color_theme', 'min_age', 'max_age', 'logo_path', 'sort_order']
                : ($hasLogoColumn
                    ? ['id', 'name', 'slug', 'color_theme', 'min_age', 'max_age', 'logo_path']
                    : ['id', 'name', 'slug', 'color_theme', 'min_age', 'max_age']))
            ->map(fn (Organization $organization) => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'color_theme' => $organization->color_theme,
                'min_age' => $organization->min_age,
                'max_age' => $organization->max_age,
                'logo_path' => $hasLogoColumn ? $this->normalizeStorageUrl($organization->logo_path) : null,
                'sort_order' => $hasSortOrderColumn ? $organization->sort_order : null,
                'member_count' => $organization->members_count,
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
            'color_theme' => ['nullable', 'string', 'max:20'],
            'min_age' => ['required', 'integer', 'min:0', 'max:120'],
            'max_age' => ['nullable', 'integer', 'min:0', 'max:120', 'gte:min_age'],
            'sort_order' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ]);

        $payload = [
            'name' => $data['name'],
            'color_theme' => $data['color_theme'] ?? null,
            'min_age' => (int) $data['min_age'],
            'max_age' => $data['max_age'] !== null ? (int) $data['max_age'] : null,
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
            'logo_path' => '/storage/' . ltrim($storedPath, '/'),
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
}

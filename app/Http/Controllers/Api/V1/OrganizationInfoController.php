<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationChartMember;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationInfoController extends Controller
{
    /**
     * Maklumat semua organisasi + carta organisasi.
     *
     * Ahli boleh memilih mana-mana organisasi (PKPIM / ABIM / WADAH) melalui
     * query `?organization=<slug>`. Tanpa parameter, organisasi ahli semasa
     * digunakan; tetamu (tanpa log masuk) mendapat organisasi utama
     * (sort_order pertama).
     */
    public function show(Request $request): JsonResponse
    {
        $organizations = Organization::query()
            ->withCount(['members', 'chartMembers'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($organizations->isEmpty()) {
            return ApiResponse::error('Tiada organisasi untuk dipaparkan.', status: 404);
        }

        $currentOrganization = $request->user()?->organization;
        $requestedSlug = $request->query('organization') ?? $request->query('org');

        $selected = $requestedSlug
            ? $organizations->firstWhere('slug', $requestedSlug)
            : null;

        $organization = $selected
            ?? $currentOrganization
            ?? $organizations->first();

        $chartMembers = $organization->chartMembers()->get()
            ->map(fn (OrganizationChartMember $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'position' => $member->position,
                'email' => $member->email,
                'image_path' => $member->image_path,
                'display_order' => $member->display_order,
            ]);

        return ApiResponse::success([
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
                'is_selected' => $organization->id === $item->id,
            ])->values(),
            'organization' => $this->organizationPayload($organization, $currentOrganization),
            'chart_members' => $chartMembers,
        ]);
    }

    private function organizationPayload(
        Organization $organization,
        ?Organization $currentOrganization,
    ): array {
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

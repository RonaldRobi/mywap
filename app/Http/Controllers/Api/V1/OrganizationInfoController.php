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
     * Maklumat organisasi ahli semasa + carta organisasi.
     * Sama logic dengan web OrganizationInfoController::show.
     * Tetamu (tanpa log masuk) dipaparkan organisasi utama (sort_order pertama).
     */
    public function show(Request $request): JsonResponse
    {
        $organization = $request->user()?->organization
            ?? Organization::query()->orderBy('sort_order')->orderBy('id')->first();

        if (! $organization) {
            return ApiResponse::error('Tiada organisasi untuk dipaparkan.', status: 404);
        }

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
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'description' => $organization->description,
                'logo_path' => $organization->logo_path,
                'color_theme' => $organization->color_theme,
                'website_url' => $organization->website_url,
                'facebook_url' => $organization->facebook_url,
                'instagram_url' => $organization->instagram_url,
                'twitter_url' => $organization->twitter_url,
                'youtube_url' => $organization->youtube_url,
                'tiktok_url' => $organization->tiktok_url,
            ],
            'chart_members' => $chartMembers,
        ]);
    }
}

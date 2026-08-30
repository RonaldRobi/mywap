<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OnboardingSlide;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class OnboardingController extends Controller
{
    public function index(): JsonResponse
    {
        $slides = OnboardingSlide::query()->where('is_active', true)->orderBy('slide_order')->limit(3)->get()->map(fn (OnboardingSlide $slide) => [
            'order' => $slide->slide_order, 'title' => $slide->title, 'body' => $slide->body,
            'button_label' => $slide->button_label, 'button_url' => $slide->button_url,
            'background_start' => $slide->background_start, 'background_end' => $slide->background_end,
            'text_color' => $slide->text_color, 'media_url' => $slide->media_path ? url($slide->media_path) : null,
            'media_type' => $slide->media_type,
        ]);
        return ApiResponse::success($slides);
    }
}

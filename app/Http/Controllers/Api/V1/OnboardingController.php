<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OnboardingSlide;
use App\Models\AppSetting;
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
            'text_color' => $slide->text_color, 'overlay_start_color' => $slide->overlay_start_color ?? '#071525', 'overlay_end_color' => $slide->overlay_end_color ?? '#071525',
            'overlay_start_opacity' => $slide->overlay_start_opacity ?? 0, 'overlay_end_opacity' => $slide->overlay_end_opacity ?? 90,
            'media_url' => $slide->media_path ? url($slide->media_path) : null,
            'media_type' => $slide->media_type,
        ]);
        $setting = AppSetting::singleton();
        return ApiResponse::success([
            'slides' => $slides,
            'login' => [
                'title' => $setting->mobile_login_title ?? 'Selamat kembali',
                'subtitle' => $setting->mobile_login_subtitle ?? 'Log masuk untuk meneruskan ke myWAP.',
                'background_start' => $setting->mobile_login_background_start ?? '#F4F6F1',
                'background_end' => $setting->mobile_login_background_end ?? '#EDF5EE',
                'accent' => $setting->mobile_login_accent ?? '#2F6B32',
                'logo_url' => $setting->system_logo_path ? url($setting->system_logo_path) : null,
                'image_url' => $setting->login_image_path ? url($setting->login_image_path) : null,
            ],
        ]);
    }
}

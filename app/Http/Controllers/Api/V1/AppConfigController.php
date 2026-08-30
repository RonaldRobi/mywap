<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AppConfigController extends Controller
{
    /**
     * Konfigurasi aplikasi mudah alih (Flutter) yang perlu diketahui sebelum
     * pengguna log masuk. Ia termasuk loading screen (GIF + gradient).
     *
     * Hanya untuk apps (iOS/Android) — web tidak menggunakan endpoint ini.
     */
    public function index(): JsonResponse
    {
        $setting = AppSetting::singleton();

        return ApiResponse::success([
            'loading_screen' => [
                'enabled' => (bool) $setting->loading_screen_enabled,
                'gif_url' => $setting->loading_screen_gif_path ? url($setting->loading_screen_gif_path) : null,
                'background_start' => $setting->loading_screen_background_start ?? '#071525',
                'background_end' => $setting->loading_screen_background_end ?? '#2F6B32',
                'duration_ms' => (int) ($setting->loading_screen_duration_ms ?? 2500),
            ],
        ]);
    }
}

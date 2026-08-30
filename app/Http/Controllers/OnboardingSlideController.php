<?php

namespace App\Http\Controllers;

use App\Models\OnboardingSlide;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingSlideController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Superadmin/OnboardingManage', [
            'slides' => OnboardingSlide::query()->orderBy('slide_order')->get(),
            'loginBranding' => $this->loginBranding(AppSetting::singleton()),
        ]);
    }

    public function updateLoginBranding(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mobile_login_title' => ['nullable', 'string', 'max:120'],
            'mobile_login_subtitle' => ['nullable', 'string', 'max:255'],
            'mobile_login_background_start' => ['required', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'mobile_login_background_end' => ['required', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'mobile_login_accent' => ['required', 'regex:/^#([A-Fa-f0-9]{6})$/'],
        ]);
        AppSetting::singleton()->update($data);
        return back()->with('success', 'Branding log masuk aplikasi mudah alih berjaya dikemas kini.');
    }

    public function update(Request $request, OnboardingSlide $onboardingSlide): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:120'], 'body' => ['nullable', 'string', 'max:1000'],
            'button_label' => ['nullable', 'string', 'max:40'], 'button_url' => ['nullable', 'url', 'max:2048'],
            'background_start' => ['required', 'regex:/^#([A-Fa-f0-9]{6})$/'], 'background_end' => ['required', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'text_color' => ['required', 'regex:/^#([A-Fa-f0-9]{6})$/'], 'overlay_start_color' => ['required', 'regex:/^#([A-Fa-f0-9]{6})$/'], 'overlay_end_color' => ['required', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'overlay_start_opacity' => ['required', 'integer', 'min:0', 'max:100'], 'overlay_end_opacity' => ['required', 'integer', 'min:0', 'max:100'],
            'overlay_start_position' => ['required', 'integer', 'min:0', 'max:100'], 'overlay_end_position' => ['required', 'integer', 'min:0', 'max:100', 'gte:overlay_start_position'],
            'is_active' => ['nullable', 'boolean'],
            'media' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,mp4', 'max:10240'],
        ]);

        $mediaPath = $onboardingSlide->media_path;
        $mediaType = $onboardingSlide->media_type;
        if ($request->hasFile('media')) {
            $this->deleteMedia($mediaPath);
            $file = $request->file('media');
            $mediaPath = '/storage/'.$file->store('onboarding', 'public');
            $mediaType = $file->getClientOriginalExtension() === 'mp4' ? 'video' : 'image';
        }
        unset($data['media']);
        $onboardingSlide->update(array_merge($data, ['media_path' => $mediaPath, 'media_type' => $mediaType]));
        return back()->with('success', "Slide {$onboardingSlide->slide_order} berjaya dikemas kini.");
    }

    public function destroyMedia(OnboardingSlide $onboardingSlide): RedirectResponse
    {
        $this->deleteMedia($onboardingSlide->media_path);
        $onboardingSlide->update(['media_path' => null, 'media_type' => null]);
        return back()->with('success', "Media slide {$onboardingSlide->slide_order} berjaya dipadam.");
    }

    private function deleteMedia(?string $url): void
    {
        $path = ltrim(str_replace('/storage/', '', parse_url((string) $url, PHP_URL_PATH) ?? ''), '/');
        if ($path !== '' && Storage::disk('public')->exists($path)) Storage::disk('public')->delete($path);
    }

    private function loginBranding(AppSetting $setting): array
    {
        return [
            'title' => $setting->mobile_login_title ?? 'Selamat kembali',
            'subtitle' => $setting->mobile_login_subtitle ?? 'Log masuk untuk meneruskan ke myWAP.',
            'background_start' => $setting->mobile_login_background_start ?? '#F4F6F1',
            'background_end' => $setting->mobile_login_background_end ?? '#EDF5EE',
            'accent' => $setting->mobile_login_accent ?? '#2F6B32',
            'logo_url' => $setting->system_logo_path ? url($setting->system_logo_path) : null,
            'image_url' => $setting->login_image_path ? url($setting->login_image_path) : null,
        ];
    }
}

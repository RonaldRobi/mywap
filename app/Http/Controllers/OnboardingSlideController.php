<?php

namespace App\Http\Controllers;

use App\Models\OnboardingSlide;
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
        ]);
    }

    public function update(Request $request, OnboardingSlide $onboardingSlide): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:120'], 'body' => ['nullable', 'string', 'max:1000'],
            'button_label' => ['nullable', 'string', 'max:40'], 'button_url' => ['nullable', 'url', 'max:2048'],
            'background_start' => ['required', 'regex:/^#([A-Fa-f0-9]{6})$/'], 'background_end' => ['required', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'text_color' => ['required', 'regex:/^#([A-Fa-f0-9]{6})$/'], 'is_active' => ['nullable', 'boolean'],
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
}

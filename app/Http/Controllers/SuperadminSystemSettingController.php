<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SuperadminSystemSettingController extends Controller
{
    public function index(): Response
    {
        $canManageSystemLogo = Schema::hasTable('app_settings');
        $setting = $canManageSystemLogo ? AppSetting::singleton() : null;

        return Inertia::render('Superadmin/SystemSettings', [
            'systemLogoPath' => $this->normalizeStorageUrl($setting?->system_logo_path),
            'splashImagePath' => $this->normalizeStorageUrl($setting?->splash_image_path),
            'splashBackgroundColor' => $setting?->splash_background_color ?? '#0f172a',
            'splashTitle' => $setting?->splash_title ?? 'myWAP',
            'splashDurationMs' => $setting?->splash_duration_ms ?? 1800,
            'splashEnabled' => (bool) ($setting?->splash_enabled ?? true),
            'canManageSystemLogo' => $canManageSystemLogo,
        ]);
    }

    public function updateSystemLogo(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('app_settings')) {
            return back()->with('error', 'Sila jalankan migration terlebih dahulu untuk tetapan MyMarhalah.');
        }

        $data = $request->validate([
            'system_logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
        ]);

        $setting = AppSetting::singleton();

        if ($setting->system_logo_path) {
            $oldPath = ltrim(str_replace('/storage/', '', parse_url((string) $setting->system_logo_path, PHP_URL_PATH) ?? ''), '/');
            if ($oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $storedPath = $data['system_logo']->store('logos/system', 'public');

        $setting->update([
            'system_logo_path' => '/storage/' . ltrim($storedPath, '/'),
        ]);

        return back()->with('success', 'Logo MyMarhalah berjaya dikemas kini.');
    }

    public function updateSplashSetting(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('app_settings')) {
            return back()->with('error', 'Sila jalankan migration terlebih dahulu untuk tetapan MyMarhalah.');
        }

        $data = $request->validate([
            'splash_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg,gif', 'max:3072'],
            'splash_background_color' => ['required', 'string', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'splash_title' => ['nullable', 'string', 'max:120'],
            'splash_duration_ms' => ['required', 'integer', 'min:300', 'max:6000'],
            'splash_enabled' => ['nullable', 'boolean'],
        ]);

        $setting = AppSetting::singleton();
        $splashImagePath = $setting->splash_image_path;

        if ($request->hasFile('splash_image')) {
            if ($setting->splash_image_path) {
                $oldPath = ltrim(str_replace('/storage/', '', parse_url((string) $setting->splash_image_path, PHP_URL_PATH) ?? ''), '/');
                if ($oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }

            $storedPath = $request->file('splash_image')->store('logos/splash', 'public');
            $splashImagePath = '/storage/' . ltrim($storedPath, '/');
        }

        $setting->update([
            'splash_image_path' => $splashImagePath,
            'splash_background_color' => $data['splash_background_color'],
            'splash_title' => trim((string) ($data['splash_title'] ?? '')) ?: 'myWAP',
            'splash_duration_ms' => (int) $data['splash_duration_ms'],
            'splash_enabled' => (bool) ($data['splash_enabled'] ?? false),
        ]);

        return back()->with('success', 'Tetapan splash screen berjaya dikemas kini.');
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

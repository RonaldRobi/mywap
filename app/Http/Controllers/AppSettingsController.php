<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tetapan khusus aplikasi mudah alih (Flutter iOS/Android).
 * Dipisahkan daripada tetapan sistem supaya tidak bercampur
 * dengan konfigurasi web/pentadbir.
 */
class AppSettingsController extends Controller
{
    public function index(): Response
    {
        $setting = AppSetting::singleton();

        return Inertia::render('Superadmin/AppSettings', [
            'loadingScreen' => [
                'enabled' => (bool) $setting->loading_screen_enabled,
                'gif_path' => $this->normalizeStorageUrl($setting->loading_screen_gif_path),
                'background_start' => $setting->loading_screen_background_start ?? '#071525',
                'background_end' => $setting->loading_screen_background_end ?? '#2F6B32',
                'duration_ms' => (int) ($setting->loading_screen_duration_ms ?? 2500),
            ],
        ]);
    }

    public function updateLoadingScreen(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('app_settings')) {
            return back()->with('error', 'Sila jalankan migration terlebih dahulu.');
        }

        $data = $request->validate([
            'loading_screen_gif' => ['nullable', 'image', 'mimes:gif', 'max:10240'],
            'loading_screen_background_start' => ['required', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'loading_screen_background_end' => ['required', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'loading_screen_duration_ms' => ['required', 'integer', 'min:500', 'max:8000'],
            'loading_screen_enabled' => ['nullable', 'boolean'],
        ]);

        $setting = AppSetting::singleton();
        $gifPath = $setting->loading_screen_gif_path;

        if ($request->hasFile('loading_screen_gif')) {
            $this->deleteStoredImage($gifPath);

            $storedPath = $request->file('loading_screen_gif')->store('loading-screens', 'public');
            $gifPath = '/storage/'.ltrim($storedPath, '/');
        }

        $setting->update([
            'loading_screen_gif_path' => $gifPath,
            'loading_screen_background_start' => $data['loading_screen_background_start'],
            'loading_screen_background_end' => $data['loading_screen_background_end'],
            'loading_screen_duration_ms' => (int) $data['loading_screen_duration_ms'],
            'loading_screen_enabled' => (bool) ($data['loading_screen_enabled'] ?? false),
        ]);

        return back()->with('success', 'Tetapan loading screen aplikasi berjaya dikemas kini.');
    }

    public function removeLoadingScreenGif(): RedirectResponse
    {
        if (! Schema::hasTable('app_settings')) {
            return back()->with('error', 'Sila jalankan migration terlebih dahulu.');
        }

        $setting = AppSetting::singleton();

        $this->deleteStoredImage($setting->loading_screen_gif_path);

        $setting->update(['loading_screen_gif_path' => null]);

        return back()->with('success', 'GIF loading screen berjaya dibuang.');
    }

    private function deleteStoredImage(?string $path): void
    {
        if (! $path) {
            return;
        }

        $oldPath = ltrim(str_replace('/storage/', '', parse_url((string) $path, PHP_URL_PATH) ?? ''), '/');

        if ($oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }
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

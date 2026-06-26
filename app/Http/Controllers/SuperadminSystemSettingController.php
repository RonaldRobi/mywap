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
            'appName' => $setting?->app_name ?? config('app.name', 'myWAP'),
            'systemLogoPath' => $this->normalizeStorageUrl($setting?->system_logo_path),
            'chatbotLogoPath' => $this->normalizeStorageUrl($setting?->chatbot_logo_path),
            'splashImagePath' => $this->normalizeStorageUrl($setting?->splash_image_path),
            'splashBackgroundColor' => $setting?->splash_background_color ?? '#0f172a',
            'splashTitle' => $setting?->splash_title ?? 'myWAP',
            'splashDurationMs' => $setting?->splash_duration_ms ?? 1800,
            'splashEnabled' => (bool) ($setting?->splash_enabled ?? true),
            'adminContactEmail' => $setting?->admin_contact_email ?? '',
            'adminContactPhone' => $setting?->admin_contact_phone ?? '',
            'hasResendKey' => $setting && $setting->resend_api_key ? true : false,
            'hasGeminiKey' => $setting && $setting->gemini_api_key ? true : false,
            'mailFromAddress' => $setting?->mail_from_address ?? '',
            'mailFromName' => $setting?->mail_from_name ?? '',
            'canManageSystemLogo' => $canManageSystemLogo,
        ]);
    }

    public function updateAppName(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('app_settings')) {
            return back()->with('error', 'Sistem tetapan tidak tersedia.');
        }

        $data = $request->validate([
            'app_name' => ['required', 'string', 'max:100'],
        ]);

        $setting = AppSetting::singleton();
        $setting->update(['app_name' => trim($data['app_name'])]);

        config(['app.name' => trim($data['app_name'])]);

        return back()->with('success', 'Nama aplikasi berjaya dikemas kini.');
    }

    public function updateResendKey(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('app_settings')) {
            return back()->with('error', 'Sistem tetapan tidak tersedia.');
        }

        $data = $request->validate([
            'resend_api_key' => ['nullable', 'string', 'max:255'],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
        ]);

        $setting = AppSetting::singleton();
        $updateData = [];
        $mailConfig = [];

        // Only update API key if a non-empty value was sent
        if ($request->filled('resend_api_key')) {
            $updateData['resend_api_key'] = $data['resend_api_key'];
            $mailConfig['mail.default'] = 'resend';
            $mailConfig['mail.mailers.resend.key'] = $data['resend_api_key'];
            $mailConfig['services.resend.key'] = $data['resend_api_key'];
            $mailConfig['resend.api_key'] = $data['resend_api_key'];
        }

        if ($request->has('mail_from_address')) {
            $updateData['mail_from_address'] = $data['mail_from_address'] ?: null;
        }

        if ($request->has('mail_from_name')) {
            $updateData['mail_from_name'] = $data['mail_from_name'] ?: null;
        }

        if (! empty($updateData)) {
            $setting->update($updateData);
        }

        if ($setting->mail_from_address) {
            $mailConfig['mail.from.address'] = $setting->mail_from_address;
            $mailConfig['mail.from.name'] = $setting->mail_from_name ?: config('app.name');
        }

        config($mailConfig);

        return back()->with('success', 'Tetapan emel berjaya disimpan.');
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

    public function updateChatbotLogo(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('app_settings')) {
            return back()->with('error', 'Sila jalankan migration terlebih dahulu.');
        }

        $data = $request->validate([
            'chatbot_logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
        ]);

        $setting = AppSetting::singleton();

        if ($setting->chatbot_logo_path) {
            $oldPath = ltrim(str_replace('/storage/', '', parse_url((string) $setting->chatbot_logo_path, PHP_URL_PATH) ?? ''), '/');
            if ($oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $storedPath = $data['chatbot_logo']->store('logos/chatbot', 'public');

        $setting->update([
            'chatbot_logo_path' => '/storage/' . ltrim($storedPath, '/'),
        ]);

        return back()->with('success', 'Logo chatbot berjaya dikemas kini.');
    }

    public function updateGeminiKey(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('app_settings')) {
            return back()->with('error', 'Sistem tetapan tidak tersedia.');
        }

        $data = $request->validate([
            'gemini_api_key' => ['nullable', 'string', 'max:255'],
        ]);

        $setting = AppSetting::singleton();
        $updateData = [];

        if ($request->filled('gemini_api_key')) {
            $updateData['gemini_api_key'] = $data['gemini_api_key'];
        }

        if ($request->has('gemini_api_key') && ! $request->filled('gemini_api_key')) {
            $updateData['gemini_api_key'] = null;
        }

        if (! empty($updateData)) {
            $setting->update($updateData);
        }

        return back()->with('success', 'Kunci API Gemini berjaya disimpan.');
    }

    public function updateAdminContact(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('app_settings')) {
            return back()->with('error', 'Sistem tetapan tidak tersedia.');
        }

        $data = $request->validate([
            'admin_contact_email' => ['nullable', 'email', 'max:255'],
            'admin_contact_phone' => ['nullable', 'string', 'max:50'],
        ]);

        $setting = AppSetting::singleton();
        $setting->update([
            'admin_contact_email' => $data['admin_contact_email'] ?: null,
            'admin_contact_phone' => $data['admin_contact_phone'] ?: null,
        ]);

        return back()->with('success', 'Maklumat hubungi admin berjaya dikemas kini.');
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

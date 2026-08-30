<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
            'ogImagePath' => $this->normalizeStorageUrl($setting?->og_image_path),
            'loginImagePath' => $this->normalizeStorageUrl($setting?->login_image_path),
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
            'mailMailer' => $setting?->mail_mailer ?? 'log',
            'mailSmtpHost' => $setting?->mail_smtp_host ?? '',
            'mailSmtpPort' => $setting?->mail_smtp_port ?? '',
            'mailSmtpUsername' => $setting?->mail_smtp_username ?? '',
            'mailSmtpEncryption' => $setting?->mail_smtp_encryption ?? '',
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

        // Only update API key if a non-empty value was sent
        if ($request->filled('resend_api_key')) {
            $updateData['resend_api_key'] = $data['resend_api_key'];
            $updateData['mail_mailer'] = 'resend';
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

        config($this->buildMailConfig($setting));

        return back()->with('success', 'Tetapan emel berjaya disimpan.');
    }

    public function updateMailSettings(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('app_settings')) {
            return back()->with('error', 'Sistem tetapan tidak tersedia.');
        }

        $data = $request->validate([
            'mail_mailer' => ['required', 'in:resend,log,smtp'],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
            'resend_api_key' => ['nullable', 'string', 'max:255'],
            'mail_smtp_host' => ['nullable', 'string', 'max:255'],
            'mail_smtp_port' => ['nullable', 'string', 'max:10'],
            'mail_smtp_username' => ['nullable', 'string', 'max:255'],
            'mail_smtp_password' => ['nullable', 'string', 'max:255'],
            'mail_smtp_encryption' => ['nullable', 'in:tls,ssl,null,'],
        ]);

        $setting = AppSetting::singleton();

        $updateData = [
            'mail_mailer' => $data['mail_mailer'],
            'mail_from_address' => $data['mail_from_address'] ?: null,
            'mail_from_name' => $data['mail_from_name'] ?: null,
        ];

        // Only replace the Resend key if a non-empty value was sent
        if ($request->filled('resend_api_key')) {
            $updateData['resend_api_key'] = $data['resend_api_key'];
        }

        // SMTP fields — allow clearing
        $updateData['mail_smtp_host'] = $data['mail_smtp_host'] ?: null;
        $updateData['mail_smtp_port'] = $data['mail_smtp_port'] ?: null;
        $updateData['mail_smtp_username'] = $data['mail_smtp_username'] ?: null;
        $updateData['mail_smtp_encryption'] = $data['mail_smtp_encryption'] ?: null;

        // Only replace SMTP password if a non-empty value was sent
        if ($request->filled('mail_smtp_password')) {
            $updateData['mail_smtp_password'] = $data['mail_smtp_password'];
        }

        $setting->update($updateData);

        // Rebuild runtime mail config so the next email uses the new driver.
        config($this->buildMailConfig($setting));

        return back()->with('success', 'Tetapan mailer berjaya disimpan.');
    }

    /**
     * Bina konfigurasi mail runtime daripada app_settings.
     *
     * Apabila mailer utama ialah "resend" DAN SMTP backup diisi, guna failover
     * (Resend dahulu → SMTP sekiranya Resend gagal / kuota habis) supaya emel
     * penting tetap keluar.
     */
    private function buildMailConfig(AppSetting $setting): array
    {
        $mailer = $setting->mail_mailer ?: 'log';
        $mailConfig = [];

        if ($mailer === 'resend' && $setting->resend_api_key) {
            $mailConfig = [
                'mail.default' => 'resend',
                'mail.mailers.resend.key' => $setting->resend_api_key,
                'services.resend.key' => $setting->resend_api_key,
                'resend.api_key' => $setting->resend_api_key,
            ];
        }

        if (in_array($mailer, ['resend', 'smtp'], true)) {
            $mailConfig['mail.mailers.smtp.host'] = $setting->mail_smtp_host ?: config('mail.mailers.smtp.host');
            $mailConfig['mail.mailers.smtp.port'] = $setting->mail_smtp_port ?: config('mail.mailers.smtp.port');
            $mailConfig['mail.mailers.smtp.username'] = $setting->mail_smtp_username ?: config('mail.mailers.smtp.username');
            $mailConfig['mail.mailers.smtp.password'] = $setting->mail_smtp_password ?: config('mail.mailers.smtp.password');

            $encryption = $setting->mail_smtp_encryption ?: config('mail.mailers.smtp.encryption');
            if ($encryption && $encryption !== 'null') {
                $mailConfig['mail.mailers.smtp.encryption'] = $encryption;
            }

            if ($mailer === 'resend' && $setting->mail_smtp_host) {
                $mailConfig['mail.default'] = 'failover';
            }
        }

        if ($mailer === 'smtp') {
            $mailConfig['mail.default'] = 'smtp';
        } elseif ($mailer === 'log') {
            $mailConfig['mail.default'] = 'log';
        }

        if ($setting->mail_from_address) {
            $mailConfig['mail.from.address'] = $setting->mail_from_address;
            $mailConfig['mail.from.name'] = $setting->mail_from_name ?: config('app.name');
        }

        return $mailConfig;
    }

    public function testMail(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('app_settings')) {
            return back()->with('error', 'Sistem tetapan tidak tersedia.');
        }

        $data = $request->validate([
            'test_email' => ['required', 'email'],
        ]);

        $setting = AppSetting::singleton();
        $appName = $setting->app_name ?? config('app.name');

        try {
            Mail::raw(
                "Ini adalah emel ujian daripada {$appName}. Konfigurasi emel berfungsi dengan baik.",
                function ($message) use ($data, $appName) {
                    $message->to($data['test_email'])
                        ->subject('Emel Ujian '.$appName);
                }
            );

            return back()->with('success', 'Emel ujian berjaya dihantar ke '.$data['test_email'].'.');
        } catch (\Throwable $e) {
            Log::error('Mail test failed', ['error' => $e->getMessage()]);

            return back()->with('error', 'Gagal menghantar emel ujian: '.$e->getMessage());
        }
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
            'system_logo_path' => '/storage/'.ltrim($storedPath, '/'),
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
            $splashImagePath = '/storage/'.ltrim($storedPath, '/');
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
            'chatbot_logo_path' => '/storage/'.ltrim($storedPath, '/'),
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

    public function updateOgImage(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('app_settings')) {
            return back()->with('error', 'Sila jalankan migration terlebih dahulu.');
        }

        $data = $request->validate([
            'og_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ]);

        $setting = AppSetting::singleton();

        if ($setting->og_image_path) {
            $oldPath = ltrim(str_replace('/storage/', '', parse_url((string) $setting->og_image_path, PHP_URL_PATH) ?? ''), '/');
            if ($oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $storedPath = $data['og_image']->store('og-images', 'public');

        $setting->update([
            'og_image_path' => '/storage/'.ltrim($storedPath, '/'),
        ]);

        return back()->with('success', 'Gambar OG (Open Graph) berjaya dikemas kini.');
    }

    public function updateLoginImage(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('app_settings')) {
            return back()->with('error', 'Sila jalankan migration terlebih dahulu.');
        }

        $data = $request->validate([
            'login_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ]);

        $setting = AppSetting::singleton();

        $this->deleteStoredImage($setting->login_image_path);

        $storedPath = $data['login_image']->store('login-images', 'public');

        $setting->update([
            'login_image_path' => '/storage/'.ltrim($storedPath, '/'),
        ]);

        return back()->with('success', 'Gambar halaman log masuk berjaya dikemas kini.');
    }

    public function removeLoginImage(): RedirectResponse
    {
        if (! Schema::hasTable('app_settings')) {
            return back()->with('error', 'Sila jalankan migration terlebih dahulu.');
        }

        $setting = AppSetting::singleton();

        $this->deleteStoredImage($setting->login_image_path);

        $setting->update(['login_image_path' => null]);

        return back()->with('success', 'Gambar halaman log masuk berjaya dibuang.');
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

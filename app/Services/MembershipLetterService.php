<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\MembershipLetterNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Surat pengesahan keahlian.
 *
 * Satu sumber untuk PDF yang dimuat turun oleh ahli dan surat yang dihantar
 * automatik selepas pendaftaran diaktifkan. Setiap organisasi boleh
 * menetapkan letterhead, badan surat dan penandatangan sendiri.
 */
class MembershipLetterService
{
    public const DEFAULT_SIGNATORY_POSITION = 'Setiausaha / Pendaftar';

    public const DEFAULT_SUBJECT = 'Surat Pengesahan Keahlian - {{organization}}';

    public const DEFAULT_BODY = <<<'BODY'
Assalamualaikum dan salam sejahtera,

Dengan sukacita dimaklumkan bahawa {{name}} telah didaftarkan dan disahkan sebagai ahli {{organization}}.

No. Ahli: {{member_no}}
Tarikh Pengesahan: {{date}}

Pengesahan ini adalah untuk kegunaan rasmi dan boleh dijadikan bukti keahlian. Sila simpan surat ini untuk rujukan.

Sekian, terima kasih.
BODY;

    public function isEnabled(?User $user): bool
    {
        $user?->loadMissing('organization');

        return (bool) $user?->organization?->membership_letter_enabled;
    }

    /**
     * Hantar surat sekali sahaja selepas keahlian diaktifkan. Panggilan
     * berulang tidak menghasilkan emel pendua.
     */
    public function sendIfEligible(User $user): bool
    {
        $user->loadMissing(['organization', 'branch']);

        if (! $this->isEnabled($user)) {
            return false;
        }

        if (! $user->email || $user->emailIsPlaceholder()) {
            return false;
        }

        if ($user->membership_letter_sent_at !== null) {
            return false;
        }

        $user->notify(new MembershipLetterNotification($user));

        $user->forceFill(['membership_letter_sent_at' => now()])->saveQuietly();

        return true;
    }

    /**
     * @return array<string, string>
     */
    public function context(User $user): array
    {
        $user->loadMissing(['organization', 'branch']);
        $org = $user->organization;

        $data = [
            'name' => (string) $user->name,
            'member_no' => (string) ($user->member_no ?: '-'),
            'ic_number' => (string) ($user->ic_number ?: '-'),
            'organization' => (string) ($org?->name ?: 'Organisasi'),
            'branch' => (string) ($user->branch?->name ?? $user->branch_name ?? 'Tidak Berkenaan'),
            'state' => (string) ($user->state ?: '-'),
            'joined_at' => optional($user->created_at)->format('d/m/Y') ?? '-',
            'issued_at' => now()->format('d/m/Y'),
            'date' => now()->format('d/m/Y'),
            'app_name' => (string) (AppSetting::query()->first()?->app_name ?: config('app.name')),
            'signatory_name' => (string) ($org?->membership_letter_signatory_name ?? ''),
            'signatory_position' => (string) ($org?->membership_letter_signatory_position ?: self::DEFAULT_SIGNATORY_POSITION),
        ];

        $data['body'] = $this->renderBody($org, $data);

        return $data;
    }

    public function subject(User $user): string
    {
        $user->loadMissing('organization');

        $template = trim((string) ($user->organization?->membership_letter_subject ?? ''));

        if ($template === '') {
            $template = self::DEFAULT_SUBJECT;
        }

        return $this->replacePlaceholders($template, $this->context($user));
    }

    public function pdf(User $user): \Barryvdh\DomPDF\PDF
    {
        $context = $this->context($user);
        $org = $user->organization;

        return Pdf::loadView('exports.member-letter', [
            ...$context,
            'letterhead' => $this->imageDataUri($org?->membership_letter_letterhead_path),
            'org_logo' => $this->imageDataUri($org?->logo_path),
            'org_color' => $this->validHex($org?->color_theme) ? $org->color_theme : '#1f2937',
        ]);
    }

    public function filename(User $user): string
    {
        $reference = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) ($user->member_no ?: $user->id));

        return 'surat-pengesahan-keahlian-'.trim((string) $reference, '-').'.pdf';
    }

    /**
     * @param  array<string, string>  $data
     */
    private function renderBody(?Organization $org, array $data): string
    {
        $template = trim((string) ($org?->membership_letter_body ?? ''));

        if ($template === '') {
            $template = self::DEFAULT_BODY;
        }

        return $this->replacePlaceholders($template, $data);
    }

    /**
     * @param  array<string, string>  $data
     */
    private function replacePlaceholders(string $template, array $data): string
    {
        foreach ($data as $key => $value) {
            $template = str_replace('{{'.$key.'}}', $value, $template);
        }

        return $template;
    }

    private function validHex(?string $value): bool
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1;
    }

    /**
     * DomPDF tidak memuatkan imej jauh secara lalai, jadi imej tempatan
     * dihantar sebagai data URI.
     */
    private function imageDataUri(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $file = $this->publicFilePath($path);

        if ($file === null || ! is_file($file)) {
            return null;
        }

        $bytes = @file_get_contents($file);

        if ($bytes === false || $bytes === '') {
            return null;
        }

        $mime = match (strtolower((string) pathinfo($file, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/png',
        };

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }

    private function publicFilePath(string $path): ?string
    {
        $parsed = parse_url($path, PHP_URL_PATH) ?: $path;
        $relative = ltrim($parsed, '/');

        if (str_starts_with($relative, 'storage/')) {
            $relative = substr($relative, strlen('storage/'));
        }

        if ($relative !== '' && Storage::disk('public')->exists($relative)) {
            return Storage::disk('public')->path($relative);
        }

        return null;
    }
}

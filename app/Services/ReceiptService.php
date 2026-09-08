<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\InfaqDonation;
use App\Models\MembershipFee;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Registration;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Jana resit PDF (generik) bagi sebarang Payment berjaya — yuran keahlian,
 * infaq, pesanan mall dan yuran pendaftaran. Template tidak lagi menampal
 * nama sistem "myWAP"; sebaliknya ia dibranding mengikut organisasi penerima
 * (nama + logo + warna) supaya boleh terus diguna sebagai resit rasmi.
 *
 * Setiap resit dilindungi dengan URL ditandatangani (sama corak surat ahli)
 * supaya ahli boleh buka PDF dalam pelayar tanpa header Authorization.
 */
class ReceiptService
{
    /**
     * Label mesra manusia untuk channel pembayaran mentah yang disimpan oleh
     * gateway (contoh: INTERNET_BANKING_FPX). Kekunci tidak dikenali akan
     * dipaparkan sebagaimana disimpan (underscore → ruang).
     */
    private const CHANNEL_LABELS = [
        'INTERNET_BANKING_FPX' => 'FPX',
        'EWALLET_TNG' => 'TNG eWallet',
        'EWALLET_GRABPAY' => 'GrabPay',
        'EWALLET_SHOPEEPAY' => 'ShopeePay',
        'EWALLET_MAE' => 'MAE',
        'DUITNOW_QR' => 'DuitNow QR',
        'fpx' => 'FPX',
        'duitnow_qr' => 'DuitNow QR',
    ];

    private const GATEWAY_NAMES = [
        'senangpay' => 'SenangPay',
        'bayarcash' => 'BayarCash',
        'doku' => 'DOKU',
        'dummy' => 'Dalam Talian',
    ];

    /**
     * Muat turun URL yang ditandatangani untuk satu resit. URL ini bersifat
     * sementara dan boleh dibuka terus dalam pelayar tanpa sesi/login.
     */
    public function signedUrl(Payment $payment): string
    {
        return URL::temporarySignedRoute(
            'receipt.show',
            now()->addMinutes(30),
            ['payment' => $payment->id],
        );
    }

    public function pdf(Payment $payment): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('exports.receipt', $this->context($payment));
    }

    public function filename(Payment $payment): string
    {
        $ref = (string) ($payment->reference ?: 'payment-'.$payment->id);
        $ref = preg_replace('/[^A-Za-z0-9\-_.]/', '_', $ref);

        return 'resit-'.trim($ref, '-_').'.pdf';
    }

    /**
     * Satu baris untuk "Sejarah Pembayaran" (payload overview) supaya web dan
     * API berkongsi bentuk yang sama termasuk pautan resit.
     */
    public function historyRow(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'payable_type' => $payment->payable_type,
            'amount' => (float) $payment->amount,
            'status' => $payment->status,
            'reference' => $payment->reference,
            'description' => $payment->description,
            'gateway' => $payment->gateway,
            'channel' => $payment->channel,
            'created_at' => $payment->created_at?->toISOString(),
            'receipt_url' => $payment->status === 'successful' ? $this->signedUrl($payment) : null,
        ];
    }

    /**
     * Konteks lengkap untuk template `exports/receipt`. Dibina dari satu
     * Payment + rekod yang berkaitan (polymorphic) tanpa mengira jenis.
     *
     * @return array<string, mixed>
     */
    public function context(Payment $payment): array
    {
        $payment->loadMissing(['user.organization', 'organization']);

        $record = $this->resolveRecord($payment);
        $type = $this->typeKey($payment);

        $org = $this->resolveOrganization($payment, $record);
        $billTo = $this->billTo($payment, $record, $type);
        $extra = $this->extraMeta($payment, $record, $type);
        $items = $this->items($payment, $record, $type);

        $amount = (float) $payment->amount;

        return [
            'title' => $this->title($type),
            'org_name' => $org['name'],
            'org_color' => $org['color'],
            'org_logo' => $this->logoDataUri($org['logo'] ?? null),
            'receipt_no' => $payment->reference ?: 'RESIT-'.$payment->id,
            'status_label' => 'LUNAS',
            'paid_at' => $this->paidAt($payment, $record)->format('d/m/Y h:i A'),
            'payment_method' => $this->methodLabel($payment),
            'bill_to' => $billTo,
            'extra' => $extra,
            'items' => $items,
            'items_have_qty' => collect($items)->contains(fn ($i) => ! empty($i['qty'])),
            'total' => $this->money($amount),
            'footer_note' => 'Resit ini dijana secara automatik oleh sistem dan disahkan secara digital.',
            'generated_at' => now()->format('d/m/Y h:i A'),
        ];
    }

    // ─── Rekod & jenis ─────────────────────────────────────────────────────────

    private function typeKey(Payment $payment): string
    {
        return match ($payment->payable_type) {
            MembershipFee::class, 'membership_fee' => 'membership',
            Order::class, 'order' => 'order',
            InfaqDonation::class, 'infaq_donation' => 'infaq',
            Registration::class => 'registration',
            default => 'other',
        };
    }

    private function resolveRecord(Payment $payment): ?Model
    {
        return match ($payment->payable_type) {
            MembershipFee::class => $payment->payable_id
                ? MembershipFee::find($payment->payable_id)
                : null,
            'membership_fee' => MembershipFee::where('payment_id', $payment->id)->first(),
            Order::class, 'order' => $payment->payable_id
                ? Order::with(['items.product', 'user', 'organization'])->find($payment->payable_id)
                : null,
            InfaqDonation::class, 'infaq_donation' => $payment->payable_id
                ? InfaqDonation::with(['infaq', 'user'])->find($payment->payable_id)
                : null,
            Registration::class => $payment->payable_id
                ? Registration::with(['event', 'user', 'organization'])->find($payment->payable_id)
                : null,
            default => null,
        };
    }

    private function title(string $type): string
    {
        return match ($type) {
            'membership' => 'RESIT PEMBAYARAN YURAN',
            'order' => 'RESIT PEMBAYARAN PESANAN',
            'infaq' => 'RESIT SUMBANGAN INFAQ',
            'registration' => 'RESIT YURAN PENDAFTARAN',
            default => 'RESIT PEMBAYARAN',
        };
    }

    // ─── Organisasi (branding) ────────────────────────────────────────────────

    /**
     * @return array{name: ?string, color: ?string, logo: ?string}
     */
    private function resolveOrganization(Payment $payment, ?Model $record): array
    {
        $org = $payment->relationLoaded('organization') && $payment->organization
            ? $payment->organization
            : null;

        if (! $org && $payment->user?->organization) {
            $org = $payment->user->organization;
        }

        if (! $org) {
            $org = match (true) {
                $record instanceof Order => $record->organization,
                $record instanceof InfaqDonation => $record->infaq?->organization,
                $record instanceof Registration => $record->organization
                    ?? $record->event?->organization,
                default => null,
            };
        }

        if ($org) {
            return [
                'name' => $org->name,
                'color' => $this->validHex($org->color_theme) ? $org->color_theme : null,
                'logo' => $org->logo_path,
            ];
        }

        $setting = AppSetting::query()->first();

        return [
            'name' => $setting?->app_name ?: 'Organisasi',
            'color' => null,
            'logo' => $setting?->system_logo_path,
        ];
    }

    private function validHex(?string $value): bool
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1;
    }

    /**
     * Tukar logo (laluan storage / URL) kepada data-URI base64 supaya boleh
     * dipaparkan oleh DomPDF tanpa enable_remote.
     */
    private function logoDataUri(?string $logo): ?string
    {
        if (! $logo) {
            return null;
        }

        $mime = null;
        $file = $this->logoFile($logo);

        if ($file && is_file($file)) {
            $mime = match (strtolower((string) pathinfo($file, PATHINFO_EXTENSION))) {
                'png' => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'svg' => 'image/svg+xml',
                default => mime_content_type($file) ?: 'image/png',
            };
            $bytes = @file_get_contents($file);
        } else {
            // Logo dihoskan pada URL jauh (bukan cakera tempatan).
            if (! str_starts_with($logo, 'http://') && ! str_starts_with($logo, 'https://')) {
                return null;
            }

            $mime = 'image/png';
            $bytes = @file_get_contents($logo);
        }

        if ($bytes === false || $bytes === '') {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }

    private function logoFile(string $logo): ?string
    {
        $path = parse_url($logo, PHP_URL_PATH) ?: $logo;
        $path = ltrim($path, '/');

        // /storage/... atau URL awam → cari dalam cakera awam (storage/app/public).
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));

            return Storage::disk('public')->path($path);
        }

        // Laluan relatif lain → andaikan ia di bawah cakera awam.
        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->path($path);
        }

        // URL luar biasa penuh (bukan storage).
        if (str_starts_with($logo, 'http://') || str_starts_with($logo, 'https://')) {
            return $logo;
        }

        return null;
    }

    // ─── Maklumat pembayar & meta ─────────────────────────────────────────────

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function billTo(Payment $payment, ?Model $record, string $type): array
    {
        $user = $payment->user;
        $rows = [];

        switch ($type) {
            case 'registration':
                if ($record instanceof Registration) {
                    $rows['Nama Peserta'] = $record->name;
                    $this->push($rows, 'No Ahli', $record->member_no);
                    $this->push($rows, 'No IC', $record->ic_number);
                    $this->push($rows, 'Emel', $record->email);
                    $this->push($rows, 'Telefon', $record->phone);
                }
                break;

            case 'order':
                if ($record instanceof Order && $record->shipping_name && (! $user || $user->name !== $record->shipping_name)) {
                    $rows['Nama'] = $record->shipping_name;
                    $this->push($rows, 'Telefon', $record->shipping_phone);
                }
                break;

            case 'infaq':
                if ($record instanceof InfaqDonation && $record->is_anonymous && ! $user) {
                    $rows['Nama'] = 'Hamba Allah';
                }
                break;
        }

        if ($user) {
            $rows['Nama'] = $user->name;
            $this->push($rows, 'No Ahli', $user->member_no);
            $this->push($rows, 'No IC', $user->ic_number);
            $this->push($rows, 'Emel', $user->email);
            $this->push($rows, 'Telefon', $user->phone);
        }

        if (empty($rows) && $record instanceof InfaqDonation) {
            $rows['Nama'] = $record->donor_name ?: 'Hamba Allah';
        }

        return $this->pairRows($rows);
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function extraMeta(Payment $payment, ?Model $record, string $type): array
    {
        $rows = [];

        if ($record instanceof MembershipFee) {
            $this->push($rows, 'Tahun Yuran', (string) $record->year);
        }

        if ($type === 'registration' && $record instanceof Registration) {
            $this->push($rows, 'No Pendaftaran', $record->registration_no);
            $this->push($rows, 'Acara', $record->event?->title);
            $this->push($rows, 'Kategori Tiket', $record->ticket_type);
        }

        if ($type === 'order' && $record instanceof Order) {
            $this->push($rows, 'No Pesanan', '#'.$record->id);
        }

        if ($type === 'infaq' && $record instanceof InfaqDonation) {
            $this->push($rows, 'Kempen', $record->infaq?->title);
            if ($record->is_recurring) {
                $this->push($rows, 'Jenis', 'Donasi Berkala ('.ucfirst((string) $record->frequency).')');
            }
        }

        return $this->pairRows($rows);
    }

    // ─── Baris item ────────────────────────────────────────────────────────────

    /**
     * @return array<int, array{label: string, note?: string, qty?: int, amount: float, amount_label: string}>
     */
    private function items(Payment $payment, ?Model $record, string $type): array
    {
        $amount = (float) $payment->amount;

        if ($record instanceof Order) {
            $lines = [];

            foreach ($record->items as $item) {
                $note = $this->orderItemNote($item);

                $lines[] = [
                    'label' => $item->product?->name ?: 'Produk',
                    'note' => $note,
                    'qty' => (int) $item->quantity,
                    'unit_label' => $this->money((float) $item->price),
                    'amount' => (float) $item->price * (int) $item->quantity,
                ];
            }

            if ((float) $record->postage_cost > 0) {
                $lines[] = [
                    'label' => 'Penghantaran',
                    'note' => null,
                    'qty' => null,
                    'unit_label' => null,
                    'amount' => (float) $record->postage_cost,
                ];
            }

            return $this->withAmountLabels($lines);
        }

        $description = $payment->description;

        if ($type === 'membership') {
            $year = $record instanceof MembershipFee
                ? (int) $record->year
                : (int) ($payment->created_at?->year ?: now()->year);

            $description = $description ?: 'Yuran Keahlian '.($this->orgName($payment) ?? '').' '.$year;
        }

        if ($type === 'registration' && $record instanceof Registration) {
            $description = $description ?: 'Yuran Pendaftaran '.($record->event?->title ?: '');
        }

        if ($type === 'infaq' && $record instanceof InfaqDonation) {
            $description = $description ?: 'Donasi: '.($record->infaq?->title ?: '');
        }

        $description = trim((string) $description) ?: 'Pembayaran';

        return $this->withAmountLabels([
            ['label' => $description, 'amount' => $amount],
        ]);
    }

    private function withAmountLabels(array $lines): array
    {
        return collect($lines)
            ->map(fn ($line) => [
                ...$line,
                'amount_label' => $this->money((float) ($line['amount'] ?? 0)),
            ])
            ->values()
            ->all();
    }

    private function orderItemNote(OrderItem $item): ?string
    {
        $note = null;
        $productName = $item->product?->name;

        if ($item->variation_snapshot && $productName && ! str_contains($item->variation_snapshot, $productName)) {
            $note = $item->variation_snapshot;
        }

        return $note ?: null;
    }

    // ─── Kaedah bayaran ───────────────────────────────────────────────────────

    private function methodLabel(Payment $payment): string
    {
        $reference = strtoupper((string) $payment->reference);

        if ($payment->gateway === 'dummy' || str_starts_with($reference, 'DUMMY-')) {
            return 'Bayaran Dalam Talian';
        }

        if (str_starts_with($reference, 'MANUAL-')) {
            return 'Bayaran Manual (Disahkan Admin)';
        }

        if (str_starts_with($reference, 'CSV-')) {
            return 'Kemaskini (Import CSV)';
        }

        $gateway = self::GATEWAY_NAMES[$payment->gateway ?? ''] ?? null;

        $channel = $payment->channel
            ? self::CHANNEL_LABELS[$payment->channel] ?? $this->prettyChannel($payment->channel)
            : null;

        $method = $gateway ?: 'Dalam Talian';
        if ($channel) {
            $method .= ' ('.$channel.')';
        }

        return $method;
    }

    private function prettyChannel(string $channel): string
    {
        return ucwords(str_replace('_', ' ', strtolower($channel)));
    }

    // ─── Tarikh ───────────────────────────────────────────────────────────────

    private function paidAt(Payment $payment, ?Model $record): \DateTimeInterface
    {
        $paidAt = $record instanceof MembershipFee && $record->paid_at
            ? $record->paid_at
            : $payment->updated_at;

        return $paidAt ?: $payment->created_at ?: now();
    }

    // ─── Utiliti ──────────────────────────────────────────────────────────────

    private function orgName(Payment $payment): ?string
    {
        if ($payment->organization) {
            return $payment->organization->name;
        }

        return $payment->user?->organization?->name;
    }

    private function money(float $amount): string
    {
        return 'RM '.number_format($amount, 2);
    }

    private function push(array &$rows, string $label, mixed $value): void
    {
        if ($value !== null && $value !== '') {
            $rows[$label] = (string) $value;
        }
    }

    /**
     * @param array<string, string> $rows
     *
     * @return array<int, array{label: string, value: string}>
     */
    private function pairRows(array $rows): array
    {
        return collect($rows)
            ->map(fn (string $value, string $label) => ['label' => $label, 'value' => $value])
            ->values()
            ->all();
    }
}

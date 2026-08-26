<?php

namespace App\Http\Controllers;

use App\Models\Infaq;
use App\Models\InfaqDonation;
use App\Models\Organization;
use App\Services\BayarCashService;
use App\Services\DonorService;
use App\Services\InfaqService;
use App\Services\PaymentGatewayManager;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class InfaqController extends Controller
{
    public function __construct(
        protected BayarCashService $bayarCashService,
        protected DonorService $donorService,
        protected PaymentGatewayManager $gateways,
        protected InfaqService $infaqService,
    ) {}

    // ─── Superadmin: list all infaq ─────────────────────────────────────────

    public function manage(): Response
    {
        $items = Infaq::query()
            ->with('organization:id,name,slug')
            ->withCount('donations')
            ->orderBy('display_order')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Infaq $infaq) => [
                'id' => $infaq->id,
                'title' => $infaq->title,
                'slug' => $infaq->slug,
                'description' => $infaq->description,
                'external_url' => $infaq->external_url,
                'is_external' => $infaq->is_external,
                'image_path' => $infaq->image_path,
                'type' => $infaq->type,
                'allow_recurring' => $infaq->allow_recurring,
                'target_amount' => $infaq->target_amount,
                'collected_amount' => $infaq->collected_amount,
                'progress_percent' => $infaq->progress_percent,
                'is_active' => $infaq->is_active,
                'display_order' => $infaq->display_order,
                'organization_id' => $infaq->organization_id,
                'organization_name' => $infaq->organization?->name ?? 'Global',
                'donations_count' => $infaq->donations_count,
                'public_url' => $infaq->public_url,
            ]);

        return Inertia::render('Superadmin/InfaqManage', [
            'organizations' => Organization::query()->orderBy('min_age')->get(['id', 'name', 'slug']),
            'infaqItems' => $items,
        ]);
    }

    // ─── Superadmin: create ──────────────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'external_url' => ['nullable', 'url', 'max:1000'],
            'type' => ['required', 'in:one_off,progress'],
            'allow_recurring' => ['nullable', 'boolean'],
            'target_amount' => ['nullable', 'numeric', 'min:1', 'max:9999999'],
            'is_active' => ['nullable', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'infaq_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:5120'],
        ]);

        $isExternal = filled($data['external_url'] ?? null);

        $imagePath = null;
        if ($request->hasFile('infaq_image')) {
            $storedPath = $request->file('infaq_image')->store('infaq', 'public');
            $imagePath = '/storage/'.ltrim($storedPath, '/');
        }

        Infaq::create([
            'organization_id' => $data['organization_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'external_url' => $data['external_url'] ?? null,
            'image_path' => $imagePath,
            'type' => $data['type'],
            // External campaigns never use internal recurring donation.
            'allow_recurring' => $isExternal ? false : (bool) ($data['allow_recurring'] ?? false),
            'target_amount' => $data['type'] === 'progress' ? ($data['target_amount'] ?? null) : null,
            'collected_amount' => 0,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'display_order' => (int) ($data['display_order'] ?? 1),
        ]);

        return back()->with('success', 'Infaq berjaya dicipta.');
    }

    // ─── Superadmin: update ──────────────────────────────────────────────────

    public function update(Request $request, Infaq $infaq): RedirectResponse
    {
        $data = $request->validate([
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'external_url' => ['nullable', 'url', 'max:1000'],
            'type' => ['required', 'in:one_off,progress'],
            'allow_recurring' => ['nullable', 'boolean'],
            'target_amount' => ['nullable', 'numeric', 'min:1', 'max:9999999'],
            'is_active' => ['nullable', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'infaq_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:5120'],
        ]);

        $isExternal = filled($data['external_url'] ?? null);

        $imagePath = $infaq->image_path;

        if ($request->hasFile('infaq_image')) {
            // Delete old image
            if ($imagePath) {
                $oldRel = ltrim(str_replace('/storage/', '', parse_url($imagePath, PHP_URL_PATH) ?? ''), '/');
                if ($oldRel && Storage::disk('public')->exists($oldRel)) {
                    Storage::disk('public')->delete($oldRel);
                }
            }
            $newPath = $request->file('infaq_image')->store('infaq', 'public');
            $imagePath = '/storage/'.ltrim($newPath, '/');
        }

        $infaq->update([
            'organization_id' => $data['organization_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'external_url' => $data['external_url'] ?? null,
            'image_path' => $imagePath,
            'type' => $data['type'],
            'allow_recurring' => $isExternal ? false : (bool) ($data['allow_recurring'] ?? false),
            'target_amount' => $data['type'] === 'progress' ? ($data['target_amount'] ?? null) : null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'display_order' => (int) ($data['display_order'] ?? 1),
        ]);

        return back()->with('success', 'Infaq berjaya dikemas kini.');
    }

    // ─── Superadmin: delete ──────────────────────────────────────────────────

    public function destroy(Infaq $infaq): RedirectResponse
    {
        if ($infaq->image_path) {
            $oldRel = ltrim(str_replace('/storage/', '', parse_url($infaq->image_path, PHP_URL_PATH) ?? ''), '/');
            if ($oldRel && Storage::disk('public')->exists($oldRel)) {
                Storage::disk('public')->delete($oldRel);
            }
        }

        $infaq->delete();

        return back()->with('success', 'Infaq berjaya dipadam.');
    }

    // ─── Superadmin: seed demo data ──────────────────────────────────────────

    public function seedDemo(): RedirectResponse
    {
        $seeds = [
            [
                'title' => 'Infaq Masjid Al-Iman',
                'description' => 'Bantu kami membina kemudahan solat yang lebih selesa untuk komuniti.',
                'type' => 'progress',
                'target_amount' => 50000,
                'collected_amount' => 23750,
                'display_order' => 1,
            ],
            [
                'title' => 'Infaq Anak Yatim Ramadan',
                'description' => 'Sumbangan untuk anak-anak yatim sempena bulan Ramadan yang mulia.',
                'type' => 'one_off',
                'target_amount' => null,
                'collected_amount' => 8100,
                'display_order' => 2,
            ],
            [
                'title' => 'Dana Pendidikan Islam',
                'description' => 'Tajaan kelas Quran & fardhu ain untuk pelajar kurang berkemampuan.',
                'type' => 'progress',
                'target_amount' => 15000,
                'collected_amount' => 9600,
                'display_order' => 3,
            ],
            [
                'title' => 'Infaq Buku & Pustaka',
                'description' => 'Sumbangkan untuk pengembangan koleksi buku perpustakaan komuniti.',
                'type' => 'progress',
                'target_amount' => 8000,
                'collected_amount' => 4200,
                'display_order' => 4,
            ],
            [
                'title' => 'Infaq Am — Derma Bebas',
                'description' => 'Sumbangan am untuk kegunaan operasi pertubuhan.',
                'type' => 'one_off',
                'target_amount' => null,
                'collected_amount' => 3300,
                'display_order' => 5,
            ],
        ];

        $palettes = [
            ['from' => '#059669', 'to' => '#065f46', 'text' => '#d1fae5'],
            ['from' => '#6366f1', 'to' => '#3730a3', 'text' => '#e0e7ff'],
            ['from' => '#f59e0b', 'to' => '#b45309', 'text' => '#fef3c7'],
            ['from' => '#0ea5e9', 'to' => '#0369a1', 'text' => '#e0f2fe'],
            ['from' => '#ec4899', 'to' => '#9d174d', 'text' => '#fce7f3'],
        ];

        foreach ($seeds as $i => $seed) {
            $palette = $palettes[$i % count($palettes)];
            $shortTitle = mb_substr($seed['title'], 0, 30);

            $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="600" viewBox="0 0 800 600">
  <defs>
    <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:{$palette['from']};stop-opacity:1"/>
      <stop offset="100%" style="stop-color:{$palette['to']};stop-opacity:1"/>
    </linearGradient>
  </defs>
  <rect width="800" height="600" fill="url(#bg)"/>
  <circle cx="700" cy="80" r="180" fill="white" fill-opacity="0.06"/>
  <circle cx="120" cy="520" r="140" fill="white" fill-opacity="0.06"/>
  <text x="60" y="200" font-family="Arial, sans-serif" font-size="26" font-weight="bold" fill="{$palette['text']}" opacity="0.8">INFAQ</text>
  <text x="60" y="260" font-family="Arial, sans-serif" font-size="38" font-weight="900" fill="white">{$shortTitle}</text>
  <text x="60" y="320" font-family="Arial, sans-serif" font-size="20" fill="white" opacity="0.75">Derma &amp; Sumbangan</text>
  <rect x="60" y="380" width="120" height="4" rx="2" fill="white" fill-opacity="0.5"/>
</svg>
SVG;

            $filename = 'infaq/demo_infaq_'.($i + 1).'.svg';
            Storage::disk('public')->put($filename, $svg);
            $imagePath = '/storage/'.ltrim($filename, '/');

            Infaq::updateOrCreate(
                ['title' => $seed['title']],
                array_merge($seed, [
                    'organization_id' => null,
                    'image_path' => $imagePath,
                    'is_active' => true,
                ])
            );
        }

        return back()->with('success', 'Demo infaq berjaya dijana ('.count($seeds).' item).');
    }

    // ─── Public: list page ────────────────────────────────────────────────

    public function index()
    {
        return Inertia::render('Infaq/Index', $this->infaqService->index());
    }

    // ─── Member: detail page ────────────────────────────────────────────────

    public function show(Request $request, $year, $month, $day, Infaq $infaq): Response
    {
        abort_unless((bool) $infaq->is_active, 404);

        return Inertia::render('Infaq/Show', $this->infaqService->showDetail($infaq));
    }

    // ─── Member: submit a donation ───────────────────────────────────────────

    public function donateForm(Request $request, $year, $month, $day, Infaq $infaq)
    {
        abort_unless((bool) $infaq->is_active, 404);

        // External campaigns have no internal donation form — send them to DOKU.
        if ($infaq->is_external) {
            return Inertia::location($infaq->external_url);
        }

        $infaq->load('organization:id,name');

        return Inertia::render('Infaq/Donate', [
            'infaq' => [
                'id' => $infaq->id,
                'title' => $infaq->title,
                'image_path' => $infaq->image_path,
                'allow_recurring' => $infaq->allow_recurring,
                'public_url' => $infaq->public_url,
            ],
        ]);
    }

    public function donate(Request $request, $year, $month, $day, Infaq $infaq): \Symfony\Component\HttpFoundation\Response
    {
        $result = $this->infaqService->donate($request, $infaq, $request->user());

        if ($result['status'] === 'redirect') {
            return Inertia::location($result['payment_url']);
        }

        if ($result['status'] === 'error') {
            return back()->with('error', $result['message']);
        }

        return redirect()->route('infaq.success', [
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'infaq' => $infaq->slug,
        ]);
    }

    public function qrCode($year, $month, $day, Infaq $infaq)
    {
        return $this->renderInfaqQr($infaq);
    }

    public function qrCodeSuperadmin(Infaq $infaq)
    {
        return $this->renderInfaqQr($infaq);
    }

    private function renderInfaqQr(Infaq $infaq)
    {
        $shortUrl = route('infaq.short', ['infaq' => $infaq->slug]);

        $png = extension_loaded('imagick')
            ? QrCode::format('png')->size(300)->margin(1)->generate($shortUrl)
            : $this->qrPngGd($shortUrl, 300, 1);

        return response($png, 200, ['Content-Type' => 'image/png']);
    }

    /**
     * qrPngGd()
     *
     * Renders a QR code to PNG using only the GD extension (no Imagick).
     * Reads the module matrix from BaconQrCode and draws it with GD primitives.
     * Used as a fallback so PNG QR codes work even when Imagick is not installed.
     */
    private function qrPngGd(string $text, int $size = 300, int $margin = 1, string $errorCorrection = 'H'): string
    {
        $ecLevel = match (strtoupper($errorCorrection)) {
            'L' => ErrorCorrectionLevel::L(),
            'M' => ErrorCorrectionLevel::M(),
            'Q' => ErrorCorrectionLevel::Q(),
            default => ErrorCorrectionLevel::H(),
        };

        $matrix = Encoder::encode($text, $ecLevel)->getMatrix();
        $modules = $matrix->getWidth();
        $total = $modules + ($margin * 2);

        $scale = (int) floor($size / $total);
        if ($scale < 1) {
            $scale = 1;
        }
        $pixelSize = $total * $scale;

        $image = imagecreatetruecolor($pixelSize, $pixelSize);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefilledrectangle($image, 0, 0, $pixelSize, $pixelSize, $white);

        for ($y = 0; $y < $modules; $y++) {
            for ($x = 0; $x < $modules; $x++) {
                if ($matrix->get($x, $y)) {
                    imagefilledrectangle(
                        $image,
                        ($x + $margin) * $scale,
                        ($y + $margin) * $scale,
                        (($x + $margin) * $scale) + $scale - 1,
                        (($y + $margin) * $scale) + $scale - 1,
                        $black
                    );
                }
            }
        }

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }

    public function donors(Infaq $infaq): Response
    {
        $donations = InfaqDonation::query()
            ->where('infaq_id', $infaq->id)
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (InfaqDonation $d) => [
                'id' => $d->id,
                'donor_name' => $d->is_anonymous ? 'Hamba Allah' : ($d->donor_name ?? $d->user?->name ?? 'Tanpa Nama'),
                'donor_email' => $d->donor_email,
                'donor_phone' => $d->donor_phone,
                'amount' => (float) $d->amount,
                'status' => $d->status,
                'reference' => $d->reference,
                'is_anonymous' => $d->is_anonymous,
                'is_recurring' => $d->is_recurring,
                'frequency' => $d->frequency,
                'recurring_status' => $d->recurring_status,
                'prayer_message' => $d->prayer_message,
                'wants_updates' => $d->wants_updates,
                'created_at' => $d->created_at->format('d M Y, h:i A'),
                'created_at_iso' => $d->created_at->toISOString(),
            ]);

        return Inertia::render('Superadmin/InfaqDonors', [
            'infaq' => [
                'id' => $infaq->id,
                'title' => $infaq->title,
                'slug' => $infaq->slug,
                'type' => $infaq->type,
                'target_amount' => (float) $infaq->target_amount,
                'collected_amount' => (float) $infaq->collected_amount,
                'progress_percent' => $infaq->progress_percent,
                'organization_name' => $infaq->organization?->name ?? 'Global',
                'public_url' => $infaq->public_url,
            ],
            'donations' => $donations,
        ]);
    }

    public function success(Request $request, $year, $month, $day, Infaq $infaq): Response
    {
        return Inertia::render('Infaq/Success', [
            'infaq' => [
                'id' => $infaq->id,
                'slug' => $infaq->slug,
                'title' => $infaq->title,
                'public_url' => $infaq->public_url,
                'year' => $year,
                'month' => $month,
                'day' => $day,
            ],
        ]);
    }
}

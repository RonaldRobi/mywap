<?php

namespace App\Http\Controllers;

use App\Enums\RegistrationStatus;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\Form;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\RegistrationItem;
use App\Models\User;
use App\Services\PaymentGatewayManager;
use App\Services\RegistrationPaymentService;
use App\Services\RegistrationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * RegistrationController
 *
 * Modul "Registration" — jambatan antara Event, Form (borang pendaftaran)
 * dan Payment. Modul-modul lain kekal berasingan; entiti Registration yang
 * menghubungkan mereka.
 */
class RegistrationController extends Controller
{
    public function __construct(
        protected PaymentGatewayManager $gateways,
        protected RegistrationService $registrations,
    ) {}

    // ─── MEMBER: Daftar Event ─────────────────────────────────────────────────

    /**
     * Papar borang pendaftaran untuk seorang ahli (login diperlukan).
     */
    public function create(Request $request, Event $event, Form $form): Response|RedirectResponse
    {
        $user = $request->user();
        $form->load(['event.organization', 'questions' => fn ($q) => $q->orderBy('sort_order')]);

        abort_unless($form->event_id === $event->id, 404, 'Borang tidak tergolong dalam event ini.');
        abort_unless($form->is_active, 404, 'Borang pendaftaran ini tidak aktif.');

        abort_unless($event->isPublished(), 404, 'Event belum diterbitkan.');
        $this->abortUnlessRegistrationOpen($event);
        $this->assertEventRegistrationAccess($event, $user);

        $existing = Registration::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            return redirect()->route('member.registrations')
                ->with('error', 'Anda sudah mendaftar untuk event ini (No: '.$existing->registration_no.').');
        }

        return Inertia::render('Events/Register', [
            'form' => $this->serializeForm($form, $user),
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'slug' => $event->slug,
                'start_date' => $event->start_time->locale('ms')->isoFormat('D MMMM YYYY'),
                'organization_name' => $event->organization?->name ?? 'Semua Organisasi',
                'featured_image_url' => $event->featured_image_url,
                'location_or_link' => $event->location_or_link,
                'google_calendar_url' => $event->google_calendar_url,
                'maps_url' => $event->maps_url
                    ?: ($event->location_or_link
                        ? 'https://www.google.com/maps/search/?api=1&query='.urlencode($event->location_or_link)
                        : null),
            ],
            'paymentGateway' => $this->resolvePaymentGateway($form, $event),
        ]);
    }

    /**
     * Ahli menghantar pendaftaran.
     */
    public function store(Request $request, Event $event, Form $form): \Symfony\Component\HttpFoundation\Response
    {
        $user = $request->user();
        $form->load('questions');

        abort_unless($form->event_id === $event->id, 404, 'Borang tidak tergolong dalam event ini.');
        abort_unless($form->is_active, 404);

        $this->abortUnlessRegistrationOpen($event);
        $this->assertEventRegistrationAccess($event, $user);

        $data = $request->validate($this->registrations->validationRules($form));

        $result = $this->registrations->submitForEvent($form, $event, $data, $user);

        return $this->registrationSubmitResponse($result);
    }

    // ─── BUKAN AHLI: Daftar via borang public ─────────────────────────────────

    /**
     * Papar borang pendaftaran event untuk bukan ahli (tanpa login).
     */
    public function publicCreate(string $token): Response
    {
        $form = Form::where('share_token', $token)
            ->where('is_active', true)
            ->with(['event.organization', 'questions' => fn ($q) => $q->orderBy('sort_order')])
            ->firstOrFail();

        abort_unless($form->event_id, 404, 'Borang ini bukan borang pendaftaran event.');
        abort_unless($form->event, 404, 'Event tidak dijumpai.');
        abort_unless($form->event->isPublished(), 404, 'Event belum diterbitkan.');
        $this->abortUnlessRegistrationOpen($form->event);
        $this->assertEventRegistrationAccess($form->event, request()->user());

        // Alat kongsi hanya untuk admin/superadmin — orang awam tidak nampak.
        $user = request()->user();
        $canShare = (bool) ($user && $user->hasRole(['Superadmin', 'Admin', 'org-admin']));

        $publicUrl = route('events.register.public', $form->share_token);

        return Inertia::render('Events/Register', [
            'form' => $this->serializeForm($form),
            'event' => [
                'id' => $form->event->id,
                'title' => $form->event->title,
                'slug' => $form->event->slug,
                'start_date' => $form->event->start_time->locale('ms')->isoFormat('D MMMM YYYY'),
                'organization_name' => $form->event->organization?->name ?? 'Semua Organisasi',
                'featured_image_url' => $form->event->featured_image_url,
                'location_or_link' => $form->event->location_or_link,
                'google_calendar_url' => $form->event->google_calendar_url,
                'maps_url' => $form->event->maps_url
                    ?: ($form->event->location_or_link
                        ? 'https://www.google.com/maps/search/?api=1&query='.urlencode($form->event->location_or_link)
                        : null),
            ],
            'isGuest' => true,
            'paymentGateway' => $this->resolvePaymentGateway($form, $form->event),
            'canShare' => $canShare,
            'publicUrl' => $publicUrl,
            'qrSvg' => $canShare ? (string) QrCode::format('svg')->size(220)->errorCorrection('M')->generate($publicUrl) : null,
        ]);
    }

    /**
     * Bukan ahli menghantar pendaftaran.
     */
    public function publicStore(Request $request, string $token): \Symfony\Component\HttpFoundation\Response
    {
        $form = Form::where('share_token', $token)
            ->where('is_active', true)
            ->with('questions')
            ->firstOrFail();

        abort_unless($form->event_id, 404);
        abort_unless($form->event, 404, 'Event tidak dijumpai.');
        $this->abortUnlessRegistrationOpen($form->event);
        $this->assertEventRegistrationAccess($form->event, $request->user());

        $data = $request->validate($this->registrations->validationRules($form));

        $result = $this->registrations->submitForEvent($form, $form->event, $data, null);

        return $this->registrationSubmitResponse($result);
    }

    // ─── Core processing ───────────────────────────────────────────────────────

    /**
     * Peta hasil kongsi (RegistrationService::submitForEvent) kepada respons
     * Inertia — redirect ke gateway untuk bayaran, atau ke halaman berjaya.
     */
    protected function registrationSubmitResponse(array $result): \Symfony\Component\HttpFoundation\Response
    {
        $registration = $result['registration'];

        if ($result['status'] === 'redirect') {
            return Inertia::location($result['payment_url']);
        }

        $flash = $result['status'] === 'success'
            ? ['success' => $result['message'] ?? 'Pendaftaran berjaya!']
            : ['error' => $result['message'] ?? 'Pembayaran gagal diproses. Sila cuba lagi.'];

        return redirect()->route('registrations.success', $registration)->with($flash);
    }

    // ─── Halaman Berjaya ───────────────────────────────────────────────────────

    public function success(Request $request, Registration $registration): Response
    {
        $registration->load(['event.organization', 'form', 'latestPayment', 'attendance', 'items']);

        $user = $request->user();
        $allowed = $registration->user_id === $user?->id
            || ($user && $user->hasRole(['Superadmin', 'Admin', 'org-admin']))
            || ! $registration->user_id;

        abort_unless($allowed, 403);

        // Rekonsiliasi bayaran DOKU yang masih pending supaya status kemas kini
        // jika bayaran sebenarnya sudah berjaya di DOKU.
        if ($payment = $registration->latestPayment) {
            app(RegistrationPaymentService::class)->reconcileDokuPayment($payment);
            $registration->load('latestPayment');
        }

        return Inertia::render('Events/RegistrationSuccess', [
            'registration' => [
                'registration_no' => $registration->registration_no,
                'name' => $registration->name,
                'email' => $registration->email,
                'phone' => $registration->phone,
                'status' => $registration->status->value,
                'status_label' => $registration->status->label(),
                'ticket_type' => $registration->ticket_type,
                'base_amount' => (float) $registration->base_amount,
                'addons_amount' => (float) $registration->addons_amount,
                'total_amount' => $registration->totalAmount(),
                'items' => $registration->items->map(fn ($item) => [
                    'name' => $item->name,
                    'variant_name' => $item->variant_name,
                    'quantity' => (int) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'line_total' => (float) $item->line_total,
                ])->values()->all(),
                'payment_status' => $registration->latestPayment?->status ?? 'paid',
                'attended' => $registration->attendance !== null,
            ],
            'event' => [
                'title' => $registration->event->title,
                'start_formatted' => $registration->event->start_time->locale('ms')->isoFormat('ddd, D MMM YYYY [•] h:mm A'),
                'location_or_link' => $registration->event->location_or_link,
                'organization_name' => $registration->event->organization?->name ?? 'Semua Organisasi',
            ],
            'form' => [
                'title' => $registration->form?->title,
                'price' => $registration->form?->price,
            ],
        ]);
    }

    // ─── AHLI: Pendaftaran Saya ────────────────────────────────────────────────

    public function memberIndex(Request $request): Response
    {
        $user = $request->user();

        $registrations = app(RegistrationService::class)->memberRegistrations($user);

        return Inertia::render('Member/Registrations', [
            'registrations' => $registrations,
        ]);
    }

    // ─── ADMIN: Senarai Pendaftaran ────────────────────────────────────────────

    public function adminIndex(Request $request, Event $event): Response
    {
        $user = $request->user();
        $isSuperadmin = $user->hasRole('Superadmin');

        $this->authorizeEventAdmin($request, $event);

        $isEventOwner = $isSuperadmin || $this->isEventOwner($user, $event);

        $base = Registration::query()->where('event_id', $event->id);

        // Pemilik/co-organiser nampak semua peserta event; admin org lain yang
        // hanya ada pendaftaran sendiri nampak peserta organisasinya sahaja.
        if (! $isEventOwner) {
            $base->where('organization_id', (int) $user->current_organization_id);
        }

        $query = (clone $base)->with([
            'user', 'organization', 'form', 'latestPayment', 'attendance',
            'formResponse.answers.question', 'items',
        ]);

        if ($request->filled('org')) {
            $query->where('organization_id', (int) $request->org);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('registration_no', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('ic_number', 'like', "%{$search}%")
                    ->orWhere('member_no', 'like', "%{$search}%");
            });
        }

        // Penapis bayaran: percuma / dibayar / belum dibayar.
        if ($request->filled('payment')) {
            match ($request->payment) {
                'free' => $query->whereRaw('(base_amount + addons_amount) <= 0'),
                'paid' => $query->whereHas('latestPayment', fn ($q) => $q->where('status', 'successful')),
                'unpaid' => $query
                    ->whereRaw('(base_amount + addons_amount) > 0')
                    ->whereDoesntHave('latestPayment', fn ($q) => $q->where('status', 'successful')),
                default => null,
            };
        }

        // Hanya peserta yang memesan item (untuk sediaan).
        if ($request->boolean('has_items')) {
            $query->whereHas('items');
        }

        match ($request->query('sort')) {
            'items' => $query->orderByDesc('addons_amount')->orderByDesc('created_at'),
            'name' => $query->orderBy('name'),
            default => $query->orderByDesc('created_at'),
        };

        $registrations = $query->paginate(25)
            ->withQueryString()
            ->through(fn (Registration $r) => $this->serialize($r));

        $stats = [
            'total' => (clone $base)->count(),
            'confirmed' => (clone $base)->where('status', RegistrationStatus::Confirmed->value)->count(),
            'pending' => (clone $base)->where('status', RegistrationStatus::Pending->value)->count(),
            'cancelled' => (clone $base)->where('status', RegistrationStatus::Cancelled->value)->count(),
            'with_items' => (clone $base)->whereHas('items')->count(),
        ];

        // Senarai sediaan: hanya item yang telah dibayar, dikumpulkan mengikut
        // nama + variant, supaya admin boleh sediakan barangan dengan tepat.
        $itemsToPrepare = RegistrationItem::query()
            ->whereHas('registration', function (Builder $q) use ($event, $user, $isEventOwner) {
                $q->where('event_id', $event->id)
                    ->where('status', '!=', RegistrationStatus::Cancelled->value)
                    ->whereHas('latestPayment', fn (Builder $p) => $p->where('status', 'successful'));

                if (! $isEventOwner) {
                    $q->where('organization_id', (int) $user->current_organization_id);
                }
            })
            ->selectRaw('name, variant_name, SUM(quantity) as quantity')
            ->groupBy('name', 'variant_name')
            ->orderBy('name')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'variant_name' => $row->variant_name,
                'quantity' => (int) $row->quantity,
            ])
            ->values();

        return Inertia::render('Admin/Events/Registrations', [
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'slug' => $event->slug,
                'registration_open' => $event->registrationIsOpen(),
                'registration_closes_at' => $event->registrationClosesAt()?->toIso8601String(),
                'attendance_open' => $event->attendanceWindowIsOpen(),
                'attendance_opens_at' => $event->attendanceOpensAt()?->toIso8601String(),
                'attendance_closes_at' => $event->attendanceClosesAt()?->toIso8601String(),
            ],
            'registrations' => $registrations,
            'organizations' => Organization::orderBy('min_age')->get(['id', 'name']),
            'filters' => $request->only(['org', 'status', 'search', 'payment', 'has_items', 'sort']),
            'stats' => $stats,
            'items_to_prepare' => $itemsToPrepare,
        ]);
    }

    /**
     * Export senarai pendaftaran sebagai CSV bersih (peserta, item, bayaran,
     * status dan kehadiran).
     */
    public function exportRegistrations(Request $request, Event $event): StreamedResponse
    {
        $user = $this->authorizeEventAdmin($request, $event);
        $isEventOwner = $user->hasRole('Superadmin') || $this->isEventOwner($user, $event);

        $filename = 'pendaftaran-'.Str::slug($event->title).'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($event, $user, $isEventOwner) {
            $file = fopen('php://output', 'w');
            fwrite($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, [
                'No. Daftar', 'Nama', 'No. IC', 'No. Ahli', 'Telefon', 'Emel',
                'Organisasi', 'Borang', 'Kategori', 'Item', 'Jumlah (RM)',
                'Bayaran', 'Status', 'Kehadiran', 'Masa Hadir',
            ]);

            $query = Registration::with(['organization', 'form', 'items', 'latestPayment', 'attendance'])
                ->where('event_id', $event->id)
                ->orderBy('id');

            if (! $isEventOwner) {
                $query->where('organization_id', (int) $user->current_organization_id);
            }

            $query->chunk(500, function ($registrations) use ($file) {
                foreach ($registrations as $registration) {
                    $items = $registration->items
                        ->map(fn ($item) => trim(
                            $item->name
                            .($item->variant_name ? ' ('.$item->variant_name.')' : '')
                            .' x'.$item->quantity
                        ))
                        ->implode('; ');

                    $payment = $registration->isFreeRegistration()
                        ? 'Percuma'
                        : ($registration->latestPayment?->status === 'successful'
                            ? 'Dibayar'.($registration->latestPayment?->uploaded_by !== null ? ' (manual)' : '')
                            : 'Menunggu Bayaran');

                    fputcsv($file, [
                        $registration->registration_no,
                        $registration->name,
                        $registration->ic_number,
                        $registration->member_no,
                        $registration->phone,
                        $registration->email,
                        $registration->organization?->name,
                        $registration->form?->title,
                        $registration->ticket_type,
                        $items !== '' ? $items : null,
                        number_format($registration->totalAmount(), 2, '.', ''),
                        $payment,
                        $registration->status->label(),
                        $registration->attendance ? 'Hadir' : 'Tidak Hadir',
                        $registration->attendance?->attended_at?->toDateTimeString(),
                    ]);
                }
            });

            fclose($file);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Batal atau pulihkan pendaftaran. Status lain (Diterima / Menunggu
     * Bayaran) ditentukan automatik oleh aliran bayaran.
     */
    public function updateStatus(Request $request, Registration $registration): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRegistrationAdmin($user, $registration);

        $data = $request->validate([
            'status' => ['required', 'in:cancelled,confirmed'],
        ]);

        if ($data['status'] === 'cancelled') {
            $registration->update(['status' => RegistrationStatus::Cancelled]);

            return back()->with('success', 'Pendaftaran dibatalkan.');
        }

        $restored = $registration->isPaid() || $registration->totalAmount() <= 0
            ? RegistrationStatus::Confirmed
            : RegistrationStatus::Pending;

        $registration->update(['status' => $restored]);

        return back()->with('success', 'Pendaftaran dipulihkan.');
    }

    /**
     * Tandakan bayaran sebagai berjaya secara manual (contoh: isu gateway atau
     * bayaran tunai di kaunter). Status pendaftaran menjadi Diterima.
     */
    public function markPaid(Request $request, Registration $registration): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRegistrationAdmin($user, $registration);

        if ($registration->status === RegistrationStatus::Cancelled) {
            return back()->with('error', 'Pendaftaran dibatalkan. Pulihkan dahulu sebelum tandakan bayaran.');
        }

        if ($registration->isPaid()) {
            return back()->with('success', 'Pendaftaran ini sudah dibayar.');
        }

        $amount = $registration->totalAmount();

        DB::transaction(function () use ($registration, $user, $amount) {
            if ($amount > 0) {
                $payment = $registration->latestPayment;

                if ($payment !== null) {
                    $payment->update([
                        'status' => 'successful',
                        'amount' => (float) $payment->amount > 0 ? $payment->amount : $amount,
                        'description' => 'Bayaran manual disahkan oleh admin',
                        'uploaded_by' => $user->id,
                        'gateway' => $payment->gateway ?: 'manual',
                    ]);
                } else {
                    Payment::create([
                        'user_id' => $registration->user_id,
                        'payable_type' => Registration::class,
                        'payable_id' => $registration->id,
                        'amount' => $amount,
                        'status' => 'successful',
                        'description' => 'Bayaran manual disahkan oleh admin',
                        'uploaded_by' => $user->id,
                        'gateway' => 'manual',
                        'organization_id' => $registration->organization_id ?? $user->current_organization_id,
                    ]);
                }
            }

            $registration->update(['status' => RegistrationStatus::Confirmed]);

            ActivityLog::create([
                'user_id' => $user->id,
                'organization_id' => $user->current_organization_id,
                'target_type' => Registration::class,
                'target_id' => $registration->id,
                'action' => 'registration_manual_paid',
                'description' => "Bayaran manual disahkan untuk {$registration->name} ({$registration->registration_no}).",
                'old_values' => ['status' => 'pending'],
                'new_values' => ['status' => 'confirmed', 'amount' => $amount],
            ]);
        });

        return back()->with('success', 'Bayaran ditanda berjaya. Status kini Diterima.');
    }

    /**
     * Pendaftaran dibuka awal (selepas diterbitkan) dan ditutup 24 jam selepas
     * program tamat.
     */
    protected function abortUnlessRegistrationOpen(Event $event): void
    {
        if ($event->registrationIsOpen()) {
            return;
        }

        $closesAt = $event->registrationClosesAt();

        $message = $closesAt !== null
            ? 'Pendaftaran untuk program ini telah ditutup pada '.$closesAt->locale('ms')->isoFormat('D MMM YYYY, h:mm A').'.'
            : 'Pendaftaran untuk program ini telah ditutup.';

        abort(403, $message);
    }

    protected function authorizeEventAdmin(Request $request, Event $event): User
    {
        $user = $request->user();

        if ($user->hasRole('Superadmin')) {
            return $user;
        }

        if ($user->isBranchAdmin()) {
            abort_unless((int) $event->branch_id === (int) $user->managedBranchId(), 403);

            return $user;
        }

        $orgId = (int) $user->current_organization_id;

        $accessible = (int) $event->organization_id === $orgId
            || $event->organizations()->where('organizations.id', $orgId)->exists()
            || $event->registrations()->where('organization_id', $orgId)->exists();

        abort_unless($accessible, 403);

        return $user;
    }

    /**
     * Superadmin dan pemilik/co-organiser event boleh urus semua peserta event
     * tersebut; admin org lain hanya boleh urus pendaftaran organisasinya.
     */
    protected function authorizeRegistrationAdmin(User $user, Registration $registration): void
    {
        if ($user->hasRole('Superadmin')) {
            return;
        }

        $event = $registration->event;

        if ($event !== null && $this->isEventOwner($user, $event)) {
            return;
        }

        abort_unless(
            (int) $registration->organization_id === (int) $user->current_organization_id,
            403
        );
    }

    /**
     * Adakah pengguna pemilik atau co-organiser event?
     */
    protected function isEventOwner(User $user, Event $event): bool
    {
        if ($user->isBranchAdmin()) {
            return (int) $event->branch_id === (int) $user->managedBranchId();
        }

        $orgId = (int) $user->current_organization_id;

        return (int) $event->organization_id === $orgId
            || $event->organizations()->where('organizations.id', $orgId)->exists();
    }

    protected function assertEventRegistrationAccess(Event $event, ?User $user): void
    {
        if (! $event->isBranchOnly()) {
            return;
        }

        abort_unless(
            $user && (int) $user->branch_id === (int) $event->branch_id,
            403,
            'Program ini hanya untuk ahli cawangan penganjur.'
        );
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Branding payment gateway untuk borang yang mewajibkan bayaran, atau null
     * untuk borang percuma. Data diambil dari config (bukan hardcode).
     */
    protected function resolvePaymentGateway(Form $form, Event $event): ?array
    {
        $amount = $form->hasTiers() ? $form->priceForTier(null) : (float) $form->price;

        $basePayable = $form->payment_required && $amount > 0;

        // A free event with paid add-ons can still require a gateway later.
        if (! $basePayable && ! $form->hasItems()) {
            return null;
        }

        $org = $form->organization
            ?? ($event->organization_id ? $event->organization : null);

        return $this->gateways->branding($org);
    }

    protected function serialize(Registration $r): array
    {
        return app(RegistrationService::class)->serialize($r);
    }

    protected function serializeForm(Form $form, ?User $user = null): array
    {
        return [
            'id' => $form->id,
            'title' => $form->title,
            'description' => $form->description,
            'price' => $form->price,
            'price_tiers' => $form->tiers(),
            'items' => $form->addonsPayload(),
            'payment_required' => $form->payment_required,
            'terms' => $form->terms,
            'organization_name' => $form->organization?->name,
            'share_token' => $form->share_token,
            'branch_options' => $form->branchOptions(),
            'header_image_url' => $form->header_image_path
                ? asset('storage/'.$form->header_image_path)
                : null,
            'questions' => $form->questions->sortBy('sort_order')->map(fn ($q) => [
                'id' => $q->id,
                'label' => $q->label,
                'type' => $q->type,
                'options' => $q->options,
                'required' => $q->required,
                'placeholder' => $q->placeholder,
                'help_text' => $q->help_text,
            ])->values(),
        ];
    }
}

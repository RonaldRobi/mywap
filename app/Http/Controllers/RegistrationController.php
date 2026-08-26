<?php

namespace App\Http\Controllers;

use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormQuestion;
use App\Models\FormResponse;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use App\Services\PaymentGatewayManager;
use App\Services\RegistrationPaymentService;
use App\Services\RegistrationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

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
        abort_if($event->isClosed(), 403, 'Pendaftaran untuk event ini telah ditutup.');

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
                'maps_url' => $event->location_or_link
                    ? 'https://www.google.com/maps/search/?api=1&query='.urlencode($event->location_or_link)
                    : null,
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

        abort_if($event->isClosed(), 403, 'Pendaftaran untuk event ini telah ditutup.');

        $data = $this->validatePayload($request, $form);

        return $this->processRegistration($form, $event, $data, $user);
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
        abort_if($form->event->isClosed(), 403, 'Pendaftaran untuk event ini telah ditutup.');

        // Alat kongsi hanya untuk admin/superadmin — orang awam tidak nampak.
        $user = request()->user();
        $canShare = (bool) ($user && $user->hasRole(['Superadmin', 'Admin']));

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
                'maps_url' => $form->event->location_or_link
                    ? 'https://www.google.com/maps/search/?api=1&query='.urlencode($form->event->location_or_link)
                    : null,
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
        abort_if($form->event->isClosed(), 403, 'Pendaftaran untuk event ini telah ditutup.');

        $data = $this->validatePayload($request, $form);

        return $this->processRegistration($form, $form->event, $data, null);
    }

    // ─── Core processing ───────────────────────────────────────────────────────

    /**
     * Cipta Registration + FormResponse + FormAnswers, dan proses bayaran
     * jika borang mewajibkan bayaran.
     */
    protected function processRegistration(Form $form, Event $event, array $data, ?User $user): \Symfony\Component\HttpFoundation\Response
    {
        $participant = $this->mapAnswersToParticipant($form, $data['answers'] ?? []);

        $registration = DB::transaction(function () use ($form, $event, $data, $user, $participant) {
            $registration = Registration::create([
                'event_id' => $event->id,
                'form_id' => $form->id,
                'user_id' => $user?->id,
                'organization_id' => $user?->current_organization_id
                    ?? $form->organization_id,
                'member_no' => $user?->member_no,
                // Ahli: profil adalah sumber maklumat, jawapan borang mengatasi jika ada.
                // Bukan ahli: semuanya daripada jawapan borang.
                'name' => $participant['name'] ?: ($user?->name ?? 'Peserta'),
                'email' => $participant['email'] ?: $user?->email,
                'phone' => $participant['phone'] ?: $user?->phone,
                'ic_number' => $participant['ic_number'] ?: $user?->ic_number,
                // Berbayar: tunggu pengesahan bayaran (pending). Percuma: disahkan terus.
                'status' => $form->payment_required ? RegistrationStatus::Pending : RegistrationStatus::Confirmed,
            ]);

            $response = FormResponse::create([
                'form_id' => $form->id,
                'user_id' => $user?->id,
                'respondent_name' => $registration->name,
                'respondent_email' => $registration->email,
                'respondent_phone' => $registration->phone,
                'submitted_at' => now(),
            ]);

            foreach ($data['answers'] as $questionId => $value) {
                $question = $form->questions->firstWhere('id', (int) $questionId);
                $stored = $this->storeAnswerValue($question, $value);

                FormAnswer::create([
                    'form_response_id' => $response->id,
                    'form_question_id' => $questionId,
                    'value' => $stored,
                ]);
            }

            return $registration;
        });

        // Jika borang tidak mewajibkan bayaran, terus disahkan + hantar emel.
        if (! $form->payment_required || ! $form->price || (float) $form->price <= 0) {
            $registration->confirmAndNotify();

            return redirect()->route('registrations.success', $registration)
                ->with('success', 'Pendaftaran berjaya! No Pendaftaran: '.$registration->registration_no);
        }

        return $this->initiatePayment($registration, $form, $event, $data['payment_method'] ?? 'fpx');
    }

    /**
     * Cipta Payment dan redirect ke gateway (atau tandakan berjaya dalam mod dummy).
     */
    protected function initiatePayment(Registration $registration, Form $form, Event $event, string $paymentMethod = 'fpx'): \Symfony\Component\HttpFoundation\Response
    {
        $org = $form->organization
            ?? ($event->organization_id ? $event->organization : null);

        $useGateway = $org ? $this->gateways->isLive($org) : false;

        $payment = $registration->payments()->create([
            'user_id' => $registration->user_id,
            'amount' => (float) $form->price,
            'status' => $useGateway ? 'pending' : 'successful',
            'reference' => $useGateway ? 'REG-'.strtoupper(Str::random(8)) : 'DUMMY-'.strtoupper(Str::random(8)),
            'description' => 'Pendaftaran: '.$event->title,
            'gateway' => $org ? $this->gateways->gatewayFor($org) : 'dummy',
            'organization_id' => $org?->id,
            'channel' => $paymentMethod,
        ]);

        // Jejak rujukan pembayaran dalam session supaya gateway redirect balik
        // (tanpa invoice_number) boleh kenal pasti pendaftaran pengguna ini.
        session(['last_payment_reference' => $payment->reference]);

        if ($useGateway && $org) {
            $url = $this->gateways->createPaymentRedirect(
                $org,
                $payment,
                $registration->name,
                $registration->email ?: ($registration->name.'@mywap.my'),
                $registration->phone,
                'Pendaftaran: '.$event->title,
                $paymentMethod,
            );

            if ($url) {
                return Inertia::location($url);
            }

            $payment->update(['status' => 'failed']);

            return redirect()->route('registrations.success', $registration)
                ->with('error', 'Pembayaran gagal diproses. Sila cuba lagi.');
        }

        // Mod dummy (tiada gateway dikonfigurasi): anggap berjaya + hantar emel.
        $registration->confirmAndNotify();

        return redirect()->route('registrations.success', $registration)
            ->with('success', 'Pendaftaran berjaya! No Pendaftaran: '.$registration->registration_no);
    }

    // ─── Halaman Berjaya ───────────────────────────────────────────────────────

    public function success(Request $request, Registration $registration): Response
    {
        $registration->load(['event.organization', 'form', 'latestPayment', 'attendance']);

        $user = $request->user();
        $allowed = $registration->user_id === $user?->id
            || ($user && $user->hasRole(['Superadmin', 'Admin']))
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

        if (! $isSuperadmin && ! $this->adminOwnsEvent($user, $event)) {
            abort(403);
        }

        $query = Registration::with(['user', 'organization', 'form', 'latestPayment', 'attendance'])
            ->where('event_id', $event->id);

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

        $registrations = $query->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Registration $r) => $this->serialize($r));

        return Inertia::render('Admin/Events/Registrations', [
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'slug' => $event->slug,
            ],
            'registrations' => $registrations,
            'organizations' => Organization::orderBy('min_age')->get(['id', 'name']),
            'filters' => $request->only(['org', 'status', 'search']),
        ]);
    }

    public function updateStatus(Request $request, Registration $registration): RedirectResponse
    {
        $user = $request->user();
        $isSuperadmin = $user->hasRole('Superadmin');

        $allowed = $isSuperadmin
            || $registration->organization_id === (int) $user->current_organization_id
            || $this->adminOwnsEvent($user, $registration->event);

        abort_unless($allowed, 403);

        $data = $request->validate([
            'status' => ['required', 'in:pending,confirmed,cancelled'],
        ]);

        $registration->update(['status' => $data['status']]);

        return back()->with('success', 'Status pendaftaran dikemas kini.');
    }

    /**
     * Admin organisasi layak jika event milik org sendiri ATAU organisasinya
     * tersenarai dalam pivot "organisasi terlibat".
     */
    protected function adminOwnsEvent($user, Event $event): bool
    {
        $ownOrg = (int) $user->current_organization_id;

        if ((int) $event->organization_id === $ownOrg) {
            return true;
        }

        return $event->organizations()->where('organizations.id', $ownOrg)->exists();
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Branding payment gateway untuk borang yang mewajibkan bayaran, atau null
     * untuk borang percuma. Data diambil dari config (bukan hardcode).
     */
    protected function resolvePaymentGateway(Form $form, Event $event): ?array
    {
        if (! $form->payment_required || ! $form->price || (float) $form->price <= 0) {
            return null;
        }

        $org = $form->organization
            ?? ($event->organization_id ? $event->organization : null);

        return $this->gateways->branding($org);
    }

    protected function validatePayload(Request $request, Form $form): array
    {
        $rules = [
            'answers' => ['required', 'array'],
            'payment_method' => ['nullable', 'in:fpx,duitnow_qr'],
        ];

        foreach ($form->questions as $q) {
            $key = "answers.{$q->id}";
            $rule = $q->required ? ['required'] : ['nullable'];

            if ($q->type === 'file') {
                $rule[] = 'file';
                $rule[] = 'mimes:pdf,png,jpg,jpeg,doc,docx,xls,xlsx,zip';
                $rule[] = 'max:10240';
            } elseif (in_array($q->type, ['email'])) {
                $rule[] = 'email';
            } elseif (in_array($q->type, ['number'])) {
                $rule[] = 'numeric';
            } elseif (in_array($q->type, ['phone'])) {
                $rule[] = 'string';
                $rule[] = 'max:50';
            } elseif (in_array($q->type, ['date'])) {
                $rule[] = 'date';
            }

            $rules[$key] = $rule;
        }

        $data = $request->validate($rules);

        return $data;
    }

    /**
     * Petakan jawapan borang kepada maklumat peserta (nama/emel/telefon/IC).
     * Form Builder ialah single source of truth — tiada field peserta auto.
     * Pengenalpastian berdasarkan jenis field & label (nama, telefon, IC, emel).
     *
     * @return array{name: string|null, email: string|null, phone: string|null, ic_number: string|null}
     */
    protected function mapAnswersToParticipant(Form $form, array $answers): array
    {
        $result = ['name' => null, 'email' => null, 'phone' => null, 'ic_number' => null];

        foreach ($form->questions as $q) {
            $value = $answers[$q->id] ?? null;

            if ($value === null || $value === '' || is_array($value)) {
                continue;
            }

            $value = trim((string) $value);
            $label = strtolower($q->label);

            if ($q->type === 'email') {
                $result['email'] = $value;

                continue;
            }

            if ($q->type === 'phone' || str_contains($label, 'telefon') || str_contains($label, 'phone') || str_contains($label, 'whatsapp')) {
                $result['phone'] ??= $value;

                continue;
            }

            if (str_contains($label, 'kad pengenalan') || str_contains($label, 'no ic') || str_contains($label, 'ic number') || str_contains($label, 'nric')) {
                $result['ic_number'] ??= $value;

                continue;
            }

            // Fallback: text/textarea pertama dianggap sebagai nama.
            if (in_array($q->type, ['text', 'textarea'], true) && $result['name'] === null) {
                $result['name'] = $value;
            }
        }

        return $result;
    }

    protected function storeAnswerValue(?FormQuestion $question, mixed $value): string
    {
        if ($question && $question->type === 'file' && $value instanceof UploadedFile) {
            return $value->store('form-uploads', 'public');
        }

        if (is_array($value)) {
            return implode(', ', $value);
        }

        return (string) $value;
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

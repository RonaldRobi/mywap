<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Form;
use App\Models\FormQuestion;
use App\Models\FormResponse;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\FormInvitationNotification;
use App\Services\FormService;
use App\Support\QrPng;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FormController extends Controller
{
    public function __construct(private readonly FormService $forms) {}

    const QUESTION_TYPES = ['text', 'textarea', 'number', 'email', 'phone', 'date', 'select', 'radio', 'checkbox', 'file', 'branch'];

    // ─── ADMIN ──────────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $user = $request->user()->load('organization');
        $isSuperadmin = $user->hasRole('Superadmin');

        $query = Form::query()
            ->with(['organization:id,name', 'event:id,title'])
            ->withCount('responses')
            ->orderByDesc('updated_at');

        if (! $isSuperadmin) {
            $query->where('organization_id', $user->current_organization_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $forms = $query->paginate(15)->withQueryString()->through(fn (Form $f) => [
            'id' => $f->id,
            'title' => $f->title,
            'slug' => $f->slug,
            'description' => $f->description,
            'is_active' => $f->is_active,
            'allow_public' => $f->allow_public,
            'share_token' => $f->share_token,
            'responses_count' => $f->responses_count,
            'recipient_count' => count($f->recipient_emails ?? []),
            'organization_name' => $f->organization?->name,
            'event_title' => $f->event?->title,
            'public_url' => $f->event_id
                ? route('events.register.public', $f->share_token)
                : $f->public_url,
            'share_url' => $f->share_url,
            'updated_at' => $f->updated_at?->toDateTimeString(),
        ]);

        $organizations = $isSuperadmin
            ? Organization::orderBy('min_age')->get(['id', 'name'])
            : [];

        return Inertia::render('Admin/Forms/Index', [
            'forms' => $forms,
            'organizations' => $organizations,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user()->load('organization');
        $isSuperadmin = $user->hasRole('Superadmin');

        $organizations = $isSuperadmin
            ? Organization::orderBy('min_age')->get(['id', 'name'])
            : [];

        $events = Event::query()
            ->when(! $isSuperadmin, function ($q) use ($user) {
                $q->where(function ($q2) use ($user) {
                    $q2->where('organization_id', $user->current_organization_id)
                        ->orWhereHas('organizations', fn ($q3) => $q3->where('organizations.id', $user->current_organization_id));
                });
            })
            ->orderByDesc('start_time')
            ->get(['id', 'title', 'start_time']);

        return Inertia::render('Admin/Forms/Builder', [
            'form' => null,
            'organizations' => $organizations,
            'events' => $events,
            'questionTypes' => self::QUESTION_TYPES,
            'preselectedEventId' => (int) $request->input('event_id', 0) ?: null,
            'backTo' => $request->input('back_to'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user()->load('organization');
        $isSuperadmin = $user->hasRole('Superadmin');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'price_tiers' => ['nullable', 'array'],
            'price_tiers.*.label' => ['required', 'string', 'max:255'],
            'price_tiers.*.price' => ['required', 'numeric', 'min:0', 'max:999999'],
            'price_tiers.*.is_default' => ['boolean'],
            'price_tiers.*.requires_document' => ['boolean'],
            'price_tiers.*.description' => ['nullable', 'string', 'max:500'],
            'payment_required' => ['boolean'],
            'terms' => ['nullable', 'string', 'max:10000'],
            'is_active' => ['boolean'],
            'allow_public' => ['boolean'],
            'organization_id' => $isSuperadmin ? ['nullable', 'exists:organizations,id'] : ['nullable'],
            'event_id' => ['nullable', 'exists:events,id'],
            'header_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'recipient_emails' => ['nullable', 'array'],
            'recipient_emails.*' => ['email'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.label' => ['required', 'string', 'max:500'],
            'questions.*.type' => ['required', 'in:'.implode(',', self::QUESTION_TYPES)],
            'questions.*.options' => ['nullable', 'array'],
            'questions.*.required' => ['boolean'],
            'questions.*.placeholder' => ['nullable', 'string', 'max:255'],
            'questions.*.help_text' => ['nullable', 'string', 'max:500'],
        ]);

        $this->assertEventAllowedForOrg($data['event_id'] ?? null, $user, $isSuperadmin);

        $priceTiers = $this->normalizePriceTiers($data['price_tiers'] ?? []);

        $this->assertPaidFormHasPrice($data, $priceTiers);

        // Auto-set org dari event jika borang tiada org (borang pendaftaran event).
        $organizationId = $isSuperadmin ? ($data['organization_id'] ?? null) : $user->current_organization_id;
        if (! $organizationId && ! empty($data['event_id'])) {
            $organizationId = Event::find($data['event_id'])?->organization_id;
        }

        $form = DB::transaction(function () use ($data, $request, $organizationId, $priceTiers) {
            $headerImagePath = $request->hasFile('header_image')
                ? $request->file('header_image')->store('forms', 'public')
                : null;

            $form = Form::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'price' => $priceTiers[0]['price'] ?? $data['price'] ?? null,
                'price_tiers' => $priceTiers,
                'payment_required' => $data['payment_required'] ?? false,
                'terms' => $data['terms'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'allow_public' => $data['allow_public'] ?? true,
                'organization_id' => $organizationId,
                'event_id' => $data['event_id'] ?? null,
                'header_image_path' => $headerImagePath,
                'recipient_emails' => collect($data['recipient_emails'] ?? [])
                    ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
                    ->unique()
                    ->values()
                    ->all(),
            ]);

            foreach ($data['questions'] as $i => $q) {
                FormQuestion::create([
                    'form_id' => $form->id,
                    'label' => $q['label'],
                    'type' => $q['type'],
                    'options' => in_array($q['type'], ['select', 'radio', 'checkbox']) ? ($q['options'] ?? []) : null,
                    'required' => $q['required'] ?? false,
                    'placeholder' => $q['placeholder'] ?? null,
                    'help_text' => $q['help_text'] ?? null,
                    'sort_order' => $i,
                ]);
            }

            return $form;
        });

        if ($backTo = $request->input('back_to')) {
            return redirect()->to($backTo)
                ->with('success', "Borang \"{$form->title}\" berjaya dicipta.");
        }

        return redirect()->route('admin.forms.index')
            ->with('success', "Borang \"{$form->title}\" berjaya dicipta.");
    }

    public function edit(Form $form): Response
    {
        $this->assertCanManageForm($form);

        $user = request()->user();
        $isSuperadmin = $user->hasRole('Superadmin');

        $form->load(['questions' => function ($q) {
            $q->orderBy('sort_order');
        }, 'organization', 'event']);

        $organizations = $isSuperadmin
            ? Organization::orderBy('min_age')->get(['id', 'name'])
            : [];

        $events = Event::query()
            ->when(! $isSuperadmin, function ($q) use ($user) {
                $q->where(function ($q2) use ($user) {
                    $q2->where('organization_id', $user->current_organization_id)
                        ->orWhereHas('organizations', fn ($q3) => $q3->where('organizations.id', $user->current_organization_id));
                });
            })
            ->orderByDesc('start_time')
            ->get(['id', 'title', 'start_time']);

        return Inertia::render('Admin/Forms/Builder', [
            'form' => [
                'id' => $form->id,
                'title' => $form->title,
                'description' => $form->description,
                'price' => $form->price,
                'price_tiers' => $form->price_tiers ?? [],
                'payment_required' => $form->payment_required,
                'terms' => $form->terms,
                'is_active' => $form->is_active,
                'allow_public' => $form->allow_public,
                'organization_id' => $form->organization_id,
                'event_id' => $form->event_id,
                'share_token' => $form->share_token,
                'header_image_path' => $form->header_image_path,
                'recipient_emails' => $form->recipient_emails ?? [],
                'questions' => $form->questions->map(fn ($q) => [
                    'id' => $q->id,
                    'label' => $q->label,
                    'type' => $q->type,
                    'options' => $q->options,
                    'required' => $q->required,
                    'placeholder' => $q->placeholder,
                    'help_text' => $q->help_text,
                    'sort_order' => $q->sort_order,
                ])->values(),
            ],
            'organizations' => $organizations,
            'events' => $events,
            'questionTypes' => self::QUESTION_TYPES,
        ]);
    }

    public function update(Request $request, Form $form): RedirectResponse
    {
        $this->assertCanManageForm($form);

        $user = $request->user()->load('organization');
        $isSuperadmin = $user->hasRole('Superadmin');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'price_tiers' => ['nullable', 'array'],
            'price_tiers.*.label' => ['required', 'string', 'max:255'],
            'price_tiers.*.price' => ['required', 'numeric', 'min:0', 'max:999999'],
            'price_tiers.*.is_default' => ['boolean'],
            'price_tiers.*.requires_document' => ['boolean'],
            'price_tiers.*.description' => ['nullable', 'string', 'max:500'],
            'payment_required' => ['boolean'],
            'terms' => ['nullable', 'string', 'max:10000'],
            'is_active' => ['boolean'],
            'allow_public' => ['boolean'],
            'organization_id' => $isSuperadmin ? ['nullable', 'exists:organizations,id'] : ['nullable'],
            'event_id' => ['nullable', 'exists:events,id'],
            'header_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'recipient_emails' => ['nullable', 'array'],
            'recipient_emails.*' => ['email'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.id' => ['nullable', 'integer'],
            'questions.*.label' => ['required', 'string', 'max:500'],
            'questions.*.type' => ['required', 'in:'.implode(',', self::QUESTION_TYPES)],
            'questions.*.options' => ['nullable', 'array'],
            'questions.*.required' => ['boolean'],
            'questions.*.placeholder' => ['nullable', 'string', 'max:255'],
            'questions.*.help_text' => ['nullable', 'string', 'max:500'],
        ]);

        $this->assertEventAllowedForOrg($data['event_id'] ?? null, $user, $isSuperadmin);

        $priceTiers = $this->normalizePriceTiers($data['price_tiers'] ?? []);

        $this->assertPaidFormHasPrice($data, $priceTiers);

        // Auto-set org dari event jika borang tiada org (borang pendaftaran event).
        $organizationId = $isSuperadmin
            ? ($data['organization_id'] ?? $form->organization_id)
            : $form->organization_id;
        if (! $organizationId && ! empty($data['event_id'])) {
            $organizationId = Event::find($data['event_id'])?->organization_id;
        }

        DB::transaction(function () use ($form, $data, $request, $organizationId, $priceTiers) {
            $updateData = [
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'price' => $priceTiers[0]['price'] ?? $data['price'] ?? $form->price,
                'price_tiers' => $priceTiers,
                'payment_required' => $data['payment_required'] ?? $form->payment_required,
                'terms' => $data['terms'] ?? null,
                'is_active' => $data['is_active'] ?? $form->is_active,
                'allow_public' => $data['allow_public'] ?? $form->allow_public,
                'organization_id' => $organizationId,
                'event_id' => $data['event_id'] ?? $form->event_id,
                'recipient_emails' => collect($data['recipient_emails'] ?? [])
                    ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
                    ->unique()
                    ->values()
                    ->all(),
            ];

            // Handle header image upload
            if ($request->hasFile('header_image')) {
                // Delete old image if exists
                if ($form->header_image_path) {
                    Storage::disk('public')->delete($form->header_image_path);
                }
                $updateData['header_image_path'] = $request->file('header_image')->store('forms', 'public');
            }

            $form->update($updateData);

            $keptIds = [];

            foreach ($data['questions'] as $i => $q) {
                if (! empty($q['id'])) {
                    $question = FormQuestion::find($q['id']);
                    if ($question && $question->form_id === $form->id) {
                        $question->update([
                            'label' => $q['label'],
                            'type' => $q['type'],
                            'options' => in_array($q['type'], ['select', 'radio', 'checkbox']) ? ($q['options'] ?? []) : null,
                            'required' => $q['required'] ?? false,
                            'placeholder' => $q['placeholder'] ?? null,
                            'help_text' => $q['help_text'] ?? null,
                            'sort_order' => $i,
                        ]);
                        $keptIds[] = $question->id;
                    }
                } else {
                    $question = FormQuestion::create([
                        'form_id' => $form->id,
                        'label' => $q['label'],
                        'type' => $q['type'],
                        'options' => in_array($q['type'], ['select', 'radio', 'checkbox']) ? ($q['options'] ?? []) : null,
                        'required' => $q['required'] ?? false,
                        'placeholder' => $q['placeholder'] ?? null,
                        'help_text' => $q['help_text'] ?? null,
                        'sort_order' => $i,
                    ]);
                    $keptIds[] = $question->id;
                }
            }

            $form->questions()->whereNotIn('id', $keptIds)->delete();
        });

        if ($backTo = $request->input('back_to')) {
            return redirect()->to($backTo)
                ->with('success', "Borang \"{$form->title}\" berjaya dikemaskini.");
        }

        return redirect()->route('admin.forms.index')
            ->with('success', "Borang \"{$form->title}\" berjaya dikemaskini.");
    }

    public function destroy(Form $form): RedirectResponse
    {
        $this->assertCanManageForm($form);

        $title = $form->title;
        $form->delete();

        return redirect()->route('admin.forms.index')
            ->with('success', "Borang \"{$title}\" berjaya dipadam.");
    }

    public function responses(Form $form): Response
    {
        $this->assertCanManageForm($form);

        $form->load('questions');

        $responses = $form->responses()
            ->with('answers.question')
            ->orderByDesc('submitted_at')
            ->orderByDesc('created_at')
            ->paginate(25)
            ->through(fn (FormResponse $r) => [
                'id' => $r->id,
                'respondent_name' => $r->respondent_name,
                'respondent_email' => $r->respondent_email,
                'respondent_phone' => $r->respondent_phone,
                'submitted_at' => $r->submitted_at?->toDateTimeString(),
                'answers' => $r->answers->map(fn ($a) => [
                    'question_label' => $a->question?->label,
                    'value' => $a->value,
                ])->values(),
            ]);

        return Inertia::render('Admin/Forms/Responses', [
            'form' => [
                'id' => $form->id,
                'title' => $form->title,
                'questions' => $form->questions,
            ],
            'responses' => $responses,
        ]);
    }

    public function exportResponses(Form $form): StreamedResponse
    {
        $this->assertCanManageForm($form);
        $form->load('questions');

        $responses = $form->responses()
            ->with('answers.question')
            ->orderByDesc('submitted_at')
            ->get();

        $filename = 'respons-'.Str::slug($form->title).'-'.now()->format('Y-m-d_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $questions = $form->questions;

        return response()->streamDownload(function () use ($responses, $questions) {
            $file = fopen('php://output', 'w');
            fwrite($file, chr(0xEF).chr(0xBB).chr(0xBF));

            $header = ['#', 'Nama', 'Emel', 'Telefon', 'Tarikh'];
            foreach ($questions as $q) {
                $header[] = $q->label;
            }
            fputcsv($file, $header);

            foreach ($responses as $r) {
                $row = [
                    $r->id,
                    $r->respondent_name ?? '—',
                    $r->respondent_email ?? '—',
                    $r->respondent_phone ?? '—',
                    $r->submitted_at?->toDateTimeString() ?? '—',
                ];
                foreach ($questions as $q) {
                    $answer = $r->answers->firstWhere('form_question_id', $q->id);
                    $row[] = $answer?->value ?? '—';
                }
                fputcsv($file, $row);
            }

            fclose($file);
        }, $filename, $headers);
    }

    public function send(Request $request, Form $form): RedirectResponse
    {
        $this->assertCanManageForm($form);
        $emails = collect($form->recipient_emails ?? [])
            ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();

        if ($emails->isEmpty()) {
            return back()->with('error', 'Tiada emel penerima diisi. Sila tambah emel dalam tetapan borang dahulu.');
        }

        $request->validate([
            'recipient_name' => ['nullable', 'string', 'max:255'],
        ]);

        $recipientName = $request->input('recipient_name', '') ?: 'Penerima';

        foreach ($emails as $email) {
            Notification::route('mail', $email)
                ->notify(new FormInvitationNotification($form, $recipientName));
        }

        return back()->with('success', "Borang dihantar ke {$emails->count()} emel penerima.");
    }

    public function sendToAllMembers(Request $request, Form $form): RedirectResponse
    {
        $this->assertCanManageForm($form);
        $user = $request->user();
        $isSuperadmin = $user->hasRole('Superadmin');

        // Determine which organization's members to send to
        $orgId = $form->organization_id ?? $user->current_organization_id;

        if (! $orgId && ! $isSuperadmin) {
            return back()->with('error', 'Tiada organisasi dikaitkan dengan borang ini.');
        }

        $query = User::query()->where('is_active', true);

        if ($orgId) {
            $query->where('current_organization_id', $orgId);
        }

        $emails = $query->pluck('email')
            ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();

        if ($emails->isEmpty()) {
            return back()->with('error', 'Tiada ahli aktif dijumpai untuk dihantar.');
        }

        foreach ($emails as $email) {
            Notification::route('mail', $email)
                ->notify(new FormInvitationNotification($form, 'Ahli'));
        }

        return back()->with('success', "Borang dihantar ke {$emails->count()} ahli.");
    }

    public function showQr(Request $request, Form $form): Response
    {
        $this->assertCanManageForm($form);
        $user = $request->user();
        abort_unless(
            $user->hasRole('Superadmin')
            || $form->organization_id === (int) $user->current_organization_id,
            403
        );

        $publicUrl = $form->public_url;

        $qrSvg = QrCode::format('svg')
            ->size(320)
            ->errorCorrection('H')
            ->generate($publicUrl);

        return Inertia::render('Admin/Forms/Qr', [
            'form' => [
                'id' => $form->id,
                'title' => $form->title,
            ],
            'qrSvg' => (string) $qrSvg,
            'publicUrl' => $publicUrl,
        ]);
    }

    public function downloadQrPng(Request $request, Form $form): StreamedResponse
    {
        $this->assertCanManageForm($form);
        $user = $request->user();
        abort_unless(
            $user->hasRole('Superadmin')
            || $form->organization_id === (int) $user->current_organization_id,
            403
        );

        $publicUrl = $form->public_url;

        $png = QrPng::render($publicUrl, 600, 2);

        return response()->streamDownload(function () use ($png) {
            echo $png;
        }, 'borang-'.Str::slug($form->title).'-qr.png', [
            'Content-Type' => 'image/png',
        ]);
    }

    // ─── PUBLIC ──────────────────────────────────────────────────────────────

    public function publicShow(string $token): Response|RedirectResponse
    {
        $form = $this->forms->findPublic($token);

        // Borang pendaftaran event → terus ke halaman daftar (bukan borang generik),
        // supaya public & ahli mengalami aliran yang sama (isi → bayaran).
        if ($form->event_id) {
            return redirect()->route('events.register.public', $form->share_token);
        }

        return Inertia::render('Forms/Public', [
            'form' => $this->forms->publicFormPayload($form),
        ]);
    }

    public function publicSubmit(Request $request, string $token): RedirectResponse
    {
        $form = $this->forms->findPublic($token);

        $data = $request->validate($this->forms->validationRules($form));

        $this->forms->storeResponse($form, $data, $request->user());

        return redirect()->back()->with('success', 'Respons anda telah diterima. Terima kasih!');
    }

    /**
     * Org admin hanya boleh urus borang organisasi sendiri. Superadmin bebas.
     */
    private function assertCanManageForm(Form $form): void
    {
        $user = request()->user();
        if (! $user->hasRole('Superadmin') && $form->organization_id !== (int) $user->current_organization_id) {
            abort(403);
        }
    }

    /**
     * Org admin hanya boleh mengaitkan borang dengan event organisasi sendiri
     * (atau organisasi terlibat dalam pivot). Superadmin bebas.
     */
    private function assertEventAllowedForOrg(?int $eventId, $user, bool $isSuperadmin): void
    {
        if ($isSuperadmin || ! $eventId) {
            return;
        }

        $event = Event::find($eventId);

        if (! $event) {
            return;
        }

        $ownOrg = (int) $user->current_organization_id;
        $belongs = (int) $event->organization_id === $ownOrg
            || $event->organizations()->where('organizations.id', $ownOrg)->exists();

        abort_unless($belongs, 403);
    }

    /**
     * Normalisasi price_tiers dari borang admin. Tier default diletakkan
     * dahulu (dijadikan harga fallback). Tier tanpa label/harga dibuang.
     *
     * @return array<int, array{label: string, price: float, is_default: bool, requires_document: bool, description: ?string}>
     */
    private function normalizePriceTiers(array $raw): array
    {
        $tiers = collect($raw)
            ->filter(fn ($t) => is_array($t)
                && ! empty(trim((string) ($t['label'] ?? '')))
                && is_numeric($t['price'] ?? null))
            ->map(function ($t) {
                return [
                    'label' => trim((string) $t['label']),
                    'price' => (float) $t['price'],
                    'is_default' => (bool) ($t['is_default'] ?? false),
                    'requires_document' => (bool) ($t['requires_document'] ?? false),
                    'description' => ($t['description'] ?? null) ? trim((string) $t['description']) : null,
                ];
            })
            ->values();

        if ($tiers->isEmpty()) {
            return [];
        }

        // Pastikan tepat satu tier default (kalau tiada, tier pertama jadi default).
        if (! $tiers->contains(fn ($t) => $t['is_default'])) {
            $tiers = $tiers->map(fn ($t, $i) => ['is_default' => $i === 0] + $t);
        }

        return $tiers->sortByDesc('is_default')->values()->all();
    }

    /**
     * Borang berbayar wajib ada harga — sama ada `price` tunggal atau `price_tiers`.
     */
    private function assertPaidFormHasPrice(array $data, array $priceTiers): void
    {
        if (empty($data['payment_required'])) {
            return;
        }

        $hasPrice = ! empty($priceTiers)
            || (isset($data['price']) && (float) $data['price'] > 0);

        abort_unless($hasPrice, 422, 'Borang berbayar perlu ada harga atau senarai harga tier.');
    }
}

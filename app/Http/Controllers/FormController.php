<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormQuestion;
use App\Models\FormResponse;
use App\Models\Organization;
use App\Notifications\FormInvitationNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FormController extends Controller
{
    const QUESTION_TYPES = ['text', 'textarea', 'number', 'email', 'phone', 'date', 'select', 'radio', 'checkbox'];

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
            'public_url' => $f->public_url,
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
            ->when(! $isSuperadmin, fn ($q) => $q->where('organization_id', $user->current_organization_id))
            ->orderByDesc('start_time')
            ->get(['id', 'title', 'start_time']);

        return Inertia::render('Admin/Forms/Builder', [
            'form' => null,
            'organizations' => $organizations,
            'events' => $events,
            'questionTypes' => self::QUESTION_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user()->load('organization');
        $isSuperadmin = $user->hasRole('Superadmin');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
            'allow_public' => ['boolean'],
            'organization_id' => $isSuperadmin ? ['nullable', 'exists:organizations,id'] : ['nullable'],
            'event_id' => ['nullable', 'exists:events,id'],
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

        $form = DB::transaction(function () use ($data, $user, $isSuperadmin) {
            $form = Form::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'allow_public' => $data['allow_public'] ?? true,
                'organization_id' => $isSuperadmin ? ($data['organization_id'] ?? null) : $user->current_organization_id,
                'event_id' => $data['event_id'] ?? null,
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

        return redirect()->route('admin.forms.index')
            ->with('success', "Borang \"{$form->title}\" berjaya dicipta.");
    }

    public function edit(Form $form): Response
    {
        $form->load(['questions' => function ($q) {
            $q->orderBy('sort_order');
        }, 'organization', 'event']);
        $user = request()->user();
        $isSuperadmin = $user->hasRole('Superadmin');

        $organizations = $isSuperadmin
            ? Organization::orderBy('min_age')->get(['id', 'name'])
            : [];

        $events = Event::query()
            ->when(! $isSuperadmin, fn ($q) => $q->where('organization_id', $user->current_organization_id))
            ->orderByDesc('start_time')
            ->get(['id', 'title', 'start_time']);

        return Inertia::render('Admin/Forms/Builder', [
            'form' => [
                'id' => $form->id,
                'title' => $form->title,
                'description' => $form->description,
                'is_active' => $form->is_active,
                'allow_public' => $form->allow_public,
                'organization_id' => $form->organization_id,
                'event_id' => $form->event_id,
                'share_token' => $form->share_token,
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
        $user = $request->user()->load('organization');
        $isSuperadmin = $user->hasRole('Superadmin');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
            'allow_public' => ['boolean'],
            'organization_id' => $isSuperadmin ? ['nullable', 'exists:organizations,id'] : ['nullable'],
            'event_id' => ['nullable', 'exists:events,id'],
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

        DB::transaction(function () use ($form, $data, $isSuperadmin) {
            $form->update([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? $form->is_active,
                'allow_public' => $data['allow_public'] ?? $form->allow_public,
                'organization_id' => $isSuperadmin ? ($data['organization_id'] ?? null) : $form->organization_id,
                'event_id' => $data['event_id'] ?? $form->event_id,
                'recipient_emails' => collect($data['recipient_emails'] ?? [])
                    ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
                    ->unique()
                    ->values()
                    ->all(),
            ]);

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

        return redirect()->route('admin.forms.index')
            ->with('success', "Borang \"{$form->title}\" berjaya dikemaskini.");
    }

    public function destroy(Form $form): RedirectResponse
    {
        $title = $form->title;
        $form->delete();

        return redirect()->route('admin.forms.index')
            ->with('success', "Borang \"{$title}\" berjaya dipadam.");
    }

    public function responses(Form $form): Response
    {
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

        $recipientName = $request->input('recipient_name', '');

        foreach ($emails as $email) {
            Notification::route('mail', $email)
                ->notify(new FormInvitationNotification($form, $recipientName));
        }

        return back()->with('success', "Borang dihantar ke {$emails->count()} emel penerima.");
    }

    // ─── PUBLIC ──────────────────────────────────────────────────────────────

    public function publicShow(string $token): Response
    {
        $form = Form::where('share_token', $token)
            ->where('is_active', true)
            ->firstOrFail();

        $form->load(['questions' => fn ($q) => $q->orderBy('sort_order'), 'organization:id,name']);

        return Inertia::render('Forms/Public', [
            'form' => [
                'id' => $form->id,
                'title' => $form->title,
                'description' => $form->description,
                'share_token' => $form->share_token,
                'organization_name' => $form->organization?->name,
                'questions' => $form->questions
                    ->sortBy('sort_order')
                    ->map(fn ($q) => [
                        'id' => $q->id,
                        'label' => $q->label,
                        'type' => $q->type,
                        'options' => $q->options,
                        'required' => $q->required,
                        'placeholder' => $q->placeholder,
                        'help_text' => $q->help_text,
                    ])->values(),
            ],
        ]);
    }

    public function publicSubmit(Request $request, string $token): RedirectResponse
    {
        $form = Form::where('share_token', $token)
            ->where('is_active', true)
            ->firstOrFail();

        $form->load('questions');

        $rules = [
            'respondent_name' => ['nullable', 'string', 'max:255'],
            'respondent_email' => ['nullable', 'email', 'max:255'],
            'respondent_phone' => ['nullable', 'string', 'max:50'],
            'answers' => ['required', 'array'],
        ];

        foreach ($form->questions as $q) {
            $key = "answers.{$q->id}";
            $rule = $q->required ? ['required'] : ['nullable'];
            $rules[$key] = $rule;
        }

        $data = $request->validate($rules);

        DB::transaction(function () use ($form, $data, $request) {
            $response = FormResponse::create([
                'form_id' => $form->id,
                'user_id' => $request->user()?->id,
                'respondent_name' => $data['respondent_name'] ?? null,
                'respondent_email' => $data['respondent_email'] ?? null,
                'respondent_phone' => $data['respondent_phone'] ?? null,
                'submitted_at' => now(),
            ]);

            foreach ($data['answers'] as $questionId => $value) {
                FormAnswer::create([
                    'form_response_id' => $response->id,
                    'form_question_id' => $questionId,
                    'value' => is_array($value) ? implode(', ', $value) : (string) $value,
                ]);
            }
        });

        return redirect()->back()->with('success', 'Respons anda telah diterima. Terima kasih!');
    }
}

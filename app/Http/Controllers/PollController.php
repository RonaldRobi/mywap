<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Poll;
use App\Models\PollAnswer;
use App\Models\PollOption;
use App\Models\PollQuestion;
use App\Models\PollResponse;
use App\Models\User;
use App\Models\UsrahGroup;
use App\Services\PollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PollController extends Controller
{
    public function __construct(private readonly PollService $polls) {}

    // ─── Member Facing ────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        return Inertia::render('Polls/Index', $this->polls->listForUser($request->user()));
    }

    public function show(Request $request, Poll $poll): Response
    {
        $user = $request->user();

        abort_unless($this->polls->canAccess($user, $poll), 403);
        abort_if(! $poll->isAvailable(), 404);

        if ($this->polls->hasResponded($user, $poll)) {
            return redirect()->route('member.polls.results', $poll->id);
        }

        return Inertia::render('Polls/Show', [
            'poll' => $this->polls->serializePoll($poll),
        ]);
    }

    public function respond(Request $request, Poll $poll): RedirectResponse
    {
        $user = $request->user();

        abort_unless($this->polls->canAccess($user, $poll), 403);
        abort_if(! $poll->isAvailable(), 404);
        abort_if($this->polls->hasResponded($user, $poll), 409);

        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*.question_id' => ['required', 'exists:poll_questions,id'],
            'answers.*.option_ids' => ['required', 'array', 'min:1'],
            'answers.*.option_ids.*' => ['exists:poll_options,id'],
        ]);

        $this->polls->respond($user, $poll, $validated['answers']);

        return redirect()->route('member.polls.results', $poll->id)
            ->with('success', 'Undian berjaya dihantar.');
    }

    // ─── Public (anonymous) poll feedback — program poster QR ─────────────────

    public function publicShow(Request $request, Poll $poll): Response
    {
        abort_unless($poll->isAvailable(), 404);

        return Inertia::render('Polls/PublicShow', [
            'poll' => $this->polls->serializePoll($poll),
        ]);
    }

    public function publicRespond(Request $request, Poll $poll): RedirectResponse
    {
        abort_unless($poll->isAvailable(), 404);

        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*.question_id' => ['required', 'exists:poll_questions,id'],
            'answers.*.option_ids' => ['required', 'array', 'min:1'],
            'answers.*.option_ids.*' => ['exists:poll_options,id'],
        ]);

        $questionIds = $poll->questions()->pluck('id')->toArray();
        $submittedQuestionIds = collect($validated['answers'])->pluck('question_id')->unique()->toArray();
        $missing = array_diff($questionIds, $submittedQuestionIds);
        abort_if(! empty($missing), 422, 'Not all questions answered.');

        DB::transaction(function () use ($poll, $validated) {
            $response = PollResponse::create([
                'user_id' => null,
                'poll_id' => $poll->id,
                'organization_id' => $poll->organization_id,
                'submitted_at' => now(),
            ]);

            foreach ($validated['answers'] as $answer) {
                foreach ($answer['option_ids'] as $optionId) {
                    PollAnswer::create([
                        'poll_response_id' => $response->id,
                        'poll_question_id' => $answer['question_id'],
                        'poll_option_id' => $optionId,
                    ]);
                }
            }
        });

        return redirect()->route('polls.public.show', $poll->id)
            ->with('success', 'Maklum balas anda berjaya dihantar. Terima kasih!');
    }

    public function results(Request $request, Poll $poll): Response
    {
        $user = $request->user();

        abort_unless($this->polls->canViewResults($user, $poll), 403);

        return Inertia::render('Polls/Results', $this->polls->results($user, $poll));
    }

    // ─── Admin Facing ─────────────────────────────────────────────────────────

    public function adminIndex(Request $request): Response
    {
        $user = $request->user();

        $query = Poll::withCount('responses');

        if (! $user->hasRole('Superadmin')) {
            $query->where(function ($q) use ($user) {
                $q->where('organization_id', $user->current_organization_id)
                    ->orWhere('target_type', 'all_orgs');
            });
        }

        $polls = $query->orderByDesc('created_at')->paginate(15)->withQueryString()->through(
            fn (Poll $poll) => [
                'id' => $poll->id,
                'title' => $poll->title,
                'type' => $poll->type,
                'target_type' => $poll->target_type,
                'ends_at' => $poll->ends_at?->toISOString(),
                'ends_at_formatted' => $poll->ends_at?->locale('ms')->isoFormat('D MMM YYYY, h:mm A'),
                'is_active' => $poll->is_active,
                'is_expired' => $poll->isExpired(),
                'show_results' => $poll->show_results,
                'response_count' => $poll->responses_count,
                'created_at_formatted' => $poll->created_at->locale('ms')->isoFormat('D MMM YYYY'),
            ]
        );

        return Inertia::render('Admin/Polls/Index', [
            'polls' => $polls,
        ]);
    }

    public function adminCreate(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Admin/Polls/Form', [
            'poll' => null,
            'organizations' => $user->hasRole('Superadmin')
                ? Organization::orderBy('min_age')->get(['id', 'name', 'slug'])
                : [],
            'usrahGroups' => UsrahGroup::where('organization_id', $user->current_organization_id)
                ->where('is_active', true)
                ->get(['id', 'name']),
            'members' => User::withoutGlobalScopes()
                ->where('current_organization_id', $user->current_organization_id)
                ->select('id', 'name', 'email')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function adminStore(Request $request): RedirectResponse
    {
        $user = $request->user();
        $isSuperadmin = $user->hasRole('Superadmin');

        $data = $this->validatePollRequest($request);

        DB::transaction(function () use ($data, $user, $isSuperadmin) {
            $orgId = $isSuperadmin
                ? (int) ($data['organization_id'] ?? $user->current_organization_id)
                : (int) $user->current_organization_id;

            $poll = Poll::create([
                'organization_id' => $orgId,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'type' => $data['type'],
                'target_type' => $data['target_type'],
                'ends_at' => $data['ends_at'] ?? null,
                'show_results' => (bool) ($data['show_results'] ?? true),
                'is_active' => true,
            ]);

            foreach ($data['questions'] as $qIndex => $q) {
                $question = PollQuestion::create([
                    'poll_id' => $poll->id,
                    'question_text' => $q['question_text'],
                    'type' => $q['type'],
                    'sort_order' => $qIndex,
                ]);

                foreach ($q['options'] as $oIndex => $o) {
                    PollOption::create([
                        'poll_question_id' => $question->id,
                        'option_text' => $o['option_text'],
                        'sort_order' => $oIndex,
                    ]);
                }
            }

            if ($data['target_type'] === 'members' && ! empty($data['target_members'])) {
                $poll->targetMembers()->sync($data['target_members']);
            }

            if ($data['target_type'] === 'usrah' && ! empty($data['target_usrah_groups'])) {
                $poll->targetUsrahGroups()->sync($data['target_usrah_groups']);
            }
        });

        return redirect()->route('admin.polls.index')
            ->with('success', 'Undian berjaya dicipta.');
    }

    public function adminEdit(Request $request, Poll $poll): Response
    {
        $user = $request->user();
        abort_unless($user->hasRole('Superadmin') || $poll->organization_id === (int) $user->current_organization_id, 403);

        $poll->load(['questions.options', 'targetMembers', 'targetUsrahGroups']);

        return Inertia::render('Admin/Polls/Form', [
            'poll' => [
                'id' => $poll->id,
                'organization_id' => $poll->organization_id,
                'title' => $poll->title,
                'description' => $poll->description,
                'type' => $poll->type,
                'target_type' => $poll->target_type,
                'ends_at' => $poll->ends_at?->format('Y-m-d\TH:i'),
                'show_results' => $poll->show_results,
                'questions' => $poll->questions->map(fn ($q) => [
                    'question_text' => $q->question_text,
                    'type' => $q->type,
                    'options' => $q->options->map(fn ($o) => [
                        'option_text' => $o->option_text,
                    ]),
                ]),
                'target_members' => $poll->targetMembers->pluck('id')->toArray(),
                'target_usrah_groups' => $poll->targetUsrahGroups->pluck('id')->toArray(),
            ],
            'organizations' => $user->hasRole('Superadmin')
                ? Organization::orderBy('min_age')->get(['id', 'name', 'slug'])
                : [],
            'usrahGroups' => UsrahGroup::where('organization_id', $user->current_organization_id)
                ->where('is_active', true)
                ->get(['id', 'name']),
            'members' => User::withoutGlobalScopes()
                ->where('current_organization_id', $user->current_organization_id)
                ->select('id', 'name', 'email')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function adminUpdate(Request $request, Poll $poll): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasRole('Superadmin') || $poll->organization_id === (int) $user->current_organization_id, 403);

        $hasResponses = $poll->responses()->exists();
        if ($hasResponses) {
            return back()->with('error', 'Tidak boleh edit undian yang sudah ada jawapan.');
        }

        $data = $this->validatePollRequest($request);

        DB::transaction(function () use ($data, $poll) {
            $poll->update([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'type' => $data['type'],
                'target_type' => $data['target_type'],
                'ends_at' => $data['ends_at'] ?? null,
                'show_results' => (bool) ($data['show_results'] ?? true),
            ]);

            $poll->questions()->each(function ($q) {
                $q->options()->delete();
            });
            $poll->questions()->delete();

            foreach ($data['questions'] as $qIndex => $q) {
                $question = PollQuestion::create([
                    'poll_id' => $poll->id,
                    'question_text' => $q['question_text'],
                    'type' => $q['type'],
                    'sort_order' => $qIndex,
                ]);

                foreach ($q['options'] as $oIndex => $o) {
                    PollOption::create([
                        'poll_question_id' => $question->id,
                        'option_text' => $o['option_text'],
                        'sort_order' => $oIndex,
                    ]);
                }
            }

            if ($data['target_type'] === 'members') {
                $poll->targetMembers()->sync($data['target_members'] ?? []);
                $poll->targetUsrahGroups()->sync([]);
            } elseif ($data['target_type'] === 'usrah') {
                $poll->targetUsrahGroups()->sync($data['target_usrah_groups'] ?? []);
                $poll->targetMembers()->sync([]);
            } else {
                $poll->targetMembers()->sync([]);
                $poll->targetUsrahGroups()->sync([]);
            }
        });

        return redirect()->route('admin.polls.index')
            ->with('success', 'Undian berjaya dikemas kini.');
    }

    public function adminDestroy(Poll $poll): RedirectResponse
    {
        $user = $request()->user();
        abort_unless($user->hasRole('Superadmin') || $poll->organization_id === (int) $user->current_organization_id, 403);

        $poll->delete();

        return back()->with('success', 'Undian berjaya dipadam.');
    }

    public function adminResults(Request $request, Poll $poll): Response
    {
        $user = $request->user();
        abort_unless($user->hasRole('Superadmin') || $poll->organization_id === (int) $user->current_organization_id, 403);

        $poll->load(['questions.options', 'responses' => fn ($q) => $q->with('user:id,name,email')]);

        $totalResponses = $poll->responses->count();
        $totalMembers = User::withoutGlobalScopes()
            ->where('current_organization_id', $poll->organization_id)
            ->count();

        $questions = $poll->questions->map(function ($question) {
            $totalForQuestion = PollAnswer::where('poll_question_id', $question->id)->count();
            $options = $question->options->map(function ($option) use ($question, $totalForQuestion) {
                $count = PollAnswer::where('poll_question_id', $question->id)
                    ->where('poll_option_id', $option->id)
                    ->count();

                return [
                    'id' => $option->id,
                    'option_text' => $option->option_text,
                    'count' => $count,
                    'percentage' => $totalForQuestion > 0 ? round(($count / $totalForQuestion) * 100, 1) : 0,
                ];
            });

            return [
                'id' => $question->id,
                'question_text' => $question->question_text,
                'type' => $question->type,
                'options' => $options,
                'total_answers' => $totalForQuestion,
            ];
        });

        $respondents = $poll->responses->map(fn ($r) => [
            'id' => $r->user_id ?? 'anon-'.$r->id,
            'name' => $r->user_id ? ($r->user?->name ?? 'Ahli Dibuang') : 'Tanpa Nama',
            'email' => $r->user_id ? ($r->user?->email ?? '-') : '-',
            'submitted_at' => $r->submitted_at->format('d/m/Y H:i'),
        ]);

        return Inertia::render('Admin/Polls/Results', [
            'poll' => [
                'id' => $poll->id,
                'title' => $poll->title,
                'description' => $poll->description,
                'type' => $poll->type,
                'ends_at' => $poll->ends_at?->toISOString(),
                'ends_at_formatted' => $poll->ends_at?->locale('ms')->isoFormat('D MMM YYYY, h:mm A'),
                'show_results' => $poll->show_results,
                'is_expired' => $poll->isExpired(),
            ],
            'questions' => $questions,
            'total_responses' => $totalResponses,
            'total_members' => $totalMembers,
            'response_rate' => $totalMembers > 0 ? round(($totalResponses / $totalMembers) * 100, 1) : 0,
            'respondents' => $respondents,
        ]);
    }

    public function exportCsv(Poll $poll): StreamedResponse
    {
        $user = request()->user();
        abort_unless($user->hasRole('Superadmin') || $poll->organization_id === (int) $user->current_organization_id, 403);

        $poll->load(['questions.options', 'responses.user', 'responses.answers']);

        $headers = [
            'Nama',
            'Email',
            'Dihantar Pada',
        ];

        $poll->questions->each(function ($q) use (&$headers) {
            $headers[] = $q->question_text;
        });

        $callback = function () use ($poll, $headers) {
            $fh = fopen('php://output', 'w');
            fputcsv($fh, $headers);

            foreach ($poll->responses as $response) {
                $row = [
                    $response->user_id ? ($response->user?->name ?? 'Ahli Dibuang') : 'Tanpa Nama',
                    $response->user_id ? ($response->user?->email ?? '-') : '-',
                    $response->submitted_at->format('d/m/Y H:i'),
                ];

                foreach ($poll->questions as $question) {
                    $selected = $response->answers
                        ->where('poll_question_id', $question->id)
                        ->pluck('poll_option_id')
                        ->toArray();

                    $texts = $question->options
                        ->whereIn('id', $selected)
                        ->pluck('option_text')
                        ->implode('; ');

                    $row[] = $texts;
                }

                fputcsv($fh, $row);
            }

            fclose($fh);
        };

        $filename = 'undian-'.Str::slug($poll->title).'-'.now()->format('Ymd').'.csv';

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv; charset=utf-8',
        ]);
    }

    /**
     * adminQr()
     *
     * Displays the program-feedback QR code for a poll. The public URL points
     * to the anonymous response page so participants can scan the code from a
     * printed poster without needing to log in.
     */
    public function adminQr(Request $request, Poll $poll): Response
    {
        $user = $request->user();
        abort_unless($user->hasRole('Superadmin') || $poll->organization_id === (int) $user->current_organization_id, 403);

        $publicUrl = route('polls.public.show', $poll->id);

        $qrSvg = QrCode::format('svg')
            ->size(320)
            ->errorCorrection('H')
            ->generate($publicUrl);

        return Inertia::render('Admin/Polls/Qr', [
            'poll' => [
                'id' => $poll->id,
                'title' => $poll->title,
            ],
            'qrSvg' => (string) $qrSvg,
            'publicUrl' => $publicUrl,
        ]);
    }

    /**
     * adminQrPng()
     *
     * Downloadable high-resolution PNG of the poll QR code, sized for printing
     * on program posters.
     */
    public function adminQrPng(Request $request, Poll $poll): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user->hasRole('Superadmin') || $poll->organization_id === (int) $user->current_organization_id, 403);

        $publicUrl = route('polls.public.show', $poll->id);

        $png = QrCode::format('png')
            ->size(600)
            ->margin(2)
            ->generate($publicUrl);

        return response()->streamDownload(function () use ($png) {
            echo $png;
        }, 'undian-'.Str::slug($poll->title).'-qr.png', [
            'Content-Type' => 'image/png',
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function validatePollRequest(Request $request): array
    {
        return $request->validate([
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:4000'],
            'type' => ['required', 'in:poll,survey'],
            'target_type' => ['required', 'in:all,members,usrah,all_orgs'],
            'ends_at' => ['nullable', 'date', 'after:now'],
            'show_results' => ['boolean'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.question_text' => ['required', 'string', 'max:500'],
            'questions.*.type' => ['required', 'in:single_choice,multiple_choice'],
            'questions.*.options' => ['required', 'array', 'min:2'],
            'questions.*.options.*.option_text' => ['required', 'string', 'max:255'],
            'target_members' => ['nullable', 'array'],
            'target_members.*' => ['integer', 'exists:users,id'],
            'target_usrah_groups' => ['nullable', 'array'],
            'target_usrah_groups.*' => ['integer', 'exists:usrah_groups,id'],
        ]);
    }
}

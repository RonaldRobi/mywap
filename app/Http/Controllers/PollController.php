<?php

namespace App\Http\Controllers;

use App\Models\Poll;
use App\Models\PollQuestion;
use App\Models\PollOption;
use App\Models\PollResponse;
use App\Models\PollAnswer;
use App\Models\User;
use App\Models\UsrahGroup;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PollController extends Controller
{
    // ─── Member Facing ────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $user = $request->user();
        $orgId = (int) $user->current_organization_id;

        $polls = Poll::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->where(function ($q) use ($user) {
                $q->where('target_type', 'all')
                    ->orWhere(function ($q) use ($user) {
                        $q->where('target_type', 'members')
                            ->whereHas('targetMembers', fn($q) => $q->where('user_id', $user->id));
                    })
                    ->orWhere(function ($q) use ($user) {
                        $q->where('target_type', 'usrah')
                            ->whereHas('targetUsrahGroups.members', fn($q) => $q->where('user_id', $user->id));
                    });
            })
            ->withCount('responses')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($poll) use ($user) {
                $myResponse = PollResponse::where('poll_id', $poll->id)
                    ->where('user_id', $user->id)
                    ->first();
                return [
                    'id' => $poll->id,
                    'title' => $poll->title,
                    'description' => $poll->description,
                    'type' => $poll->type,
                    'ends_at' => $poll->ends_at?->toISOString(),
                    'ends_at_formatted' => $poll->ends_at?->locale('ms')->isoFormat('D MMM YYYY, h:mm A'),
                    'show_results' => $poll->show_results,
                    'is_expired' => $poll->isExpired(),
                    'response_count' => $poll->responses_count,
                    'has_responded' => $myResponse !== null,
                    'my_response_id' => $myResponse?->id,
                ];
            });

        $available = $polls->filter(fn($p) => !$p['has_responded'])->values();
        $answered = $polls->filter(fn($p) => $p['has_responded'])->values();

        return Inertia::render('Polls/Index', [
            'availablePolls' => $available,
            'answeredPolls' => $answered,
        ]);
    }

    public function show(Request $request, Poll $poll): Response
    {
        $user = $request->user();

        abort_if($poll->organization_id !== (int) $user->current_organization_id, 403);
        abort_if(!$poll->isAvailable(), 404);

        if (PollResponse::where('poll_id', $poll->id)->where('user_id', $user->id)->exists()) {
            return redirect()->route('member.polls.results', $poll->id);
        }

        $poll->load(['questions' => fn($q) => $q->orderBy('sort_order'), 'questions.options' => fn($o) => $o->orderBy('sort_order')]);

        return Inertia::render('Polls/Show', [
            'poll' => $this->serializePollForMember($poll),
        ]);
    }

    public function respond(Request $request, Poll $poll): RedirectResponse
    {
        $user = $request->user();

        abort_if($poll->organization_id !== (int) $user->current_organization_id, 403);
        abort_if(!$poll->isAvailable(), 404);
        abort_if(PollResponse::where('poll_id', $poll->id)->where('user_id', $user->id)->exists(), 409);

        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*.question_id' => ['required', 'exists:poll_questions,id'],
            'answers.*.option_ids' => ['required', 'array', 'min:1'],
            'answers.*.option_ids.*' => ['exists:poll_options,id'],
        ]);

        $questionIds = $poll->questions()->pluck('id')->toArray();
        $submittedQuestionIds = collect($validated['answers'])->pluck('question_id')->unique()->toArray();
        $missing = array_diff($questionIds, $submittedQuestionIds);
        abort_if(!empty($missing), 422, 'Not all questions answered.');

        DB::transaction(function () use ($user, $poll, $validated) {
            $response = PollResponse::create([
                'user_id' => $user->id,
                'poll_id' => $poll->id,
                'organization_id' => (int) $user->current_organization_id,
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

        return redirect()->route('member.polls.results', $poll->id)
            ->with('success', 'Undian berjaya dihantar.');
    }

    public function results(Request $request, Poll $poll): Response
    {
        $user = $request->user();

        abort_if($poll->organization_id !== (int) $user->current_organization_id, 403);

        $myResponse = PollResponse::where('poll_id', $poll->id)
            ->where('user_id', $user->id)
            ->with('answers')
            ->first();

        abort_if(!$myResponse && !$poll->show_results, 403);

        $poll->load(['questions.options', 'responses']);

        $totalResponses = $poll->responses()->count();

        $questions = $poll->questions->map(function ($question) use ($poll) {
            $totalForQuestion = PollAnswer::where('poll_question_id', $question->id)->count();
            $options = $question->options->map(function ($option) use ($question) {
                $count = PollAnswer::where('poll_question_id', $question->id)
                    ->where('poll_option_id', $option->id)
                    ->count();
                return [
                    'id' => $option->id,
                    'option_text' => $option->option_text,
                    'count' => $count,
                ];
            });

            $maxCount = $options->max('count') ?: 1;

            return [
                'id' => $question->id,
                'question_text' => $question->question_text,
                'type' => $question->type,
                'options' => $options->map(fn($o) => [
                    ...$o,
                    'percentage' => $totalForQuestion > 0 ? round(($o['count'] / $totalForQuestion) * 100, 1) : 0,
                    'width_pct' => $totalForQuestion > 0 ? round(($o['count'] / $maxCount) * 100, 1) : 0,
                ]),
                'total_answers' => $totalForQuestion,
            ];
        });

        return Inertia::render('Polls/Results', [
            'poll' => $this->serializePollForMember($poll),
            'questions' => $questions,
            'total_responses' => $totalResponses,
            'my_answers' => $myResponse?->answers->pluck('poll_option_id')->toArray() ?? [],
        ]);
    }

    // ─── Admin Facing ─────────────────────────────────────────────────────────

    public function adminIndex(Request $request): Response
    {
        $user = $request->user();

        $query = Poll::withCount('responses');

        if (!$user->hasRole('Superadmin')) {
            $query->where('organization_id', $user->current_organization_id);
        }

        $polls = $query->orderByDesc('created_at')->paginate(15)->withQueryString()->through(
            fn(Poll $poll) => [
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

            if ($data['target_type'] === 'members' && !empty($data['target_members'])) {
                $poll->targetMembers()->sync($data['target_members']);
            }

            if ($data['target_type'] === 'usrah' && !empty($data['target_usrah_groups'])) {
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
                'questions' => $poll->questions->map(fn($q) => [
                    'question_text' => $q->question_text,
                    'type' => $q->type,
                    'options' => $q->options->map(fn($o) => [
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

        $poll->load(['questions.options', 'responses' => fn($q) => $q->with('user:id,name,email')]);

        $totalResponses = $poll->responses->count();
        $totalMembers = User::withoutGlobalScopes()
            ->where('current_organization_id', $poll->organization_id)
            ->count();

        $questions = $poll->questions->map(function ($question) {
            $totalForQuestion = PollAnswer::where('poll_question_id', $question->id)->count();
            $options = $question->options->map(function ($option) use ($question) {
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

        $respondents = $poll->responses->map(fn($r) => [
            'id' => $r->user_id,
            'name' => $r->user?->name ?? 'Ahli Dibuang',
            'email' => $r->user?->email ?? '-',
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
                    $response->user?->name ?? 'Ahli Dibuang',
                    $response->user?->email ?? '-',
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

        $filename = 'undian-' . Str::slug($poll->title) . '-' . now()->format('Ymd') . '.csv';

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv; charset=utf-8',
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function serializePollForMember(Poll $poll): array
    {
        return [
            'id' => $poll->id,
            'title' => $poll->title,
            'description' => $poll->description,
            'type' => $poll->type,
            'ends_at' => $poll->ends_at?->toISOString(),
            'ends_at_formatted' => $poll->ends_at?->locale('ms')->isoFormat('D MMM YYYY, h:mm A'),
            'show_results' => $poll->show_results,
            'is_expired' => $poll->isExpired(),
            'questions' => $poll->questions->map(fn($q) => [
                'id' => $q->id,
                'question_text' => $q->question_text,
                'type' => $q->type,
                'options' => $q->options->map(fn($o) => [
                    'id' => $o->id,
                    'option_text' => $o->option_text,
                ]),
            ]),
        ];
    }

    private function validatePollRequest(Request $request): array
    {
        return $request->validate([
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:4000'],
            'type' => ['required', 'in:poll,survey'],
            'target_type' => ['required', 'in:all,members,usrah'],
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

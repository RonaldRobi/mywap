<?php

namespace App\Services;

use App\Models\Poll;
use App\Models\PollAnswer;
use App\Models\PollResponse;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * PollService
 *
 * Logik tunggal untuk domain Poll (member-facing) — dikongsi oleh
 * WebController (Inertia) dan ApiController (JSON) supaya web & Flutter
 * tidak drift.
 */
class PollService
{
    /**
     * Senarai undian untuk ahli: dibahagi available / answered.
     */
    public function listForUser(User $user): array
    {
        $orgId = (int) $user->current_organization_id;

        $polls = Poll::withoutGlobalScopes()
            ->where(function ($q) use ($orgId) {
                $q->where('organization_id', $orgId)
                    ->orWhere('target_type', 'all_orgs');
            })
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->where(function ($q) use ($user) {
                $q->where('target_type', 'all')
                    ->orWhere('target_type', 'all_orgs')
                    ->orWhere(function ($q) use ($user) {
                        $q->where('target_type', 'members')
                            ->whereHas('targetMembers', fn ($q) => $q->where('user_id', $user->id));
                    })
                    ->orWhere(function ($q) use ($user) {
                        $q->where('target_type', 'usrah')
                            ->whereHas('targetUsrahGroups.members', fn ($q) => $q->where('user_id', $user->id));
                    });
            })
            ->withCount('responses')
            ->with(['responses' => fn ($q) => $q->where('user_id', $user->id)])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($poll) {
                $myResponse = $poll->responses->first();

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

        return [
            'availablePolls' => $polls->filter(fn ($p) => ! $p['has_responded'])->values(),
            'answeredPolls' => $polls->filter(fn ($p) => $p['has_responded'])->values(),
        ];
    }

    /**
     * Serialize satu undian (dengan soalan & pilihan) untuk member.
     */
    public function serializePoll(Poll $poll): array
    {
        $poll->loadMissing([
            'questions' => fn ($q) => $q->orderBy('sort_order'),
            'questions.options' => fn ($o) => $o->orderBy('sort_order'),
        ]);

        return [
            'id' => $poll->id,
            'title' => $poll->title,
            'description' => $poll->description,
            'type' => $poll->type,
            'ends_at' => $poll->ends_at?->toISOString(),
            'ends_at_formatted' => $poll->ends_at?->locale('ms')->isoFormat('D MMM YYYY, h:mm A'),
            'show_results' => $poll->show_results,
            'is_expired' => $poll->isExpired(),
            'questions' => $poll->questions->map(fn ($q) => [
                'id' => $q->id,
                'question_text' => $q->question_text,
                'type' => $q->type,
                'options' => $q->options->map(fn ($o) => [
                    'id' => $o->id,
                    'option_text' => $o->option_text,
                ]),
            ]),
        ];
    }

    /**
     * Ahli hanya boleh akses undian org sendiri atau undian semua org.
     */
    public function canAccess(User $user, Poll $poll): bool
    {
        return $poll->organization_id === (int) $user->current_organization_id
            || $poll->target_type === 'all_orgs';
    }

    public function hasResponded(User $user, Poll $poll): bool
    {
        return PollResponse::where('poll_id', $poll->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    public function myResponse(User $user, Poll $poll): ?PollResponse
    {
        return PollResponse::where('poll_id', $poll->id)
            ->where('user_id', $user->id)
            ->with('answers')
            ->first();
    }

    /**
     * Ahli boleh tengok keputusan jika sudah menjawab atau show_results aktif.
     */
    public function canViewResults(User $user, Poll $poll): bool
    {
        return $this->canAccess($user, $poll)
            && ($this->hasResponded($user, $poll) || $poll->show_results);
    }

    /**
     * Simpan respons ahli. Pastikan semua soalan dijawab (422 jika tidak).
     */
    public function respond(User $user, Poll $poll, array $answers): PollResponse
    {
        $questionIds = $poll->questions()->pluck('id')->toArray();
        $submittedQuestionIds = collect($answers)->pluck('question_id')->unique()->toArray();
        $missing = array_diff($questionIds, $submittedQuestionIds);
        abort_if(! empty($missing), 422, 'Not all questions answered.');

        return DB::transaction(function () use ($user, $poll, $answers) {
            $response = PollResponse::create([
                'user_id' => $user->id,
                'poll_id' => $poll->id,
                'organization_id' => (int) $user->current_organization_id,
                'submitted_at' => now(),
            ]);

            $now = now();
            $rows = [];

            foreach ($answers as $answer) {
                foreach ($answer['option_ids'] as $optionId) {
                    $rows[] = [
                        'poll_response_id' => $response->id,
                        'poll_question_id' => $answer['question_id'],
                        'poll_option_id' => $optionId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if ($rows !== []) {
                PollAnswer::insert($rows);
            }

            Cache::forget("member.dashboard.{$user->id}");

            return $response;
        });
    }

    /**
     * Payload penuh keputusan undian untuk member (web & API).
     */
    public function results(User $user, Poll $poll): array
    {
        $myResponse = $this->myResponse($user, $poll);

        $poll->load(['questions.options', 'responses']);

        $totalResponses = $poll->responses()->count();

        $questionCounts = collect();
        $optionCounts = collect();

        if ($poll->questions->isNotEmpty()) {
            $answerRows = PollAnswer::whereIn('poll_question_id', $poll->questions->pluck('id'))
                ->selectRaw('poll_question_id, poll_option_id, COUNT(*) as n')
                ->groupBy(['poll_question_id', 'poll_option_id'])
                ->get();

            $questionCounts = $answerRows
                ->groupBy('poll_question_id')
                ->map->sum('n');
            $optionCounts = $answerRows
                ->groupBy('poll_option_id')
                ->map->sum('n');
        }

        $questions = $poll->questions->map(function ($question) use ($questionCounts, $optionCounts) {
            $totalForQuestion = $questionCounts->get($question->id, 0);
            $options = $question->options->map(function ($option) use ($optionCounts) {
                $count = $optionCounts->get($option->id, 0);

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
                'options' => $options->map(fn ($o) => [
                    ...$o,
                    'percentage' => $totalForQuestion > 0 ? round(($o['count'] / $totalForQuestion) * 100, 1) : 0,
                    'width_pct' => $totalForQuestion > 0 ? round(($o['count'] / $maxCount) * 100, 1) : 0,
                ]),
                'total_answers' => $totalForQuestion,
            ];
        });

        return [
            'poll' => $this->serializePoll($poll),
            'questions' => $questions,
            'total_responses' => $totalResponses,
            'my_answers' => $myResponse?->answers->pluck('poll_option_id')->toArray() ?? [],
        ];
    }
}

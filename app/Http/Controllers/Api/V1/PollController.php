<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Poll;
use App\Services\PollService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PollController extends Controller
{
    public function __construct(private readonly PollService $polls) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success($this->polls->listForUser($request->user()));
    }

    public function show(Request $request, Poll $poll): JsonResponse
    {
        $user = $request->user();

        if (! $this->polls->canAccess($user, $poll)) {
            return ApiResponse::error('Anda tidak dibenarkan mengakses undian ini.', [], 403);
        }

        if (! $poll->isAvailable()) {
            return ApiResponse::error('Undian ini tidak tersedia.', [], 404);
        }

        return ApiResponse::success(['poll' => $this->polls->serializePoll($poll)]);
    }

    public function respond(Request $request, Poll $poll): JsonResponse
    {
        $user = $request->user();

        if (! $this->polls->canAccess($user, $poll)) {
            return ApiResponse::error('Anda tidak dibenarkan mengakses undian ini.', [], 403);
        }

        if (! $poll->isAvailable()) {
            return ApiResponse::error('Undian ini tidak tersedia.', [], 404);
        }

        if ($this->polls->hasResponded($user, $poll)) {
            return ApiResponse::error('Anda sudah menjawab undian ini.', [], 409);
        }

        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*.question_id' => ['required', 'exists:poll_questions,id'],
            'answers.*.option_ids' => ['required', 'array', 'min:1'],
            'answers.*.option_ids.*' => ['exists:poll_options,id'],
        ]);

        $response = $this->polls->respond($user, $poll, $validated['answers']);

        return ApiResponse::success(['response_id' => $response->id]);
    }

    public function results(Request $request, Poll $poll): JsonResponse
    {
        $user = $request->user();

        if (! $this->polls->canViewResults($user, $poll)) {
            return ApiResponse::error('Keputusan undian ini belum dibuka.', [], 403);
        }

        return ApiResponse::success($this->polls->results($user, $poll));
    }
}

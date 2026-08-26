<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\EventService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct(private readonly EventService $events) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->events->list($request, $request->user());

        return ApiResponse::paginated($paginator);
    }

    public function show(Request $request, Event $event): JsonResponse
    {
        $detail = $this->events->showDetail($event, $request->user());

        return ApiResponse::success($detail);
    }

    public function rsvp(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:going,maybe,declined'],
        ]);

        $this->events->rsvp($request->user(), $event, $validated['status']);

        return ApiResponse::success([
            'event_id' => $event->id,
            'status' => $validated['status'],
        ], ['message' => 'RSVP berjaya dikemas kini.']);
    }
}

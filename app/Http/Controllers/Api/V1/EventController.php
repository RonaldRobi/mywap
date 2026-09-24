<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\AdminService;
use App\Services\EventService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct(
        private readonly EventService $events,
        private readonly AdminService $admin,
    ) {}

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

    /**
     * Kehadiran ahli sendiri melalui imbasan QR kod (bukan admin) — token
     * ditanam dalam QR poster event. Sama logic dengan web
     * AttendanceController::scan (aliran "ahli login"), tapi sebagai JSON.
     *
     * Walk-in: jika ahli tiada pendaftaran terdahulu, satu rekod walk-in
     * dicipta automatik. Menayangkan QR poster = urusetia sengaja menjemput
     * kehadiran, jadi mengimbas QR itu sendiri adalah kebenaran untuk hadir.
     */
    public function checkIn(Request $request, int $id): JsonResponse
    {
        $request->validate(['token' => ['required', 'string']]);

        $event = Event::findOrFail($id);
        $user = $request->user();

        if (! hash_equals($event->attendance_token, (string) $request->input('token'))) {
            return ApiResponse::error('Token kehadiran tidak sah.', status: 403);
        }

        if ($user->hasRole(['Superadmin', 'Admin'])) {
            return ApiResponse::error(
                'Akaun pentadbir tidak dibenarkan merekod kehadiran program.',
                status: 403
            );
        }

        $registration = $this->admin->findRegistration($event, $user);
        $isWalkIn = $registration === null;

        if ($isWalkIn) {
            $registration = $this->admin->createWalkInRegistration($event, $user);
        }

        $blockReason = $this->admin->registrationBlockReason($registration);
        if ($blockReason) {
            return ApiResponse::error($blockReason, status: 422);
        }

        $this->admin->recordAttendance($registration, $isWalkIn ? 'walkin' : 'member');

        return ApiResponse::success([
            'event_id' => $event->id,
            'event_title' => $event->title,
            'registration_no' => $registration->registration_no,
            'walk_in' => $isWalkIn,
        ], ['message' => 'Kehadiran anda telah direkodkan.']);
    }
}

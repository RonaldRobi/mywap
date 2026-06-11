<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class EventController extends Controller
{
    /**
     * adminAttendance()
     *
     * Returns a filtered list of events with attendance counts for admin view.
     * Filters: start date, end date, status (hadir/tidak_hadir)
     */
    public function adminAttendance(Request $request): \Inertia\Response
    {
        $user = $request->user();
        $query = Event::with(['organization', 'rsvps' => function($q) {
            $q->with('user:id');
        }]);

        // Filter by organization if not superadmin
        if (! $user->hasRole('Superadmin')) {
            $query->where(function ($innerQuery) use ($user) {
                $innerQuery->where('organization_id', $user->current_organization_id)
                    ->orWhereNull('organization_id');
            });
        }

        // Filter by date
        if ($request->filled('start')) {
            $query->whereDate('start_time', '>=', $request->input('start'));
        }
        if ($request->filled('end')) {
            $query->whereDate('start_time', '<=', $request->input('end'));
        }

        $events = $query->orderBy('start_time', 'desc')->get();

        // Filter by status (hadir/tidak_hadir)
        $status = $request->input('status');
        $filtered = $events->map(function($event) use ($status) {
            $attendanceCount = $event->rsvps->where('status', 'attended')->count();
            $eventArr = [
                'id' => $event->id,
                'title' => $event->title,
                'start_formatted' => $event->start_time->locale('ms')->isoFormat('ddd, D MMM YYYY [•] h:mm A'),
                'location_or_link' => $event->location_or_link,
                'attendance_count' => $attendanceCount,
            ];
            if ($status === 'hadir' && $attendanceCount === 0) return null;
            if ($status === 'tidak_hadir' && $attendanceCount > 0) return null;
            return $eventArr;
        })->filter()->values();

        return Inertia::render('Events/AdminAttendance', [
            'adminAttendance' => $filtered,
        ]);
    }
    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Serialise an Event to the shape consumed by Vue components.
     * Centralising here avoids duplication across index(), showQr(), etc.
     */
    private function serializeEvent(Event $event, ?int $authUserId = null): array
    {
        $myRsvp = $authUserId
            ? $event->rsvps->firstWhere('user_id', $authUserId)
            : null;

        return [
            'id'                  => $event->id,
            'title'               => $event->title,
            'slug'                => $event->slug,
            'description'         => $event->description,
            'type'                => $event->type,
            'location_or_link'    => $event->location_or_link,
            'start_time'          => $event->start_time->toISOString(),
            'start_formatted'     => $event->start_time->locale('ms')->isoFormat('ddd, D MMM YYYY [•] h:mm A'),
            'end_time'            => $event->end_time->toISOString(),
            'featured_image_url'  => $event->featured_image_url,
            'google_calendar_url' => $event->google_calendar_url,
            'organization'        => [
                'name'        => $event->organization?->name ?? 'Semua Organisasi',
                'slug'        => $event->organization?->slug ?? 'semua',
                'color_theme' => $event->organization?->color_theme ?? '#334155',
            ],
            'rsvp_count' => $event->rsvps->whereIn('status', ['going', 'attended'])->count(),
            'my_rsvp'    => $myRsvp ? $myRsvp->status : null,
        ];
    }

    // ─── Member Facing ────────────────────────────────────────────────────────

    /**
     * index()
     *
     * Upcoming events scoped to the authenticated user's organisation.
     * Superadmins see all upcoming events across every NGO tier.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $tab = $request->input('tab', 'upcoming');
        $search = $request->input('search');
        $typeFilter = $request->input('type');

        $query = Event::with(['organization', 'rsvps.user']);

        if ($tab === 'past') {
            $query->where('start_time', '<', now())->orderBy('start_time', 'desc');
        } else {
            $query->where('start_time', '>=', now())->orderBy('start_time', 'asc');
        }

        if (! $user->hasRole('Superadmin')) {
            $query->where(function ($innerQuery) use ($user) {
                $innerQuery->where('organization_id', $user->current_organization_id)
                    ->orWhereNull('organization_id');
            });
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('location_or_link', 'like', "%{$search}%");
            });
        }

        if ($typeFilter) {
            $query->where('type', $typeFilter);
        }


        $events = $query->paginate(12)->withQueryString()->through(
            function (Event $e) use ($user) {
                $eventArr = $this->serializeEvent($e, $user->id);
                // For admin/superadmin, include attendance list for modal
                if ($user->hasRole(['Superadmin', 'Admin'])) {
                    $eventArr['attendance'] = $e->rsvps
                        ->where('status', 'attended')
                        ->map(function ($rsvp) {
                            return [
                                'name' => $rsvp->user?->name ?? 'Ahli Dibuang',
                                'email' => $rsvp->user?->email ?? '-',
                                'phone' => $rsvp->user?->phone ?? '-',
                                'attended_at' => optional($rsvp->attended_at)->format('d/m/Y H:i'),
                            ];
                        })->values();
                }
                return $eventArr;
            }
        );

        // Senarai program yang telah dihadiri oleh user (ahli)
        $attendedEvents = [];
        if ($user->hasRole('Member')) {
            $attended = EventRsvp::where('user_id', $user->id)
                ->where('status', 'attended')
                ->with(['event.organization'])
                ->orderByDesc('attended_at')
                ->get();
            $attendedEvents = $attended->map(function ($rsvp) {
                $event = $rsvp->event;
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'organization' => [
                        'name' => $event->organization?->name ?? 'Semua Organisasi',
                        'color_theme' => $event->organization?->color_theme ?? '#334155',
                    ],
                    'start_formatted' => $event->start_time->locale('ms')->isoFormat('ddd, D MMM YYYY [•] h:mm A'),
                    'location_or_link' => $event->location_or_link,
                    'attended_at' => optional($rsvp->attended_at)->format('d/m/Y H:i'),
                ];
            });
        }

        return Inertia::render('Events/Index', [
            'events' => $events,
            'tab' => $tab,
            'filters' => [
                'search' => $search,
                'type' => $typeFilter,
            ],
            'organizations' => $user->hasRole('Superadmin')
                ? Organization::query()->orderBy('min_age')->get(['id', 'name', 'slug'])
                : [],
            'attendedEvents' => $attendedEvents,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole(['Admin', 'Superadmin']), 403);

        $isSuperadmin = $request->user()->hasRole('Superadmin');

        $data = $request->validate([
            'organization_id' => [
                'nullable',
                'integer',
                'exists:organizations,id',
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:4000'],
            'type' => ['required', 'in:physical,online'],
            'location_or_link' => ['nullable', 'string', 'max:255'],
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after:start_time'],
            'featured_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $featuredImagePath = null;
        if ($request->hasFile('featured_image')) {
            $featuredImagePath = $request->file('featured_image')->store('events', 'public');
        }

        Event::create([
            'organization_id' => $isSuperadmin
                ? ($data['organization_id'] ?? null)
                : (int) $request->user()->current_organization_id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'location_or_link' => $data['location_or_link'] ?? null,
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'featured_image_path' => $featuredImagePath,
        ]);

        return back()->with('success', 'Program baharu berjaya ditambah.');
    }

    /**
     * rsvp()
     *
     * Accepts status = 'going' | 'maybe' | 'declined'.
     * Uses updateOrCreate so duplicate submissions are idempotent.
     */
    public function rsvp(Request $request, Event $event): RedirectResponse
    {
        if ($request->user()->hasRole(['Superadmin', 'Admin'])) {
            abort(403, 'Akaun pentadbir tidak dibenarkan mendaftar kehadiran program.');
        }

        $validated = $request->validate([
            'status' => ['required', 'in:going,maybe,declined'],
        ]);

        EventRsvp::updateOrCreate(
            ['event_id' => $event->id, 'user_id' => $request->user()->id],
            ['status'   => $validated['status']]
        );

        return back()->with('success', 'RSVP berjaya dikemas kini.');
    }

    // ─── Admin Facing ─────────────────────────────────────────────────────────

    /**
     * showQr()
     *
     * Projector mode — generates an inline SVG QR code server-side via
     * simplesoftwareio/simple-qrcode.  The SVG string is passed as a prop
     * so no client-side QR library is needed.
     *
     * Error-correction level H (30 % recovery) ensures the code remains
     * scannable even if the projector image partially obscures it.
     */
    public function showQr(Event $event): Response|JsonResponse
    {
        $attendanceUrl = $event->attendance_url;

        $qrSvg = QrCode::format('svg')
            ->size(320)
            ->errorCorrection('H')
            ->generate($attendanceUrl);

        $attendedCount = $event->rsvps()->where('status', 'attended')->count();

        // Fast JSON path: polling fetch in ShowQr.vue sends ?count=1 to avoid
        // re-rendering the full page just to refresh the live counter.
        if (request()->boolean('count')) {
            return response()->json(['attended_count' => $attendedCount]);
        }

        return Inertia::render('Events/ShowQr', [
            'event'         => $this->serializeEvent($event->load('rsvps')),
            'qrSvg'         => (string) $qrSvg,
            'attendedCount' => $attendedCount,
            'attendanceUrl' => $attendanceUrl,
        ]);
    }

    /**
     * recordAttendance()
     *
     * The endpoint embedded in the QR code URL.
     * Auth middleware is applied at route level; unauthenticated users are
     * redirected to login and back here via `intended`.
     *
     * Security: hash_equals() prevents timing attacks on token comparison.
     * Idempotent: scanning the same QR twice does NOT create a duplicate record.
     */
    public function recordAttendance(Request $request, int $id, string $token): Response
    {
        $event = Event::with('organization')->findOrFail($id);

        if (! hash_equals($event->attendance_token, $token)) {
            abort(403, 'Token kehadiran tidak sah.');
        }

        $user = $request->user();

        if ($user->hasRole(['Superadmin', 'Admin'])) {
            abort(403, 'Akaun pentadbir tidak dibenarkan merekod kehadiran program.');
        }

        EventRsvp::updateOrCreate(
            ['event_id' => $event->id, 'user_id' => $user->id],
            ['status' => 'attended', 'attended_at' => now()]
        );

        return Inertia::render('Events/AttendanceSuccess', [
            'event' => [
                'id'              => $event->id,
                'title'           => $event->title,
                'location_or_link' => $event->location_or_link,
                'start_formatted' => $event->start_time->locale('ms')->isoFormat('ddd, D MMM YYYY [•] h:mm A'),
                'organization'    => [
                    'name'        => $event->organization?->name ?? 'Semua Organisasi',
                    'color_theme' => $event->organization?->color_theme ?? '#334155',
                ],
            ],
            'memberName' => $user->name,
            'attendedAt' => now()->toISOString(),
        ]);
    }

    /**
     * printAttendance()
     *
     * Returns a bare Blade view (not Inertia) — a print-ready HTML table.
     * Not going through Inertia avoids any JS bundle overhead on print.
     */
    public function printAttendance(Event $event): \Illuminate\Contracts\View\View
    {
        $rsvps = $event->rsvps()
            ->attended()
            ->with('user:id,name,phone,email')
            ->get();

        return view('events.print-attendance', compact('event', 'rsvps'));
    }
}

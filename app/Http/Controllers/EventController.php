<?php

namespace App\Http\Controllers;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventComment;
use App\Models\EventRsvp;
use App\Models\Form;
use App\Models\Organization;
use App\Models\Registration;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class EventController extends Controller
{
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
            'id' => $event->id,
            'title' => $event->title,
            'slug' => $event->slug,
            'description' => $event->description,
            'type' => $event->type,
            'status' => $event->status->value,
            'status_label' => $event->status->label(),
            'category' => $event->category->value,
            'category_label' => $event->category->label(),
            'location_or_link' => $event->location_or_link,
            'start_time' => $event->start_time->toISOString(),
            'start_formatted' => $event->start_time->locale('ms')->isoFormat('ddd, D MMM YYYY [•] h:mm A'),
            'end_time' => $event->end_time->toISOString(),
            'featured_image_url' => $event->featured_image_url,
            'google_calendar_url' => $event->google_calendar_url,
            'organization' => [
                'id' => $event->organization?->id ?? null,
                'name' => $event->organization?->name ?? 'Semua Organisasi',
                'slug' => $event->organization?->slug ?? 'semua',
                'color_theme' => $event->organization?->color_theme ?? '#334155',
            ],
            'organizations' => $event->organizations->map(fn ($o) => [
                'id' => $o->id,
                'name' => $o->name,
                'slug' => $o->slug,
            ])->values(),
            'rsvp_count' => $event->rsvps->whereIn('status', ['going', 'attended'])->count(),
            'my_rsvp' => $myRsvp ? $myRsvp->status : null,
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

        $query = Event::with([
            'organization',
            'organizations',
            'rsvps.user' => fn ($q) => $q->withoutGlobalScopes(),
        ]);

        if ($tab === 'past') {
            $query->where('start_time', '<', now())->orderBy('start_time', 'desc');
        } else {
            $query->where('start_time', '>=', now())->orderBy('start_time', 'asc');
        }

        if (! $user->hasRole('Superadmin')) {
            $query->where(function ($innerQuery) use ($user) {
                $innerQuery->where('organization_id', $user->current_organization_id)
                    ->orWhereNull('organization_id')
                    ->orWhereHas('organizations', fn ($q) => $q->where('organizations.id', $user->current_organization_id));
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
            'statuses' => collect(EventStatus::cases())
                ->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->values(),
            'categories' => collect(EventCategory::cases())
                ->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()])->values(),
        ]);
    }

    /**
     * show()
     *
     * Display a single event with full details, comments, and related events.
     */
    public function show(Request $request, string $slug): Response
    {
        $user = $request->user();

        $event = Event::with([
            'organization',
            'organizations',
            'rsvps.user' => fn ($q) => $q->withoutGlobalScopes(),
        ])
            ->where('slug', $slug)
            ->firstOrFail();

        $eventArr = $this->serializeEvent($event, $user->id);

        // Attendance list for admins
        if ($user->hasRole(['Superadmin', 'Admin'])) {
            $eventArr['attendance'] = $event->rsvps
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

        // Check if user has RSVPed
        $myRsvp = null;
        if ($user) {
            $rsvp = $event->rsvps->firstWhere('user_id', $user->id);
            $myRsvp = $rsvp?->status;
        }

        // Comments
        $comments = EventComment::with('user')
            ->where('event_id', $event->id)
            ->where('is_hidden', false)
            ->latest()
            ->get()
            ->map(function ($comment) {
                return [
                    'id' => $comment->id,
                    'user_name' => $comment->user?->name ?? $comment->anonymous_name ?? 'Ahli',
                    'content' => $comment->content,
                    'created_at' => $comment->created_at->locale('ms')->isoFormat('D MMM YYYY, h:mm A'),
                ];
            });

        // Related events (same organization, upcoming, exclude current)
        $relatedEvents = Event::with('organization')
            ->where('start_time', '>=', now())
            ->where('id', '!=', $event->id)
            ->where(function ($q) use ($event) {
                if ($event->organization_id) {
                    $q->where('organization_id', $event->organization_id)
                        ->orWhereNull('organization_id');
                }
            })
            ->orderBy('start_time')
            ->take(3)
            ->get()
            ->map(fn ($e) => $this->serializeEvent($e, $user?->id));

        // Borang pendaftaran event (aktif) — untuk modul Registration.
        $registrationForms = Form::where('event_id', $event->id)
            ->where('is_active', true)
            ->orderBy('title')
            ->get(['id', 'title', 'description', 'price', 'payment_required', 'share_token'])
            ->map(fn ($f) => [
                'id' => $f->id,
                'title' => $f->title,
                'description' => $f->description,
                'price' => $f->price,
                'payment_required' => $f->payment_required,
                'register_url' => route('events.register', ['event' => $event->slug, 'form' => $f->id]),
                'public_url' => route('events.register.public', $f->share_token),
            ]);

        $myRegistration = $user
            ? Registration::where('event_id', $event->id)
                ->where('user_id', $user->id)
                ->first()
            : null;

        return Inertia::render('Events/Show', [
            'event' => $eventArr,
            'comments' => $comments,
            'relatedEvents' => $relatedEvents,
            'registrationForms' => $registrationForms,
            'myRegistration' => $myRegistration ? [
                'registration_no' => $myRegistration->registration_no,
                'status' => $myRegistration->status->value,
                'status_label' => $myRegistration->status->label(),
            ] : null,
            'organizations' => $user->hasRole('Superadmin')
                ? Organization::query()->orderBy('min_age')->get(['id', 'name', 'slug'])
                : [],
            'statuses' => collect(EventStatus::cases())
                ->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->values(),
            'categories' => collect(EventCategory::cases())
                ->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()])->values(),
        ]);
    }

    /**
     * update()
     *
     * Update an existing event.  Only Admin / Superadmin may call this.
     * Non-superadmins may only edit events that belong to their organisation.
     */
    public function update(Request $request, Event $event): RedirectResponse
    {
        abort_unless($request->user()->hasRole(['Admin', 'Superadmin']), 403);

        $isSuperadmin = $request->user()->hasRole('Superadmin');

        // Non-superadmin: must own the event (owner org ATAU organisasi terlibat).
        if (! $isSuperadmin && ! $this->adminOwnsEvent($request->user(), $event)) {
            abort(403);
        }

        $data = $request->validate([
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:4000'],
            'type' => ['required', 'in:physical,online'],
            // Optional — borang lama (modal Program) tidak hantar field ini.
            'status' => ['sometimes', 'required', 'in:draft,published,closed'],
            'category' => ['sometimes', 'required', 'in:muktamar,ijtimak,seminar,kursus,kem,bengkel,konvensyen,lain'],
            'location_or_link' => ['nullable', 'string', 'max:255'],
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after:start_time'],
            'featured_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'organizations' => ['nullable', 'array'],
            'organizations.*' => ['integer', 'exists:organizations,id'],
        ]);

        // Replace image only if a new one is uploaded
        if ($request->hasFile('featured_image')) {
            // Delete old image
            if ($event->featured_image_path) {
                Storage::disk('public')->delete($event->featured_image_path);
            }
            $data['featured_image_path'] = $request->file('featured_image')->store('events', 'public');
        }
        unset($data['featured_image']);

        $event->update([
            'organization_id' => $isSuperadmin
                ? ($data['organization_id'] ?: null)
                : $event->organization_id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'status' => $data['status'] ?? $event->status->value,
            'category' => $data['category'] ?? $event->category->value,
            'location_or_link' => $data['location_or_link'] ?? null,
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'featured_image_path' => $data['featured_image_path'] ?? $event->featured_image_path,
        ]);

        $this->syncOrganizations($event, $request, $isSuperadmin);

        return redirect()->route('events.show', $event->slug)
            ->with('success', 'Program berjaya dikemas kini.');
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
            'status' => ['sometimes', 'required', 'in:draft,published,closed'],
            'category' => ['sometimes', 'required', 'in:muktamar,ijtimak,seminar,kursus,kem,bengkel,konvensyen,lain'],
            'location_or_link' => ['nullable', 'string', 'max:255'],
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after:start_time'],
            'featured_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'organizations' => ['nullable', 'array'],
            'organizations.*' => ['integer', 'exists:organizations,id'],
        ]);

        $featuredImagePath = null;
        if ($request->hasFile('featured_image')) {
            $featuredImagePath = $request->file('featured_image')->store('events', 'public');
        }

        $event = Event::create([
            'organization_id' => $isSuperadmin
                ? ($data['organization_id'] ?: null)
                : (int) $request->user()->current_organization_id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'status' => $data['status'] ?? 'published',
            'category' => $data['category'] ?? 'lain',
            'location_or_link' => $data['location_or_link'] ?? null,
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'featured_image_path' => $featuredImagePath,
        ]);

        $this->syncOrganizations($event, $request, $isSuperadmin);

        return back()->with('success', 'Program baharu berjaya ditambah.');
    }

    // ─── Admin Event Management ───────────────────────────────────────────────

    /**
     * Senarai event untuk pengurusan admin/superadmin, dengan filter
     * status, kategori, organisasi dan carian.
     */
    public function adminIndex(Request $request): Response
    {
        $user = $request->user();
        $isSuperadmin = $user->hasRole('Superadmin');

        $query = Event::with(['organization', 'organizations', 'activeForms:id,event_id,share_token,is_active'])
            ->withCount('registrations')
            ->orderByDesc('start_time');

        if (! $isSuperadmin) {
            $query->where(function ($q) use ($user) {
                $q->where('organization_id', $user->current_organization_id)
                    ->orWhereHas('organizations', fn ($q2) => $q2->where('organizations.id', $user->current_organization_id));
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")
                ->orWhere('location_or_link', 'like', "%{$search}%"));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('org')) {
            $query->whereHas('organizations', fn ($q) => $q->where('organizations.id', (int) $request->org));
        }

        $events = $query->paginate(15)->withQueryString()->through(function (Event $e) {
            $activeForms = $e->activeForms;

            return [
                'id' => $e->id,
                'title' => $e->title,
                'slug' => $e->slug,
                'type' => $e->type,
                'status' => $e->status->value,
                'status_label' => $e->status->label(),
                'category' => $e->category->value,
                'category_label' => $e->category->label(),
                'start_formatted' => $e->start_time->locale('ms')->isoFormat('ddd, D MMM YYYY [•] h:mm A'),
                'organization_name' => $e->organization?->name ?? 'Semua Organisasi',
                'organizations' => $e->organizations->map(fn ($o) => ['id' => $o->id, 'name' => $o->name])->values(),
                'registrations_count' => $e->registrations_count,
                'featured_image_url' => $e->featured_image_url,
                // Butang "Borang Pendaftaran": 1 borang → buka terus; >1 → halaman urus.
                'form_url' => $activeForms->count() === 1
                    ? route('events.register.public', $activeForms->first()->share_token)
                    : ($activeForms->count() > 1 ? route('admin.events.show', $e->id) : null),
                'forms_count' => $activeForms->count(),
            ];
        });

        return Inertia::render('Admin/Events/Index', [
            'events' => $events,
            'filters' => $request->only(['search', 'status', 'category', 'org']),
            'organizations' => Organization::orderBy('min_age')->get(['id', 'name']),
            'statuses' => collect(EventStatus::cases())
                ->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->values(),
            'categories' => collect(EventCategory::cases())
                ->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()])->values(),
        ]);
    }

    /**
     * Halaman pusat pengurusan event — senarai borang pendaftaran + pautan
     * peserta/kehadiran. Admin boleh cipta borang terus dari sini (tanpa perlu
     * mencari page "Borang" berasingan).
     */
    public function showAdmin(Request $request, Event $event): Response
    {
        $user = $request->user();
        $isSuperadmin = $user->hasRole('Superadmin');

        if (! $isSuperadmin && ! $this->adminOwnsEvent($user, $event)) {
            abort(403);
        }

        $event->load(['organization', 'organizations']);

        $forms = Form::where('event_id', $event->id)
            ->withCount('responses')
            ->with('questions')
            ->orderBy('title')
            ->get()
            ->map(function ($f) {
                $publicUrl = route('events.register.public', $f->share_token);

                return [
                    'id' => $f->id,
                    'title' => $f->title,
                    'description' => $f->description,
                    'price' => $f->price,
                    'payment_required' => $f->payment_required,
                    'is_active' => $f->is_active,
                    'questions_count' => $f->questions->count(),
                    'responses_count' => $f->responses_count,
                    'public_url' => $publicUrl,
                    'qr_svg' => (string) QrCode::format('svg')->size(200)->errorCorrection('M')->generate($publicUrl),
                    'edit_url' => route('admin.forms.edit', $f->id),
                ];
            });

        return Inertia::render('Admin/Events/Show', [
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'slug' => $event->slug,
                'description' => $event->description,
                'type' => $event->type,
                'status' => $event->status->value,
                'status_label' => $event->status->label(),
                'category' => $event->category->value,
                'category_label' => $event->category->label(),
                'start_formatted' => $event->start_time->locale('ms')->isoFormat('ddd, D MMM YYYY [•] h:mm A'),
                'location_or_link' => $event->location_or_link,
                'featured_image_url' => $event->featured_image_url,
                'organization_name' => $event->organization?->name ?? 'Semua Organisasi',
                'organizations' => $event->organizations->map(fn ($o) => $o->name)->values(),
                'registrations_count' => $event->registrations()->count(),
            ],
            'forms' => $forms,
            'buildFormUrl' => route('admin.forms.create', [
                'event_id' => $event->id,
                'back_to' => route('admin.events.show', $event->id),
            ]),
            'registrationsUrl' => route('admin.events.registrations', $event->id),
            'attendanceUrl' => route('admin.attendance', ['event_id' => $event->id]),
            'editUrl' => route('admin.events.edit', $event->id),
            'qrUrl' => route('events.qr', $event->id),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->hasRole(['Admin', 'Superadmin']), 403);

        return $this->eventForm($request, null);
    }

    public function edit(Request $request, Event $event): Response
    {
        abort_unless($request->user()->hasRole(['Admin', 'Superadmin']), 403);

        $isSuperadmin = $request->user()->hasRole('Superadmin');
        if (! $isSuperadmin && ! $this->adminOwnsEvent($request->user(), $event)) {
            abort(403);
        }

        $event->load(['organization', 'organizations']);

        return $this->eventForm($request, $event);
    }

    public function storeAdmin(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole(['Admin', 'Superadmin']), 403);

        $event = $this->persistEvent(null, $request);

        return redirect()->route('admin.events.show', $event->id)
            ->with('success', 'Event baharu berjaya ditambah. Sila tambah borang pendaftaran.');
    }

    public function updateAdmin(Request $request, Event $event): RedirectResponse
    {
        abort_unless($request->user()->hasRole(['Admin', 'Superadmin']), 403);

        $this->persistEvent($event, $request);

        return redirect()->route('admin.events.show', $event->id)
            ->with('success', 'Event berjaya dikemas kini.');
    }

    private function eventForm(Request $request, ?Event $event): Response
    {
        $isSuperadmin = $request->user()->hasRole('Superadmin');

        return Inertia::render('Admin/Events/Form', [
            'event' => $event ? [
                'id' => $event->id,
                'title' => $event->title,
                'description' => $event->description,
                'type' => $event->type,
                'status' => $event->status->value,
                'category' => $event->category->value,
                'location_or_link' => $event->location_or_link,
                'start_time' => $event->start_time->format('Y-m-d\TH:i'),
                'end_time' => $event->end_time->format('Y-m-d\TH:i'),
                'organization_id' => $event->organization_id,
                'organization_ids' => $event->organizations->pluck('id')->all(),
                'featured_image_url' => $event->featured_image_url,
            ] : null,
            'organizations' => Organization::orderBy('min_age')->get(['id', 'name']),
            'isSuperadmin' => $isSuperadmin,
            'statuses' => collect(EventStatus::cases())
                ->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->values(),
            'categories' => collect(EventCategory::cases())
                ->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()])->values(),
        ]);
    }

    /**
     * Validasi + simpan event (baru atau sedia ada) dan sync organisasi terlibat.
     */
    private function persistEvent(?Event $event, Request $request): Event
    {
        $isSuperadmin = $request->user()->hasRole('Superadmin');

        $data = $request->validate([
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:4000'],
            'type' => ['required', 'in:physical,online'],
            'status' => ['sometimes', 'required', 'in:draft,published,closed'],
            'category' => ['sometimes', 'required', 'in:muktamar,ijtimak,seminar,kursus,kem,bengkel,konvensyen,lain'],
            'location_or_link' => ['nullable', 'string', 'max:255'],
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after:start_time'],
            'featured_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'organizations' => ['nullable', 'array'],
            'organizations.*' => ['integer', 'exists:organizations,id'],
        ]);

        if ($request->hasFile('featured_image')) {
            if ($event?->featured_image_path) {
                Storage::disk('public')->delete($event->featured_image_path);
            }
            $data['featured_image_path'] = $request->file('featured_image')->store('events', 'public');
        }
        unset($data['featured_image']);

        $fields = [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'status' => $data['status'] ?? $event?->status?->value ?? 'published',
            'category' => $data['category'] ?? $event?->category?->value ?? 'lain',
            'location_or_link' => $data['location_or_link'] ?? null,
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'featured_image_path' => $data['featured_image_path'] ?? $event?->featured_image_path,
        ];

        if ($isSuperadmin) {
            $fields['organization_id'] = ($data['organization_id'] ?? null) ?: null;
        } elseif ($event === null) {
            $fields['organization_id'] = (int) $request->user()->current_organization_id;
        }

        $event = $event ? tap($event)->update($fields) : Event::create($fields);

        $this->syncOrganizations($event, $request, $isSuperadmin);

        return $event;
    }

    private function adminOwnsEvent($user, Event $event): bool
    {
        $ownOrg = (int) $user->current_organization_id;

        if ((int) $event->organization_id === $ownOrg) {
            return true;
        }

        return $event->organizations()->where('organizations.id', $ownOrg)->exists();
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
            ['status' => $validated['status']]
        );

        return back()->with('success', 'RSVP berjaya dikemas kini.');
    }

    /**
     * storeComment()
     */
    public function storeComment(Request $request, Event $event): RedirectResponse
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:2000'],
            'anonymous_name' => ['nullable', 'string', 'max:100'],
        ]);

        EventComment::create([
            'event_id' => $event->id,
            'user_id' => $request->user()?->id,
            'anonymous_name' => $data['anonymous_name'] ?? null,
            'content' => $data['content'],
        ]);

        return back()->with('success', 'Komen berjaya dihantar.');
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
            'event' => $this->serializeEvent($event->load('rsvps')),
            'qrSvg' => (string) $qrSvg,
            'attendedCount' => $attendedCount,
            'attendanceUrl' => $attendanceUrl,
        ]);
    }

    /**
     * downloadQr()
     *
     * Returns the attendance QR code as a downloadable PNG image.
     * Prefers the Imagick backend when available, otherwise falls back to a
     * pure-GD renderer so PNG export works even without Imagick installed.
     */
    public function downloadQr(Event $event): \Symfony\Component\HttpFoundation\Response
    {
        $png = extension_loaded('imagick')
            ? QrCode::format('png')
                ->size(1024)
                ->margin(2)
                ->errorCorrection('H')
                ->generate($event->attendance_url)
            : $this->qrPngGd($event->attendance_url, 1024, 4, 'H');

        $filename = 'qr-'.Str::slug($event->title).'-'.$event->id.'.png';

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * qrPngGd()
     *
     * Renders a QR code to PNG using only the GD extension (no Imagick).
     * Reads the module matrix from BaconQrCode and draws it with GD primitives.
     */
    private function qrPngGd(string $text, int $size = 1024, int $margin = 4, string $errorCorrection = 'H'): string
    {
        $ecLevel = match (strtoupper($errorCorrection)) {
            'L' => ErrorCorrectionLevel::L(),
            'M' => ErrorCorrectionLevel::M(),
            'Q' => ErrorCorrectionLevel::Q(),
            default => ErrorCorrectionLevel::H(),
        };

        $matrix = Encoder::encode($text, $ecLevel)->getMatrix();
        $modules = $matrix->getWidth();
        $total = $modules + ($margin * 2);

        $scale = (int) floor($size / $total);
        if ($scale < 1) {
            $scale = 1;
        }
        $pixelSize = $total * $scale;

        $image = imagecreatetruecolor($pixelSize, $pixelSize);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefilledrectangle($image, 0, 0, $pixelSize, $pixelSize, $white);

        for ($y = 0; $y < $modules; $y++) {
            for ($x = 0; $x < $modules; $x++) {
                if ($matrix->get($x, $y)) {
                    imagefilledrectangle(
                        $image,
                        ($x + $margin) * $scale,
                        ($y + $margin) * $scale,
                        (($x + $margin) * $scale) + $scale - 1,
                        (($y + $margin) * $scale) + $scale - 1,
                        $black
                    );
                }
            }
        }

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }

    /**
     * syncOrganizations()
     *
     * Synchronise the "organisasi terlibat" pivot. Superadmin boleh menetapkan
     * sebarang gabungan organisasi; org admin hanya boleh senaraikan organisasi
     * sendiri. Default: owner organisasi sahaja.
     */
    private function syncOrganizations(Event $event, Request $request, bool $isSuperadmin): void
    {
        // Terima `organizations` (semasa) ATAU `organization_ids` (borang lama).
        $orgIds = collect($request->input('organizations', $request->input('organization_ids', [])))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if (! $isSuperadmin) {
            $ownOrg = (int) $request->user()->current_organization_id;
            $orgIds = $orgIds->filter(fn ($id) => $id === $ownOrg);
        }

        if ($orgIds->isEmpty() && $event->organization_id) {
            $orgIds = collect([$event->organization_id]);
        }

        $event->organizations()->sync($orgIds);
    }

    /**
     * printAttendance()
     *
     * Returns a bare Blade view (not Inertia) — a print-ready HTML table.
     * Not going through Inertia avoids any JS bundle overhead on print.
     */
    public function printAttendance(Event $event): View
    {
        $rsvps = $event->rsvps()
            ->attended()
            ->with(['user:id,name,phone,email' => fn ($q) => $q->withoutGlobalScopes()])
            ->get();

        return view('events.print-attendance', compact('event', 'rsvps'));
    }

    /**
     * destroy()
     *
     * Soft-deletes an event.  Only the owning admin/superadmin can delete.
     */
    public function destroy(Request $request, Event $event): RedirectResponse
    {
        $user = $request->user();

        abort_unless(
            $user->hasRole('Superadmin') || $this->adminOwnsEvent($user, $event),
            403
        );

        $event->delete();

        // Soft-delete borang pendaftaran event supaya tidak muncul lagi
        // dalam senarai borang & pautan public berhenti berfungsi.
        Form::where('event_id', $event->id)->delete();

        return redirect()->route('admin.events.index')->with('success', 'Program berjaya dipadam.');
    }
}

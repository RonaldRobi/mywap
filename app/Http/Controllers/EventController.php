<?php

namespace App\Http\Controllers;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Models\AppSetting;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Form;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\Scopes\OrganizationScope;
use App\Services\RegistrationDedupeService;
use App\Support\QrPng;
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
            'maps_url' => $event->maps_url,
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
            'registration_open' => $event->registrationIsOpen(),
            'registration_closes_at' => $event->registrationClosesAt()?->toIso8601String(),
            'attendance_open' => $event->attendanceWindowIsOpen(),
            'attendance_opens_at' => $event->attendanceOpensAt()?->toIso8601String(),
            'attendance_closes_at' => $event->attendanceClosesAt()?->toIso8601String(),
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
        $isAdmin = $user && $user->hasRole(['Superadmin', 'Admin', 'org-admin', 'Admin Cawangan']);
        $isSuperadmin = $user && $user->hasRole('Superadmin');

        $tab = $request->input('tab', 'upcoming');
        $search = $request->input('search');
        $typeFilter = $request->input('type');
        $orgFilter = $request->input('org');

        $query = Event::with([
            'organization',
            'organizations',
            'rsvps.user' => fn ($q) => $q->withoutGlobalScope(OrganizationScope::class),
        ]);

        if ($tab === 'past') {
            $query->where('start_time', '<', now())->orderBy('start_time', 'desc');
        } else {
            $query->where('start_time', '>=', now())->orderBy('start_time', 'asc');
        }

        // Tetamu & ahli biasa: hanya program 'published'. Draft/closed untuk admin sahaja.
        if (! $isAdmin) {
            $query->where('status', EventStatus::Published->value);
        }

        // Scoping organisasi: superadmin & tetamu lihat semua; ahli terhad kepada org sendiri.
        if ($user && ! $isSuperadmin) {
            $query->where(function ($innerQuery) use ($user) {
                $innerQuery->where('organization_id', $user->current_organization_id)
                    ->orWhereNull('organization_id')
                    ->orWhereHas('organizations', fn ($q) => $q->where('organizations.id', $user->current_organization_id));
            });
        }

        if (! $isSuperadmin && ! ($user?->isOrgAdmin() ?? false)) {
            $query->where(function ($q) use ($user) {
                $q->where('visibility', EventVisibility::Open->value)
                    ->orWhere(function ($q2) use ($user) {
                        $q2->where('visibility', EventVisibility::BranchOnly->value);

                        if ($user) {
                            $q2->where('branch_id', $user->branch_id);
                        } else {
                            $q2->whereRaw('1 = 0');
                        }
                    });
            });
        }

        // Penapis organisasi (query param ?org=) — untuk tetamu & superadmin.
        if ($orgFilter) {
            $query->where(function ($innerQuery) use ($orgFilter) {
                $innerQuery->where('organization_id', (int) $orgFilter)
                    ->orWhereNull('organization_id')
                    ->orWhereHas('organizations', fn ($q) => $q->where('organizations.id', (int) $orgFilter));
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
            function (Event $e) use ($user, $isAdmin) {
                $eventArr = $this->serializeEvent($e, $user?->id);
                // For admin/superadmin, include attendance list for modal
                if ($isAdmin) {
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
        if ($user && $user->hasRole('Member')) {
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
                'org' => $orgFilter,
            ],
            // Tetamu & superadmin boleh menapis mengikut organisasi; ahli kekal scoping sendiri.
            'organizations' => ($isSuperadmin || ! $user)
                ? Organization::query()->orderBy('min_age')->get(['id', 'name', 'slug'])
                : [],
            'attendedEvents' => $attendedEvents,
            'statuses' => $this->statusOptions(),
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
        $isAdmin = $user && $user->hasRole(['Superadmin', 'Admin', 'org-admin', 'Admin Cawangan']);

        $event = Event::with([
            'organization',
            'organizations',
            'rsvps.user' => fn ($q) => $q->withoutGlobalScope(OrganizationScope::class),
        ])
            ->where('slug', $slug)
            ->firstOrFail();

        if ($event->isBranchOnly() && ! $user?->hasRole(['Superadmin', 'Admin', 'org-admin'])) {
            $allowed = $user && (
                ($user->isBranchAdmin() && $user->canManageBranch($event->branch_id))
                || (int) $user->branch_id === (int) $event->branch_id
            );

            abort_unless($allowed, 404);
        }

        // Tetamu & ahli biasa tidak boleh lihat draft/closed event.
        if (! $isAdmin && ! $event->isPublished()) {
            abort(404);
        }

        $eventArr = $this->serializeEvent($event, $user?->id);

        // Attendance list for admins
        if ($isAdmin) {
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
            ->when(! $isAdmin, fn ($q) => $q->where('status', EventStatus::Published->value))
            ->when(! ($user?->isSuperadmin() ?? false) && ! ($user?->isOrgAdmin() ?? false), function ($q) use ($user) {
                $q->where(function ($inner) use ($user) {
                    $inner->where('visibility', EventVisibility::Open->value)
                        ->orWhere(function ($q2) use ($user) {
                            $q2->where('visibility', EventVisibility::BranchOnly->value);

                            if ($user) {
                                $q2->where('branch_id', $user->branch_id);
                            } else {
                                $q2->whereRaw('1 = 0');
                            }
                        });
                });
            })
            ->orderBy('start_time')
            ->take(3)
            ->get()
            ->map(fn ($e) => $this->serializeEvent($e, $user?->id));

        // Borang pendaftaran event (aktif) — untuk modul Registration.
        $activeForms = Form::where('event_id', $event->id)
            ->where('is_active', true)
            ->orderBy('title')
            ->get(['id', 'title', 'description', 'price', 'payment_required', 'share_token']);

        $registrationForms = $activeForms->map(fn ($f) => [
            'id' => $f->id,
            'title' => $f->title,
            'description' => $f->description,
            'price' => $f->price,
            'payment_required' => $f->payment_required,
            'register_url' => route('events.register', ['event' => $event->slug, 'form' => $f->id]),
            'public_url' => route('events.register.public', $f->share_token),
        ]);

        // Pautan promosi: 1 borang → terus ke pendaftaran awam; jika tidak → overview.
        $promoUrl = $activeForms->count() === 1
            ? route('events.register.public', $activeForms->first()->share_token, true)
            : route('events.show', $event->slug, true);

        $promoQrSvg = (string) QrCode::format('svg')
            ->size(220)
            ->errorCorrection('M')
            ->generate($promoUrl);

        $myRegistration = $user
            ? Registration::where('event_id', $event->id)
                ->where('user_id', $user->id)
                ->first()
            : null;

        return Inertia::render('Events/Show', [
            'event' => $eventArr,
            'relatedEvents' => $relatedEvents,
            'registrationForms' => $registrationForms,
            'promoUrl' => $promoUrl,
            'promoQrSvg' => $promoQrSvg,
            'myRegistration' => $myRegistration ? [
                'registration_no' => $myRegistration->registration_no,
                'status' => $myRegistration->status->value,
                'status_label' => $myRegistration->status->label(),
            ] : null,
            'organizations' => $user && $user->hasRole('Superadmin')
                ? Organization::query()->orderBy('min_age')->get(['id', 'name', 'slug'])
                : [],
            'statuses' => $this->statusOptions(),
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
        abort_unless($request->user()->hasRole(['Admin', 'Superadmin', 'org-admin']), 403);

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
            'status' => ['sometimes', 'required', 'in:draft,published'],
            'category' => ['sometimes', 'required', 'in:muktamar,ijtimak,seminar,kursus,kem,bengkel,konvensyen,lain'],
            'location_or_link' => ['nullable', 'string', 'max:255'],
            'maps_url' => ['nullable', 'string', 'max:500'],
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
        abort_unless($request->user()->hasRole(['Admin', 'Superadmin', 'org-admin']), 403);

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
            'status' => ['sometimes', 'required', 'in:draft,published'],
            'category' => ['sometimes', 'required', 'in:muktamar,ijtimak,seminar,kursus,kem,bengkel,konvensyen,lain'],
            'location_or_link' => ['nullable', 'string', 'max:255'],
            'maps_url' => ['nullable', 'string', 'max:500'],
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
        $now = now();

        // Asingkan program yang belum tamat daripada yang telah berlalu supaya
        // admin tidak terpandang program lama apabila membuka halaman.
        $tab = $request->input('tab', 'upcoming');
        if (! in_array($tab, ['upcoming', 'past', 'all'], true)) {
            $tab = 'upcoming';
        }

        $base = Event::query();

        if ($user->isBranchAdmin()) {
            $base->whereIn('branch_id', $user->managedBranchIds());
        } elseif (! $isSuperadmin) {            $base->where(function ($q) use ($user) {
                $q->where('organization_id', $user->current_organization_id)
                    ->orWhereHas('organizations', fn ($q2) => $q2->where('organizations.id', $user->current_organization_id));
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $base->where(fn ($q) => $q->where('title', 'like', "%{$search}%")
                ->orWhere('location_or_link', 'like', "%{$search}%"));
        }

        if ($request->filled('status')) {
            $base->where('status', $request->status);
        }

        if ($request->filled('category')) {
            $base->where('category', $request->category);
        }

        if ($request->filled('org')) {
            $base->whereHas('organizations', fn ($q) => $q->where('organizations.id', (int) $request->org));
        }

        $counts = [
            'upcoming' => (clone $base)->where('end_time', '>=', $now)->count(),
            'past' => (clone $base)->where('end_time', '<', $now)->count(),
        ];

        $query = (clone $base)
            ->with(['organization', 'organizations', 'activeForms:id,event_id,share_token,is_active'])
            ->withCount([
                'registrations',
                'registrations as attended_count' => fn ($q) => $q->whereHas('attendance'),
            ]);

        if ($tab === 'past') {
            // Program lepas: yang paling baru dahulu.
            $query->where('end_time', '<', $now)->orderByDesc('start_time');
        } elseif ($tab === 'upcoming') {
            // Program akan datang: yang paling hampir dahulu.
            $query->where('end_time', '>=', $now)->orderBy('start_time');
        } else {
            $query->orderByRaw('CASE WHEN end_time >= ? THEN 0 ELSE 1 END', [$now])
                ->orderByRaw('CASE WHEN end_time >= ? THEN start_time END ASC', [$now])
                ->orderByRaw('CASE WHEN end_time < ? THEN start_time END DESC', [$now]);
        }

        $dedupe = app(RegistrationDedupeService::class);

        $events = $query->paginate(15)->withQueryString()->through(function (Event $e) use ($dedupe, $now) {
            $activeForms = $e->activeForms;
            $uniqueParticipants = $dedupe->summary($e)['unique_identities'];

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
                'end_time' => $e->end_time?->toISOString(),
                'is_ended' => $e->end_time !== null && $e->end_time->lt($now),
                'organization_name' => $e->organization?->name ?? 'Semua Organisasi',
                'organizations' => $e->organizations->map(fn ($o) => ['id' => $o->id, 'name' => $o->name])->values(),
                'registrations_count' => $e->registrations_count,
                'unique_participants_count' => $uniqueParticipants,
                'attended_count' => (int) $e->attended_count,
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
            'tab' => $tab,
            'counts' => $counts,
            'filters' => $request->only(['search', 'status', 'category', 'org']),
            'organizations' => Organization::orderBy('min_age')->get(['id', 'name']),
            'statuses' => $this->statusOptions(),
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

        // Kira peserta unik (buang pendua import/borang) dan kehadiran sebenar.
        // `registrations_count` kekal sebagai jumlah rekod mentah.
        $registrationSummary = app(RegistrationDedupeService::class)->summary($event);
        $attendedCount = $event->registrations()->whereHas('attendance')->count();

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
                    'price_tiers' => $f->tiers(),
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
                'registrations_count' => $registrationSummary['total_rows'],
                'unique_participants_count' => $registrationSummary['unique_identities'],
                'attended_count' => $attendedCount,
                'registration_open' => $event->registrationIsOpen(),
                'registration_closes_at' => $event->registrationClosesAt()?->toIso8601String(),
                'attendance_open' => $event->attendanceWindowIsOpen(),
                'attendance_opens_at' => $event->attendanceOpensAt()?->toIso8601String(),
                'attendance_closes_at' => $event->attendanceClosesAt()?->toIso8601String(),
            ],
            'forms' => $forms,
            'isBranchAdmin' => $user->isBranchAdmin(),
            'buildFormUrl' => route('admin.forms.create', [
                'event_id' => $event->id,
                'back_to' => route('admin.events.show', $event->id),
            ]),
            'registrationsUrl' => route('admin.events.registrations', $event->id),
            'attendanceUrl' => route('admin.attendance.show', $event->id),
            'editUrl' => route('admin.events.edit', $event->id),
            'qrUrl' => $this->attendanceQrExpired($event) ? null : route('events.qr', $event->id),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->hasRole(['Admin', 'Superadmin', 'org-admin', 'Admin Cawangan']), 403);

        return $this->eventForm($request, null);
    }

    public function edit(Request $request, Event $event): Response
    {
        abort_unless($request->user()->hasRole(['Admin', 'Superadmin', 'org-admin', 'Admin Cawangan']), 403);

        $isSuperadmin = $request->user()->hasRole('Superadmin');
        if (! $isSuperadmin && ! $this->adminOwnsEvent($request->user(), $event)) {
            abort(403);
        }

        $event->load(['organization', 'organizations']);

        return $this->eventForm($request, $event);
    }

    public function storeAdmin(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole(['Admin', 'Superadmin', 'org-admin', 'Admin Cawangan']), 403);

        $event = $this->persistEvent(null, $request);

        return redirect()->route('admin.events.show', $event->id)
            ->with('success', 'Program & acara baharu berjaya ditambah. Sila tambah borang pendaftaran.');
    }

    public function updateAdmin(Request $request, Event $event): RedirectResponse
    {
        abort_unless($request->user()->hasRole(['Admin', 'Superadmin', 'org-admin', 'Admin Cawangan']), 403);

        $this->persistEvent($event, $request);

        return redirect()->route('admin.events.show', $event->id)
            ->with('success', 'Program & acara berjaya dikemas kini.');
    }

    /**
     * Pilihan status yang boleh dipilih oleh admin. `closed` dikekalkan dalam
     * enum hanya untuk rekod lama; program baharu hanya Draf atau Diterbitkan
     * (untuk menamatkan program, admin boleh padam).
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return collect(EventStatus::cases())
            ->reject(fn (EventStatus $status) => $status === EventStatus::Closed)
            ->map(fn (EventStatus $status) => ['value' => $status->value, 'label' => $status->label()])
            ->values()
            ->all();
    }

    private function eventForm(Request $request, ?Event $event): Response
    {
        $user = $request->user();
        $isSuperadmin = $user->hasRole('Superadmin');
        $isBranchAdmin = $user->isBranchAdmin();
        $branchId = $isBranchAdmin
            ? ($event && $user->canManageBranch($event->branch_id)
                ? (int) $event->branch_id
                : $user->managedBranchId())
            : null;

        return Inertia::render('Admin/Events/Form', [
            'event' => $event ? [
                'id' => $event->id,
                'title' => $event->title,
                'description' => $event->description,
                'type' => $event->type,
                // Status `closed` lama dipaparkan sebagai Draf supaya admin
                // boleh pilih semula Draf/Diterbitkan tanpa ralat.
                'status' => $event->status === EventStatus::Closed
                    ? EventStatus::Draft->value
                    : $event->status->value,
                'category' => $event->category->value,
                'location_or_link' => $event->location_or_link,
                'maps_url' => $event->maps_url,
                'start_time' => $event->start_time->format('Y-m-d\TH:i'),
                'end_time' => $event->end_time->format('Y-m-d\TH:i'),
                'organization_id' => $event->organization_id,
                'organization_ids' => $event->organizations->pluck('id')->all(),
                'branch_id' => $event->branch_id,
                'visibility' => $event->visibility?->value ?? EventVisibility::Open->value,
                'featured_image_url' => $event->featured_image_url,
            ] : null,
            'organizations' => $isSuperadmin
                ? Organization::orderBy('min_age')->get(['id', 'name'])
                : Organization::whereKey($user->current_organization_id)->get(['id', 'name']),
            'isSuperadmin' => $isSuperadmin,
            'isBranchAdmin' => $isBranchAdmin,
            'branch' => $branchId ? \App\Models\Branch::find($branchId, ['id', 'name', 'state']) : null,
            'managedBranches' => $isBranchAdmin
                ? \App\Models\Branch::whereKey($user->managedBranchIds())->orderBy('name')->get(['id', 'name', 'state'])
                : [],
            'visibilities' => collect(EventVisibility::cases())
                ->map(fn ($v) => ['value' => $v->value, 'label' => $v->label()])->values(),
            'formsCount' => $event ? $event->forms()->count() : 0,
            'statuses' => $this->statusOptions(),
            'categories' => collect(EventCategory::cases())
                ->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()])->values(),
        ]);
    }

    /**
     * Validasi + simpan event (baru atau sedia ada) dan sync organisasi terlibat.
     */
    private function persistEvent(?Event $event, Request $request): Event
    {
        $user = $request->user();
        $isSuperadmin = $user->hasRole('Superadmin');
        $isBranchAdmin = $user->isBranchAdmin();

        $data = $request->validate([
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:4000'],
            'type' => ['required', 'in:physical,online'],
            'status' => ['sometimes', 'required', 'in:draft,published'],
            'category' => ['sometimes', 'required', 'in:muktamar,ijtimak,seminar,kursus,kem,bengkel,konvensyen,lain'],
            'visibility' => ['sometimes', 'required', 'in:branch_only,open'],
            'location_or_link' => ['nullable', 'string', 'max:255'],
            'maps_url' => ['nullable', 'string', 'max:500'],
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
            'visibility' => $data['visibility'] ?? $event?->visibility?->value ?? EventVisibility::Open->value,
            'location_or_link' => $data['location_or_link'] ?? null,
            'maps_url' => $data['maps_url'] ?? $event?->maps_url,
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'featured_image_path' => $data['featured_image_path'] ?? $event?->featured_image_path,
        ];

        if ($isBranchAdmin) {
            $fields['organization_id'] = (int) $user->current_organization_id;
            $fields['branch_id'] = $event && $user->canManageBranch($event->branch_id)
                ? (int) $event->branch_id
                : $user->resolveManagedBranchId($request->integer('branch_id') ?: null);
            $fields['visibility'] = $data['visibility'] ?? $event?->visibility?->value ?? EventVisibility::BranchOnly->value;
        } elseif ($isSuperadmin) {
            $fields['organization_id'] = ($data['organization_id'] ?? null) ?: null;
        } elseif ($event === null) {
            $fields['organization_id'] = (int) $user->current_organization_id;
        }

        $event = $event ? tap($event)->update($fields) : Event::create($fields);

        $this->syncOrganizations($event, $request, $isSuperadmin);

        return $event;
    }

    private function adminOwnsEvent($user, Event $event): bool
    {
        if ($user->isBranchAdmin()) {
            return $user->canManageBranch($event->branch_id);
        }

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
        if ($request->user()->hasRole(['Superadmin', 'Admin', 'org-admin', 'Admin Cawangan'])) {
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
    public function showQr(Request $request, Event $event): Response|JsonResponse
    {
        $this->authorizeEventAdmin($request, $event);
        $this->abortIfAttendanceQrExpired($event);

        // QR paparan langsung ditandatangan dan tamat bersama tetingkap kehadiran
        // (waktu tamat program + 24 jam). Poster bercetak kekal guna token statik
        // tetapi masih disemak tetingkapnya di backend.
        $attendanceUrl = $event->signedAttendanceUrl();

        $qrSvg = QrCode::format('svg')
            ->size(320)
            ->errorCorrection('H')
            ->generate($attendanceUrl);

        // Kehadiran sebenar disimpan dalam jadual `attendances` (bukan RSVP),
        // jadi kiraan & senarai langsung mesti ambil dari situ.
        $attendedCount = Attendance::where('event_id', $event->id)->count();

        // Fast JSON path for the projector's live panel.
        if ($request->boolean('count') || $request->boolean('live')) {
            return response()->json([
                'attended_count' => $attendedCount,
                'recent' => $this->recentAttendees($event),
            ]);
        }

        return Inertia::render('Events/ShowQr', [
            'event' => $this->serializeEvent($event->load('rsvps')),
            'qrSvg' => (string) $qrSvg,
            'attendedCount' => $attendedCount,
            'attendanceUrl' => $attendanceUrl,
            'recentAttendees' => $this->recentAttendees($event),
        ]);
    }

    /**
     * Peserta terkini yang telah selesai semak masuk, untuk panel langsung pada
     * skrin projektor. Dihantar bersama kiraan dalam endpoint `?live=1`.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recentAttendees(Event $event, int $limit = 15): array
    {
        return Attendance::query()
            ->where('event_id', $event->id)
            ->with(['registration:id,name,member_no,registration_no'])
            ->orderByDesc('attended_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (Attendance $attendance) => [
                'id' => $attendance->id,
                'name' => $attendance->registration?->name ?? 'Peserta',
                'member_no' => $attendance->registration?->member_no,
                'registration_no' => $attendance->registration?->registration_no,
                'method' => $attendance->method,
                'attended_at' => $attendance->attended_at?->toISOString(),
                'attended_at_label' => $attendance->attended_at?->timezone(config('app.timezone'))->format('h:i A'),
            ])
            ->values()
            ->all();
    }

    /**
     * downloadQr()
     *
     * Returns the attendance QR code as a downloadable PNG image.
     */
    public function downloadQr(Request $request, Event $event): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorizeEventAdmin($request, $event);
        $this->abortIfAttendanceQrExpired($event);

        // Transparent by default so designers can drop the PNG onto any
        // artwork. Add ?white=1 for a white-backed copy.
        $transparent = ! $request->boolean('white');

        $png = QrPng::render($event->attendance_url, 1024, 4, 'H', $transparent);

        $filename = 'qr-'.Str::slug($event->title).'-'.$event->id.($transparent ? '-transparent' : '').'.png';

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * printQr()
     *
     * Print-ready A4 sheets for the attendance QR. Two layouts:
     *   - qr     → QR only, maximum scan size
     *   - poster → A4 sheet containing a full 4:5 programme poster area with
     *              the myWAP wordmark, organisation logo(s), featured image,
     *              event details and the QR at the bottom
     *
     * Rendered as a bare Blade view so the print output has no app shell or
     * JavaScript bundle attached.
     */
    public function printQr(Request $request, Event $event): View
    {
        $this->authorizeEventAdmin($request, $event);
        $this->abortIfAttendanceQrExpired($event);

        $layout = (string) $request->query('layout', 'poster');
        abort_unless(in_array($layout, ['qr', 'poster'], true), 404);

        $event->load('organization');

        $qrSvg = (string) QrCode::format('svg')
            ->size(420)
            ->margin(1)
            ->errorCorrection('H')
            ->generate($event->attendance_url);

        // A global event belongs to all three movements, so show all three
        // logos instead of falling back to a single initial.
        $brands = $event->organization
            ? collect([$event->organization])
            : Organization::query()
                ->whereIn('slug', ['pkpim', 'abim', 'wadah'])
                ->orderBy('min_age')
                ->get();

        return view('events.print-qr', [
            'event' => $event,
            'layout' => $layout,
            'qrSvg' => $qrSvg,
            'featuredImage' => $this->storageImageDataUri($event->featured_image_path),
            'organizationBrands' => $brands->map(fn (Organization $organization) => [
                'name' => $organization->name,
                'logo' => $this->storageImageDataUri($organization->logo_path),
            ])->values(),
            'systemLogo' => $this->storageImageDataUri(
                AppSetting::query()->value('system_logo_path')
            ),
        ]);
    }

    private function authorizeEventAdmin(Request $request, Event $event): void
    {
        $user = $request->user();

        abort_unless(
            $user->hasRole('Superadmin') || $this->adminOwnsEvent($user, $event),
            403
        );
    }

    /**
     * Program yang sudah tamat melebihi 24 jam tidak lagi membenarkan QR
     * kehadiran dijana/dimuat turun/dicetak.
     */
    private function attendanceQrExpired(Event $event): bool
    {
        $closesAt = $event->attendanceClosesAt();

        return $closesAt !== null && now()->gt($closesAt);
    }

    private function abortIfAttendanceQrExpired(Event $event): void
    {
        if ($this->attendanceQrExpired($event)) {
            abort(403, 'QR kehadiran telah tamat tempoh untuk program ini.');
        }
    }

    /**
     * Embed a public-disk image as a data URI so the print sheet does not
     * depend on an authenticated asset request or a network round-trip.
     */
    private function storageImageDataUri(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $path = parse_url($path, PHP_URL_PATH) ?: $path;
        $path = ltrim((string) $path, '/');

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, 8);
        }

        $disk = Storage::disk('public');

        if ($path === '' || ! $disk->exists($path)) {
            return null;
        }

        $absolute = $disk->path($path);
        $mime = mime_content_type($absolute) ?: 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($absolute));
    }

    /**
     * downloadShareQr()
     *
     * Muat turun QR promosi program (encode pautan pendaftaran / overview),
     * berbeza daripada QR kehadiran. Untuk poster & bahan promosi.
     */
    public function downloadShareQr(Event $event): \Symfony\Component\HttpFoundation\Response
    {
        $activeForms = $event->activeForms()->get(['id', 'share_token']);

        $promoUrl = $activeForms->count() === 1
            ? route('events.register.public', $activeForms->first()->share_token, true)
            : route('events.show', $event->slug, true);

        $png = QrPng::render($promoUrl, 1024, 4, 'H');

        $filename = 'qr-promosi-'.$event->slug.'.png';

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
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
            ->with(['user:id,name,phone,email' => fn ($q) => $q->withoutGlobalScope(OrganizationScope::class)])
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

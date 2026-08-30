<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventComment;
use App\Models\EventRsvp;
use App\Models\Form;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * EventService
 *
 * Logik tunggal untuk domain Event — dikongsi oleh WebController (Inertia)
 * dan ApiController (JSON) supaya web & Flutter tidak drift.
 * Rujuk docs/FLUTTER_PLAN.md §4.
 */
class EventService
{
    /**
     * Serialize satu Event kepada bentuk konsisten untuk web & API.
     */
    public function serialize(Event $event, ?int $authUserId = null): array
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

    /**
     * Senarai event (upcoming/past) mengikut skop organisasi user.
     * Item paginator sudah diserialize.
     */
    public function list(Request $request, User $user): LengthAwarePaginator
    {
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

        $perPage = self::perPage($request);

        return $query->paginate($perPage)->withQueryString()->through(
            function (Event $e) use ($user) {
                $eventArr = $this->serialize($e, $user->id);

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
    }

    /**
     * Payload penuh untuk halaman/endpoint show event.
     */
    public function showDetail(Event $event, ?User $user = null): array
    {
        $event->loadMissing(['organization', 'organizations', 'rsvps.user' => fn ($q) => $q->withoutGlobalScopes()]);

        $eventArr = $this->serialize($event, $user?->id);

        if ($user && $user->hasRole(['Superadmin', 'Admin'])) {
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

        $relatedEvents = Event::with(['organization', 'organizations', 'rsvps'])
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
            ->map(fn ($e) => $this->serialize($e, $user?->id));

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

        return [
            'event' => $eventArr,
            'comments' => $comments,
            'relatedEvents' => $relatedEvents,
            'registrationForms' => $registrationForms,
            'myRegistration' => $myRegistration ? [
                'registration_no' => $myRegistration->registration_no,
                'status' => $myRegistration->status->value,
                'status_label' => $myRegistration->status->label(),
            ] : null,
        ];
    }

    /**
     * Kemas kini RSVP user terhadap satu event. Pentadbir dilarang.
     */
    public function rsvp(User $user, Event $event, string $status): EventRsvp
    {
        if ($user->hasRole(['Superadmin', 'Admin'])) {
            abort(403, 'Akaun pentadbir tidak dibenarkan mendaftar kehadiran program.');
        }

        return EventRsvp::updateOrCreate(
            ['event_id' => $event->id, 'user_id' => $user->id],
            ['status' => $status]
        );
    }

    /**
     * Senarai event yang telah dihadiri oleh ahli.
     */
    public function attendedEvents(User $user): array
    {
        return EventRsvp::where('user_id', $user->id)
            ->where('status', 'attended')
            ->with(['event.organization'])
            ->orderByDesc('attended_at')
            ->get()
            ->map(function ($rsvp) {
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
            })
            ->all();
    }

    /**
     * Pastikan per_page hanya nilai yang dibenarkan (25/50/100 default 12).
     */
    public static function perPage(Request $request, int $default = 12): int
    {
        $perPage = (int) $request->input('per_page', $default);

        return in_array($perPage, [12, 25, 50, 100]) ? $perPage : $default;
    }
}

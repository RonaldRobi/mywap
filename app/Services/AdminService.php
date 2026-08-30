<?php

namespace App\Services;

use App\Jobs\SendBroadcastJob;
use App\Models\Attendance;
use App\Models\BroadcastMessage;
use App\Models\Campaign;
use App\Models\Event;
use App\Models\FacilityBooking;
use App\Models\Infaq;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\User;
use App\Models\UsrahGroup;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * AdminService
 *
 * Logik tunggal untuk skrin admin — dikongsi oleh WebController (Inertia)
 * dan ApiController (JSON) supaya web & Flutter tidak drift.
 */
class AdminService
{
    public function __construct(private readonly FeeService $fees) {}

    // ─── Dashboard ────────────────────────────────────────────────────────────

    /**
     * Payload penuh dashboard admin. Web (Inertia) render terus; API
     * mengambil subset (stats / recent_activities / revenue_by_month).
     */
    public function dashboard(User $user): array
    {
        $key = 'admin.dashboard.'.$user->current_organization_id.'.'.($user->hasRole('Superadmin') ? 'superadmin' : 'admin');

        return Cache::remember($key, 300, fn () => $this->buildDashboard($user));
    }

    private function buildDashboard(User $user): array
    {
        $user->loadMissing('organization');

        $isSuperadmin = $user->hasRole('Superadmin');
        $orgId = $user->current_organization_id;

        $users = User::withoutGlobalScopes()
            ->when(! $isSuperadmin, fn ($q) => $q->where('current_organization_id', $orgId));

        $totalMembers = (clone $users)->count();
        $activeMembers = (clone $users)->whereNotNull('profile_completed_at')->count();

        $events = Event::query()
            ->when(! $isSuperadmin, fn ($q) => $q->where('organization_id', $orgId));

        $totalEvents = (clone $events)->count();
        $upcomingEvents = (clone $events)->where('start_time', '>=', now())->count();

        $payments = Payment::query()
            ->where('status', 'successful')
            ->when(! $isSuperadmin, function ($q) use ($orgId) {
                $q->whereHas('user', fn ($inner) => $inner->withoutGlobalScopes()->where('current_organization_id', $orgId));
            });

        $totalRevenue = (float) (clone $payments)->sum('amount');

        $pendingPayments = Payment::query()
            ->where('status', 'pending')
            ->when(! $isSuperadmin, function ($q) use ($orgId) {
                $q->whereHas('user', fn ($inner) => $inner->withoutGlobalScopes()->where('current_organization_id', $orgId));
            })
            ->count();

        $feesCollectedThisMonth = Payment::query()
            ->where('status', 'successful')
            ->where('payable_type', 'membership_fee')
            ->where('created_at', '>=', now()->startOfMonth())
            ->where('created_at', '<=', now()->endOfMonth())
            ->when(! $isSuperadmin, function ($query) use ($user) {
                $query->whereHas('user', function ($innerQuery) use ($user) {
                    $innerQuery->withoutGlobalScopes()->where('current_organization_id', $user->current_organization_id);
                });
            })
            ->sum('amount');

        $activeCampaigns = Campaign::query()
            ->when(! $isSuperadmin, fn ($query) => $query->where('organization_id', $user->current_organization_id))
            ->where('status', 'active')
            ->latest()
            ->take(3)
            ->get()
            ->map(fn (Campaign $campaign) => [
                'id' => $campaign->id,
                'title' => $campaign->title,
                'status' => $campaign->status,
                'target_amount' => (float) $campaign->target_amount,
                'current_amount' => (float) $campaign->current_amount,
                'progress_percent' => $campaign->target_amount > 0
                    ? min(100, round(($campaign->current_amount / $campaign->target_amount) * 100))
                    : 0,
            ]);

        $eventsCount = $totalEvents;

        $infaqCount = Infaq::query()
            ->when(! $isSuperadmin, fn ($query) => $query->where(function ($q) use ($user) {
                $q->where('organization_id', $user->current_organization_id)
                    ->orWhereNull('organization_id');
            }))
            ->count();

        $programChart = [
            ['label' => 'Program', 'value' => $eventsCount],
            ['label' => 'Kempen', 'value' => $activeCampaigns->count()],
            ['label' => 'Infaq', 'value' => $infaqCount],
        ];

        $newMembersLast30Days = (clone $users)->where('created_at', '>=', now()->subDays(30))->count();
        $newMembersPrevious30Days = (clone $users)->whereBetween('created_at', [now()->subDays(60), now()->subDays(30)])->count();

        $newMembersTrendPercent = $newMembersPrevious30Days > 0
            ? round((($newMembersLast30Days - $newMembersPrevious30Days) / $newMembersPrevious30Days) * 100, 1)
            : ($newMembersLast30Days > 0 ? 100.0 : 0.0);

        $eventsThisMonth = (clone $events)->whereBetween('start_time', [now()->startOfMonth(), now()->endOfMonth()])->count();

        $pendingFacilityBookings = FacilityBooking::query()
            ->where('booking_status', 'pending')
            ->when(! $isSuperadmin, function ($query) use ($user) {
                $query->whereHas('facility', fn ($facilityQuery) => $facilityQuery->where('organization_id', $user->current_organization_id));
            })
            ->count();

        $membersByState = (clone $users)
            ->selectRaw("COALESCE(NULLIF(state, ''), 'Tidak Dinyatakan') as state, COUNT(*) as total")
            ->groupBy('state')
            ->orderByDesc('total')
            ->orderBy('state')
            ->get()
            ->map(fn ($row) => [
                'state' => $row->state,
                'count' => (int) $row->total,
            ])
            ->values();

        $activeMemberCount = (clone $users)->where('is_active', true)->count();
        $inactiveMembers = $totalMembers - $activeMemberCount;

        $orgMemberCounts = [];
        if ($isSuperadmin) {
            $orgs = Organization::query()
                ->where('slug', '!=', 'management')
                ->orderBy('min_age')
                ->get(['id', 'name', 'slug', 'color_theme']);

            $counts = User::withoutGlobalScopes()
                ->whereIn('current_organization_id', $orgs->pluck('id'))
                ->selectRaw('current_organization_id, COUNT(*) as total')
                ->groupBy('current_organization_id')
                ->pluck('total', 'current_organization_id');

            $orgMemberCounts = $orgs
                ->map(fn ($org) => [
                    'id' => $org->id,
                    'name' => $org->name,
                    'slug' => $org->slug,
                    'color_theme' => $org->color_theme,
                    'member_count' => (int) ($counts[$org->id] ?? 0),
                ])
                ->values()
                ->toArray();
        }

        $year = now()->year;
        $feesDueCount = $isSuperadmin
            ? (clone $users)
                ->whereDoesntHave('membershipFees', fn ($q) => $q->whereIn('status', ['life_member', 'exempted']))
                ->whereDoesntHave('membershipFees', fn ($q) => $q->where('year', $year)->where('status', 'paid'))
                ->count()
            : $this->fees->getDueCount($user->current_organization_id, $year);

        $alerts = collect();

        if ($pendingFacilityBookings > 0) {
            $alerts->push([
                'type' => $pendingFacilityBookings >= 10 ? 'high' : 'medium',
                'key' => 'pending_bookings',
                'count' => $pendingFacilityBookings,
                'title' => 'Tempahan Ruang Belum Diproses',
                'description' => "{$pendingFacilityBookings} tempahan masih berstatus pending.",
            ]);
        }

        if ($feesDueCount > 0) {
            $alerts->push([
                'type' => $feesDueCount >= 50 ? 'high' : 'medium',
                'key' => 'fees_due',
                'count' => $feesDueCount,
                'title' => 'Yuran Tertunggak',
                'description' => "{$feesDueCount} ahli belum membuat bayaran yuran untuk tahun ini.",
            ]);
        }

        if ($eventsThisMonth === 0) {
            $alerts->push([
                'type' => 'medium',
                'key' => 'no_programs',
                'count' => 0,
                'title' => 'Program Bulan Ini',
                'description' => 'Tiada program berjadual bulan ini. Pertimbangkan perancangan segera.',
            ]);
        }

        $recentActivities = collect();

        $latestMembers = (clone $users)->latest('created_at')->take(4)->get(['id', 'name', 'created_at']);

        foreach ($latestMembers as $member) {
            $recentActivities->push([
                'id' => 'member-'.$member->id,
                'type' => 'member',
                'title' => 'Ahli baharu didaftarkan',
                'description' => $member->name,
                'created_at' => $member->created_at?->toDateTimeString(),
            ]);
        }

        $latestPayments = (clone $payments)->latest('created_at')->take(4)->get(['id', 'amount', 'payable_type', 'created_at']);

        foreach ($latestPayments as $payment) {
            $recentActivities->push([
                'id' => 'payment-'.$payment->id,
                'type' => 'payment',
                'title' => 'Bayaran berjaya diterima',
                'description' => strtoupper((string) $payment->payable_type).' · RM '.number_format((float) $payment->amount, 2),
                'created_at' => $payment->created_at?->toDateTimeString(),
            ]);
        }

        $latestBookings = FacilityBooking::query()
            ->when(! $isSuperadmin, function ($query) use ($user) {
                $query->whereHas('facility', fn ($facilityQuery) => $facilityQuery->where('organization_id', $user->current_organization_id));
            })
            ->latest('created_at')
            ->take(4)
            ->get(['id', 'booking_status', 'created_at']);

        foreach ($latestBookings as $booking) {
            $recentActivities->push([
                'id' => 'booking-'.$booking->id,
                'type' => 'booking',
                'title' => 'Tempahan ruang dikemaskini',
                'description' => 'Status: '.ucfirst((string) $booking->booking_status),
                'created_at' => $booking->created_at?->toDateTimeString(),
            ]);
        }

        $recentActivities = $recentActivities->sortByDesc('created_at')->take(8)->values();

        $labels = [];
        $values = [];
        for ($i = 5; $i >= 0; $i--) {
            $start = now()->subMonths($i)->startOfMonth();
            $end = now()->subMonths($i)->endOfMonth();
            $labels[] = $start->format('M Y');
            $values[] = round((float) (clone $payments)->whereBetween('created_at', [$start, $end])->sum('amount'), 2);
        }

        return [
            'organization' => [
                'name' => $isSuperadmin ? 'Management' : $user->organization?->name,
                'slug' => $isSuperadmin ? 'management' : $user->organization?->slug,
                'color_theme' => $isSuperadmin ? '#334155' : $user->organization?->color_theme,
            ],
            'overview' => [
                'total_members' => $totalMembers,
                'fees_collected_month' => (float) $feesCollectedThisMonth,
                'total_programs' => (int) ($eventsCount + $activeCampaigns->count() + $infaqCount),
                'program_chart' => $programChart,
                'new_members_30d' => $newMembersLast30Days,
                'new_members_trend_percent' => $newMembersTrendPercent,
                'events_this_month' => $eventsThisMonth,
                'pending_facility_bookings' => $pendingFacilityBookings,
                'fees_due_count' => $feesDueCount,
                'members_by_state' => $membersByState,
                'active_members' => $activeMemberCount,
                'inactive_members' => $inactiveMembers,
                'org_member_counts' => $orgMemberCounts,
                'alerts' => $alerts->values(),
                'recent_activities' => $recentActivities,
            ],
            'managementLinks' => [
                'create_event_url' => route('events.index'),
                'create_program_url' => route('events.index'),
                'create_campaign_url' => route('admin.campaigns.store'),
                'campaigns_url' => route('admin.campaigns.index'),
                'infaq_url' => $isSuperadmin ? route('superadmin.infaq.index') : route('admin.campaigns.index'),
                'banners_url' => $isSuperadmin ? route('superadmin.banners.index') : null,
                'information_hub_manage_url' => route('admin.hub.manage'),
                'fees_members_url' => route('admin.fees.members'),
                'usrah_manage_url' => route('admin.usrah.index'),
                'broadcasts_url' => route('admin.broadcasts.index'),
                'directory_url' => route('directory.index'),
            ],
            'campaigns' => $activeCampaigns,
            'stats' => [
                'total_members' => $totalMembers,
                'active_members' => $activeMembers,
                'pending_members' => $totalMembers - $activeMembers,
                'total_events' => $totalEvents,
                'upcoming_events' => $upcomingEvents,
                'total_revenue' => $totalRevenue,
                'pending_payments' => $pendingPayments,
            ],
            'recent_activities' => $recentActivities,
            'revenue_by_month' => [
                'labels' => $labels,
                'values' => $values,
            ],
        ];
    }

    // ─── Ahli ─────────────────────────────────────────────────────────────────

    /**
     * Senarai ahli (admin). Skop organisasi untuk bukan superadmin.
     * Item paginator sudah diserialize untuk API & web.
     */
    public function members(Request $request, User $user): LengthAwarePaginator
    {
        $isSuperadmin = $user->hasRole('Superadmin');
        $search = $request->input('search');
        $status = $request->input('status');

        $query = User::withoutGlobalScopes()
            ->with('organization:id,name')
            ->when(! $isSuperadmin, fn ($q) => $q->where('current_organization_id', $user->current_organization_id))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('member_no', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('ic_number', 'like', "%{$search}%");
                });
            })
            ->when($status === 'active', fn ($q) => $q->whereNotNull('profile_completed_at'))
            ->when($status === 'pending', fn ($q) => $q->whereNull('profile_completed_at'))
            ->latest('created_at');

        $perPage = $this->perPage($request, 15, [10, 15, 25, 50, 100]);

        return $query->paginate($perPage)->withQueryString()->through(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'member_no' => $u->member_no,
            'email' => $u->email,
            'phone' => $u->phone,
            'ic_number' => $u->ic_number,
            'branch_name' => $u->branch_name,
            'organization' => $u->organization
                ? ['id' => $u->organization->id, 'name' => $u->organization->name]
                : null,
            'status' => $u->profile_completed_at ? 'active' : 'pending',
            'created_at' => $u->created_at?->toISOString(),
            'profile_completed_at' => $u->profile_completed_at?->toISOString(),
        ]);
    }

    // ─── Yuran ────────────────────────────────────────────────────────────────

    /**
     * Payload penuh halaman yuran admin (web). API mengambil subset
     * (summary + senarai fees).
     */
    public function fees(Request $request, User $user): array
    {
        $isSuperadmin = $user->hasRole('Superadmin');
        $orgId = $user->current_organization_id;

        $year = (int) $request->input('year', now()->year);

        $query = User::withoutGlobalScopes()
            ->with(['membershipFees' => fn ($q) => $q->where('year', $year)])
            ->with('organization:id,name,slug')
            ->when(! $isSuperadmin, fn ($q) => $q->where('current_organization_id', $orgId))
            ->when($isSuperadmin && $request->filled('organization_id'), fn ($q) => $q->where('current_organization_id', (int) $request->organization_id))
            ->orderBy('name');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('ic_number', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%")
                    ->orWhere('member_no', 'like', "%{$s}%");
            });
        }

        $feeStatus = $request->input('fee_status', $request->input('status'));
        if ($feeStatus) {
            if ($feeStatus === 'paid') {
                $query->whereHas('membershipFees', fn ($q) => $q->where('year', $year)->whereIn('status', ['paid', 'exempted']));
            } elseif ($feeStatus === 'life_member') {
                $query->whereHas('membershipFees', fn ($q) => $q->where('status', 'life_member'));
            } elseif ($feeStatus === 'exempted') {
                $query->whereHas('membershipFees', fn ($q) => $q->where('status', 'exempted'));
            } elseif ($feeStatus === 'pending' || $feeStatus === 'due') {
                $query->whereDoesntHave('membershipFees', fn ($q) => $q->where('year', $year)->whereIn('status', ['paid', 'exempted', 'life_member']));
            }
        }

        $perPage = $this->perPage($request, 25, [10, 15, 25, 50, 100]);

        $members = $query->paginate($perPage)->withQueryString()->through(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'ic_number' => $u->ic_number,
            'member_no' => $u->member_no,
            'phone' => $u->phone,
            'email' => $u->email,
            'organization' => $u->organization ? ['id' => $u->organization->id, 'name' => $u->organization->name, 'slug' => $u->organization->slug] : null,
            'fee' => $u->membershipFees->first() ? [
                'id' => $u->membershipFees->first()->id,
                'year' => $u->membershipFees->first()->year,
                'amount' => (float) $u->membershipFees->first()->amount,
                'status' => $u->membershipFees->first()->status?->value ?? ($u->membershipFees->first()->status ?? 'unpaid'),
                'paid_at' => $u->membershipFees->first()->paid_at?->toDateString(),
                'notes' => $u->membershipFees->first()->notes,
            ] : ['status' => 'unpaid', 'year' => $year],
        ]);

        $stats = $isSuperadmin && $request->filled('organization_id')
            ? $this->fees->getAdminStats((int) $request->organization_id, $year)
            : ($isSuperadmin
                ? $this->fees->getAdminStats(null, $year)
                : $this->fees->getAdminStats($orgId, $year));

        $monthExpr = match (DB::connection()->getDriverName()) {
            'pgsql' => 'EXTRACT(MONTH FROM created_at)',
            'sqlite' => "CAST(strftime('%m', created_at) AS INTEGER)",
            default => 'MONTH(created_at)',
        };

        $monthlyTotals = Payment::query()
            ->selectRaw("{$monthExpr} as month, SUM(amount) as total")
            ->where('status', 'successful')
            ->whereYear('created_at', $year)
            ->when(! $isSuperadmin || $request->filled('organization_id'), function ($q) use ($orgId, $isSuperadmin, $request) {
                $q->whereHas('user', fn ($uq) => $uq->withoutGlobalScopes()->where('current_organization_id',
                    $isSuperadmin ? (int) $request->organization_id : $orgId));
            })
            ->groupBy('month')
            ->pluck('total', 'month');

        $monthlyCollection = collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => (float) ($monthlyTotals[$m] ?? 0)]);

        $chart = $monthlyCollection->map(fn ($total, $m) => [
            'month' => date('F', mktime(0, 0, 0, (int) $m, 1)),
            'total' => $total,
        ])->values();

        $years = range(now()->year - 10, now()->year + 1);

        $orgIds = $isSuperadmin
            ? Organization::pluck('fee_amount', 'id')
            : [$orgId => Organization::find($orgId)?->fee_amount ?? 0];

        $expectedAmount = 0;
        $activeMembers = 0;
        if ($orgIds) {
            $counts = User::withoutGlobalScopes()
                ->whereIn('current_organization_id', array_keys($orgIds))
                ->whereDoesntHave('membershipFees', fn ($q) => $q->whereIn('status', ['life_member', 'exempted']))
                ->selectRaw('current_organization_id, COUNT(*) as total')
                ->groupBy('current_organization_id')
                ->pluck('total', 'current_organization_id');

            foreach ($orgIds as $oid => $feeAmount) {
                $cnt = (int) ($counts[$oid] ?? 0);
                $activeMembers += $cnt;
                $expectedAmount += $cnt * (float) $feeAmount;
            }
        }

        return [
            'members' => $members,
            'stats' => $stats,
            'year' => $year,
            'years' => $years,
            'chart' => $chart,
            'organizations' => $isSuperadmin ? Organization::select('id', 'name', 'slug')->get() : [],
            'reconciliation' => [
                'expected' => round($expectedAmount, 2),
                'collected' => $stats['collected_amount'] ?? 0,
                'outstanding' => round(max(0, $expectedAmount - ($stats['collected_amount'] ?? 0)), 2),
                'rate' => $expectedAmount > 0 ? round(($stats['collected_amount'] ?? 0) / $expectedAmount * 100, 1) : 0,
            ],
            'filters' => $request->only(['search', 'fee_status']),
        ];
    }

    // ─── Kehadiran ────────────────────────────────────────────────────────────

    /**
     * Payload penuh dashboard kehadiran admin (web).
     */
    public function attendanceIndex(Request $request, User $user): array
    {
        $isSuperadmin = $user->hasRole('Superadmin');

        $query = Registration::with(['user', 'organization', 'form', 'event.organization', 'attendance', 'latestPayment'])
            ->latest();

        if (! $isSuperadmin) {
            $query->where(function (Builder $q) use ($user) {
                $q->where('organization_id', $user->current_organization_id)
                    ->orWhereHas('event', fn ($q2) => $q2->where('organization_id', $user->current_organization_id));
            });
        }

        if ($request->filled('event_id')) {
            $query->where('event_id', (int) $request->event_id);
        }

        if ($request->filled('org')) {
            $query->where('organization_id', (int) $request->org);
        }

        if ($request->filled('attendance')) {
            if ($request->attendance === 'hadir') {
                $query->whereHas('attendance');
            } elseif ($request->attendance === 'tidak_hadir') {
                $query->whereDoesntHave('attendance');
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('registration_no', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('member_no', 'like', "%{$search}%");
            });
        }

        $registrations = $query->paginate(25)->withQueryString()->through(fn (Registration $r) => [
            'id' => $r->id,
            'registration_no' => $r->registration_no,
            'name' => $r->name,
            'email' => $r->email,
            'phone' => $r->phone,
            'member_no' => $r->member_no,
            'status' => $r->status->value,
            'organization_name' => $r->organization?->name ?? $r->event?->organization?->name,
            'event_title' => $r->event?->title,
            'form_title' => $r->form?->title,
            'payment_status' => $r->latestPayment?->status ?? 'paid',
            'attended' => $r->attendance !== null,
            'attended_at' => $r->attendance?->attended_at?->toDateTimeString(),
            'method' => $r->attendance?->method,
            'created_at' => $r->created_at?->toDateTimeString(),
        ]);

        $statsBase = Registration::query();
        if (! $isSuperadmin) {
            $statsBase->where(function (Builder $q) use ($user) {
                $q->where('organization_id', $user->current_organization_id)
                    ->orWhereHas('event', fn ($q2) => $q2->where('organization_id', $user->current_organization_id));
            });
        }

        $stats = [
            'total_registered' => (clone $statsBase)->count(),
            'total_attended' => (clone $statsBase)->whereHas('attendance')->count(),
            'total_pending_payment' => (clone $statsBase)
                ->whereHas('latestPayment', fn ($q) => $q->where('status', 'pending'))->count(),
        ];

        $events = Event::query()
            ->when(! $isSuperadmin, fn ($q) => $q->where('organization_id', $user->current_organization_id))
            ->orderByDesc('start_time')
            ->get(['id', 'title']);

        return [
            'registrations' => $registrations,
            'stats' => $stats,
            'events' => $events,
            'organizations' => $isSuperadmin
                ? Organization::orderBy('min_age')->get(['id', 'name'])
                : [],
            'filters' => $request->only(['event_id', 'org', 'attendance', 'search']),
        ];
    }

    /**
     * Senarai pendaftaran + statistik untuk satu event (API admin).
     */
    public function attendanceRegistrations(int $eventId, User $user): array
    {
        $event = Event::with('organization')->findOrFail($eventId);

        if (! $user->hasRole('Superadmin') && (int) $event->organization_id !== (int) $user->current_organization_id) {
            abort(403, 'Tiada kebenaran.');
        }

        $registrations = Registration::with('attendance')
            ->where('event_id', $eventId)
            ->latest()
            ->limit(200)
            ->get()
            ->map(fn (Registration $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'member_no' => $r->member_no,
                'status' => $r->status->value,
                'attended' => $r->attendance !== null,
                'attended_at' => $r->attendance?->attended_at?->toISOString(),
            ])
            ->values();

        return [
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'start_time' => $event->start_time?->toISOString(),
            ],
            'stats' => [
                'total_registered' => Registration::where('event_id', $eventId)->count(),
                'attended_count' => Registration::where('event_id', $eventId)->whereHas('attendance')->count(),
            ],
            'registrations' => $registrations,
        ];
    }

    /**
     * Scan kehadiran oleh admin (API). identifier = member_no | ic_number | phone.
     */
    public function scanAttendance(User $user, int $eventId, string $identifier): array
    {
        $event = Event::findOrFail($eventId);

        if (! $user->hasRole('Superadmin') && (int) $event->organization_id !== (int) $user->current_organization_id) {
            abort(403, 'Tiada kebenaran.');
        }

        $registration = Registration::where('event_id', $event->id)
            ->where(function (Builder $q) use ($identifier) {
                $q->where('member_no', $identifier)
                    ->orWhere('ic_number', $identifier)
                    ->orWhere('phone', $identifier);
            })
            ->latest()
            ->first();

        if (! $registration) {
            return ['status' => 'error', 'message' => 'Tiada rekod pendaftaran dijumpai. Sila semak semula maklumat.', 'registration' => null];
        }

        if ($block = $this->registrationBlockReason($registration)) {
            return ['status' => 'error', 'message' => $block, 'registration' => null];
        }

        if ($registration->attendance) {
            return ['status' => 'error', 'message' => 'Kehadiran telah direkodkan sebelum ini.', 'registration' => null];
        }

        $attendance = $this->recordAttendance($registration, 'manual');

        return [
            'status' => 'ok',
            'message' => 'Kehadiran berjaya direkodkan.',
            'registration' => [
                'id' => $registration->id,
                'name' => $registration->name,
                'member_no' => $registration->member_no,
                'attended_at' => $attendance->attended_at?->toISOString(),
            ],
        ];
    }

    // ─── Helper kehadiran (dikongsi web + API) ────────────────────────────────

    public function findRegistration(Event $event, $user): ?Registration
    {
        return Registration::where('event_id', $event->id)
            ->where(function (Builder $q) use ($user) {
                $q->where('user_id', $user->id);
                if ($user->email) {
                    $q->orWhere('email', $user->email);
                }
                if ($user->phone) {
                    $q->orWhere('phone', $user->phone);
                }
            })
            ->latest()
            ->first();
    }

    public function registrationBlockReason(Registration $registration): ?string
    {
        if ($registration->status->value === 'cancelled') {
            return 'Pendaftaran anda telah dibatalkan.';
        }

        $form = $registration->form;
        if ($form && $form->payment_required && $form->price && (float) $form->price > 0) {
            $paymentStatus = $registration->latestPayment?->status ?? 'pending';
            if ($paymentStatus !== 'successful') {
                return 'Bayaran pendaftaran anda belum lengkap.';
            }
        }

        return null;
    }

    public function recordAttendance(Registration $registration, string $method): Attendance
    {
        return Attendance::updateOrCreate(
            ['registration_id' => $registration->id],
            ['event_id' => $registration->event_id, 'attended_at' => now(), 'method' => $method],
        );
    }

    // ─── Siaran (Broadcast) ───────────────────────────────────────────────────

    /**
     * Cipta BroadcastMessage + dispatch job (dikongsi web & API).
     */
    public function broadcast(array $input): BroadcastMessage
    {
        $message = BroadcastMessage::create([
            'organization_id' => $input['organization_id'],
            'target_organization_id' => $input['target_organization_id'] ?? null,
            'branch_id' => $input['branch_id'] ?? null,
            'title' => $input['title'],
            'content' => $input['content'],
            'target_criteria' => $input['target_criteria'],
            'recipient_ids' => $input['recipient_ids'] ?? null,
            'notification_channels' => $input['notification_channels'] ?? ['in_app'],
            'email_use_template' => $input['email_use_template'] ?? false,
        ]);

        SendBroadcastJob::dispatch($message->id);

        return $message;
    }

    /**
     * Terjemah audience API kepada [target_criteria, target_organization_id, recipient_ids].
     */
    public function resolveAudience(User $user, string $audience, ?int $organizationId = null): array
    {
        return match ($audience) {
            'members' => ['organization', $user->current_organization_id, null],
            'org' => $this->orgTarget($user, $organizationId),
            'usrah' => $this->usrahTarget($user),
            default => ['all', null, null],
        };
    }

    private function orgTarget(User $user, ?int $organizationId): array
    {
        $targetOrgId = $organizationId ?? $user->current_organization_id;

        if (! $user->hasRole('Superadmin') && (int) $targetOrgId !== (int) $user->current_organization_id) {
            abort(403, 'Tiada kebenaran.');
        }

        return ['organization', $targetOrgId, null];
    }

    private function usrahTarget(User $user): array
    {
        $memberIds = UsrahGroup::query()
            ->with('members')
            ->get()
            ->flatMap(fn (UsrahGroup $group) => $group->members->pluck('id'))
            ->unique()
            ->values()
            ->all();

        return ['specific_members', null, $memberIds];
    }

    private function perPage(Request $request, int $default, array $allowed): int
    {
        $perPage = (int) $request->input('per_page', $default);

        return in_array($perPage, $allowed, true) ? $perPage : $default;
    }
}

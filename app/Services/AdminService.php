<?php

namespace App\Services;

use App\Enums\RegistrationStatus;
use App\Jobs\SendBroadcastJob;
use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\BroadcastLog;
use App\Models\BroadcastMessage;
use App\Models\Branch;
use App\Models\Campaign;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\FacilityBooking;
use App\Models\Infaq;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\Scopes\OrganizationScope;
use App\Models\User;
use App\Models\UsrahGroup;
use App\Support\MemberSearch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * AdminService
 *
 * Logik tunggal untuk skrin admin — dikongsi oleh WebController (Inertia)
 * dan ApiController (JSON) supaya web & Flutter tidak drift.
 */
class AdminService
{
    private const WADAH_STATE_ORDER = [
        'Johor',
        'Kedah',
        'Kelantan',
        'Melaka',
        'Negeri Sembilan',
        'Pahang',
        'Perak',
        'Perlis',
        'Pulau Pinang',
        'Sabah',
        'Sarawak',
        'Selangor',
        'Terengganu',
        'Wilayah Persekutuan',
        'Tidak Dinyatakan',
    ];

    public function __construct(private readonly FeeService $fees) {}

    // ─── Dashboard ────────────────────────────────────────────────────────────

    /**
     * Payload penuh dashboard admin. Web (Inertia) render terus; API
     * mengambil subset (stats / recent_activities / revenue_by_month).
     */
    public function dashboard(User $user): array
    {
        $scope = $user->hasRole('Superadmin')
            ? 'superadmin'
            : ($user->isBranchAdmin() ? 'branch-'.implode('-', $user->managedBranchIds()) : 'admin');
        $key = 'admin.dashboard.'.$user->current_organization_id.'.'.$scope;

        return Cache::remember($key, 300, fn () => $this->buildDashboard($user));
    }

    private function stateBreakdown(Builder $query, int $orgId): \Illuminate\Support\Collection
    {
        $slug = Organization::whereKey($orgId)->value('slug');

        if ($slug === 'wadah') {
            return $this->wadahStateBreakdown($query, $orgId);
        }

        return $this->genericStateBreakdown($query);
    }

    private function wadahStateBreakdown(Builder $query, int $orgId): \Illuminate\Support\Collection
    {
        $branchCounts = (clone $query)
            ->selectRaw('branch_id, COUNT(*) as total')
            ->groupBy('branch_id')
            ->pluck('total', 'branch_id');

        $labels = Branch::query()
            ->where('organization_id', $orgId)
            ->pluck('name', 'id')
            ->map(fn ($name) => $this->canonicalStateLabel((string) $name))
            ->all();

        $counts = array_fill_keys(self::WADAH_STATE_ORDER, 0);

        foreach ($branchCounts as $branchId => $total) {
            $label = $branchId ? ($labels[$branchId] ?? 'Tidak Dinyatakan') : 'Tidak Dinyatakan';
            if (! array_key_exists($label, $counts)) {
                $counts[$label] = 0;
            }
            $counts[$label] += (int) $total;
        }

        return collect(self::WADAH_STATE_ORDER)
            ->map(fn ($state) => ['state' => $state, 'count' => $counts[$state] ?? 0])
            ->values();
    }

    private function genericStateBreakdown(Builder $query): \Illuminate\Support\Collection
    {
        $rows = (clone $query)
            ->selectRaw("COALESCE(NULLIF(TRIM(state), ''), 'Tidak Dinyatakan') as state, COUNT(*) as total")
            ->groupBy('state')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $label = $this->canonicalStateLabel((string) $row->state);
            $counts[$label] = ($counts[$label] ?? 0) + (int) $row->total;
        }

        return collect($counts)
            ->map(fn ($count, $state) => ['state' => $state, 'count' => $count])
            ->sort(function (array $a, array $b) {
                return $b['count'] <=> $a['count'] ?: strcmp($a['state'], $b['state']);
            })
            ->values();
    }

    private function canonicalStateLabel(string $value): string
    {
        $key = (string) preg_replace('/[^A-Z]/', '', strtoupper(trim($value)));

        $labels = [
            'JOHOR' => 'Johor',
            'KEDAH' => 'Kedah',
            'KELANTAN' => 'Kelantan',
            'MELAKA' => 'Melaka',
            'NEGERISEMBILAN' => 'Negeri Sembilan',
            'PAHANG' => 'Pahang',
            'PERAK' => 'Perak',
            'PERLIS' => 'Perlis',
            'PULAUPINANG' => 'Pulau Pinang',
            'PENANG' => 'Pulau Pinang',
            'SABAH' => 'Sabah',
            'SARAWAK' => 'Sarawak',
            'SELANGOR' => 'Selangor',
            'TERENGGANU' => 'Terengganu',
            'WILAYAHPERSEKUTUAN' => 'Wilayah Persekutuan',
            'WILAYAHPERSEKUTAN' => 'Wilayah Persekutuan',
            'PUSAT' => 'Tidak Dinyatakan',
            'TIADANEGERI' => 'Tidak Dinyatakan',
            'TIDAKDINYATAKAN' => 'Tidak Dinyatakan',
        ];

        if ($key === '') {
            return 'Tidak Dinyatakan';
        }

        return $labels[$key] ?? Str::title(strtolower(trim($value)));
    }

    private function buildDashboard(User $user): array
    {
        $user->loadMissing('organization');

        $isSuperadmin = $user->hasRole('Superadmin');
        $isBranchAdmin = $user->isBranchAdmin();
        $branchIds = $isBranchAdmin ? $user->managedBranchIds() : [];
        $orgId = $user->current_organization_id;

        $users = User::withoutGlobalScope(OrganizationScope::class)
            ->when(! $isSuperadmin, fn ($q) => $q->where('current_organization_id', $orgId))
            ->when($branchIds !== [], fn ($q) => $q->whereIn('branch_id', $branchIds));

        $totalMembers = (clone $users)->count();
        $activeMembers = (clone $users)->whereNotNull('profile_completed_at')->count();

        $events = Event::query()
            ->when($branchIds !== [], fn ($q) => $q->whereIn('branch_id', $branchIds))
            ->when(! $isSuperadmin, fn ($q) => $q->where(function ($scope) use ($orgId) {
                // Sama seperti senarai program admin: milik organisasi ATAU
                // program bersama (pivot organizations).
                $scope->where('organization_id', $orgId)
                    ->orWhereHas('organizations', fn ($pivot) => $pivot->where('organizations.id', $orgId));
            }));

        $totalEvents = (clone $events)->count();
        $upcomingEvents = (clone $events)->where('start_time', '>=', now())->count();

        $payments = Payment::query()
            ->where('status', 'successful')
            ->when(! $isSuperadmin, function ($q) use ($orgId, $branchIds) {
                $q->whereHas('user', fn ($inner) => $inner->withoutGlobalScope(OrganizationScope::class)
                    ->where('current_organization_id', $orgId)
                    ->when($branchIds !== [], fn ($userQuery) => $userQuery->whereIn('branch_id', $branchIds)));
            });

        $totalRevenue = (float) (clone $payments)->sum('amount');

        $pendingPayments = Payment::query()
            ->where('status', 'pending')
            ->when(! $isSuperadmin, function ($q) use ($orgId, $branchIds) {
                $q->whereHas('user', fn ($inner) => $inner->withoutGlobalScope(OrganizationScope::class)
                    ->where('current_organization_id', $orgId)
                    ->when($branchIds !== [], fn ($userQuery) => $userQuery->whereIn('branch_id', $branchIds)));
            })
            ->count();

        $feesCollectedThisMonth = Payment::query()
            ->where('status', 'successful')
            ->where('payable_type', 'membership_fee')
            ->where('created_at', '>=', now()->startOfMonth())
            ->where('created_at', '<=', now()->endOfMonth())
            ->when(! $isSuperadmin, function ($query) use ($user, $branchIds) {
                $query->whereHas('user', function ($innerQuery) use ($user, $branchIds) {
                    $innerQuery->withoutGlobalScope(OrganizationScope::class)
                        ->where('current_organization_id', $user->current_organization_id)
                        ->when($branchIds !== [], fn ($q) => $q->whereIn('branch_id', $branchIds));
                });
            })
            ->sum('amount');

        $activeCampaigns = $isBranchAdmin ? collect() : Campaign::query()
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

        $infaqCount = $isBranchAdmin ? 0 : Infaq::query()
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
            ->when($branchIds !== [], fn ($query) => $query->whereHas('facility', fn ($facilityQuery) => $facilityQuery->whereIn('branch_id', $branchIds)))
            ->count();

        $membersByState = $this->stateBreakdown(clone $users, $orgId);

        $activeMemberCount = (clone $users)->where('is_active', true)->count();
        $inactiveMembers = $totalMembers - $activeMemberCount;

        $orgMemberCounts = [];
        $orgMemberStats = [];
        if ($isSuperadmin) {
            $orgs = Organization::query()
                ->where('slug', '!=', 'management')
                ->orderBy('min_age')
                ->get(['id', 'name', 'slug', 'color_theme']);
            $orgIds = $orgs->pluck('id');

            $counts = User::withoutGlobalScope(OrganizationScope::class)
                ->whereIn('current_organization_id', $orgIds)
                ->selectRaw('current_organization_id, COUNT(*) as total')
                ->groupBy('current_organization_id')
                ->pluck('total', 'current_organization_id');

            $stateRows = User::withoutGlobalScope(OrganizationScope::class)
                ->whereIn('current_organization_id', $orgIds)
                ->selectRaw("current_organization_id, COALESCE(NULLIF(state, ''), 'Tidak Dinyatakan') as state, COUNT(*) as total")
                ->groupBy('current_organization_id', 'state')
                ->orderBy('state')
                ->get();

            $branchRows = User::withoutGlobalScope(OrganizationScope::class)
                ->whereIn('current_organization_id', $orgIds)
                ->whereNotNull('branch_id')
                ->selectRaw('current_organization_id, branch_id, COUNT(*) as total')
                ->groupBy('current_organization_id', 'branch_id')
                ->get();

            $branchesById = Branch::query()
                ->whereIn('id', $branchRows->pluck('branch_id'))
                ->get(['id', 'name', 'state'])
                ->keyBy('id');

            $statesByOrg = $stateRows->groupBy('current_organization_id');
            $branchesByOrg = $branchRows->groupBy('current_organization_id');

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

            $orgMemberStats = $orgs
                ->map(function ($org) use ($statesByOrg, $branchesByOrg, $branchesById, $counts) {
                    $states = collect($statesByOrg[$org->id] ?? [])
                        ->map(fn ($row) => ['state' => $row->state, 'count' => (int) $row->total])
                        ->sortBy('state')
                        ->values();

                    $branches = collect($branchesByOrg[$org->id] ?? [])
                        ->map(function ($row) use ($branchesById) {
                            $branch = $branchesById[$row->branch_id] ?? null;

                            return [
                                'id' => (int) $row->branch_id,
                                'name' => $branch?->name,
                                'state' => $branch?->state,
                                'count' => (int) $row->total,
                            ];
                        })
                        ->sortBy('name')
                        ->values();

                    return [
                        'id' => $org->id,
                        'name' => $org->name,
                        'slug' => $org->slug,
                        'color_theme' => $org->color_theme,
                        'member_count' => (int) ($counts[$org->id] ?? 0),
                        'state_count' => $states->count(),
                        'branch_count' => $branches->count(),
                        'states' => $states->all(),
                        'branches' => $branches->all(),
                    ];
                })
                ->values()
                ->toArray();
        }

        $year = now()->year;
        $feesDueCount = ($isSuperadmin || $isBranchAdmin)
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
            ->when($branchIds !== [], fn ($query) => $query->whereHas('facility', fn ($facilityQuery) => $facilityQuery->whereIn('branch_id', $branchIds)))
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
                'total_programs' => (int) $eventsCount,
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
                'org_member_stats' => $orgMemberStats,
                'alerts' => $alerts->values(),
                'recent_activities' => $recentActivities,
            ],
            'managementLinks' => $isBranchAdmin ? [
                'create_event_url' => route('admin.events.index'),
                'create_program_url' => route('admin.events.create'),
                'create_campaign_url' => null,
                'campaigns_url' => null,
                'infaq_url' => null,
                'banners_url' => null,
                'information_hub_manage_url' => route('admin.hub.manage'),
                'fees_members_url' => route('admin.fees.members'),
                'usrah_manage_url' => route('admin.usrah.index'),
                'broadcasts_url' => route('admin.broadcasts.index'),
                'supporters_url' => null,
                'directory_url' => null,
            ] : [
                'create_event_url' => route('admin.events.index'),
                'create_program_url' => route('admin.events.create'),
                'create_campaign_url' => route('admin.campaigns.store'),
                'campaigns_url' => route('admin.campaigns.index'),
                'infaq_url' => $isSuperadmin ? route('superadmin.infaq.index') : route('admin.campaigns.index'),
                'banners_url' => $isSuperadmin ? route('superadmin.banners.index') : null,
                'information_hub_manage_url' => route('admin.hub.manage'),
                'fees_members_url' => route('admin.fees.members'),
                'usrah_manage_url' => route('admin.usrah.index'),
                'broadcasts_url' => route('admin.broadcasts.index'),
                'supporters_url' => route('admin.supporters.index'),
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
        $branchIds = $user->isBranchAdmin() ? $user->managedBranchIds() : [];
        $search = $request->input('search');
        $status = $request->input('status');

        $query = User::withoutGlobalScope(OrganizationScope::class)
            ->with(['organization:id,name', 'branch:id,name'])
            ->when(! $isSuperadmin, fn ($q) => $q->where('current_organization_id', $user->current_organization_id))
            ->when($branchIds !== [], fn ($q) => $q->whereIn('branch_id', $branchIds))
            ->when($search, fn ($q) => MemberSearch::apply($q, $search))
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
            'branch_name' => $u->branch?->name ?? $u->branch_name,
            'organization' => $u->organization
                ? ['id' => $u->organization->id, 'name' => $u->organization->name]
                : null,
            'status' => $u->profile_completed_at ? 'active' : 'pending',
            'is_active' => (bool) $u->is_active,
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
        $branchIds = $user->isBranchAdmin() ? $user->managedBranchIds() : [];
        $orgId = $user->current_organization_id;

        $year = (int) $request->input('year', now()->year);

        $query = User::withoutGlobalScope(OrganizationScope::class)
            ->with(['membershipFees' => fn ($q) => $q->where('year', $year)])
            ->with('organization:id,name,slug')
            ->when(! $isSuperadmin, fn ($q) => $q->where('current_organization_id', $orgId))
            ->when($branchIds !== [], fn ($q) => $q->whereIn('branch_id', $branchIds))
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
            ? $this->fees->getAdminStats((int) $request->organization_id, $year, null)
            : ($isSuperadmin
                ? $this->fees->getAdminStats(null, $year, null)
                : $this->fees->getAdminStats($orgId, $year, $branchIds));

        $monthExpr = match (DB::connection()->getDriverName()) {
            'pgsql' => 'EXTRACT(MONTH FROM created_at)',
            'sqlite' => "CAST(strftime('%m', created_at) AS INTEGER)",
            default => 'MONTH(created_at)',
        };

        $monthlyTotals = Payment::query()
            ->selectRaw("{$monthExpr} as month, SUM(amount) as total")
            ->where('status', 'successful')
            ->whereYear('created_at', $year)
            ->when(! $isSuperadmin || $request->filled('organization_id'), function ($q) use ($orgId, $isSuperadmin, $request, $branchIds) {
                $q->whereHas('user', fn ($uq) => $uq->withoutGlobalScope(OrganizationScope::class)
                    ->where('current_organization_id', $isSuperadmin ? (int) $request->organization_id : $orgId)
                    ->when($branchIds !== [], fn ($userQuery) => $userQuery->whereIn('branch_id', $branchIds)));
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
            $counts = User::withoutGlobalScope(OrganizationScope::class)
                ->whereIn('current_organization_id', array_keys($orgIds))
                ->when($branchIds !== [], fn ($q) => $q->whereIn('branch_id', $branchIds))
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
     * Halaman utama kehadiran: senarai program sahaja (tiada nama peserta).
     * Setiap kad membawa ringkasan peserta/hadir/tidak hadir. Klik kad untuk
     * masuk ke halaman butiran program.
     */
    public function attendanceIndex(Request $request, User $user): array
    {
        $isSuperadmin = $user->hasRole('Superadmin');
        $branchIds = $user->isBranchAdmin() ? $user->managedBranchIds() : [];

        // Sekatan skop: admin biasa hanya nampak pendaftaran organisasinya.
        $restrict = function (Builder $query) use ($user, $isSuperadmin, $branchIds): Builder {
            if ($branchIds !== []) {
                $query->whereHas('event', fn ($q) => $q->whereIn('branch_id', $branchIds));
            } elseif (! $isSuperadmin) {
                $orgId = (int) $user->current_organization_id;
                $query->where(function (Builder $q) use ($orgId) {
                    $q->where('organization_id', $orgId)
                        ->orWhereHas('event', fn ($q2) => $q2->where('organization_id', $orgId)
                            ->orWhereHas('organizations', fn ($q3) => $q3->where('organizations.id', $orgId)));
                });
            }

            return $query;
        };

        // Skop senarai program: event milik org ATAU event yang mempunyai
        // pendaftaran daripada org (cth program global yang disertai ahli org).
        $eventScope = function () use ($user, $isSuperadmin, $branchIds): Builder {
            $query = Event::query();

            if ($branchIds !== []) {
                $query->whereIn('branch_id', $branchIds);
            } elseif (! $isSuperadmin) {
                $orgId = (int) $user->current_organization_id;
                $query->where(function (Builder $q) use ($orgId) {
                    $q->where('organization_id', $orgId)
                        ->orWhereHas('organizations', fn ($q2) => $q2->where('organizations.id', $orgId))
                        ->orWhereHas('registrations', fn ($r) => $r->where('organization_id', $orgId));
                });
            }

            return $query;
        };

        $programEvents = $eventScope()
            ->when($request->filled('org'), fn ($q) => $q->where('organization_id', (int) $request->org))
            ->when($request->filled('event_id'), fn ($q) => $q->where('id', (int) $request->event_id))
            ->when($request->filled('search'), fn ($q) => $q->where('title', 'like', '%'.$request->search.'%'))
            ->whereHas('registrations', fn ($q) => $restrict($q))
            ->with(['organization', 'organizations'])
            ->get();

        $now = now();

        // Susunan: program yang baru selesai di atas (End Date terbaru), diikuti
        // program sedang berjalan, kemudian program akan datang (terdekat dulu).
        $programEvents = $programEvents
            ->sort(function (Event $a, Event $b) use ($now) {
                $rank = function (Event $event) use ($now): int {
                    if ($event->end_time !== null && $event->end_time->lt($now)) {
                        return 0;
                    }

                    if ($event->start_time !== null && $event->start_time->lte($now)) {
                        return 1;
                    }

                    return 2;
                };

                $rankA = $rank($a);
                $rankB = $rank($b);

                if ($rankA !== $rankB) {
                    return $rankA <=> $rankB;
                }

                if ($rankA === 0) {
                    return ($b->end_time?->timestamp ?? 0) <=> ($a->end_time?->timestamp ?? 0);
                }

                if ($rankA === 1) {
                    return ($b->start_time?->timestamp ?? 0) <=> ($a->start_time?->timestamp ?? 0);
                }

                return ($a->start_time?->timestamp ?? 0) <=> ($b->start_time?->timestamp ?? 0);
            })
            ->values();

        $eventIds = $programEvents->pluck('id')->all();

        $dedupe = app(RegistrationDedupeService::class);
        $identityColumns = ['id', 'event_id', 'name', 'email', 'phone', 'ic_number'];

        $countsFor = function (Builder $query) use ($restrict, $eventIds, $identityColumns, $dedupe): array {
            $query = $restrict($query->whereIn('event_id', $eventIds));

            return $dedupe->uniqueCountsByEvent($query->get($identityColumns));
        };

        $registeredByEvent = $countsFor(Registration::query());
        $attendedByEvent = $countsFor(Registration::query()->whereHas('attendance'));
        $pendingByEvent = $countsFor(
            Registration::query()->whereHas('latestPayment', fn ($q) => $q->where('status', 'pending'))
        );

        $programs = $programEvents
            ->map(function (Event $e) use ($now, $user, $isSuperadmin, $registeredByEvent, $attendedByEvent, $pendingByEvent) {
                $registered = (int) ($registeredByEvent[$e->id] ?? 0);
                $attended = (int) ($attendedByEvent[$e->id] ?? 0);
                $isEnded = $e->end_time !== null && $e->end_time->lt($now);
                $isOngoing = $e->start_time !== null && $e->end_time !== null
                    && $e->start_time->lte($now) && $e->end_time->gte($now);
                $canOpenQr = $isSuperadmin
                    || (int) $e->organization_id === (int) $user->current_organization_id
                    || $e->organizations->contains(fn ($org) => (int) $org->id === (int) $user->current_organization_id);
                $qrExpired = $e->attendanceClosesAt() !== null && now()->gt($e->attendanceClosesAt());

                return [
                    'id' => $e->id,
                    'title' => $e->title,
                    'slug' => $e->slug,
                    'status' => $e->status->value,
                    'status_label' => $e->status->label(),
                    'start_formatted' => $e->start_time?->locale('ms')->isoFormat('D MMM YYYY, h:mm A'),
                    'end_formatted' => $e->end_time?->locale('ms')->isoFormat('D MMM YYYY, h:mm A'),
                    'end_time' => $e->end_time?->toISOString(),
                    'attendance_closes_at' => $e->attendanceClosesAt()?->toISOString(),
                    'state' => $isEnded ? 'completed' : ($isOngoing ? 'ongoing' : 'upcoming'),
                    'is_ended' => $isEnded,
                    'is_ongoing' => $isOngoing,
                    'location_or_link' => $e->location_or_link,
                    'organization_name' => $e->organization?->name ?? 'Semua Organisasi',
                    'registered_count' => $registered,
                    'attended_count' => $attended,
                    'not_attended_count' => max(0, $registered - $attended),
                    'pending_payment_count' => (int) ($pendingByEvent[$e->id] ?? 0),
                    'attendance_url' => route('admin.attendance.show', $e->id),
                    'registrations_url' => route('admin.events.registrations', $e->id),
                    'qr_url' => ($canOpenQr && ! $qrExpired) ? route('events.qr', $e->id) : null,
                ];
            })
            ->values();

        $uniqueTotal = function (Builder $query) use ($dedupe, $restrict, $identityColumns, $eventIds): int {
            $query = $restrict($query->whereIn('event_id', $eventIds));

            return array_sum($dedupe->uniqueCountsByEvent($query->get($identityColumns)));
        };

        $totalRegistered = $uniqueTotal(Registration::query());
        $totalAttended = $uniqueTotal(Registration::query()->whereHas('attendance'));
        $totalPending = $uniqueTotal(
            Registration::query()->whereHas('latestPayment', fn ($q) => $q->where('status', 'pending'))
        );

        return [
            'stats' => [
                'total_programs' => count($eventIds),
                'total_registered' => $totalRegistered,
                'total_attended' => $totalAttended,
                'total_not_attended' => max(0, $totalRegistered - $totalAttended),
                'total_pending_payment' => $totalPending,
            ],
            'programs' => $programs,
            'organizations' => $isSuperadmin
                ? Organization::orderBy('min_age')->get(['id', 'name'])
                : [],
            'filters' => $request->only(['org', 'search']),
        ];
    }

    /**
     * Butiran kehadiran satu program: statistik + senarai peserta boleh
     * ditapis (Hadir / Tidak Hadir / Semua, carian nama/IC, ahli/bukan ahli).
     */
    public function attendanceDetail(Request $request, Event $event, User $user): array
    {
        $this->assertCanManageEvent($user, $event);

        $attendanceFilter = $request->query('attendance');
        $attendanceFilter = in_array($attendanceFilter, ['hadir', 'tidak_hadir', 'all'], true)
            ? $attendanceFilter
            : 'hadir';

        $typeFilter = $request->query('type');
        $typeFilter = in_array($typeFilter, ['ahli', 'bukan_ahli'], true) ? $typeFilter : '';

        $search = trim((string) $request->query('search', ''));

        $base = Registration::query()->where('event_id', $event->id);

        // Kekalkan skop org yang sama seperti senarai program: admin biasa hanya
        // nampak pendaftaran organisasinya (kecuali event milik/bersama org).
        if ($user->isBranchAdmin()) {
            $base->whereHas('event', fn ($q) => $q->whereIn('branch_id', $user->managedBranchIds()));
        } elseif (! $user->hasRole('Superadmin')) {
            $orgId = (int) $user->current_organization_id;
            $base->where(function (Builder $q) use ($orgId) {
                $q->where('organization_id', $orgId)
                    ->orWhereHas('event', fn ($q2) => $q2->where('organization_id', $orgId)
                        ->orWhereHas('organizations', fn ($q3) => $q3->where('organizations.id', $orgId)));
            });
        }

        $listQuery = (clone $base)->with(['organization', 'event.organization', 'attendance', 'latestPayment']);

        if ($attendanceFilter === 'hadir') {
            $listQuery->whereHas('attendance');
        } elseif ($attendanceFilter === 'tidak_hadir') {
            $listQuery->whereDoesntHave('attendance');
        }

        if ($typeFilter === 'ahli') {
            $listQuery->whereNotNull('user_id');
        } elseif ($typeFilter === 'bukan_ahli') {
            $listQuery->whereNull('user_id');
        }

        if ($search !== '') {
            $digits = preg_replace('/\D+/', '', $search) ?? '';
            $listQuery->where(function (Builder $q) use ($search, $digits) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('registration_no', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('member_no', 'like', "%{$search}%")
                    ->orWhere('ic_number', 'like', "%{$search}%");

                if ($digits !== '') {
                    $q->orWhere('ic_normalized', $digits)
                        ->orWhere('phone_normalized', $digits);
                }
            });
        }

        $registrations = $listQuery
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Registration $r) => [
                'id' => $r->id,
                'registration_no' => $r->registration_no,
                'name' => $r->name,
                'email' => $r->email,
                'phone' => $r->phone,
                'ic_number' => $r->ic_number,
                'member_no' => $r->member_no,
                'is_member' => $r->user_id !== null,
                'organization_name' => $r->organization?->name ?? $r->event?->organization?->name,
                'payment_status' => $r->latestPayment?->status ?? 'paid',
                'attended' => $r->attendance !== null,
                'attended_at' => $r->attendance?->attended_at?->toDateTimeString(),
                'method' => $r->attendance?->method,
                'recorded_by' => $r->attendance?->recorded_by,
            ]);

        $dedupe = app(RegistrationDedupeService::class);
        $totalRegistered = $dedupe->uniqueCount(clone $base);
        $totalAttended = $dedupe->uniqueCount((clone $base)->whereHas('attendance'));

        $auditTrail = ActivityLog::query()
            ->where(function (Builder $q) use ($event, $base) {
                $q->where(function (Builder $q2) use ($base) {
                    $q2->where('target_type', Registration::class)
                        ->whereIn('target_id', (clone $base)->select('id'))
                        ->whereIn('action', [
                            'attendance_manual_set',
                            'attendance_manual_clear',
                            'attendance_participant_add',
                        ]);
                })->orWhere(function (Builder $q2) use ($event) {
                    $q2->where('target_type', Event::class)
                        ->where('target_id', $event->id)
                        ->whereIn('action', ['attendance_bulk_set', 'attendance_bulk_clear']);
                });
            })
            ->with('user:id,name')
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (ActivityLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'actor' => $log->user?->name,
                'created_at' => $log->created_at?->toDateTimeString(),
            ])
            ->values();

        $canOpenQr = ($user->hasRole('Superadmin')
            || (int) $event->organization_id === (int) $user->current_organization_id
            || $event->organizations()->where('organizations.id', $user->current_organization_id)->exists())
            && ! ($event->attendanceClosesAt() !== null && now()->gt($event->attendanceClosesAt()));

        return [
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'slug' => $event->slug,
                'status' => $event->status->value,
                'status_label' => $event->status->label(),
                'start_formatted' => $event->start_time?->locale('ms')->isoFormat('D MMM YYYY, h:mm A'),
                'end_formatted' => $event->end_time?->locale('ms')->isoFormat('D MMM YYYY, h:mm A'),
                'location_or_link' => $event->location_or_link,
                'organization_name' => $event->organization?->name ?? 'Semua Organisasi',
                'attendance_closes_at' => $event->attendanceClosesAt()?->toISOString(),
                'attendance_opens_at' => $event->attendanceOpensAt()?->toISOString(),
                'attendance_window_open' => $event->attendanceWindowIsOpen(),
                'is_ended' => $event->end_time !== null && $event->end_time->lt(now()),
                'registrations_url' => route('admin.events.registrations', $event->id),
            ],
            'stats' => [
                'total_registered' => $totalRegistered,
                'total_attended' => $totalAttended,
                'total_not_attended' => max(0, $totalRegistered - $totalAttended),
            ],
            'registrations' => $registrations,
            'audit_trail' => $auditTrail,
            'filters' => [
                'attendance' => $attendanceFilter,
                'type' => $typeFilter,
                'search' => $search,
            ],
            'qr_url' => $canOpenQr ? route('events.qr', $event->id) : null,
        ];
    }

    /**
     * Pembetulan manual: tandakan hadir atau batalkan rekod kehadiran.
     * Setiap tindakan direkodkan dalam ActivityLog sebagai audit trail.
     *
     * @return array{attended: bool, attended_at: ?string, method: ?string, message: string}
     */
    public function toggleAttendance(Event $event, Registration $registration, User $actor, bool $present): array
    {
        abort_unless((int) $registration->event_id === (int) $event->id, 404);
        $this->assertCanManageEvent($actor, $event);
        $this->assertRegistrationInScope($actor, $event, $registration);

        if ($present) {
            $attendance = $this->recordAttendance($registration, 'manual', $actor);
            $action = 'attendance_manual_set';
            $description = "Kehadiran manual direkodkan untuk {$registration->name} ({$event->title}).";
            $oldValues = null;
            $newValues = ['attended_at' => $attendance->attended_at?->toDateTimeString(), 'method' => 'manual'];
        } else {
            $existing = $registration->attendance;
            $registration->attendance()->delete();
            $this->clearProfileAttendance($registration);
            $attendance = null;
            $action = 'attendance_manual_clear';
            $description = "Rekod kehadiran {$registration->name} ({$event->title}) dibatalkan.";
            $oldValues = ['attended_at' => $existing?->attended_at?->toDateTimeString(), 'method' => $existing?->method];
            $newValues = null;
        }

        ActivityLog::create([
            'user_id' => $actor->id,
            'organization_id' => $actor->current_organization_id,
            'target_type' => Registration::class,
            'target_id' => $registration->id,
            'action' => $action,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);

        return [
            'attended' => $present,
            'attended_at' => $attendance?->attended_at?->toDateTimeString(),
            'method' => $present ? 'manual' : null,
            'message' => $present ? 'Kehadiran direkodkan.' : 'Rekod kehadiran dibatalkan.',
        ];
    }

    /**
     * Tanda hadir / batal secara pukal untuk peserta terpilih.
     *
     * @param  array<int, int>  $registrationIds
     * @return array{updated: int, skipped: int, present: bool}
     */
    public function bulkAttendance(Event $event, User $actor, array $registrationIds, bool $present): array
    {
        $this->assertCanManageEvent($actor, $event);

        $ids = collect($registrationIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $registrations = Registration::query()
            ->where('event_id', $event->id)
            ->whereIn('id', $ids)
            ->get();

        // Superadmin boleh urus semua; admin biasa hanya dalam skop org sendiri.
        if (! $actor->hasRole('Superadmin')) {
            $orgId = (int) $actor->current_organization_id;
            $ownsEvent = (int) $event->organization_id === $orgId
                || $event->organizations()->where('organizations.id', $orgId)->exists();

            if (! $ownsEvent) {
                $registrations = $registrations
                    ->filter(fn (Registration $registration) => (int) $registration->organization_id === $orgId)
                    ->values();
            }
        }

        foreach ($registrations as $registration) {
            if ($present) {
                $this->recordAttendance($registration, 'manual', $actor);
            } else {
                $registration->attendance()->delete();
                $this->clearProfileAttendance($registration);
            }
        }

        $count = $registrations->count();
        $skipped = max(0, $ids->count() - $count);

        if ($count > 0) {
            ActivityLog::create([
                'user_id' => $actor->id,
                'organization_id' => $actor->current_organization_id,
                'target_type' => Event::class,
                'target_id' => $event->id,
                'action' => $present ? 'attendance_bulk_set' : 'attendance_bulk_clear',
                'description' => ($present ? 'Kehadiran pukal direkodkan' : 'Rekod kehadiran pukal dibatalkan')
                    ." untuk {$count} peserta ({$event->title}).",
                'old_values' => null,
                'new_values' => [
                    'registration_ids' => $registrations->pluck('id')->all(),
                    'present' => $present,
                ],
            ]);
        }

        return [
            'updated' => $count,
            'skipped' => $skipped,
            'present' => $present,
        ];
    }

    /**
     * Tambah peserta baharu secara manual dari halaman kehadiran.
     *
     * Bukan ahli disokong (user_id null). Jika No. IC sepadan dengan akaun,
     * rekod dipautkan dan sejarah profil diselaraskan. Rekod sedia ada untuk
     * event yang sama tidak diduplikasi.
     *
     * @param  array{name: string, ic_number?: ?string, phone?: ?string, email?: ?string, member_no?: ?string, mark_present?: bool}  $data
     * @return array{status: string, registration: Registration, attended: bool, message: string}
     */
    public function addParticipant(Event $event, User $actor, array $data): array
    {
        $this->assertCanManageEvent($actor, $event);

        $name = trim((string) $data['name']);
        $icNumber = isset($data['ic_number']) ? trim((string) $data['ic_number']) : '';
        $phone = isset($data['phone']) ? trim((string) $data['phone']) : '';
        $email = isset($data['email']) ? trim((string) $data['email']) : '';

        $existing = Registration::findExistingForEvent(
            $event->id,
            $icNumber !== '' ? $icNumber : null,
            $phone !== '' ? $phone : null,
            $email !== '' ? $email : null,
            $name,
        );

        // Passport / ID bukan Malaysia tidak dinormalisasi ke 12 digit, jadi
        // bandingkan nilai mentah untuk elak rekod pendua.
        if ($existing === null && $icNumber !== '' && Registration::normalizeIc($icNumber) === null) {
            $existing = Registration::query()
                ->where('event_id', $event->id)
                ->whereRaw('UPPER(ic_number) = ?', [mb_strtoupper($icNumber)])
                ->latest()
                ->first();
        }

        if ($existing !== null) {
            return [
                'status' => 'exists',
                'registration' => $existing,
                'attended' => $existing->attendance !== null,
                'message' => 'Peserta ini sudah ada dalam senarai program.',
            ];
        }

        $userId = $icNumber !== '' ? $this->matchUserByIc($icNumber) : null;
        $matchedUser = $userId !== null
            ? User::withoutGlobalScope(OrganizationScope::class)->find($userId)
            : null;

        if ($actor->isBranchAdmin()) {
            if (! $matchedUser || ! $actor->canManageBranch($matchedUser->branch_id)) {
                throw ValidationException::withMessages([
                    'ic_number' => 'Peserta mesti ahli cawangan ini.',
                ]);
            }
        }

        $registration = Registration::create([
            'event_id' => $event->id,
            'user_id' => $userId,
            'organization_id' => $actor->current_organization_id ?? $event->organization_id,
            'member_no' => ($data['member_no'] ?? null) ?: $matchedUser?->member_no,
            'name' => $name,
            'email' => $email !== '' ? $email : null,
            'phone' => $phone !== '' ? $phone : null,
            'ic_number' => $icNumber !== '' ? $icNumber : null,
            'status' => RegistrationStatus::WalkIn,
            'source' => Registration::SOURCE_MANUAL,
        ]);

        $markPresent = $data['mark_present'] ?? true;

        if ($markPresent) {
            $this->recordAttendance($registration, 'manual', $actor);
        }

        ActivityLog::create([
            'user_id' => $actor->id,
            'organization_id' => $actor->current_organization_id,
            'target_type' => Registration::class,
            'target_id' => $registration->id,
            'action' => 'attendance_participant_add',
            'description' => "Peserta manual ditambah: {$registration->name} ({$event->title}).",
            'old_values' => null,
            'new_values' => ['attended' => (bool) $markPresent, 'user_id' => $userId],
        ]);

        return [
            'status' => 'created',
            'registration' => $registration,
            'attended' => (bool) $markPresent,
            'message' => $markPresent
                ? 'Peserta ditambah dan kehadiran direkodkan.'
                : 'Peserta ditambah.',
        ];
    }

    /**
     * Admin organisasi layak jika event milik org sendiri, tersenarai dalam
     * pivot organisasi terlibat, atau mempunyai pendaftaran daripada org.
     */
    protected function assertCanManageEvent(User $user, Event $event): void
    {
        if ($user->hasRole('Superadmin')) {
            return;
        }

        if ($user->isBranchAdmin()) {
            abort_unless(
                $user->canManageBranch($event->branch_id),
                403,
                'Tiada kebenaran.'
            );

            return;
        }

        $orgId = (int) $user->current_organization_id;

        $owns = (int) $event->organization_id === $orgId
            || $event->organizations()->where('organizations.id', $orgId)->exists()
            || $event->registrations()->where('organization_id', $orgId)->exists();

        abort_unless($owns, 403, 'Tiada kebenaran.');
    }

    /**
     * Admin biasa tidak boleh mengubah peserta organisasi lain pada event
     * global yang bukan milik/bersama organisasinya.
     */
    protected function assertRegistrationInScope(User $user, Event $event, Registration $registration): void
    {
        if ($user->hasRole('Superadmin') || $user->isBranchAdmin()) {
            return;
        }

        $orgId = (int) $user->current_organization_id;

        $inScope = (int) $registration->organization_id === $orgId
            || (int) $event->organization_id === $orgId
            || $event->organizations()->where('organizations.id', $orgId)->exists();

        abort_unless($inScope, 403, 'Tiada kebenaran.');
    }

    /**
     * Senarai pendaftaran + statistik untuk satu event (API admin).
     */
    public function attendanceRegistrations(int $eventId, User $user): array
    {
        $event = Event::with(['organization', 'organizations'])->findOrFail($eventId);

        if (! $user->isSuperadmin()) {
            $orgId = (int) $user->current_organization_id;
            $ownsEvent = (int) $event->organization_id === $orgId
                || $event->organizations->contains(fn ($org) => (int) $org->id === $orgId);

            abort_unless($ownsEvent, 403, 'Tiada kebenaran.');
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
                'total_registered' => app(RegistrationDedupeService::class)
                    ->uniqueCount(Registration::where('event_id', $eventId)),
                'attended_count' => app(RegistrationDedupeService::class)
                    ->uniqueCount(Registration::where('event_id', $eventId)->whereHas('attendance')),
            ],
            'registrations' => $registrations,
        ];
    }

    /**
     * Scan kehadiran oleh admin (API). identifier = member_no | ic_number | phone.
     * Ahli yang belum mendaftar tetapi kadnya sah akan didaftar sebagai walk-in.
     */
    public function scanAttendance(User $user, int $eventId, string $identifier): array
    {
        $event = Event::findOrFail($eventId);

        // Selaraskan dengan akses halaman kehadiran: admin bersama (pivot) juga
        // boleh mengimbas, bukan hanya pemilik event.
        $this->assertCanManageEvent($user, $event);

        $registration = Registration::findForIdentity($event->id, $identifier)
            ?? $this->walkInFromCardScan($event, $identifier);

        if (! $registration) {
            return ['status' => 'error', 'message' => 'Tiada rekod pendaftaran dijumpai. Sila semak semula maklumat.', 'registration' => null];
        }

        if ($block = $this->registrationBlockReason($registration)) {
            return ['status' => 'error', 'message' => $block, 'registration' => null];
        }

        if ($registration->attendance) {
            return ['status' => 'error', 'message' => 'Kehadiran telah direkodkan sebelum ini.', 'registration' => null];
        }

        $attendance = $this->recordAttendance($registration, 'manual', $user);

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
        $phoneNormalized = $user->phone ? Registration::normalizePhone($user->phone) : null;
        $icNormalized = $user->ic_number ? Registration::normalizeIc($user->ic_number) : null;

        return Registration::where('event_id', $event->id)
            ->where(function (Builder $q) use ($user, $phoneNormalized, $icNormalized) {
                $q->where('user_id', $user->id);

                if ($user->email) {
                    $q->orWhereRaw('LOWER(email) = ?', [mb_strtolower($user->email)]);
                }

                if ($phoneNormalized !== null) {
                    $q->orWhere('phone_normalized', $phoneNormalized)
                        ->orWhere('phone', $user->phone);
                }

                if ($icNormalized !== null) {
                    $q->orWhere('ic_normalized', $icNormalized);
                }
            })
            ->latest()
            ->first();
    }

    /**
     * Cipta pendaftaran walk-in untuk ahli yang mengimbas QR kehadiran tanpa
     * pendaftaran terdahulu.
     *
     * Konteks: urusetia memaparkan QR poster (mengandungi `attendance_token`
     * rahsia). Menayangkan QR itu = sengaja menjemput kehadiran, jadi ahli
     * yang mengimbas dibenarkan hadir tanpa mendaftar. Rekod ini ditandakan
     * `status = walkin` supaya laporan boleh membezakannya daripada
     * pendaftaran rasmi.
     */
    public function createWalkInRegistration(Event $event, $user): Registration
    {
        return Registration::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'organization_id' => $user->current_organization_id
                ?? $event->organization_id,
            'member_no' => $user->member_no,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'ic_number' => $user->ic_number,
            'status' => RegistrationStatus::WalkIn,
            'source' => Registration::SOURCE_WALKIN,
        ]);
    }

    /**
     * Cari pendaftaran ahli, atau cipta walk-in jika tiada. Digunakan oleh
     * aliran imbasan QR kehadiran (web & API).
     */
    public function findOrCreateRegistration(Event $event, $user): Registration
    {
        return $this->findRegistration($event, $user)
            ?? $this->createWalkInRegistration($event, $user);
    }

    /**
     * Imbasan kad ahli yang belum mendaftar: ahli sah didaftar sebagai walk-in
     * secara automatik sebelum kehadiran direkodkan.
     */
    protected function walkInFromCardScan(Event $event, string $identifier): ?Registration
    {
        $member = $this->findMemberByIdentifier($identifier);

        // Bukan ahli: jangan cipta rekod automatik. Daftar manual sebagai
        // peserta tetamu, dan Pendukung dicipta oleh RegistrationObserver.
        if (! $member) {
            return null;
        }

        // Elak pendua bila pendaftaran sedia ada disimpan di bawah emel/telefon/IC.
        return $this->findRegistration($event, $member)
            ?? $this->createWalkInRegistration($event, $member);
    }

    /**
     * Cari akaun ahli daripada nilai imbasan: URL kad, No. ahli, emel, IC atau telefon.
     */
    protected function findMemberByIdentifier(string $identifier): ?User
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        $memberNo = Registration::extractMemberNo($identifier) ?? $identifier;

        if ($member = $this->findMemberByMemberNo($memberNo)) {
            return $member;
        }

        if (str_contains($identifier, '@')) {
            return User::withoutGlobalScope(OrganizationScope::class)
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($identifier)])
                ->first();
        }

        if ($userId = $this->matchUserByIc($identifier)) {
            return User::withoutGlobalScope(OrganizationScope::class)->find($userId);
        }

        $phone = Registration::normalizePhone($identifier);

        return $phone !== null ? $this->findMemberByPhone($phone) : null;
    }

    protected function findMemberByMemberNo(string $memberNo): ?User
    {
        return User::withoutGlobalScope(OrganizationScope::class)
            ->where('member_no', $memberNo)
            ->first();
    }

    /**
     * Padan telefon ahli tanpa bergantung pada format simpanan (dashes, +60, dll).
     */
    protected function findMemberByPhone(string $normalized): ?User
    {
        $local = ltrim($normalized, '0');

        $variants = array_values(array_unique(array_filter([
            $normalized,
            $local,
            $local !== '' ? '60'.$local : null,
        ])));

        if ($variants === []) {
            return null;
        }

        return User::withoutGlobalScope(OrganizationScope::class)
            ->whereNotNull('phone')
            ->where(function (Builder $query) use ($variants) {
                foreach ($variants as $variant) {
                    $query->orWhereRaw(
                        "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, '-', ''), ' ', ''), '+', ''), '.', ''), '(', ''), ')', '') = ?",
                        [$variant],
                    );
                }
            })
            ->first();
    }

    /**
     * Cari pendaftaran tetamu sedia ada untuk event ini berdasarkan telefon
     * atau emel (tanpa akaun). Digunakan oleh walk-in tetamu supaya orang yang
     * sama tidak mencipta pendaftaran berulang.
     */
    public function findGuestRegistration(Event $event, ?string $phone, ?string $email): ?Registration
    {
        $phone = $phone !== null ? trim($phone) : '';
        $email = $email !== null ? trim($email) : '';

        if ($phone === '' && $email === '') {
            return null;
        }

        $phoneNormalized = $phone !== '' ? Registration::normalizePhone($phone) : null;

        return Registration::where('event_id', $event->id)
            ->where(function (Builder $q) use ($phone, $phoneNormalized, $email) {
                if ($phoneNormalized !== null) {
                    $q->orWhere('phone_normalized', $phoneNormalized)
                        ->orWhere('phone', $phone);
                }
                if ($email !== '') {
                    $q->orWhereRaw('LOWER(email) = ?', [mb_strtolower($email)]);
                }
            })
            ->latest()
            ->first();
    }

    /**
     * Cipta pendaftaran walk-in untuk tetamu (bukan ahli, tiada akaun) yang
     * mengisi nama + telefon + emel di halaman kehadiran web.
     *
     * Sama prinsip dengan [createWalkInRegistration]: urusetia yang menayangkan
     * QR = sengaja menjemput kehadiran. `user_id = null` menandakan tetamu.
     *
     * @param  array{name: string, phone: string, email?: string|null}  $data
     */
    public function createGuestWalkInRegistration(Event $event, array $data): Registration
    {
        return Registration::create([
            'event_id' => $event->id,
            'user_id' => null,
            'organization_id' => $event->organization_id,
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'status' => RegistrationStatus::WalkIn,
            'source' => Registration::SOURCE_WALKIN,
        ]);
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

    public function recordAttendance(Registration $registration, string $method, ?User $actor = null): Attendance
    {
        $attendance = Attendance::updateOrCreate(
            ['registration_id' => $registration->id],
            [
                'event_id' => $registration->event_id,
                'attended_at' => now(),
                'method' => $method,
                'recorded_by' => $method === 'manual' ? $actor?->id : null,
            ],
        );

        // Kehadiran sebenar mesti muncul dalam sejarah penyertaan profil ahli.
        // Sebelum ini dua jadual (`attendances` dan `event_rsvps`) tidak disambung.
        $this->syncProfileAttendance($registration, $attendance->attended_at ?? now());

        return $attendance;
    }

    /**
     * Selaraskan rekod kehadiran ke profil individu (EventRsvp status=attended).
     *
     * Ahli (user_id wujud) terus diselaraskan. Peserta bukan ahli yang No. IC
     * sepadan dengan akaun pengguna akan dipautkan, supaya rekod kehadiran tetap
     * masuk sejarah profil mereka. Bukan ahli tanpa akaun kekal dalam
     * `attendances` sahaja.
     */
    protected function syncProfileAttendance(Registration $registration, \DateTimeInterface $attendedAt): void
    {
        $userId = $registration->user_id;

        if ($userId === null) {
            $userId = $this->matchUserByIc($registration->ic_number);

            if ($userId !== null) {
                $registration->forceFill(['user_id' => $userId])->saveQuietly();
            }
        }

        if ($userId === null) {
            return;
        }

        $rsvp = EventRsvp::firstOrNew([
            'event_id' => $registration->event_id,
            'user_id' => $userId,
        ]);

        $rsvp->status = 'attended';
        $rsvp->attended_at = $rsvp->attended_at ?? $attendedAt;
        $rsvp->save();
    }

    /**
     * Elakkan sejarah profil tersasar apabila admin membatalkan rekod kehadiran
     * yang sebelum ini dihasilkan oleh kehadiran (bukan RSVP manual).
     */
    protected function clearProfileAttendance(Registration $registration): void
    {
        if ($registration->user_id === null) {
            return;
        }

        EventRsvp::query()
            ->where('event_id', $registration->event_id)
            ->where('user_id', $registration->user_id)
            ->where('status', 'attended')
            ->delete();
    }

    /**
     * Padanan No. IC tepat: normalize ke 12 digit dan bandingkan dengan
     * `users.ic_number` (yang mungkin disimpan dengan sengkang/ruang).
     */
    protected function matchUserByIc(?string $icNumber): ?int
    {
        $digits = Registration::normalizeIc($icNumber);

        if ($digits === null) {
            return null;
        }

        return User::withoutGlobalScope(OrganizationScope::class)
            ->whereNotNull('ic_number')
            ->whereRaw("REPLACE(REPLACE(REPLACE(ic_number, '-', ''), ' ', ''), '.', '') = ?", [$digits])
            ->value('id');
    }

    // ─── Siaran (Broadcast) ───────────────────────────────────────────────────

    /**
     * Cipta BroadcastMessage + dispatch job (dikongsi web & API).
     */
    public function broadcast(array $input): BroadcastMessage
    {
        $message = BroadcastMessage::create([
            'organization_id' => $input['organization_id'],
            'sender_label' => $input['sender_label'] ?? null,
            'target_organization_id' => $input['target_organization_id'] ?? null,
            'branch_id' => $input['branch_id'] ?? null,
            'title' => $input['title'],
            'content' => $input['content'],
            'action_type' => $input['action_type'] ?? null,
            'action_id' => $input['action_id'] ?? null,
            'action_label' => $input['action_label'] ?? null,
            'action_url' => $input['action_url'] ?? null,
            'target_criteria' => $input['target_criteria'],
            'recipient_ids' => $input['recipient_ids'] ?? null,
            'notification_channels' => $input['notification_channels'] ?? ['in_app'],
            'email_use_template' => $input['email_use_template'] ?? false,
        ]);

        SendBroadcastJob::dispatch($message->id);

        BroadcastLog::create([
            'broadcast_message_id' => $message->id,
            'event' => 'queued',
            'message' => 'Siaran dimasukkan ke dalam giliran pemprosesan.',
        ]);

        return $message;
    }

    /**
     * Terjemah audience API kepada [target_criteria, target_organization_id, recipient_ids].
     */
    public function resolveAudience(User $user, string $audience, ?int $organizationId = null): array
    {
        // 'all' untuk org-admin terhad kepada organisasi sendiri (bukan seluruh platform).
        if ($audience === 'all' && ! $user->hasRole('Superadmin')) {
            $audience = 'members';
        }

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
            ->when($user->isBranchAdmin(), fn ($q) => $q->whereIn('branch_id', $user->managedBranchIds()))
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

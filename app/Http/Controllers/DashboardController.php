<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\EventRsvp;
use App\Models\Event;
use App\Models\FacilityBooking;
use App\Models\Infaq;
use App\Models\LibraryItem;
use App\Models\Payment;
use App\Models\Branch;
use App\Models\NewsPost;
use App\Models\DashboardBanner;
use App\Models\UsrahGroup;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function landing(Request $request): Response
    {
        $banners = DashboardBanner::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->latest()
            ->get();

        $infaq = Infaq::query()
            ->where('is_active', true)
            ->latest()
            ->take(3)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'description' => $item->description,
                    'image_path' => $item->image_path,
                    'target_amount' => $item->target_amount,
                    'collected_amount' => $item->collected_amount,
                    'progress_percent' => $item->progress_percent,
                    'public_url' => $item->public_url,
                ];
            });

        $news = NewsPost::query()
            ->where('is_published', true)
            ->latest('published_at')
            ->take(3)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'slug' => $item->slug,
                    'excerpt' => $item->excerpt,
                    'cover_image_path' => $item->cover_image_path,
                    'published_at' => $item->published_at ? $item->published_at->format('d M Y') : null,
                ];
            });

        $articles = \App\Models\Article::query()
            ->where('is_published', true)
            ->latest('published_at')
            ->take(3)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'slug' => $item->slug,
                    'excerpt' => $item->excerpt,
                    'cover_image_path' => $item->cover_image_path,
                    'published_at' => $item->published_at ? $item->published_at->format('d M Y') : null,
                ];
            });

        $organizations = \App\Models\Organization::query()
            ->where('slug', '!=', 'management')
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'color_theme', 'logo_path', 'min_age', 'max_age']);

        return Inertia::render('Welcome', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'laravelVersion' => Application::VERSION,
            'phpVersion' => PHP_VERSION,
            'banners' => $banners,
            'infaq' => $infaq,
            'news' => $news,
            'articles' => $articles,
            'organizations' => $organizations,
        ]);
    }

    public function dashboardRedirect(Request $request): RedirectResponse
    {
        return redirect()->route($this->dashboardRouteFor($request->user()));
    }

    public function admin(Request $request): Response
    {
        $user = $request->user()->load('organization');
        $isSuperadmin = $user->hasRole('Superadmin');

        $totalMembers = User::withoutGlobalScopes()
            ->when(! $isSuperadmin, fn ($query) => $query->where('current_organization_id', $user->current_organization_id))
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

        $eventsCount = Event::query()
            ->when(! $isSuperadmin, fn ($query) => $query->where('organization_id', $user->current_organization_id))
            ->count();

        $infaqCount = Infaq::query()
            ->when(! $isSuperadmin, fn ($query) => $query->where(function ($q) use ($user) {
                $q->where('organization_id', $user->current_organization_id)
                  ->orWhereNull('organization_id');
            }))
            ->count();

        $programChart = [
            ['label' => 'Program', 'value' => $eventsCount],
            ['label' => 'Campaigns', 'value' => $activeCampaigns->count()],
            ['label' => 'Infaq', 'value' => $infaqCount],
        ];

        $newMembersLast30Days = User::withoutGlobalScopes()
            ->when(! $isSuperadmin, fn ($query) => $query->where('current_organization_id', $user->current_organization_id))
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $newMembersPrevious30Days = User::withoutGlobalScopes()
            ->when(! $isSuperadmin, fn ($query) => $query->where('current_organization_id', $user->current_organization_id))
            ->whereBetween('created_at', [now()->subDays(60), now()->subDays(30)])
            ->count();

        $newMembersTrendPercent = $newMembersPrevious30Days > 0
            ? round((($newMembersLast30Days - $newMembersPrevious30Days) / $newMembersPrevious30Days) * 100, 1)
            : ($newMembersLast30Days > 0 ? 100.0 : 0.0);

        $eventsThisMonth = Event::query()
            ->when(! $isSuperadmin, fn ($query) => $query->where('organization_id', $user->current_organization_id))
            ->whereBetween('start_time', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        $pendingFacilityBookings = FacilityBooking::query()
            ->where('booking_status', 'pending')
            ->when(! $isSuperadmin, function ($query) use ($user) {
                $query->whereHas('facility', fn ($facilityQuery) => $facilityQuery->where('organization_id', $user->current_organization_id));
            })
            ->count();

        $activeBranches = Branch::query()
            ->where('is_active', true)
            ->when(! $isSuperadmin, fn ($query) => $query->where('organization_id', $user->current_organization_id))
            ->count();

        $feesDueCount = User::withoutGlobalScopes()
            ->when(! $isSuperadmin, fn ($query) => $query->where('current_organization_id', $user->current_organization_id))
            ->whereDoesntHave('payments', function ($query) {
                $query->where('status', 'successful')
                    ->where('payable_type', 'membership_fee')
                    ->whereYear('created_at', now()->year);
            })
            ->count();

        $topBranches = Branch::query()
            ->with(['organization:id,name,slug'])
            ->withCount('members')
            ->when(! $isSuperadmin, fn ($query) => $query->where('organization_id', $user->current_organization_id))
            ->orderByDesc('members_count')
            ->orderBy('name')
            ->take(6)
            ->get()
            ->map(fn (Branch $branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
                'organization_name' => $branch->organization?->name ?? 'Organisasi',
                'state' => $branch->state,
                'members_count' => (int) $branch->members_count,
            ]);

        $alerts = collect();

        if ($pendingFacilityBookings > 0) {
            $alerts->push([
                'type' => $pendingFacilityBookings >= 10 ? 'high' : 'medium',
                'title' => 'Tempahan Ruang Belum Diproses',
                'description' => "{$pendingFacilityBookings} tempahan masih berstatus pending.",
            ]);
        }

        if ($feesDueCount > 0) {
            $alerts->push([
                'type' => $feesDueCount >= 50 ? 'high' : 'medium',
                'title' => 'Yuran Tertunggak',
                'description' => "{$feesDueCount} ahli belum membuat bayaran yuran untuk tahun ini.",
            ]);
        }

        if ($eventsThisMonth === 0) {
            $alerts->push([
                'type' => 'medium',
                'title' => 'Program Bulan Ini',
                'description' => 'Tiada program berjadual bulan ini. Pertimbangkan perancangan segera.',
            ]);
        }

        $recentActivities = collect();

        $latestMembers = User::withoutGlobalScopes()
            ->when(! $isSuperadmin, fn ($query) => $query->where('current_organization_id', $user->current_organization_id))
            ->latest('created_at')
            ->take(4)
            ->get(['id', 'name', 'created_at']);

        foreach ($latestMembers as $member) {
            $recentActivities->push([
                'id' => 'member-' . $member->id,
                'type' => 'member',
                'title' => 'Ahli baharu didaftarkan',
                'description' => $member->name,
                'created_at' => $member->created_at?->toDateTimeString(),
            ]);
        }

        $latestPayments = Payment::query()
            ->where('status', 'successful')
            ->when(! $isSuperadmin, function ($query) use ($user) {
                $query->whereHas('user', fn ($innerQuery) => $innerQuery
                    ->withoutGlobalScopes()
                    ->where('current_organization_id', $user->current_organization_id));
            })
            ->latest('created_at')
            ->take(4)
            ->get(['id', 'amount', 'payable_type', 'created_at']);

        foreach ($latestPayments as $payment) {
            $recentActivities->push([
                'id' => 'payment-' . $payment->id,
                'type' => 'payment',
                'title' => 'Bayaran berjaya diterima',
                'description' => strtoupper((string) $payment->payable_type) . ' · RM ' . number_format((float) $payment->amount, 2),
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
                'id' => 'booking-' . $booking->id,
                'type' => 'booking',
                'title' => 'Tempahan ruang dikemaskini',
                'description' => 'Status: ' . ucfirst((string) $booking->booking_status),
                'created_at' => $booking->created_at?->toDateTimeString(),
            ]);
        }

        $recentActivities = $recentActivities
            ->sortByDesc('created_at')
            ->take(8)
            ->values();

        return Inertia::render('Admin/Dashboard', [
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
                'active_branches' => $activeBranches,
                'alerts' => $alerts->values(),
                'recent_activities' => $recentActivities,
                'top_branches' => $topBranches,
            ],
            'managementLinks' => [
                'create_event_url' => route('events.index'),
                'create_program_url' => route('events.index'),
                'create_campaign_url' => route('admin.campaigns.store'),
                'campaigns_url' => route('admin.campaigns.index'),
                'infaq_url' => $isSuperadmin ? route('superadmin.infaq.index') : route('admin.campaigns.index'),
                'banners_url' => $isSuperadmin ? route('superadmin.banners.index') : null,
                'information_hub_manage_url' => route('admin.hub.manage'),
                'usrah_manage_url' => route('admin.usrah.index'),
                'broadcasts_url' => route('admin.broadcasts.index'),
                'directory_url' => route('directory.index'),
            ],
            'campaigns' => $activeCampaigns,
        ]);
    }

    public function member(Request $request): Response
    {
        $user = $request->user()->load('organization');

        $nextEventRsvp = EventRsvp::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['going', 'maybe'])
            ->whereHas('event', fn ($query) => $query->where('start_time', '>=', now()))
            ->with('event.organization')
            ->get()
            ->sortBy(fn (EventRsvp $rsvp) => $rsvp->event->start_time)
            ->first();

        $latestFeePayment = Payment::query()
            ->where('user_id', $user->id)
            ->where('status', 'successful')
            ->where('payable_type', 'membership_fee')
            ->latest('created_at')
            ->first();

        $feeIsActive = $latestFeePayment
            && $latestFeePayment->created_at->year === now()->year;

        $feeAmount = (float) ($user->organization?->fee_amount ?? 50.00);

        $usrahGroup = $user->usrahGroups()
            ->with(['members' => fn ($query) => $query->withoutGlobalScopes()->select('users.id', 'name')])
            ->first();

        $isNaqib = $usrahGroup
            ? $usrahGroup->members->contains(fn ($member) => (int) $member->id === (int) $user->id && (bool) $member->pivot->is_naqib)
            : false;

        $naqib = $usrahGroup
            ? $usrahGroup->members->first(fn ($member) => (bool) $member->pivot->is_naqib)
            : null;

        $campaigns = Campaign::query()
            ->where('organization_id', $user->current_organization_id)
            ->where('status', 'active')
            ->latest()
            ->take(2)
            ->get()
            ->map(fn (Campaign $campaign) => [
                'id' => $campaign->id,
                'title' => $campaign->title,
                'slug' => $campaign->slug,
                'description' => $campaign->description,
                'target_amount' => (float) $campaign->target_amount,
                'current_amount' => (float) $campaign->current_amount,
                'progress_percent' => $campaign->target_amount > 0
                    ? min(100, round(($campaign->current_amount / $campaign->target_amount) * 100))
                    : 0,
            ]);

        $libraryBooks = LibraryItem::query()
            ->where('organization_id', $user->current_organization_id)
            ->latest()
            ->take(12)
            ->get()
            ->map(fn (LibraryItem $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'category' => $item->category,
                'file_path' => $item->file_path,
                'cover_image_path' => $item->cover_image_path,
            ]);

        return Inertia::render('Member/Dashboard', [
            'member' => [
                'name' => $user->name,
                'organization' => [
                    'name' => $user->organization?->name,
                    'slug' => $user->organization?->slug,
                    'color_theme' => $user->organization?->color_theme,
                ],
            ],
            'feeStatus' => [
                'status'      => $feeIsActive ? 'active' : 'due',
                'amount_due'  => $feeIsActive ? 0 : $feeAmount,
                'last_paid_at'=> $latestFeePayment?->created_at?->toISOString(),
                'last_reference' => $latestFeePayment?->reference,
            ],
            'nextEvent' => $nextEventRsvp ? [
                'title' => $nextEventRsvp->event->title,
                'start_formatted' => $nextEventRsvp->event->start_time->locale('ms')->isoFormat('ddd, D MMM YYYY [•] h:mm A'),
                'location_or_link' => $nextEventRsvp->event->location_or_link,
                'status' => $nextEventRsvp->status,
            ] : null,
            'usrah' => $usrahGroup ? [
                'id' => $usrahGroup->id,
                'name' => $usrahGroup->name,
                'meeting_day' => $usrahGroup->meeting_day,
                'meeting_time' => $usrahGroup->meeting_time,
                'naqib_name' => $naqib?->name,
                'is_naqib' => $isNaqib,
                'members' => $usrahGroup->members->map(fn ($member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'is_naqib' => (bool) $member->pivot->is_naqib,
                ])->values(),
            ] : null,
            'campaigns' => $campaigns,
            'libraryBooks' => $libraryBooks,
        ]);
    }

    private function dashboardRouteFor(User $user): string
    {
        if ($user->hasRole(['Superadmin', 'Admin'])) {
            return 'admin.dashboard';
        }

        return 'member.dashboard';
    }
}

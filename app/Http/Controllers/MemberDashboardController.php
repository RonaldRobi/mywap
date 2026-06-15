<?php

namespace App\Http\Controllers;

use App\Actions\LoadUsrahForUser;
use App\Models\Campaign;
use App\Models\DashboardBanner;
use App\Models\EventRsvp;
use App\Models\Infaq;
use App\Models\LibraryItem;
use App\Models\NewsPost;
use App\Models\User;
use App\Services\FeeService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class MemberDashboardController extends Controller
{
    public function index(Request $request, FeeService $feeService): Response
    {
        $user = $request->user()->load('organization');

        $nextEventRsvp = EventRsvp::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['going', 'maybe'])
            ->whereHas('event', fn ($query) => $query->where('start_time', '>=', now()))
            ->with('event.organization')
            ->join('events', 'event_rsvps.event_id', '=', 'events.id')
            ->orderBy('events.start_time')
            ->select('event_rsvps.*')
            ->first();

        $feeStatus = $feeService->getStatus($user);
        $feeAmount = (float) ($user->organization?->fee_amount ?? 50.00);

        $usrahGroup = app(LoadUsrahForUser::class)->execute($user);

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

        $banners = DashboardBanner::query()
            ->where('is_active', true)
            ->where(function ($query) use ($user) {
                $query->whereNull('organization_id')
                    ->orWhere('organization_id', $user->current_organization_id);
            })
            ->orderBy('display_order')
            ->orderByDesc('id')
            ->get()
            ->map(fn (DashboardBanner $banner) => [
                'id' => $banner->id,
                'title' => $banner->title,
                'image_path' => $banner->image_path,
                'display_order' => $banner->display_order,
                'organization_id' => $banner->organization_id,
            ]);

        $latestNews = NewsPost::query()
            ->with(['category:id,name', 'organization:id,name'])
            ->where('is_published', true)
            ->where(function ($query) use ($user) {
                $query->whereNull('organization_id')
                    ->orWhere('organization_id', $user->current_organization_id);
            })
            ->where(function ($query) {
                $query->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->latest('published_at')
            ->latest('id')
            ->take(8)
            ->get()
            ->map(fn (NewsPost $post) => [
                'id' => $post->id,
                'title' => $post->title,
                'excerpt' => $post->excerpt,
                'cover_image_path' => $post->cover_image_path,
                'organization_name' => $post->organization?->name ?? 'Semua Organisasi',
                'category_name' => $post->category?->name ?? 'Umum',
                'published_at' => $post->published_at?->toDateString(),
            ]);

        // Infaq / donation campaigns (global or org-specific, active, max 6)
        $infaqItems = Infaq::query()
            ->where('is_active', true)
            ->where(function ($q) use ($user) {
                $q->whereNull('organization_id')
                  ->orWhere('organization_id', $user->current_organization_id);
            })
            ->orderBy('display_order')
            ->take(6)
            ->get()
            ->map(fn (Infaq $infaq) => [
                'id'               => $infaq->id,
                'title'            => $infaq->title,
                'description'      => $infaq->description,
                'image_path'       => $infaq->image_path,
                'type'             => $infaq->type,
                'target_amount'    => $infaq->target_amount,
                'collected_amount' => $infaq->collected_amount,
                'progress_percent' => $infaq->progress_percent,
                'public_url'       => $infaq->public_url,
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
            'feeStatus' => $feeStatus,
            'nextEvent' => $nextEventRsvp ? [
                'title' => $nextEventRsvp->event->title,
                'start_formatted' => $nextEventRsvp->event->start_time->locale('ms')->isoFormat('ddd, D MMM YYYY [•] h:mm A'),
                'location_or_link' => $nextEventRsvp->event->location_or_link,
                'status' => $nextEventRsvp->status,
            ] : null,
            'usrah' => $usrahGroup,
            'campaigns' => $campaigns,
            'libraryBooks' => $libraryBooks,
            'banners' => $banners,
            'infaqItems' => $infaqItems,
            'latestNews' => $latestNews,
        ]);
    }

    public function referral(Request $request): Response
    {
        $user = $request->user();

        $referredMembers = User::where('referred_by_user_id', $user->id)
            ->with('organization')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($member) {
                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'member_no' => $member->member_no,
                    'registered_at' => $member->created_at?->toISOString(),
                    'status' => $member->profile_completed_at ? 'active' : 'pending',
                    'organization' => $member->organization?->name,
                ];
            });

        $stats = [
            'total' => $referredMembers->count(),
            'active' => $referredMembers->where('status', 'active')->count(),
            'pending' => $referredMembers->where('status', 'pending')->count(),
        ];

        $referralLink = route('register', ['ref' => $user->member_no]);
        $qrCode = QrCode::format('svg')->size(300)->margin(2)->generate($referralLink);
        $qrCode = preg_replace('/^<\?xml.*?\?>\s*/', '', $qrCode);
        $qrCode = preg_replace('/\s(width|height)="\d+"/', '', $qrCode);

        return Inertia::render('Member/Referral', [
            'referralLink' => $referralLink,
            'memberNo' => $user->member_no,
            'qrSvg' => $qrCode,
            'stats' => $stats,
            'referredMembers' => $referredMembers,
        ]);
    }
}

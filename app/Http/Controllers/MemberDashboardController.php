<?php

namespace App\Http\Controllers;

use App\Actions\LoadUsrahForUser;
use App\Models\AppSetting;
use App\Models\Article;
use App\Models\Campaign;
use App\Models\DashboardBanner;
use App\Models\Event;
use App\Models\Popup;
use App\Models\EventRsvp;
use App\Models\Infaq;
use App\Models\LibraryItem;
use App\Models\NewsPost;
use App\Models\Poll;
use App\Models\PollResponse;
use App\Models\User;
use App\Models\Video;
use App\Services\FeeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class MemberDashboardController extends Controller
{
    public function index(Request $request, FeeService $feeService): Response
    {
        $user = $request->user()->load('organization');
        $setting = Schema::hasTable('app_settings')
            ? AppSetting::query()->first()
            : null;

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
                'link_url' => $banner->link_url,
                'link_target' => $banner->link_target,
                'display_order' => $banner->display_order,
                'organization_id' => $banner->organization_id,
            ]);

        $videos = Video::query()
            ->where(function ($query) use ($user) {
                $query->whereNull('organization_id')
                    ->orWhere('organization_id', $user->current_organization_id);
            })
            ->latest()
            ->take(10)
            ->get()
            ->map(fn (Video $video) => [
                'id' => $video->id,
                'title' => $video->title,
                'youtube_id' => $video->youtube_id,
                'thumbnail_url' => $video->thumbnail_url,
                'embed_url' => $video->embed_url,
            ]);

        $upcomingEvents = Event::with('organization')
            ->where('start_time', '>=', now())
            ->where(function ($q) use ($user) {
                $q->whereNull('organization_id')
                  ->orWhere('organization_id', $user->current_organization_id);
            })
            ->orderBy('start_time')
            ->take(5)
            ->get()
            ->map(function (Event $e) use ($user) {
                $myRsvp = EventRsvp::where('event_id', $e->id)
                    ->where('user_id', $user->id)
                    ->first();
                return [
                    'id' => $e->id,
                    'title' => $e->title,
                    'type' => $e->type,
                    'location_or_link' => $e->location_or_link,
                    'start_time' => $e->start_time->toISOString(),
                    'start_formatted' => $e->start_time->locale('ms')->isoFormat('ddd, D MMM YYYY [•] h:mm A'),
                    'featured_image_url' => $e->featured_image_url,
                    'organization' => [
                        'name' => $e->organization?->name ?? 'Semua Organisasi',
                        'slug' => $e->organization?->slug ?? 'semua',
                        'color_theme' => $e->organization?->color_theme ?? '#334155',
                    ],
                    'my_rsvp' => $myRsvp ? $myRsvp->status : null,
                ];
            });

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

        $latestArticles = Article::query()
            ->with(['author:id,name', 'organization:id,name'])
            ->where('is_published', true)
            ->where(function ($query) use ($user) {
                $query->whereNull('organization_id')
                    ->orWhere('organization_id', $user->current_organization_id);
            })
            ->latest('published_at')
            ->latest('id')
            ->take(8)
            ->get()
            ->map(fn (Article $article) => [
                'id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'excerpt' => $article->excerpt,
                'cover_image_path' => $article->cover_image_path,
                'author_name' => $article->author?->name ?? 'Admin',
                'organization_name' => $article->organization?->name ?? 'Semua Organisasi',
                'published_at' => $article->published_at?->toDateString(),
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

        $activePolls = Poll::withoutGlobalScopes()
            ->with(['questions' => function ($q) {
                $q->orderBy('sort_order')->take(1)->with(['options' => fn($o) => $o->orderBy('sort_order')]);
            }])
            ->withCount('responses')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->where(function ($q) use ($user) {
                $q->where('organization_id', $user->current_organization_id)
                  ->orWhere('target_type', 'all_orgs');
            })
            ->where(function ($q) use ($user) {
                $q->where('target_type', 'all')
                    ->orWhere('target_type', 'all_orgs')
                    ->orWhere(function ($q) use ($user) {
                        $q->where('target_type', 'members')
                            ->whereHas('targetMembers', fn($q) => $q->where('user_id', $user->id));
                    })
                    ->orWhere(function ($q) use ($user) {
                        $q->where('target_type', 'usrah')
                            ->whereHas('targetUsrahGroups.members', fn($q) => $q->where('user_id', $user->id));
                    });
            })
            ->latest()
            ->take(6)
            ->get()
            ->map(function ($poll) use ($user) {
                $hasResponded = PollResponse::where('poll_id', $poll->id)
                    ->where('user_id', $user->id)
                    ->exists();

                $firstQuestion = $poll->questions->first();
                $optionsPreview = [];
                $totalAnswers = 0;

                if ($firstQuestion) {
                    $totalAnswers = \App\Models\PollAnswer::where('poll_question_id', $firstQuestion->id)->count();
                    $optionsPreview = $firstQuestion->options->map(function ($opt) use ($firstQuestion, $totalAnswers) {
                        $count = \App\Models\PollAnswer::where('poll_question_id', $firstQuestion->id)
                            ->where('poll_option_id', $opt->id)
                            ->count();
                        return [
                            'id' => $opt->id,
                            'text' => $opt->option_text,
                            'count' => $count,
                            'width' => $totalAnswers > 0 ? round(($count / $totalAnswers) * 100) : 0,
                        ];
                    });
                }

                return [
                    'id' => $poll->id,
                    'title' => $poll->title,
                    'type' => $poll->type,
                    'ends_at_formatted' => $poll->ends_at?->locale('ms')->isoFormat('D MMM'),
                    'response_count' => $poll->responses_count,
                    'has_responded' => $hasResponded,
                    'first_question' => $firstQuestion?->question_text,
                    'options_preview' => $optionsPreview,
                    'total_answers' => $totalAnswers,
                ];
            });

        $activePopup = Popup::query()
            ->where('is_active', true)
            ->where(function ($query) use ($user) {
                $query->whereNull('organization_id')
                    ->orWhere('organization_id', $user->current_organization_id);
            })
            ->where(function ($query) {
                $query->whereNull('start_at')
                    ->orWhere('start_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('end_at')
                    ->orWhere('end_at', '>=', now());
            })
            ->orderBy('display_order')
            ->orderByDesc('id')
            ->first();

        if ($activePopup) {
            $activePopup = [
                'id' => $activePopup->id,
                'title' => $activePopup->title,
                'content' => $activePopup->content,
                'image_path' => $activePopup->image_path,
                'button_text' => $activePopup->button_text,
                'button_url' => $activePopup->button_url,
                'button_text_2' => $activePopup->button_text_2,
                'button_url_2' => $activePopup->button_url_2,
                'popup_size' => $activePopup->popup_size,
            ];
        }

        return Inertia::render('Member/Dashboard', [
            'activePopup' => $activePopup,
            'member' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'branch_name' => $user->branch_name,
                'locality' => $user->locality,
                'profession' => $user->current_profession,
                'photo_url' => $user->profile_photo_path,
                'member_since' => optional($user->created_at)->format('M Y'),
                'member_no' => $user->member_no,
                'system_logo_path' => $this->normalizeStorageUrl($setting?->system_logo_path),
                'organization' => [
                    'name' => $user->organization?->name,
                    'slug' => $user->organization?->slug,
                    'color_theme' => $user->organization?->color_theme,
                    'logo_path' => $this->normalizeStorageUrl($user->organization?->logo_path),
                ],
            ],
            'feeStatus' => $feeStatus,
            'nextEvent' => $nextEventRsvp ? [
                'title' => $nextEventRsvp->event->title,
                'start_formatted' => $nextEventRsvp->event->start_time->locale('ms')->isoFormat('ddd, D MMM YYYY [•] h:mm A'),
                'location_or_link' => $nextEventRsvp->event->location_or_link,
                'status' => $nextEventRsvp->status,
            ] : null,
            'upcomingEvents' => $upcomingEvents,
            'usrah' => $usrahGroup,
            'campaigns' => $campaigns,
            'libraryBooks' => $libraryBooks,
            'banners' => $banners,
            'videos' => $videos,
            'infaqItems' => $infaqItems,
            'latestNews' => $latestNews,
            'latestArticles' => $latestArticles,
            'activePolls' => $activePolls,
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

    private function normalizeStorageUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $parsedPath = parse_url($url, PHP_URL_PATH);

        if (is_string($parsedPath) && str_starts_with($parsedPath, '/storage/')) {
            return $parsedPath;
        }

        return $url;
    }
}

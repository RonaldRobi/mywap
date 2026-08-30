<?php

namespace App\Services;

use App\Actions\LoadUsrahForUser;
use App\Models\AppSetting;
use App\Models\Article;
use App\Models\Campaign;
use App\Models\DashboardBanner;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Infaq;
use App\Models\LibraryItem;
use App\Models\NewsPost;
use App\Models\Poll;
use App\Models\PollAnswer;
use App\Models\PollResponse;
use App\Models\Popup;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Facades\Cache;

/**
 * MemberDashboardService
 *
 * Logik tunggal untuk payload dashboard ahli — dikongsi oleh
 * WebController (Inertia) dan ApiController (JSON).
 */
class MemberDashboardService
{
    public function __construct(
        private readonly FeeService $feeService,
        private readonly LoadUsrahForUser $loadUsrah,
    ) {}

    public function data(User $user): array
    {
        return Cache::remember("member.dashboard.{$user->id}", 60, fn () => $this->buildData($user));
    }

    public function buildData(User $user): array
    {
        $user->load('organization');

        $setting = AppSetting::query()->first();

        $nextEventRsvp = EventRsvp::query()
            ->where('event_rsvps.user_id', $user->id)
            ->whereIn('event_rsvps.status', ['going', 'maybe'])
            ->whereHas('event', fn ($query) => $query->where('start_time', '>=', now()))
            ->with('event.organization')
            ->join('events', 'event_rsvps.event_id', '=', 'events.id')
            ->orderBy('events.start_time')
            ->select('event_rsvps.*')
            ->first();

        $feeStatus = $this->feeService->getStatus($user);
        $feeAmount = (float) ($user->organization?->fee_amount ?? 50.00);

        $usrahGroup = $this->loadUsrah->execute($user);

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

        $upcomingEvents = Event::with([
            'organization',
            'rsvps' => fn ($q) => $q->where('user_id', $user->id),
        ])
            ->where('start_time', '>=', now())
            ->where(function ($q) use ($user) {
                $q->whereNull('organization_id')
                    ->orWhere('organization_id', $user->current_organization_id);
            })
            ->orderBy('start_time')
            ->take(5)
            ->get()
            ->map(function (Event $e) {
                $myRsvp = $e->rsvps->first();

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
                'id' => $infaq->id,
                'title' => $infaq->title,
                'description' => $infaq->description,
                'image_path' => $infaq->image_path,
                'type' => $infaq->type,
                'target_amount' => $infaq->target_amount,
                'collected_amount' => $infaq->collected_amount,
                'progress_percent' => $infaq->progress_percent,
                'public_url' => $infaq->public_url,
            ]);

        $activePolls = Poll::withoutGlobalScopes()
            ->with(['questions' => function ($q) {
                $q->orderBy('sort_order')->take(1)->with(['options' => fn ($o) => $o->orderBy('sort_order')]);
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
                            ->whereHas('targetMembers', fn ($q) => $q->where('user_id', $user->id));
                    })
                    ->orWhere(function ($q) use ($user) {
                        $q->where('target_type', 'usrah')
                            ->whereHas('targetUsrahGroups.members', fn ($q) => $q->where('user_id', $user->id));
                    });
            })
            ->latest()
            ->take(6)
            ->get();

        $respondedPollIds = PollResponse::whereIn('poll_id', $activePolls->pluck('id')->filter())
            ->where('user_id', $user->id)
            ->pluck('poll_id');

        $answerCounts = collect();
        if ($activePolls->isNotEmpty()) {
            $firstQuestionIds = $activePolls->pluck('questions')->map->first()->pluck('id')->filter()->values();

            if ($firstQuestionIds->isNotEmpty()) {
                $answerCounts = PollAnswer::whereIn('poll_question_id', $firstQuestionIds)
                    ->selectRaw('poll_question_id, poll_option_id, COUNT(*) as n')
                    ->groupBy(['poll_question_id', 'poll_option_id'])
                    ->get()
                    ->groupBy('poll_question_id')
                    ->mapWithKeys(fn ($rows, $qid) => [$qid => $rows->pluck('n', 'poll_option_id')]);
            }
        }

        $activePolls = $activePolls->map(function ($poll) use ($respondedPollIds, $answerCounts) {
            $hasResponded = $respondedPollIds->contains($poll->id);

            $firstQuestion = $poll->questions->first();
            $optionsPreview = [];
            $totalAnswers = 0;

            if ($firstQuestion) {
                $qCounts = $answerCounts->get($firstQuestion->id, collect());
                $totalAnswers = (int) $qCounts->sum();
                $optionsPreview = $firstQuestion->options->map(function ($opt) use ($qCounts, $totalAnswers) {
                    $count = (int) $qCounts->get($opt->id, 0);

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

        return [
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
            'feeAmount' => $feeAmount,
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
        ];
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

<?php

namespace App\Http\Controllers;

use App\Actions\LoadUsrahForUser;
use App\Models\Article;
use App\Models\Campaign;
use App\Models\DashboardBanner;
use App\Models\EventRsvp;
use App\Models\Infaq;
use App\Models\LibraryItem;
use App\Models\NewsPost;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use App\Services\AdminService;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $articles = Article::query()
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

        $organizations = Organization::query()
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
        $user = $request->user();
        $data = app(AdminService::class)->dashboard($user);

        return Inertia::render('Admin/Dashboard', [
            'organization' => $data['organization'],
            'overview' => $data['overview'],
            'managementLinks' => $data['managementLinks'],
            'campaigns' => $data['campaigns'],
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
            ->join('events', 'event_rsvps.event_id', '=', 'events.id')
            ->orderBy('events.start_time')
            ->select('event_rsvps.*')
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
                'status' => $feeIsActive ? 'active' : 'due',
                'amount_due' => $feeIsActive ? 0 : $feeAmount,
                'last_paid_at' => $latestFeePayment?->created_at?->toISOString(),
                'last_reference' => $latestFeePayment?->reference,
            ],
            'nextEvent' => $nextEventRsvp ? [
                'title' => $nextEventRsvp->event->title,
                'start_formatted' => $nextEventRsvp->event->start_time->locale('ms')->isoFormat('ddd, D MMM YYYY [•] h:mm A'),
                'location_or_link' => $nextEventRsvp->event->location_or_link,
                'status' => $nextEventRsvp->status,
            ] : null,
            'usrah' => $usrahGroup,
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

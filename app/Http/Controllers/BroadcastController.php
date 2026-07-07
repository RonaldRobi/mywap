<?php

namespace App\Http\Controllers;

use App\Jobs\SendBroadcastJob;
use App\Models\BroadcastMessage;
use App\Models\Organization;
use App\Models\Announcement;
use App\Models\UsrahGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BroadcastController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $isSuperadmin = $user->hasRole('Superadmin');

        $messages = BroadcastMessage::query()
            ->with(['organization:id,name', 'targetOrganization:id,name'])
            ->latest()
            ->take(20)
            ->get()
            ->map(fn (BroadcastMessage $message) => [
                'id' => $message->id,
                'title' => $message->title,
                'target_criteria' => $message->target_criteria,
                'target_criteria_label' => match ($message->target_criteria) {
                    'all' => 'Semua Ahli',
                    'organization' => 'Ahli Organisasi',
                    'specific_members' => 'Individu Tertentu',
                    default => $message->target_criteria,
                },
                'organization_name' => $message->organization?->name,
                'target_organization_name' => $message->targetOrganization?->name,
                'recipient_count' => is_array($message->recipient_ids) ? count($message->recipient_ids) : 0,
                'notification_channels' => $message->notification_channels ?? ['in_app'],
                'sent_at' => $message->sent_at?->toDateTimeString(),
            ]);

        $announcementsQuery = Announcement::query()->with('organization:id,name,slug');
        if (! $isSuperadmin) {
            $announcementsQuery->where('organization_id', $user->current_organization_id);
        }

        $announcements = $announcementsQuery
            ->with(['author:id,name', 'images'])
            ->withCount([
                'reactions as likes_count' => fn ($q) => $q->where('reaction', 'like'),
                'reads as reads_count',
            ])
            ->latest('published_at')
            ->latest('id')
            ->take(50)
            ->get()
            ->map(fn (Announcement $item) => [
                'id' => $item->id,
                'organization_id' => $item->organization_id,
                'organization_name' => $item->organization?->name,
                'title' => $item->title,
                'content' => $item->content,
                'is_pinned' => (bool)$item->is_pinned,
                'published_at' => $item->published_at?->toDateTimeString(),
                'published_human' => $item->published_at?->locale('ms')->isoFormat('D MMM YYYY, h:mm A'),
                'cover_image_url' => $item->coverImageUrl(),
                'author_name' => $item->author?->name,
                'likes_count' => (int) $item->likes_count,
                'reads_count' => (int) $item->reads_count,
                'target_criteria' => $item->target_criteria,
                'images' => $item->images->map(fn ($img) => [
                    'id' => $img->id,
                    'url' => $img->imageUrl(),
                    'caption' => $img->caption,
                ]),
            ]);

        $organizations = $isSuperadmin
                ? Organization::query()->orderBy('min_age')->get(['id', 'name', 'slug'])
                : collect([['id' => $user->organization?->id, 'name' => $user->organization?->name]]);

        $usrahGroups = UsrahGroup::query()
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Broadcasts', [
            'recentMessages' => $messages,
            'defaultOrganizationId' => $user->current_organization_id,
            'isSuperadmin' => $isSuperadmin,
            'announcements' => $announcements,
            'organizations' => $organizations,
            'usrahGroups' => $usrahGroups,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $isSuperadmin = $user->hasRole('Superadmin');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'target_criteria' => ['required', 'in:all,organization,specific_members'],
            'target_organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'recipient_ids' => ['nullable', 'array'],
            'recipient_ids.*' => ['integer', 'exists:users,id'],
            'notification_channels' => ['required', 'array', 'min:1'],
            'notification_channels.*' => ['string', 'in:in_app,email'],
            'email_use_template' => ['boolean'],
        ]);

        $targetOrgId = null;

        if ($data['target_criteria'] === 'all') {
            $targetOrgId = null;
        } elseif ($data['target_criteria'] === 'organization') {
            if ($isSuperadmin) {
                $targetOrgId = $data['target_organization_id'] ?? $user->current_organization_id;
            } else {
                $targetOrgId = $user->current_organization_id;
            }
        } elseif ($data['target_criteria'] === 'specific_members') {
            if (empty($data['recipient_ids'])) {
                return back()->withErrors(['recipient_ids' => 'Sila pilih sekurang-kurangnya seorang ahli.']);
            }
            $targetOrgId = null;
        }

        $message = BroadcastMessage::create([
            'organization_id' => $user->current_organization_id,
            'target_organization_id' => $targetOrgId,
            'title' => $data['title'],
            'content' => $data['content'],
            'target_criteria' => $data['target_criteria'],
            'recipient_ids' => $data['recipient_ids'] ?? null,
            'notification_channels' => $data['notification_channels'],
            'email_use_template' => $request->boolean('email_use_template', false),
        ]);

        SendBroadcastJob::dispatch($message->id);

        return back()->with('success', 'Push notification sedang diproses dan akan dihantar berperingkat.');
    }
}

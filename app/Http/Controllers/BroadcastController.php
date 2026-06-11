<?php

namespace App\Http\Controllers;

use App\Jobs\SendBroadcastJob;
use App\Models\BroadcastMessage;
use App\Models\UsrahGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

use App\Models\Organization;
use App\Models\Announcement;

class BroadcastController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $isSuperadmin = $user->hasRole('Superadmin');

        $messages = BroadcastMessage::query()
            ->with(['organization:id,name', 'usrahGroup:id,name'])
            ->latest()
            ->take(20)
            ->get()
            ->map(fn (BroadcastMessage $message) => [
                'id' => $message->id,
                'title' => $message->title,
                'target_criteria' => $message->target_criteria,
                'organization_name' => $message->organization?->name,
                'usrah_group_name' => $message->usrahGroup?->name,
                'sent_at' => $message->sent_at?->toDateTimeString(),
            ]);

        $usrahGroups = UsrahGroup::query()
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $announcementsQuery = Announcement::query()->with('organization:id,name,slug');
        if (! $isSuperadmin) {
            $announcementsQuery->where('organization_id', $user->current_organization_id);
        }

        $announcements = $announcementsQuery
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
            ]);

        $organizations = $isSuperadmin
                ? Organization::query()->orderBy('min_age')->get(['id', 'name', 'slug'])
                : collect([['id' => $user->organization?->id, 'name' => $user->organization?->name]]);

        return Inertia::render('Admin/Broadcasts', [
            'recentMessages' => $messages,
            'usrahGroups' => $usrahGroups,
            'defaultOrganizationId' => $user->current_organization_id,
            'isSuperadmin' => $isSuperadmin,
            'announcements' => $announcements,
            'organizations' => $organizations,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'target_criteria' => ['required', 'in:all,unpaid_fees,specific_usrah'],
            'usrah_group_id' => ['nullable', 'integer', 'exists:usrah_groups,id'],
        ]);

        if ($data['target_criteria'] === 'specific_usrah') {
            abort_if(empty($data['usrah_group_id']), 422, 'Sila pilih kumpulan usrah sasaran.');
        }

        $message = BroadcastMessage::create([
            'organization_id' => $user->current_organization_id,
            'title' => $data['title'],
            'content' => $data['content'],
            'target_criteria' => $data['target_criteria'],
            'usrah_group_id' => $data['target_criteria'] === 'specific_usrah'
                ? $data['usrah_group_id']
                : null,
        ]);

        SendBroadcastJob::dispatch($message->id);

        return back()->with('success', 'Broadcast sedang diproses dan akan dihantar berperingkat.');
    }
}

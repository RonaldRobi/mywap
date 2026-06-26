<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementPublishedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendAnnouncementJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $announcementId)
    {
    }

    public function handle(): void
    {
        $announcement = Announcement::withoutGlobalScopes()->find($this->announcementId);

        if (! $announcement) {
            return;
        }

        $query = User::withoutGlobalScopes()
            ->where('current_organization_id', $announcement->organization_id);

        if ($announcement->target_criteria === 'unpaid_fees') {
            $query->whereDoesntHave('membershipFees', function ($feeQuery) {
                $feeQuery->where('year', now()->year)
                    ->whereIn('status', ['paid', 'exempted', 'life_member']);
            });
        }

        if ($announcement->target_criteria === 'specific_usrah' && $announcement->usrah_group_id) {
            $query->whereHas('usrahGroups', function ($usrahQuery) use ($announcement) {
                $usrahQuery->where('usrah_groups.id', $announcement->usrah_group_id);
            });
        }

        $query->orderBy('id')->chunk(200, function ($users) use ($announcement) {
            foreach ($users as $user) {
                $user->notify(new AnnouncementPublishedNotification($announcement));
            }
        });
    }
}

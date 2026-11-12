<?php

namespace App\Notifications;

use App\Models\BranchChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BranchChangeRequestReviewed extends Notification
{
    use Queueable;

    public function __construct(
        public readonly BranchChangeRequest $branchChangeRequest,
        public readonly string $status,
        public readonly ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $request = $this->branchChangeRequest->loadMissing(['fromBranch', 'toBranch']);
        $approved = $this->status === 'approved';

        return [
            'title' => $approved ? 'Pertukaran cawangan diluluskan' : 'Pertukaran cawangan ditolak',
            'content' => $approved
                ? 'Permohonan tukar ke '.($request->toBranch?->name ?? 'cawangan baharu').' telah diluluskan.'
                : 'Permohonan tukar ke '.($request->toBranch?->name ?? 'cawangan baharu').' telah ditolak.'
                    .($this->reason ? ' Sebab: '.$this->reason : ''),
            'type' => 'branch_change_request_reviewed',
            'action_type' => 'branch_change_request',
            'action_id' => $request->id,
            'action_label' => 'Lihat status',
            'action_url' => route('profile.edit'),
        ];
    }
}

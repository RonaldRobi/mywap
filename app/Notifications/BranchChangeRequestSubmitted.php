<?php

namespace App\Notifications;

use App\Models\AppSetting;
use App\Models\BranchChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BranchChangeRequestSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly BranchChangeRequest $branchChangeRequest) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->branchChangeRequest->loadMissing(['user', 'fromBranch', 'toBranch']);
        $settings = AppSetting::singleton();
        $logoPath = $settings->system_logo_path ?? '/images/logomywaphorizontal.png';

        return (new MailMessage)
            ->subject('Permohonan tukar cawangan baharu')
            ->view('emails.branch-change-request', [
                'memberName' => $request->user?->name ?? 'Ahli',
                'memberNo' => $request->user?->member_no ?? '-',
                'fromBranch' => $request->fromBranch?->name ?? 'Tiada cawangan',
                'toBranch' => $request->toBranch?->name ?? 'Tiada cawangan',
                'submittedAt' => $request->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '-',
                'logoUrl' => url($logoPath),
                'appName' => config('app.name'),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        $request = $this->branchChangeRequest->loadMissing(['user', 'fromBranch', 'toBranch']);

        return [
            'title' => 'Permohonan tukar cawangan',
            'content' => ($request->user?->name ?? 'Ahli').' memohon tukar dari '
                .($request->fromBranch?->name ?? 'Tiada cawangan').' ke '
                .($request->toBranch?->name ?? 'Tiada cawangan').'. Admin organisasi akan memproses permohonan ini.',
            'type' => 'branch_change_request',
            'action_type' => 'branch_change_request',
            'action_id' => $request->id,
            'action_label' => null,
            'action_url' => null,
        ];
    }
}

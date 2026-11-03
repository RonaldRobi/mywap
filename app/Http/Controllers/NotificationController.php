<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $this->notifications->markRead($request->user(), $notification);

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        if ($user = $request->user()) {
            $this->notifications->markAllRead($user);
        }

        return back();
    }
}

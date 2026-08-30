<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\MemberDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class MemberDashboardController extends Controller
{
    public function index(Request $request, MemberDashboardService $dashboard): Response
    {
        $data = $dashboard->data($request->user());

        return Inertia::render('Member/Dashboard', $data);
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

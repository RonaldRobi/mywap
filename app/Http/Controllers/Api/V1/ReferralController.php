<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ReferralController extends Controller
{
    /**
     * Pautan rujukan, kod QR (SVG mentah) dan senarai ahli yang dijemput.
     * Sama logic dengan web MemberDashboardController::referral.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $referredMembers = User::where('referred_by_user_id', $user->id)
            ->with('organization')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (User $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'member_no' => $member->member_no,
                'registered_at' => $member->created_at?->toISOString(),
                'status' => $member->profile_completed_at ? 'active' : 'pending',
                'organization' => $member->organization?->name,
            ]);

        $stats = [
            'total' => $referredMembers->count(),
            'active' => $referredMembers->where('status', 'active')->count(),
            'pending' => $referredMembers->where('status', 'pending')->count(),
        ];

        $referralLink = route('register', ['ref' => $user->member_no]);
        $qrSvg = QrCode::format('svg')->size(300)->margin(2)->generate($referralLink);
        $qrSvg = preg_replace('/^<\?xml.*?\?>\s*/', '', $qrSvg);
        $qrSvg = preg_replace('/\s(width|height)="\d+"/', '', $qrSvg);

        return ApiResponse::success([
            'referral_link' => $referralLink,
            'member_no' => $user->member_no,
            'qr_svg' => $qrSvg,
            'stats' => $stats,
            'referred_members' => $referredMembers,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\MemberCoreService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemberCardController extends Controller
{
    public function show(Request $request, MemberCoreService $core): Response
    {
        return Inertia::render('Member/Card', $core->card($request->user(), true));
    }

    /**
     * Download a membership confirmation letter (PDF) for the authenticated member.
     */
    public function letterPdf(Request $request)
    {
        $user = $request->user()->load('organization', 'branch');

        $pdf = Pdf::loadView('exports.member-letter', [
            'member' => [
                'name' => $user->name,
                'member_no' => $user->member_no,
                'ic_number' => $user->ic_number,
                'organization' => $user->organization?->name,
                'branch' => $user->branch?->name ?? $user->branch_name,
                'state' => $user->state,
                'joined_at' => optional($user->created_at)->format('d/m/Y'),
                'email' => $user->email,
                'phone' => $user->phone,
                'issued_at' => now()->format('d/m/Y'),
            ],
        ]);

        return $pdf->download('surat-pengesahan-keahlian-'.($user->member_no ?? $user->id).'.pdf');
    }

    /**
     * Muat turun surat pengesahan keahlian melalui URL yang ditandatangani
     * (digunakan mobile/API supaya boleh dibuka dalam pelayar tanpa header
     * Authorization). Pengesahan dibuat oleh middleware `signed`.
     */
    public function letterPdfSigned(User $user): \Symfony\Component\HttpFoundation\Response
    {
        $user->load('organization', 'branch');

        $pdf = Pdf::loadView('exports.member-letter', [
            'member' => [
                'name' => $user->name,
                'member_no' => $user->member_no,
                'ic_number' => $user->ic_number,
                'organization' => $user->organization?->name,
                'branch' => $user->branch?->name ?? $user->branch_name,
                'state' => $user->state,
                'joined_at' => optional($user->created_at)->format('d/m/Y'),
                'email' => $user->email,
                'phone' => $user->phone,
                'issued_at' => now()->format('d/m/Y'),
            ],
        ]);

        return $pdf->download('surat-pengesahan-keahlian-'.($user->member_no ?? $user->id).'.pdf');
    }
}

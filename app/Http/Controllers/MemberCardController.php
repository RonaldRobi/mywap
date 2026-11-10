<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\MemberCoreService;
use App\Services\MembershipLetterService;
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
     * Muat turun surat pengesahan keahlian (PDF) untuk ahli yang log masuk.
     */
    public function letterPdf(Request $request, MembershipLetterService $letters)
    {
        $user = $request->user();

        return $letters->pdf($user)->download($letters->filename($user));
    }

    /**
     * Surat melalui URL ditandatangani supaya mobile boleh buka dalam pelayar
     * tanpa header Authorization.
     */
    public function letterPdfSigned(User $user, MembershipLetterService $letters): \Symfony\Component\HttpFoundation\Response
    {
        return $letters->pdf($user)->download($letters->filename($user));
    }
}

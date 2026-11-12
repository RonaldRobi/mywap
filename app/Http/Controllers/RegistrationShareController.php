<?php

namespace App\Http\Controllers;

use App\Services\RegistrationShareService;
use App\Support\QrPng;
use App\Support\RegistrationPoster;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegistrationShareController extends Controller
{
    public function show(Request $request, RegistrationShareService $share): Response
    {
        return Inertia::render('Admin/RegistrationShare', [
            'share' => $share->forUser($request->user()),
        ]);
    }

    public function downloadQr(Request $request, RegistrationShareService $share): StreamedResponse
    {
        $data = $share->forUser($request->user());
        $png = QrPng::render($data['register_url'], 1000, 2);
        $slug = $data['organization']['slug'] ?? 'mywap';

        return response()->streamDownload(function () use ($png) {
            echo $png;
        }, "qr-pendaftaran-{$slug}.png", [
            'Content-Type' => 'image/png',
        ]);
    }

    public function downloadPoster(Request $request, RegistrationShareService $share): StreamedResponse
    {
        $user = $request->user();
        $organization = $user->hasRole('Superadmin') ? null : $user->organization;
        $context = $share->context($organization, $user->member_no ?: null);
        $png = RegistrationPoster::render($organization, $context['register_url']);
        $slug = $organization?->slug ?? 'mywap';

        return response()->streamDownload(function () use ($png) {
            echo $png;
        }, "poster-jemput-ahli-{$slug}.png", [
            'Content-Type' => 'image/png',
        ]);
    }
}

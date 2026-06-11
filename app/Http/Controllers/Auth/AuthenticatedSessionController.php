<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Landing', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $defaultRoute = $this->redirectRouteFor($request->user());

        return redirect()->intended(route($defaultRoute, absolute: false));
    }

    public function checkMemberIc(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ic_number' => ['required', 'string', 'max:255'],
        ]);

        $identifier = $validated['ic_number'];
        $normalizedId = Str::upper(preg_replace('/\s+/', '', trim($identifier)) ?? '');
        $email = Str::lower(trim($identifier));

        $user = User::query()
            ->with('organization')
            ->where(function ($query) use ($normalizedId, $email) {
                $query->where('ic_number', $normalizedId)
                      ->orWhere('member_no', $normalizedId)
                      ->orWhere('email', $email);
            })
            ->first();

        if (! $user || ! $user->organization) {
            return response()->json([
                'found' => false,
                'message' => 'Maklumat ahli tidak dijumpai.',
            ], 404);
        }

        return response()->json([
            'found' => true,
            'organization' => [
                'name' => $user->organization->name,
                'logo_url' => $user->organization->logo_path,
            ],
        ]);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function redirectRouteFor(?User $user): string
    {
        if (! $user) {
            return 'dashboard';
        }

        if ($user->hasRole('Superadmin')) {
            return 'admin.dashboard';
        }

        if ($user->hasAnyRole(['Admin', 'org-admin'])) {
            return 'admin.dashboard';
        }

        return 'member.dashboard';
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'ic_number' => ['required', 'string', 'max:32'],
        ]);

        $normalizedIcNumber = Str::upper(
            preg_replace('/\s+/', '', trim((string) $request->input('ic_number'))) ?? ''
        );

        $user = User::withoutGlobalScopes()
            ->where('ic_number', $normalizedIcNumber)
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'ic_number' => ['No IC/Passport tidak ditemui dalam sistem.'],
            ]);
        }

        if (! $user->email) {
            throw ValidationException::withMessages([
                'ic_number' => ['Akaun ini tiada emel berdaftar. Sila hubungi admin.'],
            ]);
        }

        $status = Password::sendResetLink(
            ['email' => $user->email]
        );

        if ($status == Password::RESET_LINK_SENT) {
            return back()->with('status', 'Pautan reset kata laluan telah dihantar ke emel berdaftar anda.');
        }

        throw ValidationException::withMessages([
            'ic_number' => [trans($status)],
        ]);
    }
}

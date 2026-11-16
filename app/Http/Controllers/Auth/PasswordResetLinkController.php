<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OneSenderWhatsappService;
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
    public function store(Request $request, OneSenderWhatsappService $whatsapp): RedirectResponse
    {
        $request->validate([
            'ic_number' => ['required', 'string', 'max:32'],
            'channel' => ['nullable', 'in:email,whatsapp'],
            'contact' => ['nullable', 'string', 'max:255'],
        ]);

        $normalizedIcNumber = (string) User::normalizeIc($request->input('ic_number'));

        $user = User::withoutGlobalScope(\App\Models\Scopes\OrganizationScope::class)
            ->where('ic_number', $normalizedIcNumber)
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'ic_number' => ['No IC/Passport tidak ditemui dalam sistem.'],
            ]);
        }

        if ($request->input('channel', 'email') === 'whatsapp') {
            $phone = $whatsapp->normalizePhone($request->input('contact'));
            if (strlen($phone) < 10) {
                throw ValidationException::withMessages(['contact' => ['Nombor WhatsApp tidak sah.']]);
            }

            $user->update(['phone' => $phone]);
            $token = Password::broker()->createToken($user);
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ], false));

            if (! $whatsapp->sendResetLink($phone, $url)) {
                Password::broker()->deleteToken($user);
                throw ValidationException::withMessages(['contact' => ['Pautan reset WhatsApp tidak dapat dihantar. Sila cuba lagi.']]);
            }

            return back()->with('status', 'Pautan reset kata laluan telah dihantar melalui WhatsApp.');
        }

        if ($request->filled('contact')) {
            $request->validate(['contact' => ['email']]);
            $user->update(['email' => Str::lower($request->input('contact'))]);
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

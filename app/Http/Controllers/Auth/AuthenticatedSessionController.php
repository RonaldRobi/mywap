<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\AppSetting;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\OtpService;
use App\Services\OneSenderWhatsappService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        $loginImagePath = null;

        if (Schema::hasTable('app_settings')) {
            $rawPath = AppSetting::singleton()->login_image_path;

            if ($rawPath) {
                $parsedPath = parse_url($rawPath, PHP_URL_PATH);
                $loginImagePath = is_string($parsedPath) && str_starts_with($parsedPath, '/storage/')
                    ? $parsedPath
                    : $rawPath;
            }
        }

        return Inertia::render('Landing', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'loginImagePath' => $loginImagePath,
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();

        if (is_null($user->first_login_at)) {
            $user->update(['first_login_at' => now()]);
        }

        $defaultRoute = $this->redirectRouteFor($user);

        return redirect()->intended(route($defaultRoute, absolute: false));
    }

    public function checkMemberIc(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ic_number' => ['required', 'string', 'max:255'],
        ]);

        $identifier = $validated['ic_number'];
        $normalizedId = User::normalizeLoginId($identifier);
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

        $setting = AppSetting::singleton();
        $roles = $user->getRoleNames();
        $isAdmin = $user->hasRole(['Superadmin', 'Admin', 'org-admin']);
        $isMember = $user->hasRole('Member');
        $hasRealEmail = ! is_null($user->email) && ! $user->emailIsPlaceholder();

        $hasRequestedOtp = OtpCode::where('user_id', $user->id)
            ->where('purpose', 'login')
            ->exists();

        return response()->json([
            'found' => true,
            'is_first_login' => is_null($user->first_login_at),
            'has_requested_otp' => $hasRequestedOtp,
            'has_email' => $hasRealEmail,
            'masked_email' => $hasRealEmail ? $this->maskEmail($user->email) : null,
            'roles' => $roles,
            'is_admin' => $isAdmin,
            'is_member' => $isMember,
            'is_dual_role' => $isAdmin && $isMember,
            'organization' => [
                'name' => $user->organization->name,
                'logo_url' => $user->organization->logo_path,
            ],
            'admin_contact_email' => $setting->admin_contact_email,
            'admin_contact_phone' => $setting->admin_contact_phone,
        ]);
    }

    public function forgotId(Request $request): JsonResponse
    {
        $request->validate([
            'ic_number' => ['required', 'string', 'max:32'],
        ]);

        $user = $this->lookupUser($request->input('ic_number'));

        if (! $user) {
            return response()->json(['message' => 'No IC/Passport tidak ditemui dalam sistem.'], 404);
        }

        if ($request->filled('dob')) {
            $request->validate(['dob' => ['required', 'date']]);

            if (! $user->dob) {
                return response()->json(['message' => 'Akaun ini tiada tarikh lahir direkodkan. Sila hubungi admin.'], 422);
            }

            $inputDob = $request->date('dob')->format('Y-m-d');
            $storedDob = $user->dob->format('Y-m-d');

            if ($inputDob !== $storedDob) {
                return response()->json(['message' => 'Tarikh lahir tidak tepat.'], 422);
            }

            return response()->json([
                'verified' => true,
                'masked_email' => $user->email ? $this->maskEmail($user->email) : null,
                'member_no' => $user->member_no,
            ]);
        }

        return response()->json([
            'needs_verification' => true,
            'message' => 'Sila masukkan tarikh lahir untuk pengesahan identiti.',
        ]);
    }

    public function sendOtp(Request $request, OtpService $otp, OneSenderWhatsappService $whatsapp): JsonResponse
    {
        $request->validate([
            'ic_number' => ['required', 'string', 'max:255'],
            'channel' => ['nullable', 'in:email,whatsapp'],
            'contact' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $this->lookupUser($request->input('ic_number'));

        if (! $user) {
            return response()->json(['message' => 'Ahli tidak dijumpai.'], 404);
        }

        if (! is_null($user->first_login_at)) {
            return response()->json(['message' => 'Akaun ini sudah pun log masuk kali pertama. Sila log masuk menggunakan kata laluan anda.'], 422);
        }

        $channel = $request->input('channel', 'email');
        $contact = trim((string) $request->input('contact'));
        if ($channel === 'whatsapp') {
            $contact = $whatsapp->normalizePhone($contact);
            if (strlen($contact) < 10) {
                return response()->json(['message' => 'Nombor WhatsApp tidak sah.'], 422);
            }
        } elseif ($contact !== '') {
            $request->validate(['contact' => ['email']]);
            $contact = Str::lower($contact);
        }

        if ($channel === 'email' && (! $user->email || $user->emailIsPlaceholder())) {
            return response()->json(['message' => 'Akaun ini tiada emel berdaftar.'], 422);
        }

        $otp->send($user, 'login', $channel, $contact ?: null);

        return response()->json([
            'message' => $channel === 'whatsapp'
                ? 'Kod OTP telah dihantar melalui WhatsApp.'
                : 'Kod OTP telah dihantar ke emel berdaftar anda.',
            'channel' => $channel,
        ]);
    }

    public function updateAndSendOtp(Request $request, OtpService $otp, OneSenderWhatsappService $whatsapp): JsonResponse
    {
        $request->validate([
            'ic_number' => ['required', 'string', 'max:255'],
            'channel' => ['nullable', 'in:email,whatsapp'],
            'contact' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'lowercase'],
        ]);

        $user = $this->lookupUser($request->input('ic_number'));

        if (! $user) {
            return response()->json(['message' => 'Ahli tidak dijumpai.'], 404);
        }

        if (! is_null($user->first_login_at)) {
            return response()->json(['message' => 'Akaun ini sudah pun log masuk kali pertama. Sila log masuk menggunakan kata laluan anda.'], 422);
        }

        $channel = $request->input('channel', $request->filled('email') ? 'email' : 'whatsapp');
        $contact = trim((string) ($request->input('contact') ?: $request->input('email')));

        if ($channel === 'whatsapp') {
            $contact = $whatsapp->normalizePhone($contact);
            if (strlen($contact) < 10) {
                return response()->json(['message' => 'Nombor WhatsApp tidak sah.'], 422);
            }
            $otp->send($user, 'login', $channel, $contact);

            return response()->json([
                'message' => 'Kod OTP telah dihantar melalui WhatsApp.',
                'channel' => 'whatsapp',
            ]);
        }

        $request->validate(['contact' => ['nullable', 'email']]);
        $newEmail = Str::lower($contact);

        if ($user->email !== $newEmail) {
            $exists = User::where('email', $newEmail)->where('id', '!=', $user->id)->exists();
            if ($exists) {
                return response()->json(['message' => 'Emel ini sudah digunakan oleh akaun lain.'], 422);
            }
        }

        $otp->send($user, 'login', 'email', $newEmail);

        return response()->json([
            'message' => 'Kod OTP telah dihantar ke emel anda.',
            'channel' => 'email',
        ]);
    }

    public function verifyOtp(Request $request, OtpService $otp): JsonResponse
    {
        $request->validate([
            'ic_number' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $this->lookupUser($request->input('ic_number'));

        if (! $user) {
            return response()->json(['message' => 'Ahli tidak dijumpai.'], 404);
        }

        $verifiedOtp = $otp->verifyAndReturn($user, $request->input('code'), 'login');
        if (! $verifiedOtp) {
            return response()->json(['message' => 'Kod OTP tidak sah atau telah tamat tempoh.'], 422);
        }

        if ($verifiedOtp->channel === 'whatsapp' && $verifiedOtp->recipient) {
            $user->update(['phone' => $verifiedOtp->recipient]);
        } elseif ($verifiedOtp->channel === 'email' && $verifiedOtp->recipient) {
            $user->update(['email' => $verifiedOtp->recipient]);
        }

        if (is_null($user->first_login_at)) {
            $request->validate([
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ]);

            $user->update([
                'password' => Hash::make($request->password),
                'first_login_at' => now(),
            ]);
        }

        Auth::login($user);

        $request->session()->regenerate();

        $route = $this->redirectRouteFor($user);

        return response()->json([
            'message' => 'Log masuk berjaya.',
            'redirect' => route($route, absolute: false),
        ]);
    }

    public function verifyIdentity(Request $request): JsonResponse
    {
        $request->validate([
            'ic_number' => ['required', 'string', 'max:255'],
            'dob' => ['required', 'date'],
        ]);

        $user = $this->lookupUser($request->input('ic_number'));

        if (! $user) {
            return response()->json(['message' => 'Ahli tidak dijumpai.'], 404);
        }

        if (! $user->dob) {
            return response()->json(['message' => 'Akaun ini tiada tarikh lahir direkodkan. Sila hubungi admin.'], 422);
        }

        $inputDob = $request->date('dob')->format('Y-m-d');
        $storedDob = $user->dob->format('Y-m-d');

        if ($inputDob !== $storedDob) {
            return response()->json(['message' => 'Tarikh lahir tidak tepat.'], 422);
        }

        return response()->json(['message' => 'Identiti disahkan.']);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/')->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    private function lookupUser(string $identifier): ?User
    {
        $normalizedId = User::normalizeLoginId($identifier);
        $email = Str::lower(trim($identifier));

        return User::withoutGlobalScope(\App\Models\Scopes\OrganizationScope::class)
            ->where(function ($query) use ($normalizedId, $email) {
                $query->where('ic_number', $normalizedId)
                    ->orWhere('member_no', $normalizedId)
                    ->orWhere('email', $email);
            })
            ->first();
    }

    private function maskEmail(?string $email): ?string
    {
        if (! $email) {
            return null;
        }

        $parts = explode('@', $email);
        $name = $parts[0];
        $domain = $parts[1] ?? '';

        $maskedName = strlen($name) > 2
            ? substr($name, 0, 1).str_repeat('*', max(strlen($name) - 2, 1)).substr($name, -1)
            : $name[0].'*';

        $domainParts = explode('.', $domain);
        $maskedDomain = (count($domainParts) > 0)
            ? substr($domainParts[0], 0, 1).str_repeat('*', max(strlen($domainParts[0]) - 1, 1)).'.'.implode('.', array_slice($domainParts, 1))
            : $domain;

        return $maskedName.'@'.$maskedDomain;
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

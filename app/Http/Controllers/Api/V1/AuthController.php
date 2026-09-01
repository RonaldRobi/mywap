<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\AppSetting;
use App\Models\Branch;
use App\Models\BranchTransitionHistory;
use App\Models\OtpCode;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\NewMemberAlert;
use App\Notifications\RegistrationActivated;
use App\Notifications\RegistrationReceived;
use App\Services\FeeService;
use App\Services\OtpService;
use App\Support\ApiResponse;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    /**
     * Log masuk (password) untuk mobile.
     *
     * Terima `email` ATAU `ic_number`/`member_no` + `password`.
     * Kembalikan Sanctum token + user. Semantik sama dengan web LoginRequest:
     * akaun yang belum selesai "log masuk kali pertama" (first_login_at null)
     * ditolak — mereka perlu guna aliran OTP (Fasa 1).
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['nullable', 'string', 'email'],
            'ic_number' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $user = $this->resolveUser($request);

        $field = $request->filled('email') ? 'email' : 'ic_number';

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            if ($user && is_null($user->first_login_at)) {
                throw ValidationException::withMessages([
                    $field => 'Akaun ini belum aktif. Sila gunakan pautan "Log Masuk Kali Pertama" di bawah.',
                ]);
            }

            throw ValidationException::withMessages([
                $field => trans('auth.failed'),
            ]);
        }

        if (is_null($user->first_login_at)) {
            $user->update(['first_login_at' => now()]);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Log keluar — padam token semasa.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return ApiResponse::success(null, ['message' => 'Log keluar berjaya.']);
    }

    /**
     * Profil pengguna semasa.
     */
    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(new UserResource($request->user()));
    }

    /**
     * Daftar ahli baharu — logic sama dengan RegisteredUserController::store,
     * tapi tanpa sesi/Inertia. Pembayaran pendaftaran (dummy) terus diproses
     * sekali gus supaya mobile tidak perlu susulan panggilan berasingan.
     *
     * @throws ValidationException
     */
    public function register(Request $request, FeeService $feeService): JsonResponse
    {
        $normalizedIcNumber = Str::upper(preg_replace('/\s+/', '', trim((string) $request->input('ic_number'))) ?? '');
        $request->merge(['ic_number' => $normalizedIcNumber]);

        if (! $request->filled('dob') && $normalizedIcNumber) {
            $parsedDob = User::parseDobFromIc($normalizedIcNumber);
            if ($parsedDob) {
                $request->merge(['dob' => $parsedDob]);
            }
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'ic_number' => 'required|string|max:32|unique:'.User::class.',ic_number',
            'phone' => 'nullable|string|max:20',
            'dob' => 'required|date',
            'branch_id' => 'nullable|exists:branches,id',
            'referral_code' => 'nullable|string|max:32',
        ]);

        $referrer = null;
        if (! empty($validated['referral_code'])) {
            $referrer = User::where('member_no', $validated['referral_code'])->first(['id']);
        }

        $dob = $request->date('dob');
        $gender = User::guessGenderFromIc($normalizedIcNumber);

        $organization = $dob ? Organization::forAge($dob->age) : null;
        $organization ??= Organization::query()->orderBy('min_age')->first();

        $branchId = $request->branch_id;
        if ($branchId && $organization) {
            $branch = Branch::find($branchId);
            if ($branch && $branch->organization_id !== $organization->id) {
                $branchId = null;
            }
        }

        $prefix = match ($organization?->slug) {
            'pkpim' => 'P',
            'abim' => 'A',
            'wadah' => 'W',
            default => 'M',
        };
        $padding = $prefix === 'W' ? 4 : 5;

        $user = null;

        DB::transaction(function () use (
            &$user, $prefix, $padding, $request, $normalizedIcNumber, $dob, $gender,
            $organization, $branchId, $referrer
        ) {
            $max = User::where('member_no', 'like', $prefix.'%')
                ->lockForUpdate()
                ->max('member_no_sequence');
            $next = ($max ?? 0) + 1;
            $memberNo = $prefix.str_pad($next, $padding, '0', STR_PAD_LEFT);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'ic_number' => $normalizedIcNumber,
                'phone' => $request->phone,
                'dob' => $dob,
                'gender' => $gender,
                'current_organization_id' => $organization?->id,
                'branch_id' => $branchId,
                'member_no' => $memberNo,
                'member_no_sequence' => $next,
                'original_member_no' => $memberNo,
                'referred_by_user_id' => $referrer?->id,
                'password' => Hash::make(Str::random(32)),
            ]);

            if ($branchId) {
                BranchTransitionHistory::create([
                    'user_id' => $user->id,
                    'from_branch_id' => null,
                    'to_branch_id' => $branchId,
                    'changed_by' => $user->id,
                    'change_type' => 'registration',
                ]);
            }

            if (Role::query()->where('name', 'Member')->where('guard_name', 'web')->exists()) {
                $user->assignRole('Member');
            }
        });

        event(new Registered($user));

        if ($user->email) {
            try {
                $user->notify(new RegistrationReceived($user));
            } catch (\Throwable) {
            }
        }

        // Proses pembayaran pendaftaran (dummy) terus supaya akaun aktif serta-merta.
        $year = now()->year;
        $feeAmount = (float) ($organization?->fee_amount ?? 50.00);

        $payment = Payment::create([
            'user_id' => $user->id,
            'payable_type' => 'membership_fee',
            'payable_id' => null,
            'amount' => $feeAmount,
            'status' => 'successful',
            'reference' => 'DUMMY-'.strtoupper(Str::random(8)),
            'description' => "Yuran Pendaftaran {$organization?->name} {$year}",
        ]);

        $feeService->markAsPaid($user, $year, $feeAmount, $payment->id);

        if ($user->email) {
            try {
                $user->notify(new RegistrationActivated($user, route('login')));
            } catch (\Throwable) {
            }
        }

        try {
            $adminEmail = AppSetting::singleton()?->admin_contact_email;
            if ($adminEmail) {
                Notification::route('mail', $adminEmail)->notify(new NewMemberAlert($user));
            }
        } catch (\Throwable) {
        }

        return ApiResponse::success([
            'member_no' => $user->member_no,
            'message' => "Pendaftaran berjaya! No Ahli anda: {$user->member_no}. Sila log masuk kali pertama menggunakan No IC anda.",
        ], status: 201);
    }

    /**
     * Semak nombor ahli/rujukan (referral) sebelum daftar — dipanggil oleh
     * skrin pendaftaran mobile jika ada kod referral.
     */
    public function resolveReferral(Request $request, string $code): JsonResponse
    {
        $referrer = User::where('member_no', $code)
            ->first(['id', 'name', 'member_no']);

        if (! $referrer) {
            return ApiResponse::error('Kod rujukan tidak dijumpai.', status: 404);
        }

        return ApiResponse::success([
            'id' => $referrer->id,
            'name' => $referrer->name,
            'member_no' => $referrer->member_no,
        ]);
    }

    /**
     * Semak status ahli (IC/No Ahli/Emel) sebelum log masuk — menentukan
     * sama ada perlu aliran "log masuk kali pertama" (OTP) atau kata laluan.
     */
    public function checkMember(Request $request): JsonResponse
    {
        $request->validate(['identifier' => ['required', 'string', 'max:255']]);

        $user = $this->lookupUser($request->input('identifier'));

        if (! $user || ! $user->organization) {
            return ApiResponse::error('Maklumat ahli tidak dijumpai.', status: 404);
        }

        $setting = AppSetting::singleton();
        $roles = $user->getRoleNames();
        $isAdmin = $user->hasRole(['Superadmin', 'Admin', 'Admin Cawangan']);
        $isMember = $user->hasRole('Member');

        $hasRequestedOtp = OtpCode::where('user_id', $user->id)
            ->where('purpose', 'login')
            ->exists();

        return ApiResponse::success([
            'is_first_login' => is_null($user->first_login_at),
            'has_requested_otp' => $hasRequestedOtp,
            'has_email' => ! is_null($user->email),
            'masked_email' => $user->email ? $this->maskEmail($user->email) : null,
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

    /**
     * Lupa No. Ahli — langkah 1 (semak IC) & langkah 2 (sahkan tarikh lahir).
     */
    public function forgotId(Request $request): JsonResponse
    {
        $request->validate(['ic_number' => ['required', 'string', 'max:32']]);

        $user = $this->lookupUser($request->input('ic_number'));

        if (! $user) {
            return ApiResponse::error('No IC/Passport tidak ditemui dalam sistem.', status: 404);
        }

        if ($request->filled('dob')) {
            $request->validate(['dob' => ['required', 'date']]);

            if (! $user->dob) {
                return ApiResponse::error('Akaun ini tiada tarikh lahir direkodkan. Sila hubungi admin.', status: 422);
            }

            $inputDob = $request->date('dob')->format('Y-m-d');
            $storedDob = $user->dob->format('Y-m-d');

            if ($inputDob !== $storedDob) {
                return ApiResponse::error('Tarikh lahir tidak tepat.', status: 422);
            }

            return ApiResponse::success([
                'verified' => true,
                'masked_email' => $user->email ? $this->maskEmail($user->email) : null,
                'member_no' => $user->member_no,
            ]);
        }

        return ApiResponse::success([
            'needs_verification' => true,
            'message' => 'Sila masukkan tarikh lahir untuk pengesahan identiti.',
        ]);
    }

    /**
     * Lupa kata laluan — hantar pautan reset ke emel berdaftar (berdasarkan IC).
     *
     * @throws ValidationException
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['ic_number' => ['required', 'string', 'max:32']]);

        $normalizedIcNumber = Str::upper(
            preg_replace('/\s+/', '', trim((string) $request->input('ic_number'))) ?? ''
        );

        $user = User::withoutGlobalScopes()->where('ic_number', $normalizedIcNumber)->first();

        if (! $user) {
            return ApiResponse::error('No IC/Passport tidak ditemui dalam sistem.', status: 404);
        }

        if (! $user->email) {
            return ApiResponse::error('Akaun ini tiada emel berdaftar. Sila hubungi admin.', status: 422);
        }

        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            return ApiResponse::error(trans($status), status: 422);
        }

        return ApiResponse::success([
            'message' => 'Pautan reset kata laluan telah dihantar ke emel berdaftar anda.',
            'masked_email' => $this->maskEmail($user->email),
        ]);
    }

    /**
     * Tetapkan semula kata laluan menggunakan token dari emel reset.
     *
     * @throws ValidationException
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return ApiResponse::error(trans($status), status: 422);
        }

        return ApiResponse::success(['message' => 'Kata laluan berjaya ditetapkan semula.']);
    }

    /**
     * Log masuk kali pertama — langkah 1: hantar OTP ke emel berdaftar.
     */
    public function sendOtp(Request $request, OtpService $otp): JsonResponse
    {
        $request->validate(['ic_number' => ['required', 'string', 'max:255']]);

        [$user, $error] = $this->guardOtpEligible($request->input('ic_number'));
        if ($error) {
            return $error;
        }

        if (! $user->email) {
            return ApiResponse::error('Akaun ini tiada emel berdaftar.', status: 422);
        }

        $otp->send($user, 'login');

        return ApiResponse::success(['message' => 'Kod OTP telah dihantar ke emel berdaftar anda.']);
    }

    /**
     * Log masuk kali pertama — kemas kini emel dahulu (jika salah/tiada) lalu hantar OTP.
     */
    public function updateAndSendOtp(Request $request, OtpService $otp): JsonResponse
    {
        $request->validate([
            'ic_number' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'lowercase'],
        ]);

        [$user, $error] = $this->guardOtpEligible($request->input('ic_number'));
        if ($error) {
            return $error;
        }

        $newEmail = Str::lower(trim($request->input('email')));

        if ($user->email !== $newEmail) {
            $exists = User::where('email', $newEmail)->where('id', '!=', $user->id)->exists();
            if ($exists) {
                return ApiResponse::error('Emel ini sudah digunakan oleh akaun lain.', status: 422);
            }
        }

        $user->update(['email' => $newEmail]);

        $otp->send($user, 'login');

        return ApiResponse::success(['message' => 'Kod OTP telah dihantar ke emel anda.']);
    }

    /**
     * Log masuk kali pertama — langkah akhir: sahkan OTP + tetapkan kata
     * laluan baharu, lalu keluarkan token Sanctum (bukan sesi web).
     *
     * @throws ValidationException
     */
    public function verifyOtp(Request $request, OtpService $otp): JsonResponse
    {
        $request->validate([
            'ic_number' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $this->lookupUser($request->input('ic_number'));

        if (! $user) {
            return ApiResponse::error('Ahli tidak dijumpai.', status: 404);
        }

        if (! $otp->verify($user, $request->input('code'), 'login')) {
            return ApiResponse::error('Kod OTP tidak sah atau telah tamat tempoh.', status: 422);
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

        $token = $user->createToken('mobile')->plainTextToken;

        return ApiResponse::success([
            'message' => 'Log masuk berjaya.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Sahkan identiti (IC + tarikh lahir) — digunakan oleh aliran lupa ID/kata laluan.
     */
    public function verifyIdentity(Request $request): JsonResponse
    {
        $request->validate([
            'ic_number' => ['required', 'string', 'max:255'],
            'dob' => ['required', 'date'],
        ]);

        $user = $this->lookupUser($request->input('ic_number'));

        if (! $user) {
            return ApiResponse::error('Ahli tidak dijumpai.', status: 404);
        }

        if (! $user->dob) {
            return ApiResponse::error('Akaun ini tiada tarikh lahir direkodkan. Sila hubungi admin.', status: 422);
        }

        $inputDob = $request->date('dob')->format('Y-m-d');
        $storedDob = $user->dob->format('Y-m-d');

        if ($inputDob !== $storedDob) {
            return ApiResponse::error('Tarikh lahir tidak tepat.', status: 422);
        }

        return ApiResponse::success(['message' => 'Identiti disahkan.']);
    }

    /**
     * Guard sepadan dengan web AuthenticatedSessionController::sendOtp —
     * dikongsi oleh sendOtp() dan updateAndSendOtp().
     *
     * @return array{0: ?User, 1: ?JsonResponse}
     */
    private function guardOtpEligible(string $identifier): array
    {
        $user = $this->lookupUser($identifier);

        if (! $user) {
            return [null, ApiResponse::error('Ahli tidak dijumpai.', status: 404)];
        }

        if (! is_null($user->first_login_at)) {
            return [null, ApiResponse::error(
                'Akaun ini sudah pun log masuk kali pertama. Sila log masuk menggunakan kata laluan anda.',
                status: 422
            )];
        }

        $hasPreviousOtp = OtpCode::where('user_id', $user->id)
            ->where('purpose', 'login')
            ->exists();

        if ($hasPreviousOtp) {
            return [null, ApiResponse::error(
                "Anda telah pun menghantar permintaan log masuk kali pertama. Sila gunakan pautan 'Lupa Kata Laluan' jika terlupa kata laluan.",
                status: 422
            )];
        }

        return [$user, null];
    }

    private function lookupUser(string $identifier): ?User
    {
        $normalizedId = Str::upper(preg_replace('/\s+/', '', trim($identifier)) ?? '');
        $email = Str::lower(trim($identifier));

        return User::withoutGlobalScopes()
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

    private function resolveUser(Request $request): ?User
    {
        if ($request->filled('email')) {
            return User::withoutGlobalScopes()
                ->where('email', Str::lower(trim((string) $request->input('email'))))
                ->first();
        }

        $identifier = (string) $request->input('ic_number');
        $normalizedId = Str::upper(preg_replace('/\s+/', '', trim($identifier)) ?? '');
        $email = Str::lower(trim($identifier));

        return User::withoutGlobalScopes()
            ->where(function ($query) use ($normalizedId, $email) {
                $query->where('ic_number', $normalizedId)
                    ->orWhere('member_no', $normalizedId)
                    ->orWhere('email', $email);
            })
            ->first();
    }
}

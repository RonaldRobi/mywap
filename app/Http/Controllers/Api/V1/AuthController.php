<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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

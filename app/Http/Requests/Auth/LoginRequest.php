<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'login_type' => ['nullable', 'in:admin,member'],
            'email' => ['nullable', 'string', 'email'],
            'ic_number' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if ($this->filled('email')) {
            $this->authenticateByEmail();
        } else {
            $this->authenticateByIdentifier();
        }

        RateLimiter::clear($this->throttleKey());
    }

    private function authenticateByEmail(): void
    {
        $email = Str::lower(trim((string) $this->input('email')));

        $user = User::where('email', $email)->first();

        if (! Auth::attempt(['email' => $email, 'password' => $this->input('password')], $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            if ($user && is_null($user->first_login_at)) {
                throw ValidationException::withMessages([
                    'email' => 'Akaun ini belum aktif. Sila gunakan pautan "Log Masuk Kali Pertama" di bawah.',
                ]);
            }

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }
    }

    private function authenticateByIdentifier(): void
    {
        $identifier = (string) $this->input('ic_number');
        $normalizedId = Str::upper(preg_replace('/\s+/', '', trim($identifier)) ?? '');
        $email = Str::lower(trim($identifier));

        $user = User::where(function ($query) use ($normalizedId, $email) {
            $query->where('ic_number', $normalizedId)
                  ->orWhere('member_no', $normalizedId)
                  ->orWhere('email', $email);
        })->first();

        if (! $user) {
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages([
                'ic_number' => trans('auth.failed'),
            ]);
        }

        if (! Auth::attempt(['id' => $user->id, 'password' => $this->input('password')], $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            if (is_null($user->first_login_at)) {
                throw ValidationException::withMessages([
                    'ic_number' => 'Akaun ini belum aktif. Sila gunakan pautan "Log Masuk Kali Pertama" di bawah.',
                ]);
            }

            throw ValidationException::withMessages([
                'ic_number' => trans('auth.failed'),
            ]);
        }
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            $this->identifierField() => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate($this->identifierValue().'|'.$this->ip());
    }

    private function identifierField(): string
    {
        if ($this->filled('email')) {
            return 'email';
        }

        return 'ic_number';
    }

    private function identifierValue(): string
    {
        if ($this->filled('email')) {
            return Str::lower(trim((string) $this->input('email')));
        }

        $raw = (string) $this->input('ic_number');

        return Str::upper(preg_replace('/\s+/', '', trim($raw)) ?? '');
    }
}

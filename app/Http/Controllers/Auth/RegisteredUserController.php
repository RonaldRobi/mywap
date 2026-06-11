<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register', [
            'organizations' => Organization::query()
                ->orderBy('min_age')
                ->get(['id', 'name', 'min_age', 'max_age']),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $normalizedIcNumber = Str::upper(preg_replace('/\s+/', '', trim((string) $request->input('ic_number'))) ?? '');
        $request->merge(['ic_number' => $normalizedIcNumber]);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'ic_number' => 'required|string|max:32|unique:'.User::class.',ic_number',
            'phone' => 'nullable|string|max:20',
            'dob' => 'required|date',
            'password' => ['required', 'confirmed'],
        ]);

        $dob = $request->date('dob');
        $organization = $dob ? Organization::forAge($dob->age) : null;
        $organization ??= Organization::query()->orderBy('min_age')->first();

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'ic_number' => $normalizedIcNumber,
            'phone' => $request->phone,
            'dob' => $dob,
            'current_organization_id' => $organization?->id,
            'password' => Hash::make($request->password),
        ]);

        if (Role::query()->where('name', 'Member')->where('guard_name', 'web')->exists()) {
            $user->assignRole('Member');
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}

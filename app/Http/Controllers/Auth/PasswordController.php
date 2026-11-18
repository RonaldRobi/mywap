<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $forceChange = (bool) $user->must_change_password;

        $rules = [
            'password' => ['required', Password::defaults(), 'confirmed'],
        ];

        if (! $forceChange) {
            $rules['current_password'] = ['required', 'current_password'];
        }

        $validated = $request->validate($rules);

        $updates = ['password' => Hash::make($validated['password'])];

        if (Schema::hasColumn('users', 'must_change_password')) {
            $updates['must_change_password'] = false;
        }

        $user->update($updates);

        // Keselamatan: bila kata laluan ditukar, tarik balik semua token API
        // (peranti mobile) supaya token lama yang terlepas tidak kekal sah.
        $user->tokens()->delete();

        if ($forceChange) {
            return redirect()
                ->route('dashboard')
                ->with('success', 'Kata laluan berjaya ditukar.');
        }

        return back();
    }
}

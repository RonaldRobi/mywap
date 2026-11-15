<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->must_change_password) {
            return $next($request);
        }

        if (
            $request->routeIs('profile.edit', 'profile.update', 'profile.show', 'password.update', 'logout')
            || $request->routeIs('password.*')
            || $request->routeIs('login*')
            || $request->is('build/*')
            || $request->is('storage/*')
            || $request->is('images/*')
        ) {
            return $next($request);
        }

        return redirect()
            ->route('profile.edit')
            ->with('warning', 'Kata laluan sementara anda perlu ditukar sebelum meneruskan.');
    }
}

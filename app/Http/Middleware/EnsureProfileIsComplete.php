<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileIsComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return $next($request);
        }

        $user = $request->user();

        if ($user->hasRole(['Superadmin', 'Admin'])) {
            return $next($request);
        }

        if ($request->routeIs(
            'logout',
            'profile.edit',
            'profile.update',
            'profile.destroy',
            'member.complete-profile',
            'member.complete-profile.store',
            'admin.*',
            'superadmin.*'
        )) {
            return $next($request);
        }

        if (is_null($user->profile_completed_at)) {
            return redirect()->route('member.complete-profile');
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * EnsureApiAdminAccess
 *
 * Penguatkuasaan akses pentadbir di lapisan routing untuk kumpulan route
 * API /api/v1/admin/* (mobile). Sebelum ini semakan hanya dilakukan secara
 * inline di dalam setiap method AdminController — mudah tertinggal bila route
 * baharu ditambah. Peranan yang layak sepadan dengan AdminController::authorizeAdmin.
 */
class EnsureApiAdminAccess
{
    public function handle(Request $request, Closure $next): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole(['Superadmin', 'Admin', 'org-admin'])) {
            return ApiResponse::error('Tiada kebenaran.', [], 403);
        }

        return $next($request);
    }
}

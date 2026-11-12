<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hadkan Admin Cawangan kepada laluan panel yang dibenarkan sahaja.
 * Admin organisasi dan Superadmin tidak terjejas.
 */
class EnsureBranchAdminAccess
{
    /**
     * @var list<string>
     */
    private const ALLOWED_ROUTES = [
        'branches.index',
        'admin.dashboard',
        'admin.events.',
        'admin.attendance',
        'admin.attendance.',
        'admin.forms.',
        'admin.articles.',
        'admin.videos.',
        'admin.library.',
        'admin.hub.library.',
        'admin.hub.announcements.',
        'admin.fees.members',
        'products.',
        'orders.updateStatus',
        'admin.usrah.',
        'admin.facilities.',
        'admin.facility-bookings.',
        'admin.broadcasts.',
        'superadmin.infaq.',
        'admin.donors.',
        'admin.members.export',
        'admin.members.update',
        'admin.hub.manage',
        'admin.hub.members.logs',
        'admin.hub.members.programs',
        'admin.hub.members.reset-password',
        'admin.branch-report.',
        'admin.program-report.',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isBranchAdmin()) {
            return $next($request);
        }

        if ($user->managedBranchIds() === []) {
            return redirect()->route('branch-admin.unassigned');
        }

        $name = (string) ($request->route()?->getName() ?? '');

        foreach (self::ALLOWED_ROUTES as $allowed) {
            if ($name === $allowed || str_starts_with($name, $allowed)) {
                return $next($request);
            }
        }

        abort(403, 'Laluan ini tidak dibenarkan untuk Admin Cawangan.');
    }
}

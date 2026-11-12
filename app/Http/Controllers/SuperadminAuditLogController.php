<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Organization;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SuperadminAuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $query = ActivityLog::query()
            ->with(['user:id,name,email'])
            ->latest('id');

        if ($request->filled('action')) {
            $query->where('action', $request->string('action'));
        }

        if ($request->filled('organization_id')) {
            $query->where('organization_id', (int) $request->input('organization_id'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            });
        }

        $logs = $query->paginate(50)->withQueryString()->through(fn (ActivityLog $log) => [
            'id' => $log->id,
            'action' => $log->action,
            'description' => $log->description,
            'organization_id' => $log->organization_id,
            'target_type' => $log->target_type,
            'target_id' => $log->target_id,
            'performed_by' => $log->user?->name,
            'performed_by_email' => $log->user?->email,
            'created_at' => $log->created_at?->toDateTimeString(),
        ]);

        return Inertia::render('Superadmin/AuditLog', [
            'logs' => $logs,
            'organizations' => Organization::query()->orderBy('min_age')->get(['id', 'name']),
            'actions' => ActivityLog::query()
                ->select('action')
                ->distinct()
                ->orderBy('action')
                ->pluck('action'),
            'filters' => $request->only(['action', 'organization_id', 'search']),
        ]);
    }
}

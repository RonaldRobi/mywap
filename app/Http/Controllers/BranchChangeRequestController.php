<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchChangeRequest;
use App\Models\BranchTransitionHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BranchChangeRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user()->load('organization');
        $isSuperadmin = $user->hasRole('Superadmin');
        $isBranchAdmin = $user->hasRole('Admin Cawangan');

        $query = BranchChangeRequest::with([
            'user:id,name,email,member_no,current_organization_id,branch_id',
            'fromBranch:id,name',
            'toBranch:id,name',
        ])->where('status', 'pending');

        if ($isSuperadmin) {
            // Superadmin sees all requests across all organizations
        } elseif ($isBranchAdmin) {
            $query->whereHas('user', function ($q) use ($user) {
                $q->where('current_organization_id', $user->current_organization_id)
                  ->where('branch_id', $user->branch_id);
            });
        } else {
            $query->whereHas('user', function ($q) use ($user) {
                $q->where('current_organization_id', $user->current_organization_id);
            });
        }

        $requests = $query->latest()->get()->map(fn ($r) => [
            'id' => $r->id,
            'user' => [
                'id' => $r->user->id,
                'name' => $r->user->name,
                'email' => $r->user->email,
                'member_no' => $r->user->member_no,
            ],
            'from_branch' => $r->fromBranch?->name,
            'to_branch' => $r->toBranch?->name ?? 'Tiada Cawangan',
            'submitted_at' => $r->created_at->diffForHumans(),
            'submitted_at_raw' => $r->created_at->toISOString(),
        ]);

        $branches = $isSuperadmin
            ? Branch::where('is_active', true)->orderBy('name')->get(['id', 'name'])
            : Branch::where('organization_id', $user->current_organization_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']);

        return Inertia::render('Admin/BranchChangeRequests', [
            'requests' => $requests,
            'branches' => $branches,
        ]);
    }

    public function approve(Request $request, BranchChangeRequest $branchChangeRequest): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeOrgAccess($user, $branchChangeRequest);

        abort_if($branchChangeRequest->status !== 'pending', 400, 'Permohonan ini sudah diproses.');

        $branchChangeRequest->update([
            'status' => 'approved',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        $branchChangeRequest->user->update([
            'branch_id' => $branchChangeRequest->to_branch_id,
        ]);

        BranchTransitionHistory::create([
            'user_id' => $branchChangeRequest->user_id,
            'from_branch_id' => $branchChangeRequest->from_branch_id,
            'to_branch_id' => $branchChangeRequest->to_branch_id,
            'changed_by' => $user->id,
            'change_type' => 'self_request',
            'request_id' => $branchChangeRequest->id,
        ]);

        return back()->with('success', 'Permohonan pertukaran cawangan telah diluluskan.');
    }

    public function reject(Request $request, BranchChangeRequest $branchChangeRequest): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeOrgAccess($user, $branchChangeRequest);

        abort_if($branchChangeRequest->status !== 'pending', 400, 'Permohonan ini sudah diproses.');

        $data = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $branchChangeRequest->update([
            'status' => 'rejected',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'rejection_reason' => $data['rejection_reason'] ?? null,
        ]);

        return back()->with('success', 'Permohonan pertukaran cawangan telah ditolak.');
    }

    private function authorizeOrgAccess($authUser, BranchChangeRequest $request): void
    {
        if ($authUser->hasRole('Superadmin')) {
            return;
        }

        $targetUser = $request->user;

        if ($targetUser->current_organization_id !== $authUser->current_organization_id) {
            abort(403, 'Akses ditolak.');
        }

        // Branch admin can only manage requests for their own branch
        if ($authUser->hasRole('Admin Cawangan')) {
            abort_if((int) $targetUser->branch_id !== (int) $authUser->branch_id, 403, 'Akses ditolak.');
        }
    }
}

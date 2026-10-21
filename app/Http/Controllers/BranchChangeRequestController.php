<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchChangeRequest;
use App\Models\BranchTransitionHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
            abort_if(! $user->branch_id, 403, 'Akaun admin cawangan ini tiada cawangan.');

            // Admin cawangan melihat permohonan yang melibatkan cawangannya,
            // sama ada ahli keluar dari cawangan itu (sumber) atau masuk ke
            // cawangan itu (destinasi).
            $query->whereHas('user', fn ($q) => $q->where('current_organization_id', $user->current_organization_id))
                ->where(function ($q) use ($user) {
                    $q->where('to_branch_id', $user->branch_id)
                        ->orWhereHas('user', fn ($inner) => $inner->where('branch_id', $user->branch_id));
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

        DB::transaction(fn () => $this->approveRequest($branchChangeRequest, $user));

        return back()->with('success', 'Permohonan pertukaran cawangan telah diluluskan.');
    }

    public function approveBulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        $user = $request->user();

        $requests = BranchChangeRequest::with('user')
            ->whereIn('id', $data['ids'])
            ->where('status', 'pending')
            ->get();

        if ($requests->isEmpty()) {
            return back()->with('error', 'Tiada permohonan yang menunggu kelulusan untuk diproses.');
        }

        // Semua permohonan mesti dalam skop pengguna sebelum apa-apa diubah.
        foreach ($requests as $branchChangeRequest) {
            $this->authorizeOrgAccess($user, $branchChangeRequest);
        }

        DB::transaction(function () use ($requests, $user) {
            foreach ($requests as $branchChangeRequest) {
                $this->approveRequest($branchChangeRequest, $user);
            }
        });

        $approved = $requests->count();
        $skipped = count($data['ids']) - $approved;

        $message = "{$approved} permohonan pertukaran cawangan telah diluluskan.";
        if ($skipped > 0) {
            $message .= " {$skipped} permohonan dilangkau (sudah diproses atau tidak dijumpai).";
        }

        return back()->with('success', $message);
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

    private function approveRequest(BranchChangeRequest $branchChangeRequest, $user): void
    {
        $branchChangeRequest->update([
            'status' => 'approved',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        $branchChangeRequest->user->update([
            'branch_id' => $branchChangeRequest->to_branch_id,
        ]);

        Cache::forget("member.dashboard.{$branchChangeRequest->user_id}");

        BranchTransitionHistory::create([
            'user_id' => $branchChangeRequest->user_id,
            'from_branch_id' => $branchChangeRequest->from_branch_id,
            'to_branch_id' => $branchChangeRequest->to_branch_id,
            'changed_by' => $user->id,
            'change_type' => 'self_request',
            'request_id' => $branchChangeRequest->id,
        ]);
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

        // Admin cawangan boleh urus permohonan yang melibatkan cawangannya —
        // sama ada ahli keluar dari cawangan itu (sumber) atau masuk ke
        // cawangan itu (destinasi).
        if ($authUser->hasRole('Admin Cawangan')) {
            $isSourceBranch = (int) $targetUser->branch_id === (int) $authUser->branch_id;
            $isDestinationBranch = (int) $request->to_branch_id === (int) $authUser->branch_id;

            abort_unless($isSourceBranch || $isDestinationBranch, 403, 'Akses ditolak.');
        }
    }
}

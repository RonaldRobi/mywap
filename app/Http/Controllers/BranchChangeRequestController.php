<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchChangeRequest;
use App\Models\BranchTransitionHistory;
use App\Notifications\BranchChangeRequestReviewed;
use App\Support\MemberSearch;
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

        $search = trim((string) $request->query('search', ''));
        $fromBranchId = $request->query('from_branch_id');
        $toBranchId = $request->query('to_branch_id');
        $perPage = (int) $request->query('per_page', 25);
        $perPage = in_array($perPage, [25, 50, 100], true) ? $perPage : 25;

        $query = BranchChangeRequest::with([
            'user:id,name,email,member_no,current_organization_id,branch_id',
            'fromBranch:id,name',
            'toBranch:id,name',
        ])->where('status', 'pending');

        if ($isSuperadmin) {
            // Superadmin sees all requests across all organizations
        } else {
            $query->whereHas('user', function ($q) use ($user) {
                $q->where('current_organization_id', $user->current_organization_id);
            });
        }

        if ($search !== '') {
            $query->whereHas('user', fn ($userQuery) => MemberSearch::apply($userQuery, $search));
        }

        $query->when($fromBranchId, fn ($q) => $q->where('from_branch_id', $fromBranchId))
            ->when($toBranchId, fn ($q) => $q->where('to_branch_id', $toBranchId));

        $requests = $query->latest()->paginate($perPage)->withQueryString()->through(fn ($r) => [
            'id' => $r->id,
            'user' => [
                'id' => $r->user->id,
                'name' => $r->user->name,
                'email' => $r->user->email,
                'member_no' => $r->user->member_no,
            ],
            'from_branch' => $r->fromBranch?->name,
            'to_branch' => $r->toBranch?->name ?? 'Tiada Cawangan/Negeri',
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
            'filters' => [
                'search' => $search,
                'from_branch_id' => $fromBranchId,
                'to_branch_id' => $toBranchId,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function movements(Request $request): Response
    {
        $user = $request->user();
        $branchIds = $user->managedBranchIds();

        abort_if($branchIds === [], 403, 'Akaun admin cawangan/negeri belum ditugaskan.');

        $search = trim((string) $request->query('search', ''));
        $direction = in_array($request->query('direction'), ['incoming', 'outgoing', 'all'], true)
            ? $request->query('direction')
            : 'incoming';
        $selectedBranchId = (int) $request->query('branch_id', 0);
        $selectedBranchId = in_array($selectedBranchId, $branchIds, true) ? $selectedBranchId : null;
        $scopeBranchIds = $selectedBranchId ? [$selectedBranchId] : $branchIds;

        $query = BranchTransitionHistory::query()
            ->with([
                'user:id,name,email,member_no,branch_id',
                'fromBranch:id,name,state',
                'toBranch:id,name,state',
            ])
            ->when($direction === 'incoming', fn ($q) => $q->whereIn('to_branch_id', $scopeBranchIds))
            ->when($direction === 'outgoing', fn ($q) => $q->whereIn('from_branch_id', $scopeBranchIds))
            ->when($direction === 'all', fn ($q) => $q->where(function ($movementQuery) use ($scopeBranchIds) {
                $movementQuery->whereIn('from_branch_id', $scopeBranchIds)
                    ->orWhereIn('to_branch_id', $scopeBranchIds);
            }))
            ->when($search !== '', fn ($q) => $q->whereHas(
                'user',
                fn ($userQuery) => MemberSearch::apply($userQuery, $search, MemberSearch::COLUMNS, false),
            ));

        $movements = $query
            ->latest()
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(function (BranchTransitionHistory $movement) use ($branchIds) {
                $fromManaged = $movement->from_branch_id && in_array((int) $movement->from_branch_id, $branchIds, true);
                $toManaged = in_array((int) $movement->to_branch_id, $branchIds, true);
                $direction = $fromManaged && $toManaged
                    ? 'internal'
                    : ($toManaged ? 'incoming' : 'outgoing');

                return [
                    'id' => $movement->id,
                    'user' => [
                        'id' => $movement->user->id,
                        'name' => $movement->user->name,
                        'email' => $movement->user->email,
                        'member_no' => $movement->user->member_no,
                    ],
                    'from_branch' => $movement->fromBranch?->name,
                    'to_branch' => $movement->toBranch?->name ?? 'Tiada cawangan/negeri',
                    'direction' => $direction,
                    'source' => match ($movement->change_type) {
                        'self_request' => 'Permohonan ahli',
                        'admin_direct' => 'Kemas kini admin',
                        'admin_bulk' => 'Pemindahan pukal',
                        'registration' => 'Pendaftaran',
                        'profile_completion' => 'Lengkap profil',
                        'import' => 'Import ahli',
                        default => 'Kemas kini cawangan/negeri',
                    },
                    'changed_at' => $movement->created_at->format('d/m/Y, H:i'),
                    'changed_at_relative' => $movement->created_at->diffForHumans(),
                    'member_url' => in_array((int) $movement->user->branch_id, $branchIds, true)
                        ? route('admin.hub.manage', [
                            'search' => $movement->user->member_no ?: $movement->user->name,
                        ])
                        : null,
                ];
            });

        $branches = Branch::query()
            ->whereKey($branchIds)
            ->orderBy('name')
            ->get(['id', 'name', 'state']);

        return Inertia::render('Admin/MemberMovements', [
            'movements' => $movements,
            'branches' => $branches,
            'filters' => [
                'search' => $search,
                'direction' => $direction,
                'branch_id' => $selectedBranchId,
            ],
        ]);
    }

    public function approve(Request $request, BranchChangeRequest $branchChangeRequest): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeOrgAccess($user, $branchChangeRequest);

        abort_if($branchChangeRequest->status !== 'pending', 400, 'Permohonan ini sudah diproses.');

        DB::transaction(fn () => $this->approveRequest($branchChangeRequest, $user));

        return back()->with('success', 'Permohonan pertukaran cawangan/negeri telah diluluskan.');
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

        $message = "{$approved} permohonan pertukaran cawangan/negeri telah diluluskan.";
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

        $branchChangeRequest->user?->notify(new BranchChangeRequestReviewed(
            $branchChangeRequest,
            'rejected',
            $data['rejection_reason'] ?? null,
        ));

        return back()->with('success', 'Permohonan pertukaran cawangan/negeri telah ditolak.');
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

        $branchChangeRequest->user?->notify(new BranchChangeRequestReviewed($branchChangeRequest, 'approved'));
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
    }
}

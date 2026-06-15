<?php

namespace App\Http\Controllers;

use App\Imports\FeeImport as FeeImportCsv;
use App\Models\ActivityLog;
use App\Models\FeeImport as FeeImportModel;
use App\Models\MembershipFee;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use App\Services\FeeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class MemberFeeController extends Controller
{
    public function index(Request $request, FeeService $feeService): Response
    {
        $user = $request->user();
        $isSuperadmin = $user->hasRole('Superadmin');
        $orgId = $user->current_organization_id;

        $year = (int) $request->input('year', now()->year);

        $query = User::withoutGlobalScopes()
            ->with(['membershipFees' => fn ($q) => $q->where('year', $year)])
            ->with('organization:id,name,slug')
            ->when(! $isSuperadmin, fn ($q) => $q->where('current_organization_id', $orgId))
            ->when($isSuperadmin && $request->filled('organization_id'), fn ($q) => $q->where('current_organization_id', $request->organization_id))
            ->orderBy('name');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('ic_number', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%");
            });
        }

        if ($request->filled('fee_status')) {
            $status = $request->fee_status;
            if ($status === 'paid') {
                $query->whereHas('membershipFees', fn ($q) => $q->where('year', $year)->whereIn('status', ['paid', 'exempted']));
            } elseif ($status === 'life_member') {
                $query->whereHas('membershipFees', fn ($q) => $q->where('status', 'life_member'));
            } elseif ($status === 'exempted') {
                $query->whereHas('membershipFees', fn ($q) => $q->where('status', 'exempted'));
            } elseif ($status === 'due') {
                $query->whereDoesntHave('membershipFees', fn ($q) => $q->where('year', $year)->whereIn('status', ['paid', 'exempted', 'life_member']));
            }
        }

        $members = $query->paginate(25)->through(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'ic_number' => $u->ic_number,
            'member_no' => $u->member_no,
            'phone' => $u->phone,
            'email' => $u->email,
            'organization' => $u->organization ? ['id' => $u->organization->id, 'name' => $u->organization->name, 'slug' => $u->organization->slug] : null,
            'fee' => $u->membershipFees->first() ? [
                'id' => $u->membershipFees->first()->id,
                'year' => $u->membershipFees->first()->year,
                'amount' => (float) $u->membershipFees->first()->amount,
                'status' => $u->membershipFees->first()->status?->value ?? ($u->membershipFees->first()->status ?? 'unpaid'),
                'paid_at' => $u->membershipFees->first()->paid_at?->toDateString(),
                'notes' => $u->membershipFees->first()->notes,
            ] : ['status' => 'unpaid', 'year' => $year],
        ]);

        $stats = $isSuperadmin && $request->filled('organization_id')
            ? $feeService->getAdminStats((int) $request->organization_id, $year)
            : ($isSuperadmin
                ? $feeService->getAdminStats(null, $year)
                : $feeService->getAdminStats($orgId, $year));

        $monthlyCollection = collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => (float) Payment::query()
            ->where('status', 'successful')
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $m)
            ->when(! $isSuperadmin || $request->filled('organization_id'), function ($q) use ($orgId, $isSuperadmin, $request) {
                $q->whereHas('user', fn ($uq) => $uq->withoutGlobalScopes()->where('current_organization_id',
                    $isSuperadmin ? (int) $request->organization_id : $orgId));
            })
            ->sum('amount')
        ]);

        $chart = $monthlyCollection->map(fn ($total, $m) => [
            'month' => date('F', mktime(0, 0, 0, (int) $m, 1)),
            'total' => $total,
        ])->values();

        $years = range(now()->year - 10, now()->year + 1);

        $orgIds = $isSuperadmin
            ? Organization::pluck('id', 'fee_amount')
            : [$orgId => Organization::find($orgId)?->fee_amount ?? 0];

        $expectedAmount = 0;
        $activeMembers = 0;
        foreach ($orgIds as $oid => $feeAmount) {
            $cnt = User::withoutGlobalScopes()
                ->where('current_organization_id', $oid)
                ->whereDoesntHave('membershipFees', fn ($q) => $q->whereIn('status', ['life_member', 'exempted']))
                ->count();
            $activeMembers += $cnt;
            $expectedAmount += $cnt * (float) $feeAmount;
        }

        return Inertia::render('Admin/MemberFees/Index', [
            'members' => $members,
            'stats' => $stats,
            'year' => $year,
            'years' => $years,
            'chart' => $chart,
            'organizations' => $isSuperadmin ? Organization::select('id', 'name', 'slug')->get() : [],
            'reconciliation' => [
                'expected' => round($expectedAmount, 2),
                'collected' => $stats['collected_amount'] ?? 0,
                'outstanding' => round(max(0, $expectedAmount - ($stats['collected_amount'] ?? 0)), 2),
                'rate' => $expectedAmount > 0 ? round(($stats['collected_amount'] ?? 0) / $expectedAmount * 100, 1) : 0,
            ],
            'filters' => $request->only(['search', 'fee_status']),
        ]);
    }

    public function paymentHistory(Request $request, FeeService $feeService, User $targetUser): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole(['Superadmin', 'Admin'])) abort(403);
        $this->authorizeOrg($user, $targetUser);

        $history = $feeService->getPaymentHistory($targetUser);

        return response()->json(['data' => $history]);
    }

    public function activityLog(Request $request, User $targetUser): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole(['Superadmin', 'Admin'])) abort(403);
        $this->authorizeOrg($user, $targetUser);

        $logs = ActivityLog::with('user:id,name')
            ->where('target_type', User::class)
            ->where('target_id', $targetUser->id)
            ->latest()
            ->take(20)
            ->get()
            ->map(fn (ActivityLog $log) => [
                'action' => $log->action,
                'description' => $log->description,
                'performed_by' => $log->user?->name,
                'created_at' => $log->created_at?->toISOString(),
            ]);

        return response()->json(['data' => $logs]);
    }

    public function downloadReceipt(Request $request, Payment $payment)
    {
        $user = $request->user();
        if (! $user->hasRole(['Superadmin', 'Admin'])) abort(403);

        $targetUser = $payment->user;
        $this->authorizeOrg($user, $targetUser);

        $fee = MembershipFee::where('payment_id', $payment->id)->first();
        if (! $fee) abort(404);

        $pdf = Pdf::loadView('exports.receipt', [
            'payment' => $payment,
            'fee' => $fee,
            'member' => $payment->user,
        ]);

        $filename = 'resit-yuran-' . $fee->year . '-' . ($payment->user->member_no ?? $payment->user->id) . '.pdf';
        return $pdf->download($filename);
    }

    public function feeDetail(Request $request, User $targetUser, MembershipFee $membershipFee): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole(['Superadmin', 'Admin'])) abort(403);
        $this->authorizeOrg($user, $targetUser);

        return response()->json([
            'data' => [
                'id' => $membershipFee->id,
                'year' => $membershipFee->year,
                'amount' => (float) $membershipFee->amount,
                'status' => $membershipFee->status instanceof \App\Enums\FeeStatus
                    ? $membershipFee->status->value
                    : $membershipFee->status,
                'paid_at' => $membershipFee->paid_at?->toISOString(),
                'notes' => $membershipFee->notes,
                'payment_id' => $membershipFee->payment_id,
                'reference' => $membershipFee->payment?->reference,
            ],
        ]);
    }

    public function updateMemberFee(Request $request, User $targetUser, MembershipFee $membershipFee): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole(['Superadmin', 'Admin'])) abort(403);
        $this->authorizeOrg($user, $targetUser);

        $data = $request->validate([
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
            'paid_at' => ['nullable', 'date'],
        ]);

        $oldValues = $membershipFee->only(['amount', 'notes', 'paid_at']);

        $updateData = [];
        if ($request->has('amount')) $updateData['amount'] = (float) $data['amount'];
        if ($request->has('notes')) $updateData['notes'] = $data['notes'];
        if ($request->has('paid_at')) $updateData['paid_at'] = $data['paid_at'] ?: null;

        if (!empty($updateData)) {
            $membershipFee->update($updateData);
        }

        $newValues = $membershipFee->only(['amount', 'notes', 'paid_at']);

        $changes = [];
        foreach ($updateData as $field => $newVal) {
            $oldVal = $oldValues[$field] ?? null;
            $newVal = $newValues[$field] ?? null;
            if ($oldVal instanceof \Carbon\Carbon) $oldVal = $oldVal->toISOString();
            if ($newVal instanceof \Carbon\Carbon) $newVal = $newVal->toISOString();
            $oldVal = is_numeric($oldVal) ? (float) $oldVal : $oldVal;
            $newVal = is_numeric($newVal) ? (float) $newVal : $newVal;
            if ($oldVal !== $newVal) {
                $changes[] = "$field: " . (is_null($oldVal) ? 'kosong' : $oldVal) . ' → ' . (is_null($newVal) ? 'kosong' : $newVal);
            }
        }

        if (!empty($changes)) {
            ActivityLog::create([
                'user_id' => $user->id,
                'organization_id' => $user->current_organization_id,
                'target_type' => MembershipFee::class,
                'target_id' => $membershipFee->id,
                'action' => 'update_fee',
                'description' => "Yuran {$targetUser->name} tahun {$membershipFee->year} dikemaskini: " . implode('; ', $changes),
                'old_values' => $oldValues,
                'new_values' => $newValues,
            ]);
        }

        return response()->json(['success' => true, 'changes' => $changes]);
    }

    public function reversePayment(Request $request, Payment $payment): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole(['Superadmin', 'Admin'])) abort(403);

        $targetUser = $payment->user;
        $this->authorizeOrg($user, $targetUser);

        if ($payment->status === 'voided') {
            return response()->json(['success' => false, 'message' => 'Pembayaran ini sudah divoid.'], 422);
        }

        $fee = MembershipFee::where('payment_id', $payment->id)->first();
        $memberName = $payment->user?->name ?? 'Unknown';

        DB::transaction(function () use ($payment, $fee, $user) {
            $payment->update(['status' => 'voided']);

            if ($fee) {
                $fee->update(['status' => 'unpaid', 'payment_id' => null, 'paid_at' => null]);
            }

            ActivityLog::create([
                'user_id' => $user->id,
                'organization_id' => $user->current_organization_id,
                'target_type' => Payment::class,
                'target_id' => $payment->id,
                'action' => 'void_payment',
                'description' => "Pembayaran {$payment->reference} untuk {$memberName} telah divoidkan. Jumlah: RM {$payment->amount}.",
            ]);
        });

        return response()->json(['success' => true, 'message' => 'Pembayaran berjaya divoidkan.']);
    }

    public function downloadTemplate()
    {
        $headers = ['ic_number', 'no_ahli', 'amount', 'reference'];
        $callback = function () use ($headers) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            fputcsv($handle, ['900101025432', 'P00123', '50.00', 'REF-001']);
            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="template-import-yuran.csv"',
        ]);
    }

    public function manualPayment(Request $request, FeeService $feeService): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole(['Superadmin', 'Admin'])) abort(403);

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:' . (now()->year + 1)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:100'],
            'proof' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:10240'],
        ]);

        $targetUser = User::withoutGlobalScopes()->findOrFail($data['user_id']);
        $this->authorizeOrg($user, $targetUser);

        if ($feeService->isLifeMember($targetUser) || $feeService->isExempted($targetUser)) {
            return response()->json(['success' => false, 'message' => 'Ahli ini dikecualikan / life member.'], 422);
        }

        $ref = $data['reference'] ?? ('MANUAL-' . strtoupper(Str::random(8)));
        if ($data['reference'] && Payment::where('reference', $data['reference'])->exists()) {
            return response()->json(['success' => false, 'message' => 'Nombor rujukan ini telah digunakan. Sila guna rujukan lain.'], 422);
        }

        $proofPath = $request->file('proof')->store('manual-payments');

        $fee = $feeService->markAsPaid($targetUser, $data['year'], (float) $data['amount']);

        $payment = Payment::create([
            'user_id' => $targetUser->id,
            'payable_type' => MembershipFee::class,
            'payable_id' => $fee->id,
            'amount' => (float) $data['amount'],
            'status' => 'successful',
            'reference' => $ref,
            'description' => "Yuran {$targetUser->organization?->name} {$data['year']} (Bayaran Manual)",
            'proof_path' => $proofPath,
            'uploaded_by' => $user->id,
        ]);

        $fee->update(['payment_id' => $payment->id]);

        ActivityLog::create([
            'user_id' => $user->id,
            'organization_id' => $user->current_organization_id,
            'target_type' => User::class,
            'target_id' => $targetUser->id,
            'action' => 'manual_payment',
            'description' => "Bayaran manual RM {$data['amount']} untuk {$targetUser->name} tahun {$data['year']}. Rujukan: {$payment->reference}",
        ]);

        return response()->json(['success' => true, 'message' => 'Pembayaran berjaya direkodkan.']);
    }

    // ─── IMPORT ─────────────────────────────────────────────────────────────────

    public function previewImport(Request $request, FeeService $feeService): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole(['Superadmin', 'Admin'])) abort(403);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx'],
            'year' => ['required', 'integer', 'min:2000', 'max:' . (now()->year + 1)],
        ]);

        $year = (int) $request->year;
        $rows = [];

        Excel::import(new class($rows) implements \Maatwebsite\Excel\Concerns\ToCollection, \Maatwebsite\Excel\Concerns\WithHeadingRow, \Maatwebsite\Excel\Concerns\WithStartRow {
            public $rows;
            public function __construct(array &$rows) { $this->rows = &$rows; }
            public function startRow(): int { return 2; }
            public function collection(\Illuminate\Support\Collection $collection) {
                $this->rows = $collection->take(50)->map(fn ($r) => [
                    'ic_number' => $r['ic_number'] ?? '',
                    'no_ahli' => $r['no_ahli'] ?? '',
                    'amount' => $r['amount'] ?? '',
                    'reference' => $r['reference'] ?? '',
                ])->toArray();
            }
        }, $request->file('file'));

        $preview = [];
        foreach ($rows as $row) {
            $ic = trim((string) ($row['ic_number'] ?? ''));
            $memberNo = trim((string) ($row['no_ahli'] ?? ''));
            $userMatch = null;

            if ($ic || $memberNo) {
                $query = User::withoutGlobalScopes();
                if (! $user->hasRole('Superadmin')) {
                    $query->where('current_organization_id', $user->current_organization_id);
                }
                if ($ic && $memberNo) {
                    $query->where('ic_number', $ic)
                        ->where(fn ($q) => $q->where('member_no', $memberNo)->orWhere('original_member_no', $memberNo));
                } elseif ($ic) {
                    $query->where('ic_number', $ic);
                } else {
                    $query->where(fn ($q) => $q->where('member_no', $memberNo)->orWhere('original_member_no', $memberNo));
                }
                $userMatch = $query->first();
            }

            if (! $userMatch) {
                $preview[] = ['ic_number' => $ic, 'no_ahli' => $memberNo, 'name' => null, 'status' => 'not_found'];
            } elseif ($feeService->isLifeMember($userMatch) || $feeService->isExempted($userMatch)) {
                $preview[] = ['ic_number' => $ic, 'no_ahli' => $memberNo, 'name' => $userMatch->name, 'status' => 'exempted'];
            } elseif (MembershipFee::where('user_id', $userMatch->id)->where('year', $year)->where('status', 'paid')->exists()) {
                $preview[] = ['ic_number' => $ic, 'no_ahli' => $memberNo, 'name' => $userMatch->name, 'status' => 'already_paid'];
            } else {
                $preview[] = ['ic_number' => $ic, 'no_ahli' => $memberNo, 'name' => $userMatch->name, 'status' => 'ready'];
            }
        }

        return response()->json([
            'preview' => $preview,
            'total' => count($preview),
            'ready' => count(array_filter($preview, fn ($p) => $p['status'] === 'ready')),
            'skipped' => count(array_filter($preview, fn ($p) => $p['status'] !== 'ready')),
        ]);
    }

    public function processImport(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole(['Superadmin', 'Admin'])) abort(403);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx'],
            'year' => ['required', 'integer', 'min:2000', 'max:' . (now()->year + 1)],
            'proof' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:10240'],
        ]);

        $year = (int) $request->year;
        $csvPath = $request->file('file')->store('fee-imports');
        $proofPath = $request->hasFile('proof') ? $request->file('proof')->store('fee-imports/proofs') : '';

        $importBatch = FeeImportModel::create([
            'user_id' => $user->id,
            'year' => $year,
            'csv_file' => $csvPath,
            'proof_file' => $proofPath ?: null,
        ]);

        $feeService = app(FeeService::class);
        $orgId = $user->hasRole('Superadmin') ? null : $user->current_organization_id;
        $import = new FeeImportCsv($year, $feeService, $proofPath, $importBatch->id, $orgId);

        try {
            Excel::import($import, $request->file('file'));

            $importBatch->update([
                'total_rows' => $import->successCount + $import->skipCount,
                'success_count' => $import->successCount,
                'skip_count' => $import->skipCount,
                'errors' => $import->errors,
            ]);
        } catch (\Exception $e) {
            $importBatch->update(['errors' => ['error' => $e->getMessage()]]);

            return response()->json(['success' => false, 'message' => 'Import gagal: ' . $e->getMessage()], 500);
        }

        ActivityLog::create([
            'user_id' => $user->id,
            'organization_id' => $user->current_organization_id,
            'target_type' => FeeImportModel::class,
            'target_id' => $importBatch->id,
            'action' => 'csv_import',
            'description' => "Import CSV yuran {$year}: {$import->successCount} berjaya, {$import->skipCount} skip.",
        ]);

        return response()->json([
            'success' => true,
            'total_rows' => $import->successCount + $import->skipCount,
            'success_count' => $import->successCount,
            'skip_count' => $import->skipCount,
            'errors' => $import->errors,
            'import_batch_id' => $importBatch->id,
        ]);
    }

    // ─── EXPORT ─────────────────────────────────────────────────────────────────

    public function exportCsv(Request $request)
    {
        $user = $request->user();
        if (! $user->hasRole(['Superadmin', 'Admin'])) abort(403);

        $year = (int) $request->input('year', now()->year);
        $members = $this->exportQuery($request, $year)->get();

        $callback = function () use ($members) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Nama', 'No IC', 'No Ahli', 'Organisasi', 'Status', 'Jumlah Dibayar', 'Tarikh Bayar', 'Rujukan']);

            foreach ($members as $m) {
                $fee = $m->membershipFees->first();
                fputcsv($handle, [
                    $m->name, $m->ic_number, $m->member_no, $m->organization?->name ?? '',
                    $fee?->status ?? 'unpaid',
                    $fee?->amount ? number_format((float) $fee->amount, 2) : '0.00',
                    $fee?->paid_at?->toDateString() ?? '',
                    $fee?->payment?->reference ?? '',
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"yuran-{$year}.csv\"",
        ]);
    }

    public function exportExcel(Request $request)
    {
        $user = $request->user();
        if (! $user->hasRole(['Superadmin', 'Admin'])) abort(403);

        $year = (int) $request->input('year', now()->year);
        $members = $this->exportQuery($request, $year)->get();

        return Excel::download(new class($members, $year) implements \Maatwebsite\Excel\Concerns\FromCollection, \Maatwebsite\Excel\Concerns\WithHeadings {
            public function __construct(protected $members, protected int $year) {}
            public function collection() { return $this->members; }
            public function headings(): array { return ['Nama', 'No IC', 'No Ahli', 'Organisasi', 'Status', 'Jumlah', 'Tarikh Bayar', 'Rujukan']; }
        }, "yuran-{$year}.xlsx");
    }

    public function exportPdf(Request $request)
    {
        $user = $request->user();
        if (! $user->hasRole(['Superadmin', 'Admin'])) abort(403);

        $year = (int) $request->input('year', now()->year);
        $members = $this->exportQuery($request, $year)->get();

        $pdf = Pdf::loadView('exports.fees', [
            'members' => $members,
            'year' => $year,
            'org' => $user->organization,
        ]);

        return $pdf->download("yuran-{$year}.pdf");
    }

    private function exportQuery(Request $request, int $year)
    {
        $user = $request->user();
        $isSuperadmin = $user->hasRole('Superadmin');

        return User::withoutGlobalScopes()
            ->with(['membershipFees' => fn ($q) => $q->where('year', $year), 'organization'])
            ->when(! $isSuperadmin, fn ($q) => $q->where('current_organization_id', $user->current_organization_id))
            ->orderBy('name');
    }

    // ─── ORIGINAL METHODS (with Activity Log) ───────────────────────────────────

    public function toggleLifeMember(Request $request, FeeService $feeService, User $targetUser): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasRole('Superadmin')) abort(403);
        $this->authorizeOrg($user, $targetUser);

        if ($feeService->isLifeMember($targetUser)) {
            $feeService->unmarkLifeMember($targetUser);

            ActivityLog::create([
                'user_id' => $user->id,
                'organization_id' => $user->current_organization_id,
                'target_type' => User::class,
                'target_id' => $targetUser->id,
                'action' => 'unmark_life_member',
                'description' => "{$targetUser->name} dibuang status ahli seumur hidup.",
            ]);

            return back()->with('success', "{$targetUser->name} bukan lagi ahli seumur hidup.");
        }

        $org = $targetUser->organization;
        if (! $org) return back()->with('error', 'Ahli tiada organisasi.');

        $feeService->markAsLifeMember($targetUser, $org);

        ActivityLog::create([
            'user_id' => $user->id,
            'organization_id' => $user->current_organization_id,
            'target_type' => User::class,
            'target_id' => $targetUser->id,
            'action' => 'mark_life_member',
            'description' => "{$targetUser->name} dilantik sebagai ahli seumur hidup.",
        ]);

        return back()->with('success', "{$targetUser->name} kini ahli seumur hidup.");
    }

    public function markExempted(Request $request, FeeService $feeService, User $targetUser): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasRole('Superadmin')) abort(403);
        $this->authorizeOrg($user, $targetUser);

        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $feeService->markAsExempted($targetUser, $data['reason']);

        ActivityLog::create([
            'user_id' => $user->id,
            'organization_id' => $user->current_organization_id,
            'target_type' => User::class,
            'target_id' => $targetUser->id,
            'action' => 'exempted',
            'description' => "{$targetUser->name} dikecualikan yuran. Sebab: {$data['reason']}",
        ]);

        return back()->with('success', "Yuran {$targetUser->name} dikecualikan secara tetap.");
    }

    public function generateFees(Request $request, FeeService $feeService): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole(['Superadmin', 'Admin'])) abort(403);

        $year = (int) $request->input('year', now()->year);
        $isSuperadmin = $user->hasRole('Superadmin');

        $orgs = $isSuperadmin
            ? Organization::all()
            : Organization::where('id', $user->current_organization_id)->get();

        $total = 0;
        foreach ($orgs as $org) {
            $total += $feeService->generateAnnualFees($org, $year);
        }

        return response()->json([
            'success' => true,
            'message' => "{$total} rekod yuran bagi tahun {$year} berjaya dijana untuk " . ($isSuperadmin ? 'semua organisasi.' : $orgs->first()?->name . '.'),
            'count' => $total,
        ]);
    }

    private function authorizeOrg(User $admin, User $target): void
    {
        if (! $admin->hasRole('Superadmin') && $target->current_organization_id !== $admin->current_organization_id) {
            abort(403);
        }
    }
}

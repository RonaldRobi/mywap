<?php

namespace App\Http\Controllers;

use App\Models\InfaqDonation;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class AdminFinanceController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        if (! $user->hasRole(['Superadmin', 'Admin'])) abort(403);

        $isSuperadmin = $user->hasRole('Superadmin');
        $orgId = $user->current_organization_id;
        $year = (int) $request->input('year', now()->year);
        $filterOrgId = $this->resolveOrgId($isSuperadmin, $orgId, $request);

        $paymentQuery = $this->paymentQuery($year, $filterOrgId);
        $infaqQuery = $this->infaqQuery($year, $filterOrgId);

        // By Source
        $feeTotal = (float) (clone $paymentQuery)->sum('amount');
        $infaqTotal = (float) (clone $infaqQuery)->sum('amount');

        $bySource = collect();
        if ($feeTotal > 0) $bySource->push(['type' => 'Yuran Keahlian', 'total' => $feeTotal]);
        if ($infaqTotal > 0) $bySource->push(['type' => 'Infaq', 'total' => $infaqTotal]);

        // Monthly chart - combine both
        $monthly = collect(range(1, 12))->mapWithKeys(fn ($m) => [
            $m => (float) (clone $paymentQuery)->whereMonth('created_at', $m)->sum('amount')
                + (float) (clone $infaqQuery)->whereMonth('created_at', $m)->sum('amount'),
        ]);

        $chart = $monthly->map(fn ($total, $m) => [
            'month' => date('F', mktime(0, 0, 0, (int) $m, 1)),
            'total' => $total,
        ])->values();

        // Merged transactions
        $feeRows = (clone $paymentQuery)->with('user:id,name,member_no')->latest('created_at')->get()
            ->map(fn (Payment $p) => [
                'id' => 'p-' . $p->id,
                'user_id' => $p->user_id,
                'created_at' => $p->created_at?->toISOString(),
                'member_name' => $p->user?->name ?? '—',
                'member_no' => $p->user?->member_no ?? '—',
                'type' => 'Yuran Keahlian',
                'amount' => (float) $p->amount,
                'reference' => $p->reference,
            ]);

        $infaqRows = (clone $infaqQuery)->with('infaq:id,title')->latest('created_at')->get()
            ->map(fn (InfaqDonation $d) => [
                'id' => 'i-' . $d->id,
                'user_id' => $d->user_id,
                'created_at' => $d->created_at?->toISOString(),
                'member_name' => $d->user?->name ?? ($d->donor_name ?? 'Tanpa Nama'),
                'member_no' => $d->user?->member_no ?? '—',
                'type' => 'Infaq',
                'amount' => (float) $d->amount,
                'reference' => $d->reference,
            ]);

        $merged = $feeRows->concat($infaqRows)->sortByDesc('created_at')->values();
        $page = (int) $request->input('page', 1);
        $perPage = 25;
        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $merged->forPage($page, $perPage),
            $merged->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $totalRevenue = $feeTotal + $infaqTotal;

        $organizations = $isSuperadmin ? Organization::select('id', 'name', 'slug')->get() : [];
        $years = range(now()->year - 5, now()->year + 1);

        return Inertia::render('Admin/Finance/Index', [
            'stats' => [
                'total' => $totalRevenue,
                'by_source' => $bySource,
            ],
            'chart' => $chart,
            'transactions' => $paginated,
            'year' => $year,
            'years' => $years,
            'organizations' => $organizations,
            'filters' => $request->only(['organization_id']),
        ]);
    }

    public function exportPdf(Request $request)
    {
        $user = $request->user();
        if (! $user->hasRole(['Superadmin', 'Admin'])) abort(403);

        $isSuperadmin = $user->hasRole('Superadmin');
        $orgId = $user->current_organization_id;
        $year = (int) $request->input('year', now()->year);
        $filterOrgId = $this->resolveOrgId($isSuperadmin, $orgId, $request);

        $merged = $this->mergedTransactions($year, $filterOrgId);
        $totalRevenue = $merged->sum('amount');
        $bySource = $merged->groupBy('type')->map->sum('amount')->map(fn ($t, $type) => ['type' => $type, 'total' => $t])->values();

        $org = $filterOrgId ? Organization::find($filterOrgId) : null;

        $pdf = Pdf::loadView('exports.finance-report', [
            'transactions' => $merged,
            'totalRevenue' => $totalRevenue,
            'bySource' => $bySource,
            'year' => $year,
            'org' => $org,
            'generatedBy' => $user->name,
            'generatedAt' => now(),
        ]);

        $filename = 'laporan-kewangan-' . $year . ($org ? '-' . $org->slug : '') . '.pdf';
        return $pdf->download($filename);
    }

    public function exportExcel(Request $request)
    {
        $user = $request->user();
        if (! $user->hasRole(['Superadmin', 'Admin'])) abort(403);

        $isSuperadmin = $user->hasRole('Superadmin');
        $orgId = $user->current_organization_id;
        $year = (int) $request->input('year', now()->year);
        $filterOrgId = $this->resolveOrgId($isSuperadmin, $orgId, $request);

        $merged = $this->mergedTransactions($year, $filterOrgId);

        return Excel::download(new class($merged, $year) implements \Maatwebsite\Excel\Concerns\FromCollection, \Maatwebsite\Excel\Concerns\WithHeadings {
            public function __construct(protected Collection $merged, protected int $year) {}
            public function collection()
            {
                return $this->merged->map(fn ($r, $i) => [
                    $i + 1,
                    \Carbon\Carbon::parse($r['created_at'])->format('d/m/Y'),
                    $r['member_name'],
                    $r['member_no'],
                    $r['type'],
                    number_format((float) $r['amount'], 2),
                    $r['reference'] ?? '—',
                ]);
            }
            public function headings(): array { return ['#', 'Tarikh', 'Ahli', 'No Ahli', 'Jenis', 'Jumlah (RM)', 'Rujukan']; }
        }, "transaksi-kewangan-{$year}.xlsx");
    }

    public function exportCsv(Request $request)
    {
        $user = $request->user();
        if (! $user->hasRole(['Superadmin', 'Admin'])) abort(403);

        $isSuperadmin = $user->hasRole('Superadmin');
        $orgId = $user->current_organization_id;
        $year = (int) $request->input('year', now()->year);
        $filterOrgId = $this->resolveOrgId($isSuperadmin, $orgId, $request);

        $merged = $this->mergedTransactions($year, $filterOrgId);

        $callback = function () use ($merged) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['#', 'Tarikh', 'Ahli', 'No Ahli', 'Jenis', 'Jumlah (RM)', 'Rujukan']);
            foreach ($merged as $i => $r) {
                fputcsv($handle, [
                    $i + 1,
                    \Carbon\Carbon::parse($r['created_at'])->format('d/m/Y'),
                    $r['member_name'],
                    $r['member_no'],
                    $r['type'],
                    number_format((float) $r['amount'], 2),
                    $r['reference'] ?? '—',
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"transaksi-kewangan-{$year}.csv\"",
        ]);
    }

    public function memberTransactions(Request $request, User $targetUser): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole(['Superadmin', 'Admin'])) abort(403);

        $isSuperadmin = $user->hasRole('Superadmin');
        if (! $isSuperadmin && $targetUser->current_organization_id !== $user->current_organization_id) abort(403);

        $feeRows = Payment::where('user_id', $targetUser->id)
            ->where('status', 'successful')
            ->latest('created_at')
            ->get()
            ->map(fn (Payment $p) => [
                'created_at' => $p->created_at?->toISOString(),
                'type' => 'Yuran Keahlian',
                'amount' => (float) $p->amount,
                'reference' => $p->reference ?? '—',
                'description' => $p->description,
            ]);

        $infaqRows = InfaqDonation::where('user_id', $targetUser->id)
            ->where('status', 'confirmed')
            ->with('infaq:id,title')
            ->latest('created_at')
            ->get()
            ->map(fn (InfaqDonation $d) => [
                'created_at' => $d->created_at?->toISOString(),
                'type' => 'Infaq',
                'amount' => (float) $d->amount,
                'reference' => $d->reference ?? '—',
                'description' => $d->infaq?->title ?? 'Sumbangan',
            ]);

        $history = $feeRows->concat($infaqRows)->sortByDesc('created_at')->values();

        return response()->json([
            'data' => [
                'name' => $targetUser->name,
                'member_no' => $targetUser->member_no,
                'organization' => $targetUser->organization?->name,
                'history' => $history,
            ],
        ]);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    private function resolveOrgId(bool $isSuperadmin, int $userOrgId, Request $request): ?int
    {
        return $isSuperadmin && $request->filled('organization_id')
            ? (int) $request->organization_id
            : (! $isSuperadmin ? $userOrgId : null);
    }

    private function paymentQuery(int $year, ?int $orgId)
    {
        $query = Payment::query()->where('status', 'successful')->whereYear('created_at', $year);
        if ($orgId) {
            $query->whereHas('user', fn ($q) => $q->withoutGlobalScopes()->where('current_organization_id', $orgId));
        }
        return $query;
    }

    private function infaqQuery(int $year, ?int $orgId)
    {
        $query = InfaqDonation::query()->where('status', 'confirmed')->whereYear('created_at', $year);
        if ($orgId) {
            $query->whereHas('infaq', fn ($q) => $q->where('organization_id', $orgId));
        }
        return $query;
    }

    private function mergedTransactions(int $year, ?int $orgId): Collection
    {
        $feeRows = $this->paymentQuery($year, $orgId)->with('user:id,name,member_no')->latest('created_at')->get()
            ->map(fn (Payment $p) => [
                'created_at' => $p->created_at?->toISOString() ?? '',
                'member_name' => $p->user?->name ?? '—',
                'member_no' => $p->user?->member_no ?? '—',
                'type' => 'Yuran Keahlian',
                'amount' => (float) $p->amount,
                'reference' => $p->reference ?? '—',
            ]);

        $infaqRows = $this->infaqQuery($year, $orgId)->with('user:id,name,member_no')->latest('created_at')->get()
            ->map(fn (InfaqDonation $d) => [
                'created_at' => $d->created_at?->toISOString() ?? '',
                'member_name' => $d->user?->name ?? ($d->donor_name ?? 'Tanpa Nama'),
                'member_no' => $d->user?->member_no ?? '—',
                'type' => 'Infaq',
                'amount' => (float) $d->amount,
                'reference' => $d->reference ?? '—',
            ]);

        return $feeRows->concat($infaqRows)->sortByDesc('created_at')->values();
    }
}

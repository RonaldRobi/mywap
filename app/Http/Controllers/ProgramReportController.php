<?php

namespace App\Http\Controllers;

use App\Enums\EventCategory;
use App\Models\Branch;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Registration;
use App\Services\RegistrationDedupeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan program/acara yang telah selesai untuk keperluan pengurusan.
 *
 * Skop mengikut peranan:
 *  - Superadmin      : semua organisasi, boleh tapis organisasi/cawangan.
 *  - Admin organisasi: program milik organisasi sendiri atau yang melibatkan
 *                      organisasi tersebut melalui pivot `event_organization`.
 *  - Admin cawangan  : program bagi cawangan yang diuruskan sahaja.
 */
class ProgramReportController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const MONTHS = [
        1 => 'Jan',
        2 => 'Feb',
        3 => 'Mac',
        4 => 'Apr',
        5 => 'Mei',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Ogos',
        9 => 'Sep',
        10 => 'Okt',
        11 => 'Nov',
        12 => 'Dis',
    ];

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/ProgramReport', $this->reportData($request));
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $data = $this->reportData($request, withAllEvents: true);
        $filename = 'laporan-program-'.$data['period_slug'].'.csv';

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Laporan Program']);
            fputcsv($handle, ['Tempoh', $data['period_label']]);
            fputcsv($handle, ['Skop', $data['scope_label']]);
            fputcsv($handle, []);
            fputcsv($handle, ['Ringkasan', 'Nilai']);
            foreach ($data['summary'] as $row) {
                fputcsv($handle, [$row['label'], $row['value']]);
            }
            fputcsv($handle, ['Kadar kehadiran (%)', $data['attendance_rate']]);
            fputcsv($handle, []);
            fputcsv($handle, [
                'Program', 'Kategori', 'Organisasi', 'Cawangan', 'Tarikh', 'Lokasi',
                'Peserta unik', 'Hadir', 'Kadar (%)', 'Status',
            ]);

            foreach ($data['all_events'] as $event) {
                fputcsv($handle, [
                    $event['title'],
                    $event['category_label'],
                    $event['organization_name'],
                    $event['branch_name'] ?? 'Semua cawangan',
                    $event['date_label'],
                    $event['location_or_link'] ?? '',
                    $event['unique_participants_count'],
                    $event['attended_count'],
                    $event['attendance_rate'],
                    $event['status_label'],
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function exportPdf(Request $request)
    {
        @ini_set('memory_limit', '512M');

        $data = $this->reportData($request, withAllEvents: true);
        $maxRows = 600;
        $rows = collect($data['all_events']);

        $pdf = Pdf::loadView('exports.program-report', [
            'summary' => $data['summary'],
            'attendanceRate' => $data['attendance_rate'],
            'events' => $rows->take($maxRows)->values(),
            'shown' => min($rows->count(), $maxRows),
            'total' => $rows->count(),
            'periodLabel' => $data['period_label'],
            'scopeLabel' => $data['scope_label'],
            'categories' => $data['categories'],
            'generatedBy' => $request->user()->name,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('laporan-program-'.$data['period_slug'].'.pdf');
    }

    /**
     * @return array<string, mixed>
     */
    private function reportData(Request $request, bool $withAllEvents = false): array
    {
        $user = $request->user();
        $isSuperadmin = $user->hasRole('Superadmin');
        $isBranchAdmin = $user->isBranchAdmin();

        abort_if($isBranchAdmin && ! $user->managedBranchId(), 403, 'Akaun admin cawangan belum ditugaskan.');

        $years = $this->availableYears($user);
        $year = $this->resolveYear($request);
        $month = $this->resolveMonth($request);

        $branches = $this->availableBranches($user);
        $organizations = $isSuperadmin
            ? Organization::orderBy('min_age')->get(['id', 'name'])
            : collect();

        $scoped = $this->scopeQuery($user);

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $scoped->where(function (Builder $query) use ($search) {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('location_or_link', 'like', "%{$search}%");
            });
        }

        $organizationId = null;
        if ($isSuperadmin && $request->filled('organization_id')) {
            $organizationId = (int) $request->input('organization_id');
            $scoped->where(function (Builder $query) use ($organizationId) {
                $query->where('organization_id', $organizationId)
                    ->orWhereHas('organizations', fn (Builder $q) => $q->where('organizations.id', $organizationId));
            });
        }

        $branchId = null;
        if ($isBranchAdmin) {
            $branchId = $user->managedBranchId();
        } elseif ($request->filled('branch_id')) {
            $candidate = (int) $request->input('branch_id');

            if ($branches->contains('id', $candidate)) {
                $branchId = $candidate;
                $scoped->where('branch_id', $candidate);
            }
        }

        $filtered = clone $scoped;

        if ($year !== null) {
            $filtered->whereYear('start_time', $year);
        }

        if ($month !== null) {
            $filtered->whereMonth('start_time', $month);
        }

        $eventIds = (clone $filtered)->pluck('id');

        $registrations = Registration::query()
            ->whereIn('event_id', $eventIds)
            ->get(['id', 'event_id', 'name', 'email', 'phone', 'ic_number']);

        $uniqueCounts = app(RegistrationDedupeService::class)->uniqueCountsByEvent($registrations);
        $registrationCounts = $registrations->countBy('event_id');

        $attendedCounts = Registration::query()
            ->whereIn('event_id', $eventIds)
            ->whereHas('attendance')
            ->selectRaw('event_id, COUNT(*) as total')
            ->groupBy('event_id')
            ->pluck('total', 'event_id')
            ->map(fn ($value) => (int) $value);

        $totalPrograms = $eventIds->count();
        $totalRegistrations = $registrations->count();
        $totalAttended = (int) $attendedCounts->sum();
        $totalUnique = (int) array_sum($uniqueCounts);
        $attendanceRate = $totalRegistrations > 0
            ? round(($totalAttended / $totalRegistrations) * 100, 1)
            : 0.0;

        $categoryCounts = (clone $filtered)
            ->selectRaw('category, COUNT(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->map(fn ($value) => (int) $value);

        $categories = collect(EventCategory::cases())
            ->map(fn (EventCategory $category) => [
                'value' => $category->value,
                'label' => $category->label(),
                'total' => (int) ($categoryCounts[$category->value] ?? 0),
            ])
            ->filter(fn (array $row) => $row['total'] > 0)
            ->sortByDesc('total')
            ->values();

        $mapEvent = function (Event $event) use ($uniqueCounts, $registrationCounts, $attendedCounts): array {
            $registrationCount = (int) ($registrationCounts[$event->id] ?? 0);
            $attendedCount = (int) ($attendedCounts[$event->id] ?? 0);

            return [
                'id' => $event->id,
                'title' => $event->title,
                'category_label' => $event->category?->label() ?? 'Lain-lain',
                'status' => $event->status?->value ?? 'published',
                'status_label' => $event->status?->label() ?? '',
                'organization_name' => $event->organization?->name ?? 'Semua Organisasi',
                'branch_name' => $event->branch?->name,
                'type_label' => $event->type === 'online' ? 'Online' : 'Fizikal',
                'location_or_link' => $event->location_or_link,
                'date_label' => $event->start_time?->locale('ms')->isoFormat('D MMM YYYY'),
                'date_iso' => $event->start_time?->toDateString(),
                'registrations_count' => $registrationCount,
                'unique_participants_count' => (int) ($uniqueCounts[$event->id] ?? 0),
                'attended_count' => $attendedCount,
                'attendance_rate' => $registrationCount > 0
                    ? round(($attendedCount / $registrationCount) * 100, 1)
                    : 0,
                'url' => route('admin.events.show', $event->id),
                'registrations_url' => route('admin.events.registrations', $event->id),
            ];
        };

        $eventsQuery = fn () => (clone $filtered)
            ->with(['organization:id,name', 'branch:id,name,state'])
            ->orderByDesc('start_time');

        $events = $eventsQuery()->paginate(20)->withQueryString()->through($mapEvent);

        $allEvents = $withAllEvents
            ? $eventsQuery()->get()->map($mapEvent)->values()
            : collect();

        return [
            'events' => $events,
            'all_events' => $allEvents,
            'summary' => [
                ['key' => 'total_programs', 'label' => 'Jumlah program', 'value' => $totalPrograms],
                ['key' => 'total_registrations', 'label' => 'Jumlah pendaftaran', 'value' => $totalRegistrations],
                ['key' => 'total_unique_participants', 'label' => 'Peserta unik', 'value' => $totalUnique],
                ['key' => 'total_attended', 'label' => 'Jumlah kehadiran', 'value' => $totalAttended],
            ],
            'attendance_rate' => $attendanceRate,
            'breakdown' => $this->breakdown($scoped, $year, $years),
            'breakdown_mode' => $year !== null ? 'month' : 'year',
            'categories' => $categories,
            'filters' => [
                'year' => $year === null ? 'all' : (string) $year,
                'month' => $month === null ? 'all' : (string) $month,
                'organization_id' => $organizationId === null ? '' : (string) $organizationId,
                'branch_id' => $branchId === null ? '' : (string) $branchId,
                'search' => (string) $request->input('search', ''),
            ],
            'years' => $years,
            'months' => collect(self::MONTHS)->map(fn (string $label, int $value) => [
                'value' => (string) $value,
                'label' => $label,
            ])->values(),
            'organizations' => $organizations,
            'branches' => $branches,
            'is_superadmin' => $isSuperadmin,
            'is_branch_admin' => $isBranchAdmin,
            'scope_label' => $this->scopeLabel($user, $branches, $organizations, $organizationId, $branchId),
            'period_label' => $this->periodLabel($year, $month),
            'period_slug' => $this->periodSlug($year, $month),
        ];
    }

    private function scopeQuery($user): Builder
    {
        $query = Event::query()
            ->whereNotNull('end_time')
            ->where('end_time', '<', now());

        if ($user->isBranchAdmin()) {
            $query->where('branch_id', $user->managedBranchId());
        } elseif (! $user->hasRole('Superadmin')) {
            $query->where(function (Builder $query) use ($user) {
                $query->where('organization_id', $user->current_organization_id)
                    ->orWhereHas('organizations', fn (Builder $q) => $q->where('organizations.id', $user->current_organization_id));
            });
        }

        return $query;
    }

    /**
     * @return Collection<int, int>
     */
    private function availableYears($user): Collection
    {
        $expression = $this->yearExpression();

        return $this->scopeQuery($user)
            ->selectRaw("{$expression} as report_year")
            ->distinct()
            ->pluck('report_year')
            ->map(fn ($year) => (int) $year)
            ->push((int) now()->year)
            ->unique()
            ->sortDesc()
            ->values();
    }

    /**
     * @return Collection<int, Branch>
     */
    private function availableBranches($user): Collection
    {
        $query = Branch::query()->where('is_active', true);

        if ($user->isBranchAdmin()) {
            $query->whereKey($user->managedBranchId());
        } elseif (! $user->hasRole('Superadmin')) {
            $query->where('organization_id', $user->current_organization_id);
        }

        return $query
            ->orderBy('state')
            ->orderBy('name')
            ->get(['id', 'organization_id', 'name', 'state']);
    }

    /**
     * @param  Collection<int, int>  $years
     * @return Collection<int, array{label: string, total: int}>
     */
    private function breakdown(Builder $scoped, ?int $year, Collection $years): Collection
    {
        if ($year !== null) {
            $expression = $this->monthExpression();

            $counts = (clone $scoped)
                ->whereYear('start_time', $year)
                ->selectRaw("{$expression} as report_month, COUNT(*) as total")
                ->groupBy('report_month')
                ->pluck('total', 'report_month')
                ->mapWithKeys(fn ($total, $month) => [(int) $month => (int) $total]);

            return collect(self::MONTHS)
                ->map(fn (string $label, int $month) => [
                    'label' => $label,
                    'total' => (int) ($counts[$month] ?? 0),
                ])
                ->values();
        }

        $expression = $this->yearExpression();

        $counts = (clone $scoped)
            ->selectRaw("{$expression} as report_year, COUNT(*) as total")
            ->groupBy('report_year')
            ->pluck('total', 'report_year')
            ->mapWithKeys(fn ($total, $year) => [(int) $year => (int) $total]);

        return $years
            ->sort()
            ->values()
            ->map(fn (int $year) => [
                'label' => (string) $year,
                'total' => (int) ($counts[$year] ?? 0),
            ]);
    }

    private function resolveYear(Request $request): ?int
    {
        $input = $request->input('year', (string) now()->year);

        if ((string) $input === 'all') {
            return null;
        }

        $year = (int) $input;

        return $year >= 2000 && $year <= now()->year + 1 ? $year : (int) now()->year;
    }

    private function resolveMonth(Request $request): ?int
    {
        $input = $request->input('month', 'all');

        if ((string) $input === 'all') {
            return null;
        }

        $month = (int) $input;

        return $month >= 1 && $month <= 12 ? $month : null;
    }

    private function scopeLabel(
        $user,
        Collection $branches,
        Collection $organizations,
        ?int $organizationId,
        ?int $branchId,
    ): string {
        if ($user->isBranchAdmin()) {
            return $branches->firstWhere('id', $branchId)?->name ?? 'Cawangan';
        }

        $parts = [];

        if ($organizationId !== null) {
            $parts[] = $organizations->firstWhere('id', $organizationId)?->name ?? 'Organisasi';
        } elseif ($user->hasRole('Superadmin')) {
            $parts[] = 'Semua organisasi';
        } else {
            $parts[] = $user->organization?->name ?? 'Organisasi';
        }

        if ($branchId !== null) {
            $branch = $branches->firstWhere('id', $branchId);

            if ($branch) {
                $parts[] = $branch->name;
            }
        }

        return implode(' · ', $parts);
    }

    private function periodLabel(?int $year, ?int $month): string
    {
        if ($year === null) {
            return 'Semua tahun';
        }

        if ($month === null) {
            return 'Tahun '.$year;
        }

        return Carbon::create($year, $month, 1)->locale('ms')->isoFormat('MMMM YYYY');
    }

    private function periodSlug(?int $year, ?int $month): string
    {
        if ($year === null) {
            return 'semua-tahun';
        }

        return $month === null
            ? (string) $year
            : $year.'-'.str_pad((string) $month, 2, '0', STR_PAD_LEFT);
    }

    private function monthExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => 'EXTRACT(MONTH FROM start_time)',
            'sqlite' => "CAST(strftime('%m', start_time) AS INTEGER)",
            default => 'MONTH(start_time)',
        };
    }

    private function yearExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => 'EXTRACT(YEAR FROM start_time)',
            'sqlite' => "CAST(strftime('%Y', start_time) AS INTEGER)",
            default => 'YEAR(start_time)',
        };
    }
}

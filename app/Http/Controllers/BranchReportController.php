<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Event;
use App\Models\MembershipFee;
use App\Models\Registration;
use App\Models\Scopes\OrganizationScope;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BranchReportController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/BranchReport', $this->reportData($request));
    }

    public function export(Request $request): StreamedResponse
    {
        $data = $this->reportData($request);
        $filename = 'laporan-cawangan-'.str($data['branch']['name'] ?? 'cawangan')->slug().'-'.$data['year'].'.csv';

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Laporan Cawangan', $data['branch']['name'] ?? '-', 'Tahun', $data['year']]);
            fputcsv($handle, []);
            fputcsv($handle, ['Ringkasan', 'Jumlah']);
            foreach ($data['summary'] as $row) {
                fputcsv($handle, [$row['label'], $row['value']]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Program', 'Tarikh', 'Peserta', 'Hadir']);

            foreach ($data['events'] as $event) {
                fputcsv($handle, [
                    $event['title'],
                    $event['start_formatted'],
                    $event['registrations_count'],
                    $event['attended_count'],
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array<string, mixed>
     */
    private function reportData(Request $request): array
    {
        $user = $request->user();
        $isSuperadmin = $user->hasRole('Superadmin');
        $isBranchAdmin = $user->isBranchAdmin();
        $year = (int) $request->input('year', now()->year);
        $year = $year >= 2000 && $year <= now()->year + 1 ? $year : now()->year;

        $branches = Branch::query()
            ->when(! $isSuperadmin && $isBranchAdmin, fn ($q) => $q->whereKey($user->managedBranchIds()))
            ->when(! $isSuperadmin && ! $isBranchAdmin, fn ($q) => $q->where('organization_id', $user->current_organization_id))
            ->where('is_active', true)
            ->orderBy('state')
            ->orderBy('name')
            ->get(['id', 'organization_id', 'name', 'state']);

        abort_if($branches->isEmpty(), 403, 'Tiada cawangan untuk laporan.');

        $branchId = $isBranchAdmin
            ? $user->resolveManagedBranchId($request->integer('branch_id') ?: null)
            : (int) $request->input('branch_id', $branches->first()->id);

        $branch = $branches->firstWhere('id', $branchId);

        if (! $branch) {
            $branch = $branches->first();
            $branchId = $branch->id;
        }

        $users = User::withoutGlobalScope(OrganizationScope::class)
            ->where('current_organization_id', $branch->organization_id)
            ->where('branch_id', $branch->id);

        $totalMembers = (clone $users)->count();
        $activeMembers = (clone $users)->where('is_active', true)->count();
        $profileCompleted = (clone $users)->whereNotNull('profile_completed_at')->count();
        $newMembers = (clone $users)->whereYear('created_at', $year)->count();

        $paidMembers = (clone $users)->whereHas('membershipFees', fn ($q) => $q->where('year', $year)->where('status', 'paid'))->count();
        $lifeMembers = (clone $users)->whereHas('membershipFees', fn ($q) => $q->where('status', 'life_member'))->count();
        $exemptedMembers = (clone $users)->whereHas('membershipFees', fn ($q) => $q->where('status', 'exempted'))->count();
        $dueMembers = max(0, $totalMembers - $paidMembers - $lifeMembers - $exemptedMembers);

        $collected = (float) MembershipFee::query()
            ->where('year', $year)
            ->where('status', 'paid')
            ->whereHas('user', fn ($q) => $q->withoutGlobalScope(OrganizationScope::class)
                ->where('branch_id', $branch->id))
            ->sum('amount');

        $eventsQuery = Event::query()
            ->where('branch_id', $branch->id)
            ->whereYear('start_time', $year);

        $totalEvents = (clone $eventsQuery)->count();
        $pastEvents = (clone $eventsQuery)->where('end_time', '<', now())->count();
        $upcomingEvents = (clone $eventsQuery)->where('end_time', '>=', now())->count();

        $eventIds = (clone $eventsQuery)->pluck('id');

        $registeredUserIds = Registration::query()
            ->whereIn('event_id', $eventIds)
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');

        $attendedUserIds = Registration::query()
            ->whereIn('event_id', $eventIds)
            ->whereHas('attendance')
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');

        $membersRegisteredProgram = (clone $users)->whereIn('id', $registeredUserIds)->count();
        $membersAttendedProgram = (clone $users)->whereIn('id', $attendedUserIds)->count();
        $membersWithoutProgram = max(0, $totalMembers - $membersRegisteredProgram);

        $totalRegistrations = Registration::query()->whereIn('event_id', $eventIds)->count();
        $totalAttended = Registration::query()->whereIn('event_id', $eventIds)->whereHas('attendance')->count();

        $monthExpr = match (DB::connection()->getDriverName()) {
            'pgsql' => 'EXTRACT(MONTH FROM start_time)',
            'sqlite' => "CAST(strftime('%m', start_time) AS INTEGER)",
            default => 'MONTH(start_time)',
        };

        $monthlyEvents = (clone $eventsQuery)
            ->selectRaw("{$monthExpr} as month, COUNT(*) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        $events = (clone $eventsQuery)
            ->withCount([
                'registrations',
                'registrations as attended_count' => fn ($q) => $q->whereHas('attendance'),
            ])
            ->orderByDesc('start_time')
            ->limit(20)
            ->get()
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'start_formatted' => $event->start_time?->locale('ms')->isoFormat('D MMM YYYY'),
                'status' => $event->status->value,
                'status_label' => $event->status->label(),
                'registrations_count' => $event->registrations_count,
                'attended_count' => $event->attended_count,
                'attendance_rate' => $event->registrations_count > 0
                    ? round(($event->attended_count / $event->registrations_count) * 100, 1)
                    : 0,
            ])
            ->values();

        $monthly = collect(range(1, 12))->map(fn ($m) => [
            'month' => date('M', mktime(0, 0, 0, (int) $m, 1)),
            'events' => (int) ($monthlyEvents[$m] ?? 0),
        ])->values();

        return [
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'state' => $branch->state,
            ],
            'branches' => $branches,
            'year' => $year,
            'years' => range(now()->year - 5, now()->year),
            'summary' => [
                ['key' => 'total_members', 'label' => 'Jumlah ahli', 'value' => $totalMembers],
                ['key' => 'active_members', 'label' => 'Ahli aktif', 'value' => $activeMembers],
                ['key' => 'profile_completed', 'label' => 'Profil lengkap', 'value' => $profileCompleted],
                ['key' => 'new_members', 'label' => 'Ahli baharu tahun ini', 'value' => $newMembers],
                ['key' => 'paid_members', 'label' => 'Yuran telah dibayar', 'value' => $paidMembers],
                ['key' => 'due_members', 'label' => 'Yuran tertunggak', 'value' => $dueMembers],
                ['key' => 'life_members', 'label' => 'Ahli seumur hidup', 'value' => $lifeMembers],
                ['key' => 'exempted_members', 'label' => 'Dikecualikan yuran', 'value' => $exemptedMembers],
                ['key' => 'total_events', 'label' => 'Jumlah program', 'value' => $totalEvents],
                ['key' => 'past_events', 'label' => 'Program selesai', 'value' => $pastEvents],
                ['key' => 'upcoming_events', 'label' => 'Program akan datang', 'value' => $upcomingEvents],
                ['key' => 'total_registrations', 'label' => 'Jumlah penyertaan', 'value' => $totalRegistrations],
                ['key' => 'total_attended', 'label' => 'Jumlah kehadiran', 'value' => $totalAttended],
            ],
            'collected_amount' => round($collected, 2),
            'activity' => [
                ['key' => 'registered_program', 'label' => 'Ahli mendaftar program', 'value' => $membersRegisteredProgram],
                ['key' => 'attended_program', 'label' => 'Ahli hadir program', 'value' => $membersAttendedProgram],
                ['key' => 'no_program', 'label' => 'Ahli tanpa penyertaan', 'value' => $membersWithoutProgram],
            ],
            'monthly' => $monthly,
            'events' => $events,
            'attendance_rate' => $totalRegistrations > 0
                ? round(($totalAttended / $totalRegistrations) * 100, 1)
                : 0,
        ];
    }
}

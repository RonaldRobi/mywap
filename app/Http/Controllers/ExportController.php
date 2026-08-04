<?php

namespace App\Http\Controllers;

use App\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /**
     * Report of total membership overall and broken down by state (CSV).
     */
    public function exportMembersByState(): StreamedResponse
    {
        $admin = request()->user();

        abort_unless($admin->hasRole(['Admin', 'Superadmin']), 403);

        $query = User::withoutGlobalScopes();

        if ($admin->hasRole('Admin')) {
            $query->where('current_organization_id', $admin->current_organization_id);
        }

        $total = (clone $query)->count();

        $states = (clone $query)
            ->selectRaw('COALESCE(NULLIF(state, ""), "Tidak Dinyatakan") as state, COUNT(*) as total')
            ->groupBy('state')
            ->orderByDesc('total')
            ->orderBy('state')
            ->get();

        $fileName = 'laporan-keahlian-'.now()->format('Ymd-His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ];

        return response()->stream(function () use ($total, $states): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Laporan Jumlah Keahlian']);
            fputcsv($handle, ['Jumlah Keseluruhan Ahli', $total]);
            fputcsv($handle, []);
            fputcsv($handle, ['Negeri', 'Jumlah Ahli', 'Peratus']);
            fputcsv($handle, ['Semua Negeri', $total, '100%']);

            foreach ($states as $state) {
                $percent = $total > 0 ? round(($state->total / $total) * 100, 1) : 0;
                fputcsv($handle, [$state->state, $state->total, $percent.'%']);
            }

            fclose($handle);
        }, 200, $headers);
    }

    public function exportMembers(): StreamedResponse
    {
        $admin = request()->user();

        abort_unless($admin->hasRole(['Admin', 'Superadmin']), 403);

        $query = User::query()
            ->with(['organization', 'branch', 'membershipFees' => fn ($q) => $q->where('year', now()->year)]);

        if ($admin->hasRole('Admin')) {
            $query->where('current_organization_id', $admin->current_organization_id);
        }

        $members = $query
            ->orderBy('name')
            ->get();

        $fileName = 'members-export-'.now()->format('Ymd-His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ];

        return response()->stream(function () use ($members): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['No Ahli', 'Nama', 'Email', 'Phone', 'IC', 'DOB', 'Organisasi', 'Cawangan', 'Status Yuran']);

            foreach ($members as $member) {
                $fee = $member->membershipFees->first();
                fputcsv($handle, [
                    $member->member_no,
                    $member->name,
                    $member->email,
                    $member->phone,
                    $member->ic_number,
                    optional($member->dob)->format('Y-m-d'),
                    $member->organization?->name,
                    $member->branch?->name,
                    $fee?->status?->value ?? ($fee?->status ?? 'unpaid'),
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }
}

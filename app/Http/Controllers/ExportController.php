<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Response;

class ExportController extends Controller
{
    public function exportMembers(): Response
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

        $fileName = 'members-export-' . now()->format('Ymd-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
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

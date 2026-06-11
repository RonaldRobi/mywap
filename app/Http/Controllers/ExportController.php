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

        $query = User::query()->with('organization');

        if ($admin->hasRole('Admin')) {
            $query->where('current_organization_id', $admin->current_organization_id);
        }

        $members = $query
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'dob', 'current_organization_id']);

        $fileName = 'members-export-' . now()->format('Ymd-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        return response()->stream(function () use ($members): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Name', 'Email', 'Phone', 'DOB', 'Organization']);

            foreach ($members as $member) {
                fputcsv($handle, [
                    $member->name,
                    $member->email,
                    $member->phone,
                    optional($member->dob)->format('Y-m-d'),
                    $member->organization?->name,
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }
}

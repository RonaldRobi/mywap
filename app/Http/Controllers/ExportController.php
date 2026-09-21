<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /**
     * Report of total membership overall and broken down by state (CSV).
     */
    public function exportMembersByState(Request $request): StreamedResponse
    {
        $admin = request()->user();

        abort_unless($admin->hasRole(['Admin', 'Superadmin']), 403);

        $query = User::withoutGlobalScope(\App\Models\Scopes\OrganizationScope::class);

        if ($admin->hasRole('Admin')) {
            $query->where('current_organization_id', $admin->current_organization_id);
        } elseif ($organizationId = $request->input('organization_id')) {
            $query->where('current_organization_id', $organizationId);
        }

        $total = (clone $query)->count();

        $states = (clone $query)
            ->selectRaw("COALESCE(NULLIF(state, ''), 'Tidak Dinyatakan') as state, COUNT(*) as total")
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

    /**
     * Export senarai ahli (CSV).
     *
     * - `type`            : `simple` (ringkas) atau `full` (penuh — termasuk alamat & maklumat lain).
     * - `organization_id` : hanya untuk Superadmin. Kosong = semua organisasi.
     *
     * Admin organisasi sentiasa dihadkan kepada ahli organisasi sendiri.
     */
    public function exportMembers(Request $request): StreamedResponse
    {
        $admin = $request->user();

        abort_unless($admin->hasRole(['Admin', 'Superadmin']), 403);

        $isFull = $request->input('type') === 'full';

        $query = User::query()
            ->with(['organization', 'branch', 'membershipFees' => fn ($q) => $q->where('year', now()->year)]);

        if ($admin->hasRole('Admin')) {
            $query->where('current_organization_id', $admin->current_organization_id);
        } elseif ($organizationId = $request->input('organization_id')) {
            $query->where('current_organization_id', $organizationId);
        }

        $query->orderBy('name');

        $label = $isFull ? 'members-full' : 'members';
        $fileName = $label.'-export-'.now()->format('Ymd-His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ];

        return response()->stream(function () use ($query, $isFull): void {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM supaya Excel membuka aksara dengan betul.
            fwrite($handle, "\xEF\xBB\xBF");

            $columns = $isFull
                ? $this->fullColumns()
                : $this->simpleColumns();

            fputcsv($handle, $columns);

            $query->lazy(500)->each(function ($member) use ($handle, $isFull): void {
                $fee = $member->membershipFees->first();

                if ($isFull) {
                    fputcsv($handle, $this->fullRow($member, $fee));
                } else {
                    fputcsv($handle, $this->simpleRow($member, $fee));
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    private function simpleColumns(): array
    {
        return ['No Ahli', 'Nama', 'Email', 'Phone', 'IC', 'DOB', 'Organisasi', 'Cawangan', 'Status Yuran'];
    }

    private function fullColumns(): array
    {
        return [
            'No Ahli', 'Nama', 'Email', 'Phone', 'IC', 'DOB', 'Jantina', 'Status Perkahwinan',
            'Organisasi', 'Cawangan', 'Alamat 1', 'Alamat 2', 'Poskod', 'Bandar', 'Negeri',
            'Telefon Rumah', 'Telefon Pejabat', 'No Faks',
            'Tahap Pendidikan', 'Profesion', 'Industri', 'Kepakaran', 'Jawatan',
            'Nama Kontak Kecemasan', 'Telefon Kontak Kecemasan',
            'Lokaliti', 'LinkedIn', 'Status Yuran', 'Status Aktif', 'Tarikh Daftar',
        ];
    }

    private function simpleRow(User $member, $fee): array
    {
        return [
            $member->member_no,
            $member->name,
            $member->email,
            $member->phone,
            $member->ic_number,
            optional($member->dob)->format('Y-m-d'),
            $member->organization?->name,
            $member->branch?->name,
            $this->feeStatusLabel($fee),
        ];
    }

    private function fullRow(User $member, $fee): array
    {
        return [
            $member->member_no,
            $member->name,
            $member->email,
            $member->phone,
            $member->ic_number,
            optional($member->dob)->format('Y-m-d'),
            $member->gender,
            $member->marital_status,
            $member->organization?->name,
            $member->branch?->name,
            $member->address_1,
            $member->address_2,
            $member->postcode,
            $member->city,
            $member->state,
            $member->home_phone,
            $member->office_phone,
            $member->fax_number,
            $member->education_level,
            $member->current_profession,
            $member->industry,
            $member->expertise,
            $member->position,
            $member->emergency_contact_name,
            $member->emergency_contact_phone,
            $member->locality,
            $member->linkedin_url,
            $this->feeStatusLabel($fee),
            $member->is_active ? 'Aktif' : 'Tidak Aktif',
            $member->created_at?->format('Y-m-d'),
        ];
    }

    private function feeStatusLabel($fee): string
    {
        $status = $fee?->status?->value ?? $fee?->status ?? 'unpaid';

        return match ($status) {
            'paid' => 'Sudah Bayar',
            'exempted' => 'Dikecualikan',
            'life_member' => 'Seumur Hidup',
            default => 'Belum Bayar',
        };
    }
}

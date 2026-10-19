<?php

namespace App\Http\Controllers;

use App\Models\Scopes\OrganizationScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /**
     * Report of total membership overall and broken down by state (CSV).
     */
    public function exportMembersByState(Request $request): StreamedResponse
    {
        $admin = $request->user();

        abort_unless($admin->hasRole(['Admin', 'Superadmin', 'org-admin', 'Admin Cawangan']), 403);

        $query = $this->scopedMembers($request);

        $total = (clone $query)->count();

        $states = (clone $query)
            ->selectRaw("COALESCE(NULLIF(state, ''), 'Tidak Dinyatakan') as state, COUNT(*) as total")
            ->groupBy('state')
            ->orderByDesc('total')
            ->orderBy('state')
            ->get();

        $fileName = 'laporan-keahlian-'.$this->scopeSuffix($request).now()->format('Ymd-His').'.csv';

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
     * - `branch_id`       : Admin organisasi boleh tapis cawangan/negeri sendiri.
     * - `state`           : Admin organisasi boleh tapis mengikut negeri.
     *
     * Admin Cawangan sentiasa dihadkan kepada ahli cawangan sendiri.
     * Admin organisasi dihadkan kepada organisasi sendiri.
     */
    public function exportMembers(Request $request): StreamedResponse
    {
        $admin = $request->user();

        abort_unless($admin->hasRole(['Admin', 'Superadmin', 'org-admin', 'Admin Cawangan']), 403);

        $isFull = $request->input('type') === 'full';

        $query = $this->scopedMembers($request)
            ->with(['organization', 'branch', 'membershipFees' => fn ($q) => $q->where('year', now()->year)])
            ->orderBy('name');

        $label = $isFull ? 'members-full' : 'members';
        $fileName = $label.'-export-'.$this->scopeSuffix($request).now()->format('Ymd-His').'.csv';

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

    /**
     * Had skop eksport mengikut peranan pengguna.
     *
     * - Superadmin : semua ahli; boleh tapis `organization_id` / `branch_id` / `state`.
     * - Admin Cawangan : hanya ahli cawangan sendiri (tidak boleh ditapis ke cawangan lain).
     * - Admin / org-admin : hanya organisasi sendiri; boleh tapis cawangan/negeri.
     */
    private function scopedMembers(Request $request): Builder
    {
        $admin = $request->user();

        $query = User::withoutGlobalScope(OrganizationScope::class);

        $organizationId = (int) $request->input('organization_id');
        $branchId = (int) $request->input('branch_id');
        $state = trim((string) $request->input('state'));

        if ($admin->hasRole('Superadmin')) {
            if ($organizationId) {
                $query->where('current_organization_id', $organizationId);
            }

            if ($branchId) {
                $query->where('branch_id', $branchId);
            }

            if ($state !== '') {
                $query->whereHas('branch', fn (Builder $q) => $q->where('state', $state));
            }

            return $query;
        }

        abort_if(! $admin->current_organization_id, 403, 'Akses ditolak.');

        $query->where('current_organization_id', $admin->current_organization_id);

        if ($admin->hasRole('Admin Cawangan')) {
            abort_if(! $admin->branch_id, 403, 'Akaun admin cawangan ini tiada cawangan.');

            return $query->where('branch_id', $admin->branch_id);
        }

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($state !== '') {
            $query->whereHas('branch', fn (Builder $q) => $q->where('state', $state));
        }

        return $query;
    }

    /**
     * Akhiran nama fail supaya jelas skop eksport (cawangan/negeri).
     */
    private function scopeSuffix(Request $request): string
    {
        $admin = $request->user();

        if ($admin->hasRole('Admin Cawangan') && $admin->branch_id) {
            return 'cawangan-'.$admin->branch_id.'-';
        }

        $branchId = (int) $request->input('branch_id');
        if ($branchId) {
            return 'cawangan-'.$branchId.'-';
        }

        $state = trim((string) $request->input('state'));
        if ($state !== '') {
            return preg_replace('/[^A-Za-z0-9]+/', '-', $state).'-';
        }

        return '';
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

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MemberSearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        // Skop organisasi ditetapkan secara eksplisit di sini (bukan bergantung
        // pada global-scope sesi) supaya konsisten di seluruh penggunaan:
        //  - org-admin hanya melihat ahli organisasinya sendiri,
        //  - superadmin & tetamu (pendaftaran awam) tanpa sekatan kecuali
        //    organization_id diberikan secara jelas.
        $actingUser = Auth::user();
        $isSuperadmin = $actingUser ? $actingUser->hasRole('Superadmin') : false;
        $orgId = $request->query('organization_id');

        // Tetamu awam (halaman pendaftaran) tidak boleh mengecam maklumat
        // sensitif (IC/emel/telefon) orang lain — padanan nama & no ahli sahaja.
        $isGuest = ! $actingUser;

        $members = User::query()
            ->withoutGlobalScopes()
            ->when($orgId, fn ($query) => $query->where('current_organization_id', (int) $orgId))
            ->when(! $orgId && $actingUser && ! $isSuperadmin, fn ($query) => $query->where('current_organization_id', $actingUser->current_organization_id))
            ->when($isGuest, function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', '%'.$q.'%')
                        ->orWhere('member_no', 'like', '%'.$q.'%');
                });
            }, function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', '%'.$q.'%')
                        ->orWhere('member_no', 'like', '%'.$q.'%')
                        ->orWhere('ic_number', 'like', '%'.$q.'%')
                        ->orWhere('phone', 'like', '%'.$q.'%')
                        ->orWhere('email', 'like', '%'.$q.'%');
                });
            })
            ->orderByRaw('CASE WHEN member_no LIKE ? THEN 0 ELSE 1 END', [$q.'%'])
            ->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', [$q.'%'])
            ->orderBy('name')
            ->limit(25)
            ->get(['id', 'name', 'member_no', 'current_organization_id']);

        return response()->json($members);
    }
}

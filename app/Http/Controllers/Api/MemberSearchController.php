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

        // Pecahkan pertanyaan kepada token pada SEMUA jenis ruang putih (termasuk
        // non-breaking space \x{00A0} dari import Excel/legacy Windows-1252 dan
        // ruang ideografik \x{3000}) supaya carian nama seperti "Ahmad Firdaus"
        // masih padan dengan nama yang disimpan sebagai "Ahmad\u{00A0}Firdaus"
        // atau dengan berbilang ruang antara perkataan. Ini selari dengan carian
        // ahli di InformationHubAdminController.
        $tokens = preg_split('/[\s\x{00A0}\x{3000}]+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = array_map(fn ($token) => addcslashes($token, '\\%_'), $tokens);

        // Prefix (tanpa escape) untuk susunan keutamaan — sekadar isihan, bukan padanan.
        $prefix = $tokens[0] ?? $q;

        $members = User::query()
            ->withoutGlobalScopes()
            ->when($orgId, fn ($query) => $query->where('current_organization_id', (int) $orgId))
            ->when(! $orgId && $actingUser && ! $isSuperadmin, fn ($query) => $query->where('current_organization_id', $actingUser->current_organization_id))
            ->when($isGuest, function ($query) use ($tokens) {
                $query->where(function ($outer) use ($tokens) {
                    foreach ($tokens as $token) {
                        $outer->where(function ($inner) use ($token) {
                            $inner->where('name', 'like', '%'.$token.'%')
                                ->orWhere('member_no', 'like', '%'.$token.'%');
                        });
                    }
                });
            }, function ($query) use ($tokens) {
                $query->where(function ($outer) use ($tokens) {
                    foreach ($tokens as $token) {
                        $outer->where(function ($inner) use ($token) {
                            $inner->where('name', 'like', '%'.$token.'%')
                                ->orWhere('member_no', 'like', '%'.$token.'%')
                                ->orWhere('ic_number', 'like', '%'.$token.'%')
                                ->orWhere('phone', 'like', '%'.$token.'%')
                                ->orWhere('email', 'like', '%'.$token.'%');
                        });
                    }
                });
            })
            ->orderByRaw('CASE WHEN member_no LIKE ? THEN 0 ELSE 1 END', [$prefix.'%'])
            ->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', [$prefix.'%'])
            ->orderBy('name')
            ->limit(25)
            ->get(['id', 'name', 'member_no', 'current_organization_id']);

        return response()->json($members);
    }
}

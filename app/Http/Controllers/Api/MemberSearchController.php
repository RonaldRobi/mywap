<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberSearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $q = $request->query('q');

        if (!$q || mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $members = User::query()
            ->when($request->query('branch_id'), fn ($q, $branchId) => $q->where('branch_id', $branchId))
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', '%' . $q . '%')
                    ->orWhere('member_no', 'like', '%' . $q . '%');
            })
            ->orderByRaw("CASE WHEN member_no LIKE ? THEN 0 ELSE 1 END", [$q . '%'])
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'member_no']);

        return response()->json($members);
    }
}

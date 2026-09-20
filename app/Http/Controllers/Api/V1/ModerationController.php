<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ArticleComment;
use App\Models\ContentReport;
use App\Models\NewsPostComment;
use App\Models\User;
use App\Models\UserBlock;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ModerationController
 *
 * Lapor & sekat kandungan buatan pengguna (UGC) — keperluan App Review
 * Guideline 1.2 (report/block). Komen adalah ciri ahli, jadi semua endpoint
 * di sini memerlukan log masuk.
 */
class ModerationController extends Controller
{
    /**
     * Peta jenis kandungan yang boleh dilaporkan → model.
     */
    private const REPORTABLE = [
        'article_comment' => ArticleComment::class,
        'news_comment' => NewsPostComment::class,
    ];

    public function report(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reportable_type' => ['required', 'string', 'in:'.implode(',', array_keys(self::REPORTABLE))],
            'reportable_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'in:spam,harassment,hate,sexual,violence,misinformation,other'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $model = self::REPORTABLE[$validated['reportable_type']];
        $comment = $model::findOrFail($validated['reportable_id']);

        ContentReport::create([
            'reporter_id' => $request->user()->id,
            'reportable_type' => $model,
            'reportable_id' => $comment->id,
            'reason' => $validated['reason'],
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending',
        ]);

        return ApiResponse::success(null, ['message' => 'Laporan anda telah dihantar. Terima kasih.'], 201);
    }

    public function blocks(Request $request): JsonResponse
    {
        $ids = UserBlock::query()
            ->where('blocker_id', $request->user()->id)
            ->pluck('blocked_id')
            ->values();

        return ApiResponse::success(['blocked_user_ids' => $ids]);
    }

    public function block(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'blocked_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $blockedId = (int) $validated['blocked_user_id'];

        if ($blockedId === (int) $request->user()->id) {
            return ApiResponse::error('Anda tidak boleh menyekat diri sendiri.', status: 422);
        }

        UserBlock::firstOrCreate([
            'blocker_id' => $request->user()->id,
            'blocked_id' => $blockedId,
        ]);

        return ApiResponse::success(
            ['blocked_user_id' => $blockedId],
            ['message' => 'Pengguna telah disekat. Komen mereka tidak akan dipaparkan.']
        );
    }

    public function unblock(Request $request, User $user): JsonResponse
    {
        UserBlock::query()
            ->where('blocker_id', $request->user()->id)
            ->where('blocked_id', $user->id)
            ->delete();

        return ApiResponse::success(
            ['blocked_user_id' => $user->id],
            ['message' => 'Sekatan telah dibuka.']
        );
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminService;
use App\Support\ApiResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function __construct(private readonly AdminService $admin) {}

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorizeAdmin($user);

        $data = $this->admin->dashboard($user);

        return ApiResponse::success([
            'stats' => $data['stats'],
            'recent_activities' => $data['recent_activities'],
            'revenue_by_month' => $data['revenue_by_month'],
        ]);
    }

    public function members(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorizeAdmin($user);

        return ApiResponse::paginated($this->admin->members($request, $user));
    }

    public function fees(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorizeAdmin($user);

        $data = $this->admin->fees($request, $user);

        $fees = collect($data['members']->items())->map(function (array $member) {
            return [
                'id' => $member['fee']['id'] ?? $member['id'],
                'user_id' => $member['id'],
                'name' => $member['name'],
                'member_no' => $member['member_no'],
                'year' => $member['fee']['year'] ?? now()->year,
                'amount' => (float) ($member['fee']['amount'] ?? 0),
                'status' => $member['fee']['status'] ?? 'unpaid',
                'paid_at' => $member['fee']['paid_at'] ?? null,
            ];
        })->values();

        return ApiResponse::success([
            'summary' => [
                'total_members' => $data['stats']['total'],
                'paid_count' => $data['stats']['paid'] + $data['stats']['life_member'] + $data['stats']['exempted'],
                'pending_count' => $data['stats']['due'],
                'revenue' => (float) $data['stats']['collected_amount'],
            ],
            'fees' => $fees,
        ]);
    }

    public function attendance(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorizeAdmin($user);

        $validated = $request->validate([
            'event_id' => ['required', 'integer', 'exists:events,id'],
        ]);

        $data = $this->admin->attendanceRegistrations((int) $validated['event_id'], $user);

        return ApiResponse::success($data);
    }

    public function scan(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorizeAdmin($user);

        $validated = $request->validate([
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'identifier' => ['required', 'string', 'max:100'],
        ]);

        $result = $this->admin->scanAttendance($user, (int) $validated['event_id'], trim($validated['identifier']));

        return ApiResponse::success($result, [], $result['status'] === 'ok' ? 200 : 422);
    }

    public function broadcast(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorizeAdmin($user);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'audience' => ['required', 'in:all,members,usrah,org'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
        ]);

        [$targetCriteria, $targetOrgId, $recipientIds] = $this->admin->resolveAudience($user, $validated['audience'], $validated['organization_id'] ?? null);

        $this->admin->broadcast([
            'organization_id' => $user->current_organization_id,
            'target_organization_id' => $targetOrgId,
            'title' => $validated['title'],
            'content' => $validated['message'],
            'target_criteria' => $targetCriteria,
            'recipient_ids' => $recipientIds,
            'notification_channels' => ['in_app'],
            'email_use_template' => false,
        ]);

        return ApiResponse::success([
            'success' => true,
            'message' => 'Siaran sedang diproses dan akan dihantar berperingkat.',
        ]);
    }

    private function authorizeAdmin(User $user): void
    {
        if (! $user->hasRole(['Admin', 'org-admin', 'Superadmin'])) {
            throw new HttpResponseException(ApiResponse::error('Tiada kebenaran.', [], 403));
        }
    }
}

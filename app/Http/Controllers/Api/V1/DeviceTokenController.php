<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PushNotificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeviceTokenController extends Controller
{
    public function __construct(private readonly PushNotificationService $push) {}

    /**
     * Daftar token peranti FCM untuk user semasa (upsert).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['required', 'in:android,ios'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $this->push->register(
            $request->user(),
            $validated['token'],
            $validated['platform'],
            $validated['device_name'] ?? null,
        );

        Log::info('DeviceToken registered.', [
            'user_id' => $request->user()->id,
            'platform' => $validated['platform'],
            'token' => substr($validated['token'], 0, 12).'...',
        ]);

        return ApiResponse::success(['registered' => true]);
    }

    /**
     * Buang token peranti FCM untuk user semasa.
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255'],
        ]);

        $this->push->unregister($request->user(), $validated['token']);

        return ApiResponse::success(['registered' => false]);
    }
}

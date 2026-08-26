<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Services\FacilityService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FacilityController extends Controller
{
    public function __construct(private readonly FacilityService $facilities) {}

    public function index(Request $request): JsonResponse
    {
        $data = $this->facilities->indexData(
            $request->user(),
            trim((string) $request->query('history_status', ''))
        );

        return ApiResponse::success($data);
    }

    public function show(Request $request, Facility $facility): JsonResponse
    {
        $data = $this->facilities->showFacility($facility, $request->user());

        return ApiResponse::success($data);
    }

    public function book(Request $request, Facility $facility): JsonResponse
    {
        $user = $request->user();
        $isMember = $user->hasRole('Member');

        $validated = $request->validate([
            'start_datetime' => ['required', 'date'],
            'end_datetime' => ['required', 'date', 'after:start_datetime'],
            'contact_name' => [$isMember ? 'nullable' : 'required', 'string', 'max:255'],
            'contact_phone' => [$isMember ? 'nullable' : 'required', 'string', 'max:30'],
        ]);

        $booking = $this->facilities->createBooking($user, $facility, $validated);

        return ApiResponse::success([
            'booking' => $this->facilities->serializeMyBooking($booking),
            'total_price' => (float) $booking->total_price,
            'booking_status' => $booking->booking_status,
        ], status: 201);
    }
}

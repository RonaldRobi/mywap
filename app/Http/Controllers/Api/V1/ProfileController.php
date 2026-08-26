<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileUpdateRequest;
use App\Services\ProfileService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(private readonly ProfileService $profile) {}

    /**
     * Profil penuh (perjalanan ahli) — sama bentuk dengan prop Inertia
     * ProfileController::show.
     */
    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success($this->profile->showPayload($request->user()));
    }

    /**
     * Meta skrin "lengkapkan profil" — DOB & jantina diekstrak dari IC.
     */
    public function complete(Request $request): JsonResponse
    {
        return ApiResponse::success($this->profile->completeMeta($request->user()));
    }

    /**
     * Lengkapkan profil (skrin wajib isi) dan pulangkan profil terkemas kini.
     */
    public function storeComplete(Request $request): JsonResponse
    {
        $data = $request->validate(ProfileService::completeRules());

        $user = $this->profile->completeProfile($request->user(), $data);

        return ApiResponse::success($this->profile->serializeProfile($user));
    }

    /**
     * Kemas kini profil — gunakan ProfileUpdateRequest (sama dengan web).
     */
    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        $user = $request->user();

        $this->profile->updateProfile($user, $request);

        return ApiResponse::success($this->profile->serializeProfile($user->refresh()));
    }

    /**
     * Meta borang edit profil — cawangan, jawatan, kebenaran edit IC.
     */
    public function editMeta(Request $request): JsonResponse
    {
        return ApiResponse::success($this->profile->editMeta($request->user()));
    }
}

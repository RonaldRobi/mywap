<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileUpdateRequest;
use App\Services\ProfileService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

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

    /**
     * Tukar kata laluan — sahkan kata laluan semasa secara eksplisit supaya
     * berfungsi dalam konteks token Sanctum (tanpa sesi).
     */
    public function password(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Kata laluan semasa tidak tepat.',
            ]);
        }

        $user->update(['password' => Hash::make($validated['password'])]);

        $user->tokens()
            ->where('id', '!=', $user->currentAccessToken()?->id)
            ->delete();

        return ApiResponse::success(null, ['message' => 'Kata laluan berjaya dikemas kini.']);
    }

    /**
     * Padam akaun sendiri (Play Store compliance) — sama dengan web
     * ProfileController::destroy, tetapi untuk token Sanctum.
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Kata laluan tidak tepat.',
            ]);
        }

        $user->currentAccessToken()?->delete();
        $user->delete();

        return ApiResponse::success(null, ['message' => 'Akaun anda telah dipadam.']);
    }

    /**
     * Muat naik foto profil — simpan ke 'profiles', buang foto lama, pulang
     * URL baharu (relative '/storage/...' seperti serialize profil lain).
     */
    public function photo(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $user = $request->user();

        $oldPath = ltrim(str_replace('/storage/', '', parse_url((string) $user->profile_photo_path, PHP_URL_PATH) ?? ''), '/');
        if ($oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $newPath = $request->file('photo')->store('profiles', 'public');
        $path = '/storage/'.ltrim($newPath, '/');

        $user->update(['profile_photo_path' => $path]);

        return ApiResponse::success(['photo_url' => $path]);
    }
}

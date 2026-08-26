<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->resource->loadMissing(['organization', 'branch']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'ic_number' => $user->ic_number,
            'member_no' => $user->member_no,
            'dob' => $user->dob?->toISOString(),
            'gender' => $user->gender,
            'marital_status' => $user->marital_status,
            'branch_id' => $user->branch_id,
            'branch_name' => $user->branch_name,
            'locality' => $user->locality,
            'address_1' => $user->address_1,
            'address_2' => $user->address_2,
            'postcode' => $user->postcode,
            'city' => $user->city,
            'state' => $user->state,
            'current_profession' => $user->current_profession,
            'education_level' => $user->education_level,
            'profile_photo_path' => $user->profile_photo_path,
            'is_public_in_directory' => $user->is_public_in_directory,
            'is_first_login' => is_null($user->first_login_at),
            'profile_completed_at' => $user->profile_completed_at?->toISOString(),
            'member_since' => optional($user->created_at)->format('M Y'),
            'roles' => $user->getRoleNames()->values(),
            'organization' => $user->organization ? [
                'id' => $user->organization->id,
                'name' => $user->organization->name,
                'slug' => $user->organization->slug,
                'color_theme' => $user->organization->color_theme,
                'logo_path' => $user->organization->logo_path,
            ] : null,
            'branch' => $user->branch ? [
                'id' => $user->branch->id,
                'name' => $user->branch->name,
            ] : null,
        ];
    }
}

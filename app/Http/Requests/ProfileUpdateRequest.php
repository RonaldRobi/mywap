<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('ic_number')) {
            $this->merge(['ic_number' => User::normalizeIc($this->input('ic_number'))]);
        }
    }

    public function rules(): array
    {
        $icRule = ['nullable', 'string', 'max:32'];
        $normalizedIc = User::normalizeIc($this->input('ic_number'));
        if ($normalizedIc !== null && $normalizedIc !== User::normalizeIc($this->user()?->ic_number)) {
            $icRule[] = 'regex:'.User::IC_VALIDATION_REGEX;
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'ic_number' => $icRule,
            'phone' => ['nullable', 'string', 'max:30'],
            'dob' => ['nullable', 'date'],
            'education_level' => ['nullable', 'string', 'max:120'],
            'current_profession' => ['nullable', 'string', 'max:120'],
            'industry' => ['nullable', 'string', 'max:120'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'locality' => ['nullable', 'string', 'max:120'],
            'expertise' => ['nullable', 'string', 'max:255'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'profile_photo' => ['nullable', 'image', 'max:2048'],
            'is_public_in_directory' => ['nullable', 'boolean'],
            'gender' => ['nullable', 'in:lelaki,perempuan'],
            'marital_status' => ['nullable', 'in:bujang,berkahwin,bercerai,duda/janda'],
            'address_1' => ['nullable', 'string', 'max:255'],
            'address_2' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:5'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'topics' => ['nullable', 'string'],
        ];
    }
}

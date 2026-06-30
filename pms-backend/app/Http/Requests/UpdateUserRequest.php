<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')->id ?? $this->user;

        return [
            // Identity
            'username'          => ['sometimes', 'required', 'string', 'max:100', Rule::unique('users')->ignore($userId)],
            'email'             => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users')->ignore($userId)],
            'password'          => ['nullable', 'confirmed', Password::min(8)->mixedCase()->numbers()],

            // Name
            'first_name'        => 'sometimes|required|string|max:100',
            'middle_name'       => 'sometimes|nullable|string|max:100',
            'last_name'         => 'sometimes|required|string|max:100',
            'suffix'            => 'sometimes|nullable|string|max:20',

            // Contact & Address
            'phone_number'      => 'sometimes|nullable|string|max:20',
            'address'           => 'sometimes|nullable|string|max:500',
            'organization_name' => 'sometimes|nullable|string|max:255',
            'organization_type' => 'sometimes|nullable|string|max:80',
            'organization_registration_no' => 'sometimes|nullable|string|max:255',
            'proponent_profile' => 'sometimes|nullable|array',
            'proponent_profile.business_summary' => 'nullable|string|max:5000',
            'proponent_profile.project_experience' => 'nullable|string|max:5000',
            'proponent_profile.previous_projects' => 'nullable|string|max:12000',
            'proponent_profile.major_clients' => 'nullable|string|max:5000',
            'proponent_profile.certifications' => 'nullable|string|max:5000',

            // Profile
            'profile_photo_url' => 'sometimes|nullable|url|max:500',

            // Employment
            'employee_id'       => ['sometimes', 'nullable', 'string', 'max:100', Rule::unique('users')->ignore($userId)],
            'department'        => 'sometimes|nullable|string|max:100',
            'position'          => 'sometimes|nullable|string|max:100',
            'date_hired'        => 'sometimes|nullable|date',
            'birth_date'        => 'sometimes|nullable|date|before:today',

            // Access
            'default_role_id'   => 'sometimes|required|exists:roles,id',
            'is_active'         => 'sometimes|boolean',
        ];
    }
}

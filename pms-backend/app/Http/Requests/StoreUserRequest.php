<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Identity
            'username'          => 'required|string|max:100|unique:users,username',
            'email'             => 'required|email|max:255|unique:users,email',
            'password'          => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],

            // Name
            'first_name'        => 'required|string|max:100',
            'middle_name'       => 'nullable|string|max:100',
            'last_name'         => 'required|string|max:100',
            'suffix'            => 'nullable|string|max:20',

            // Contact & Address
            'phone_number'      => 'nullable|string|max:20',
            'address'           => 'nullable|string|max:500',
            'organization_name' => 'nullable|string|max:255',
            'organization_type' => 'nullable|string|max:80',
            'organization_registration_no' => 'nullable|string|max:255',
            'proponent_profile' => 'nullable|array',
            'proponent_profile.business_summary' => 'nullable|string|max:5000',
            'proponent_profile.project_experience' => 'nullable|string|max:5000',
            'proponent_profile.previous_projects' => 'nullable|string|max:12000',
            'proponent_profile.major_clients' => 'nullable|string|max:5000',
            'proponent_profile.certifications' => 'nullable|string|max:5000',

            // Profile
            'profile_photo_url' => 'nullable|url|max:500',

            // Employment
            'employee_id'       => 'nullable|string|max:100|unique:users,employee_id',
            'department'        => 'nullable|string|max:100',
            'position'          => 'nullable|string|max:100',
            'date_hired'        => 'nullable|date',
            'birth_date'        => 'nullable|date|before:today',

            // Access
            'default_role_id'   => 'required|exists:roles,id',
            'is_active'         => 'boolean',
        ];
    }
}

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
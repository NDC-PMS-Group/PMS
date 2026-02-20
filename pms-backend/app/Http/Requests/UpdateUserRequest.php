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
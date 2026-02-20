<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            // Identity — username and email must stay unique but ignore self
            'username'      => ['sometimes', 'required', 'string', 'max:100', Rule::unique('users')->ignore($userId)],
            'email'         => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users')->ignore($userId)],

            // Name
            'first_name'    => 'sometimes|required|string|max:100',
            'middle_name'   => 'sometimes|nullable|string|max:100',
            'last_name'     => 'sometimes|required|string|max:100',
            'suffix'        => 'sometimes|nullable|string|max:20',

            // Contact
            'phone_number'  => 'sometimes|nullable|string|max:20',
            'address'       => 'sometimes|nullable|string|max:500',

            // Personal
            'birth_date'    => 'sometimes|nullable|date|before:today',

            // NOTE: The following fields are intentionally excluded.
            // They are admin-only and must go through UserController:
            //   - default_role_id
            //   - employee_id
            //   - department
            //   - position
            //   - date_hired
            //   - is_active
            //   - profile_photo_url (handled by uploadAvatar endpoint)
        ];
    }

    public function messages(): array
    {
        return [
            'username.unique'   => 'This username is already taken.',
            'email.unique'      => 'This email address is already in use.',
            'birth_date.before' => 'Birth date must be in the past.',
        ];
    }
}
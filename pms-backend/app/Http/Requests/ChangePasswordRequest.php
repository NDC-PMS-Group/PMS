<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => 'required|string',

            'new_password' => [
                'required',
                'string',
                'confirmed',                          // requires new_password_confirmation field
                Password::min(8)->mixedCase()->numbers(),
                'different:current_password',         // must not be the same as old password
            ],

            'new_password_confirmation' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'new_password.confirmed'  => 'The new password confirmation does not match.',
            'new_password.different'  => 'New password must be different from your current password.',
            'new_password.min'        => 'New password must be at least 8 characters.',
        ];
    }
}
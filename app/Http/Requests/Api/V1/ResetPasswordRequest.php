<?php

namespace App\Http\Requests\Api\V1;

use App\Support\PasswordPolicy;

class ResetPasswordRequest extends StrictApiRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return ['token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => PasswordPolicy::rules(),
            'password_confirmation' => PasswordPolicy::confirmationRules()];
    }
}

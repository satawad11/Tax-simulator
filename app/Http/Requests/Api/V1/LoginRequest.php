<?php

namespace App\Http\Requests\Api\V1;

class LoginRequest extends StrictApiRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email', 'max:255'], 'password' => ['required', 'string', 'max:72'],
            'device_name' => ['required', 'string', 'max:255']];
    }
}

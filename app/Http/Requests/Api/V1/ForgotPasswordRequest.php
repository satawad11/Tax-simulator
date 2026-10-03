<?php

namespace App\Http\Requests\Api\V1;

class ForgotPasswordRequest extends StrictApiRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        // Deliberately no `exists` rule. Validating that the address is registered would answer,
        // in a 422, the one question this endpoint must never answer.
        return ['email' => ['required', 'string', 'email', 'max:255']];
    }
}

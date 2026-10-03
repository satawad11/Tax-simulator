<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;

class UpdateProfileRequest extends StrictApiRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return ['name' => ['sometimes', 'required', 'string', 'max:255'], 'email' => ['sometimes', 'required', 'string', 'email', 'max:255',
            Rule::unique('users', 'email')->ignore($this->user()->id)]];
    }
}

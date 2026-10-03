<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StrictApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (is_string($this->input('password')) && strlen($this->input('password')) > 72) {
                $validator->errors()->add('password', 'The password must not exceed 72 bytes.');
            }
            $allowed = array_map(fn (string $key): string => explode('.', $key)[0], array_keys($this->rules()));
            foreach (array_diff(array_keys($this->all()), $allowed) as $key) {
                $validator->errors()->add($key, 'Unknown or server-controlled fields are not accepted.');
            }
        });
    }
}

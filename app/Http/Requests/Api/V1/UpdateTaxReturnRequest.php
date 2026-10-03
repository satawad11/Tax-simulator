<?php

namespace App\Http\Requests\Api\V1;

class UpdateTaxReturnRequest extends StrictApiRequest
{
    public function rules(): array
    {
        return ['name' => ['sometimes', 'required', 'string', 'max:255'], 'current_step' => ['sometimes', 'required', 'integer', 'min:1', 'max:65535']];
    }
}

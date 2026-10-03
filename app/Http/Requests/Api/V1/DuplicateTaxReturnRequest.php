<?php

namespace App\Http\Requests\Api\V1;

class DuplicateTaxReturnRequest extends StrictApiRequest
{
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255']];
    }
}

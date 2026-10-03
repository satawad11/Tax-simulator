<?php

namespace App\Http\Requests\Api\V1;

class ListTaxCalculationsRequest extends StrictApiRequest
{
    public function rules(): array
    {
        return ['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']];
    }
}

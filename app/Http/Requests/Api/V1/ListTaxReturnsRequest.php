<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;

class ListTaxReturnsRequest extends StrictApiRequest
{
    public function rules(): array
    {
        return ['status' => ['sometimes', Rule::in(['draft', 'completed', 'archived'])],
            'tax_year' => ['sometimes', 'integer', 'min:1', 'max:65535'], 'form' => ['sometimes', Rule::in(['PND90', 'PND91'])],
            'q' => ['sometimes', 'string', 'max:255'], 'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']];
    }
}

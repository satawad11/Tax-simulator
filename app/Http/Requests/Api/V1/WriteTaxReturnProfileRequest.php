<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;

class WriteTaxReturnProfileRequest extends StrictApiRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('PATCH') ? 'sometimes' : 'required';

        return ['birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'], 'marital_status' => ['nullable', Rule::in(['single', 'married', 'divorced', 'widowed'])], 'filing_status' => ['nullable', 'string', 'max:50']];
    }
}

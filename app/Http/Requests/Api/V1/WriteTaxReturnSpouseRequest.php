<?php

namespace App\Http\Requests\Api\V1;

class WriteTaxReturnSpouseRequest extends StrictApiRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('PATCH') ? 'sometimes' : 'required';

        return ['birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'], 'has_income' => ['required', 'boolean'], 'filing_status' => ['nullable', 'string', 'max:50']];
    }
}

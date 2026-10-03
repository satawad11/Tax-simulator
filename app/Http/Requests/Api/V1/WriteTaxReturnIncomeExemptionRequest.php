<?php

namespace App\Http\Requests\Api\V1;

use App\Rules\MoneyInput;

/**
 * The code is checked against the return's own rule version rather than a table-wide list, so a
 * member cannot save a line their saved version has no rule for. That check needs the return, so
 * it lives in TaxReturnInputService where the return is in hand.
 */
class WriteTaxReturnIncomeExemptionRequest extends StrictApiRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('PATCH') ? 'sometimes' : 'required';

        return [
            'code' => [$required, 'required', 'string', 'max:100'],
            'input_amount' => [$required, 'required', new MoneyInput],
            'declarations_confirmed' => ['sometimes', 'boolean'],
        ];
    }
}

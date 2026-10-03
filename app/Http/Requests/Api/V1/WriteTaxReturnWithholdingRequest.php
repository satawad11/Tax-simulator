<?php

namespace App\Http\Requests\Api\V1;

use App\Rules\MoneyInput;
use App\Services\Tax\TaxCreditCalculator;
use Illuminate\Validation\Rule;

class WriteTaxReturnWithholdingRequest extends StrictApiRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('PATCH') ? 'sometimes' : 'required';

        return ['type' => [$required, 'required', Rule::in(TaxCreditCalculator::TYPES)], 'payer_name' => ['sometimes', 'nullable', 'string', 'max:255'], 'payer_tax_id' => ['sometimes', 'nullable', 'string', 'max:30'], 'amount' => [$required, 'required', new MoneyInput]];
    }
}

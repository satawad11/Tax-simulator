<?php

namespace App\Http\Requests\Api\V1;

use App\Rules\MoneyInput;
use Illuminate\Validation\Rule;

class WriteTaxReturnAllowanceRequest extends StrictApiRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('PATCH') ? 'sometimes' : 'required';

        return ['code' => [$required, 'required', 'string', Rule::exists('allowance_types', 'code')->where('is_active', true)], 'input_amount' => [$required, 'required', new MoneyInput]];
    }
}

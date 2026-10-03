<?php

namespace App\Http\Requests\Api\V1;

use App\Rules\MoneyInput;

class WriteTaxReturnDonationRequest extends StrictApiRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('PATCH') ? 'sometimes' : 'required';

        return ['donation_code' => [$required, 'required', 'string', 'max:50'], 'input_amount' => [$required, 'required', new MoneyInput]];
    }
}

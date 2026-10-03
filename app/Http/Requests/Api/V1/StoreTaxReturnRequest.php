<?php

namespace App\Http\Requests\Api\V1;

use App\Services\Tax\TaxCalculationService;
use Illuminate\Validation\Rule;

class StoreTaxReturnRequest extends StrictApiRequest
{
    public function rules(): array
    {
        return ['tax_year' => ['required', 'integer', Rule::exists('tax_years', 'year')->where('active', true)],
            'form_code' => ['required', Rule::in(TaxCalculationService::FORMS)], 'name' => ['required', 'string', 'max:255']];
    }
}

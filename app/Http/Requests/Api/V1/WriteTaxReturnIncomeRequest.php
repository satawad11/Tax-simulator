<?php

namespace App\Http\Requests\Api\V1;

use App\Rules\MoneyInput;
use App\Services\Tax\ExpenseRuleResolver;
use App\Services\Tax\SeparateTaxCalculator;
use Illuminate\Validation\Rule;

class WriteTaxReturnIncomeRequest extends StrictApiRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('PATCH') ? 'sometimes' : 'required';

        // Which codes and subtypes are acceptable depends on the parent return's form mapping,
        // which TaxReturnInputService resolves; existence is all that can be checked here.
        return ['income_type' => [$required, 'required', 'string', Rule::exists('income_types', 'code')],
            'income_subtype' => ['sometimes', 'nullable', 'string', 'max:50'],
            // M7.4 — the ตารางที่ 2 activity and the จำนวนปีที่ถือครอง the form prints for ข้อ 7.
            'expense_activity' => ['sometimes', 'nullable', 'string', 'max:50'],
            'holding_years' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'gross_amount' => [$required, 'required', new MoneyInput],
            'exempt_amount' => ['sometimes', 'required', new MoneyInput],
            'actual_expense' => ['sometimes', 'nullable', new MoneyInput],
            'expense_method_selection' => ['sometimes', 'nullable', Rule::in(ExpenseRuleResolver::SELECTIONS)],
            'tax_treatment' => ['sometimes', 'nullable', Rule::in(SeparateTaxCalculator::TREATMENTS)]];
    }
}

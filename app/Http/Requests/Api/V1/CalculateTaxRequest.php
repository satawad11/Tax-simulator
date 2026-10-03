<?php

namespace App\Http\Requests\Api\V1;

use App\Models\TaxYear;
use App\Rules\MoneyInput;
use App\Services\Tax\Allowances\DisabledPersonAllowanceStrategy;
use App\Services\Tax\ExpenseRuleResolver;
use App\Services\Tax\PublishedTaxRuleResolver;
use App\Services\Tax\SeparateTaxCalculator;
use App\Services\Tax\TaxCalculationInputGuard;
use App\Services\Tax\TaxCalculationService;
use App\Services\Tax\TaxCreditCalculator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CalculateTaxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tax_year' => ['required', 'integer', Rule::exists('tax_years', 'year')->where('active', true)],
            'form_code' => ['required', Rule::in(TaxCalculationService::FORMS)],
            'profile' => ['sometimes', 'array:birth_date,marital_status'],
            'profile.birth_date' => ['sometimes', 'date_format:Y-m-d', 'before_or_equal:today'],
            'profile.marital_status' => ['sometimes', Rule::in(['single', 'married', 'divorced', 'widowed'])],
            // M7.3 — the facts ใบแนบ items 1–5 are derived from. Facts only: no family
            // allowance amount is accepted here or anywhere else on the request.
            'spouse' => ['sometimes', 'array:birth_date,has_income'],
            'spouse.birth_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'spouse.has_income' => ['required_with:spouse', 'boolean'],
            'dependents' => ['sometimes', 'array', 'list', 'max:100'],
            'dependents.*' => ['required', 'array:relation_type,disabled_person_relationship,child_type,birth_order,birth_date,eligible'],
            'dependents.*.relation_type' => ['required', Rule::in(WriteTaxReturnDependentRequest::RELATION_TYPES)],
            'dependents.*.disabled_person_relationship' => ['sometimes', 'nullable', Rule::in(DisabledPersonAllowanceStrategy::RELATIONSHIPS)],
            'dependents.*.child_type' => ['sometimes', 'nullable', Rule::in(WriteTaxReturnDependentRequest::CHILD_TYPES)],
            'dependents.*.birth_order' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
            'dependents.*.birth_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'dependents.*.eligible' => ['required', 'boolean'],
            'incomes' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'incomes.*' => ['required', 'array:income_type,income_subtype,expense_activity,holding_years,description,gross_amount,exempt_amount,actual_expense,expense_method_selection,tax_treatment'],
            // The form mapping decides which codes are acceptable; see the guard in after().
            'incomes.*.income_type' => ['required', 'string', Rule::exists('income_types', 'code')],
            'incomes.*.description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'incomes.*.gross_amount' => ['required', new MoneyInput],
            'incomes.*.exempt_amount' => ['sometimes', 'required', new MoneyInput],
            'incomes.*.actual_expense' => ['sometimes', 'required', new MoneyInput],
            'incomes.*.income_subtype' => ['sometimes', 'nullable', 'string', 'max:50'],
            // M7.4 — the two further facts ภ.ง.ด.90 ข้อ 7 prints beside a blank percentage.
            // Which categories require them is a semantic question; see the guard in after().
            'incomes.*.expense_activity' => ['sometimes', 'nullable', 'string', 'max:50'],
            'incomes.*.holding_years' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:200'],
            'incomes.*.expense_method_selection' => ['sometimes', 'nullable', Rule::in(ExpenseRuleResolver::SELECTIONS)],
            'incomes.*.tax_treatment' => ['sometimes', 'nullable', Rule::in(SeparateTaxCalculator::TREATMENTS)],
            'allowances' => ['sometimes', 'array', 'list', 'max:100'],
            'allowances.*' => ['required', 'array:code,amount'],
            'allowances.*.code' => ['required', 'string', Rule::exists('allowance_types', 'code')->where('is_active', true)],
            'allowances.*.amount' => ['required', new MoneyInput],
            /*
             * เงินได้ที่ได้รับยกเว้น taken after expenses — ใบแนบ ข้อ 13 and ข้อ 20. Separate from
             * `allowances` because the two land at different points in the calculation.
             *
             * `declarations_confirmed` is the filer affirming the printed conditions the engine
             * cannot check — a construction date, a geographic zone, a contractor's registration.
             * It is required and must be true for any positive amount; see the guard in after().
             */
            'income_exemptions' => ['sometimes', 'array', 'list', 'max:100'],
            'income_exemptions.*' => ['required', 'array:code,amount,declarations_confirmed'],
            'income_exemptions.*.code' => ['required', 'string', 'max:100'],
            'income_exemptions.*.amount' => ['required', new MoneyInput],
            'income_exemptions.*.declarations_confirmed' => ['sometimes', 'boolean'],
            'donations' => ['sometimes', 'array', 'list', 'max:100'],
            'donations.*' => ['required', 'array:code,amount'],
            'donations.*.code' => ['required', 'string', 'max:100'],
            'donations.*.amount' => ['required', new MoneyInput],
            'withholdings' => ['sometimes', 'array', 'list', 'max:100'],
            'withholdings.*' => ['required', 'array:type,amount'],
            'withholdings.*.type' => ['required', Rule::in(TaxCreditCalculator::TYPES)],
            'withholdings.*.amount' => ['required', new MoneyInput],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), ['tax_year', 'form_code', 'profile', 'spouse', 'dependents', 'incomes', 'allowances', 'income_exemptions', 'donations', 'withholdings']) as $key) {
                $validator->errors()->add($key, 'Unknown or calculated fields are not accepted.');
            }
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $year = TaxYear::where('year', $this->integer('tax_year'))->where('active', true)->firstOrFail();
            $version = app(PublishedTaxRuleResolver::class)->resolve($year);
            foreach (app(TaxCalculationInputGuard::class)->errors($year, $version, $this->all()) as $attribute => $message) {
                $validator->errors()->add($attribute, $message);
            }
        }];
    }
}

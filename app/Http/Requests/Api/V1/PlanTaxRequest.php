<?php

namespace App\Http\Requests\Api\V1;

use App\Models\TaxYear;
use App\Services\Tax\PublishedTaxRuleResolver;
use App\Services\Tax\TaxCalculationInputGuard;
use App\Support\ScenarioPayloadRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PlanTaxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // The base block reuses the M4 calculation contract verbatim, one level deeper.
        $calculation = (new CalculateTaxRequest)->rules();
        $rules = [
            'tax_year' => $calculation['tax_year'],
            'form_code' => $calculation['form_code'],
            'base' => ['required', 'array:profile,spouse,dependents,incomes,allowances,donations,withholdings'],
        ];
        foreach ($calculation as $field => $rule) {
            if (! in_array($field, ['tax_year', 'form_code'], true)) {
                $rules['base.'.$field] = $rule;
            }
        }

        return [...$rules, ...ScenarioPayloadRules::rules('scenario')];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), ['tax_year', 'form_code', 'base', 'scenario']) as $key) {
                $validator->errors()->add($key, 'Unknown or calculated fields are not accepted.');
            }
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $year = TaxYear::where('year', $this->integer('tax_year'))->where('active', true)->firstOrFail();
            $version = app(PublishedTaxRuleResolver::class)->resolve($year);
            // Planning accepts exactly what /tax/calculate accepts: one gate, one rule set.
            $base = [...$this->input('base', []), 'form_code' => $this->input('form_code')];
            foreach (app(TaxCalculationInputGuard::class)->errors($year, $version, $base, 'base.') as $attribute => $message) {
                $validator->errors()->add($attribute, $message);
            }
            ScenarioPayloadRules::validate($validator, 'scenario', $this->input('scenario'), $version->id);
        }];
    }
}

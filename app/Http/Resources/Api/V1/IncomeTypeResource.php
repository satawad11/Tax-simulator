<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ExpenseRule;
use App\Services\Tax\ExpenseActivityCatalogue;
use App\Services\Tax\ExpenseRuleResolver;
use App\Services\Tax\IncomeSubtypeCatalogue;
use App\Services\Tax\IncomeTypePresentationCatalogue;
use App\Services\Tax\PropertyHoldingPeriodCatalogue;
use Illuminate\Http\Request;

class IncomeTypeResource extends MetadataResource
{
    public function toArray(Request $request): array
    {
        $presentation = IncomeTypePresentationCatalogue::for($this->code);

        return [
            'code' => $this->code,
            'section_code' => $this->section_code,
            'name' => $this->name, 'description' => $this->description,
            'plain_language_name' => $presentation['plain_language_name'],
            'examples' => $presentation['examples'],
            'form_sections' => $presentation['form_sections'],
            $this->mergeWhen($this->resource->relationLoaded('expenseRules'), fn (): array => [
                'rule' => ['active' => $this->incomeRules->first()?->active ?? true],
                // Unchanged M3 field: the rule covering the whole income type, or null when
                // ภ.ง.ด.90 splits the type into subcategories.
                'expense_rule' => ExpenseRuleResource::make($this->expenseRules->firstWhere('income_subtype', null)),
                'expense_rules' => $this->readiness(),
            ]),
        ];
    }

    /**
     * Per-subcategory rule readiness, so a client knows what it may offer before a
     * calculation is attempted. Unverified categories expose no numeric value.
     *
     * @return list<array<string, mixed>>
     */
    private function readiness(): array
    {
        $rules = $this->expenseRules->keyBy(fn (ExpenseRule $rule): string => (string) $rule->income_subtype);
        $subtypes = IncomeSubtypeCatalogue::requiresSubtype($this->code)
            ? IncomeSubtypeCatalogue::codes($this->code)
            : [null];

        return array_map(function (?string $subtype) use ($rules): array {
            $entry = ['income_subtype' => $subtype,
                'label' => $subtype === null ? null : IncomeSubtypeCatalogue::SUBTYPES[$this->code][$subtype]];
            // M7.4 — ข้อ 7 items 1 and 3 (2) hold one rule per further printed fact, so a
            // single method/percentage would be a fiction. The client is told which fact
            // selects the rule instead, and the values come from the calculation itself.
            if (ExpenseActivityCatalogue::requiresActivity($this->code, $subtype)) {
                $activityRules = $this->expenseRules->where('income_subtype', $subtype)
                    ->keyBy(fn (ExpenseRule $rule): string => (string) $rule->expense_activity);

                return [...$entry, 'status' => 'VERIFIED', 'keyed_by' => 'expense_activity',
                    'keys' => array_map(function (string $code) use ($activityRules): array {
                        $rule = $activityRules->get($code);

                        return [
                            'code' => $code,
                            'row' => ExpenseActivityCatalogue::row($code),
                            'label' => ExpenseActivityCatalogue::label($code),
                            'method' => $rule?->method,
                            'actual_expense_supported' => $rule !== null
                                && in_array($rule->method, ['actual', ...ExpenseRuleResolver::ELECTION_METHODS], true),
                            'expense_method_selection_required' => $rule !== null
                                && in_array($rule->method, ExpenseRuleResolver::ELECTION_METHODS, true),
                        ];
                    }, ExpenseActivityCatalogue::codes())];
            }
            if (PropertyHoldingPeriodCatalogue::requiresHoldingYears($this->code, $subtype)) {
                return [...$entry, 'status' => 'VERIFIED', 'keyed_by' => 'holding_years',
                    'keys' => array_map(fn (array $band): array => [
                        'value' => $band[0],
                        'label' => $band[1] === null ? $band[0].' ปีขึ้นไป' : $band[0].' ปี',
                    ], PropertyHoldingPeriodCatalogue::BANDS),
                    'actual_expense_supported' => true, 'expense_method_selection_required' => true];
            }
            $rule = $rules->get((string) $subtype);
            if ($rule === null) {
                return [...$entry, 'status' => 'UNVERIFIED'];
            }

            return [...$entry, 'status' => 'VERIFIED', 'method' => $rule->method,
                'percentage' => $rule->percentage, 'maximum_amount' => $rule->maximum_amount,
                'fixed_amount' => $rule->fixed_amount, 'expense_group' => $rule->expense_group,
                'actual_expense_supported' => in_array($rule->method, ['actual', ...ExpenseRuleResolver::ELECTION_METHODS], true),
                'expense_method_selection_required' => in_array($rule->method, ExpenseRuleResolver::ELECTION_METHODS, true)];
        }, $subtypes);
    }
}

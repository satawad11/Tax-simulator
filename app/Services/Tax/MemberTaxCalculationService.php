<?php

namespace App\Services\Tax;

use App\DTO\Tax\TaxCalculationData;
use App\Http\Requests\Api\V1\CalculateTaxRequest;
use App\Models\TaxCalculation;
use App\Models\TaxReturn;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class MemberTaxCalculationService
{
    public function __construct(private TaxReturnService $returns, private TaxCalculationService $engine, private TaxCalculationInputGuard $guard) {}

    public function calculate(TaxReturn $return, bool $complete = false): TaxCalculation
    {
        return DB::transaction(function () use ($return, $complete): TaxCalculation {
            $return = $this->returns->locked($return);
            $this->returns->assertDraft($return);
            $payload = $this->payload($return);
            $result = $this->engine->calculate(TaxCalculationData::fromArray($payload), $return->ruleVersion);
            $snapshot = json_decode(json_encode($result, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
            $values = [
                'gross_income' => $snapshot['income']['gross_income'], 'exempt_income' => $snapshot['income']['exempt_income'],
                'total_expense' => $snapshot['expenses']['total'], 'income_after_expense' => $snapshot['income_after_expense'],
                'total_allowance' => $snapshot['allowances']['total_eligible'], 'total_donation' => $snapshot['donations']['total_eligible'],
                'net_income' => $snapshot['net_income'], 'calculated_tax' => $snapshot['progressive_tax']['total'],
                'tax_credit' => $snapshot['credits']['total'], 'withholding_tax' => $snapshot['credits']['withholding'],
                'prepaid_tax' => '0.00', 'final_tax' => $snapshot['result']['amount'],
            ];
            $columns = array_map($this->projection(...), $values);
            $calculation = $return->calculations()->create([...$columns, 'rule_version_id' => $return->rule_version_id,
                'result_status' => $snapshot['result']['status'], 'input_snapshot' => $payload, 'calculation_trace' => $snapshot['trace'],
                'result_snapshot' => $snapshot, 'calculated_at' => now()]);
            $bracketIds = $return->ruleVersion->brackets()->pluck('id', 'sort_order');
            foreach ($snapshot['progressive_tax']['brackets'] as $bracket) {
                $calculation->brackets()->create(['tax_bracket_id' => $bracketIds[$bracket['sort_order']],
                    'sort_order' => $bracket['sort_order'], 'from_amount' => $bracket['min_amount'], 'to_amount' => $bracket['max_amount'],
                    'rate' => $bracket['rate'], 'taxable_amount' => $this->projection($bracket['taxable_amount']),
                    'tax_amount' => $this->projection($bracket['tax']), 'result_snapshot' => $bracket]);
            }
            if ($complete) {
                $return->status = 'completed';
                $return->completed_at = now();
            }
            $return->touch();

            return $calculation->load('brackets');
        });
    }

    /** Builds and validates the saved return's calculation input. Read-only; persists nothing. */
    public function payload(TaxReturn $return): array
    {
        $return->load(TaxReturnService::DETAIL);
        $payload = $this->inputs($return);
        $rules = (new CalculateTaxRequest)->rules();
        $rules['tax_year'] = ['required', 'integer', 'exists:tax_years,year'];
        Validator::make($payload, $rules)->validate();
        // Same semantic gate as Guest calculate and planning, resolved against the saved
        // return's own rule version rather than the currently published one.
        $errors = $this->guard->errors($return->ruleVersion->taxYear, $return->ruleVersion, $payload);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $payload;
    }

    private function inputs(TaxReturn $return): array
    {
        // M7.3 — the saved family rows feed exactly the same payload keys a guest sends, so
        // the derivation in FamilyAllowanceResolver has one implementation, not two.
        $profile = $return->profile;
        $spouse = $return->spouse;
        // Rows saved before M2 introduced relation_type carry only the legacy relationship
        // and cannot name a ใบแนบ line, so they are left out rather than assigned one.
        $dependents = $return->dependents->filter(fn ($row): bool => $row->relation_type !== null)->values();

        return ['tax_year' => $return->taxYear->year, 'form_code' => $return->taxForm->code,
            ...($profile === null ? [] : ['profile' => array_filter([
                'birth_date' => $profile->birth_date?->format('Y-m-d'), 'marital_status' => $profile->marital_status,
            ], fn ($value): bool => $value !== null)]),
            ...($spouse === null ? [] : ['spouse' => ['has_income' => (bool) $spouse->has_income,
                ...($spouse->birth_date === null ? [] : ['birth_date' => $spouse->birth_date->format('Y-m-d')])]]),
            ...($dependents->isEmpty() ? [] : ['dependents' => $dependents->map(fn ($row) => [
                'relation_type' => $row->relation_type, 'eligible' => (bool) $row->eligible,
                ...($row->disabled_person_relationship === null ? [] : ['disabled_person_relationship' => $row->disabled_person_relationship]),
                ...($row->child_type === null ? [] : ['child_type' => $row->child_type]),
                ...($row->birth_order === null ? [] : ['birth_order' => (int) $row->birth_order]),
                ...($row->birth_date === null ? [] : ['birth_date' => $row->birth_date->format('Y-m-d')]),
            ])->all()]),
            'incomes' => $return->incomes->map(fn ($row) => ['income_type' => $row->incomeType->code,
                'gross_amount' => $row->gross_amount, 'exempt_amount' => $row->exempt_amount ?? '0.00',
                ...($row->income_subtype === null ? [] : ['income_subtype' => $row->income_subtype]),
                ...($row->expense_activity === null ? [] : ['expense_activity' => $row->expense_activity]),
                ...($row->holding_years === null ? [] : ['holding_years' => (int) $row->holding_years]),
                ...($row->expense_method_selection === null ? [] : ['expense_method_selection' => $row->expense_method_selection]),
                ...($row->tax_treatment === null ? [] : ['tax_treatment' => $row->tax_treatment]),
                ...($row->actual_expense === null ? [] : ['actual_expense' => $row->actual_expense])])->all(),
            'allowances' => $return->allowances->map(fn ($row) => ['code' => $row->allowanceType->code, 'amount' => $row->input_amount])->all(),
            // The affirmation travels with the amount: the guard refuses a positive exemption
            // without it, so a saved return that dropped it could not recalculate itself.
            'income_exemptions' => $return->incomeExemptions->map(fn ($row) => ['code' => $row->code,
                'amount' => $row->input_amount, 'declarations_confirmed' => (bool) $row->declarations_confirmed])->all(),
            'donations' => $return->donations->map(fn ($row) => ['code' => $row->donation_code, 'amount' => $row->input_amount])->all(),
            'withholdings' => $return->withholdings->map(fn ($row) => ['type' => $row->type, 'amount' => $row->amount])->all()];
    }

    /** Exact DECIMAL(15,2) projection only; JSON remains authoritative when this is null. */
    private function projection(string $value): ?string
    {
        $decimal = BigDecimal::of($value)->strippedOfTrailingZeros();
        if ($decimal->getScale() > 2 || $decimal->abs()->compareTo('9999999999999.99') > 0) {
            return null;
        }

        return (string) $decimal->toScale(2);
    }
}

<?php

namespace App\Services\Tax;

use App\Models\DonationRule;
use App\Models\IncomeExemptionRule;
use App\Models\TaxRuleVersion;
use App\Models\TaxYear;
use App\Services\Tax\Allowances\DisabledPersonAllowanceStrategy;
use App\ValueObjects\Money;

/**
 * Semantic checks that field-level validation rules cannot express, shared by every
 * calculation entry point: Guest calculate, Guest planning and Member saved returns.
 *
 * Keeping them in one place is what guarantees a saved PND90 return, a guest request and a
 * planning scenario are accepted or rejected on exactly the same grounds.
 */
class TaxCalculationInputGuard
{
    public function __construct(
        private ExpenseRuleResolver $resolver,
        private InputIntegrityValidator $inputIntegrity,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  a full TaxCalculationData payload
     * @param  string  $prefix  attribute prefix, e.g. "base." for a planning request
     * @return array<string, string> attribute => message
     */
    public function errors(TaxYear $year, TaxRuleVersion $version, array $payload, string $prefix = ''): array
    {
        $incomes = is_array($payload['incomes'] ?? null) ? $payload['incomes'] : [];
        $form = $year->forms()->where('code', $payload['form_code'] ?? '')->where('active', true)->first();
        $mapped = $form === null ? [] : $form->incomeTypes()->pluck('code')->all();

        if ($form === null || $mapped === []) {
            return [$prefix.'form_code' => 'An active tax form with mapped income types is required for this tax year.'];
        }

        $errors = $this->inputIntegrity->errors($payload, $prefix);
        foreach ($incomes as $index => $income) {
            if (! is_array($income)) {
                continue;
            }
            $code = $income['income_type'] ?? null;
            if (! in_array($code, $mapped, true)) {
                $errors[$prefix."incomes.$index.income_type"] = 'This income type is not supported by the selected tax form.';

                continue;
            }
            if ((new Money((string) ($income['exempt_amount'] ?? '0')))
                ->compare(new Money((string) ($income['gross_amount'] ?? '0'))) > 0) {
                $errors[$prefix."incomes.$index.exempt_amount"] = 'Exempt amount must not exceed gross amount.';
            }
            $errors += $this->subtypeErrors((string) $code, $income, $index, $prefix);
            $errors += $this->activityErrors((string) $code, $income, $index, $prefix);
            $errors += $this->holdingYearErrors((string) $code, $income, $index, $prefix);
            $errors += $this->treatmentErrors((string) $code, $income, $index, $prefix);
        }
        if ($errors === [] && ! $this->hasProgressiveLine($incomes)) {
            $errors[$prefix.'incomes'] = 'At least one income line must use the progressive tax base.';
        }
        if ($errors !== []) {
            // Income-shape errors stop the expense checks, which depend on a resolvable rule,
            // but the allowance, credit and donation checks read their own blocks and stay.
            return [...$errors, ...$this->allowanceErrors($payload, $prefix),
                ...$this->exemptionErrors($payload, $version, $prefix),
                ...$this->dependentErrors($payload, $version, $prefix),
                ...$this->creditErrors($payload, $form->code, $prefix), ...$this->donationErrors($payload, $version, $prefix)];
        }

        return [...$this->expenseErrors($incomes, $version, $prefix),
            ...$this->allowanceErrors($payload, $prefix),
            ...$this->exemptionErrors($payload, $version, $prefix),
            ...$this->dependentErrors($payload, $version, $prefix),
            ...$this->creditErrors($payload, $form->code, $prefix),
            ...$this->donationErrors($payload, $version, $prefix)];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function dependentErrors(array $payload, TaxRuleVersion $version, string $prefix): array
    {
        $ruleIsActive = $version->allowanceRules()->where('code', DisabledPersonAllowanceStrategy::RULE_CODE)
            ->where('active', true)->exists();
        $errors = [];

        foreach (is_array($payload['dependents'] ?? null) ? $payload['dependents'] : [] as $index => $dependent) {
            if (! is_array($dependent)) {
                continue;
            }

            $relationType = $dependent['relation_type'] ?? null;
            $relationship = $dependent['disabled_person_relationship'] ?? null;
            $attribute = $prefix."dependents.$index.disabled_person_relationship";

            if ($relationType !== 'disabled_person' && $relationship !== null) {
                $errors[$attribute] = 'This discriminator is accepted only for a disabled_person dependent.';
            } elseif ($ruleIsActive && $relationType === 'disabled_person' && $relationship === null) {
                $errors[$attribute] = 'State whether this disabled person is a family_member or other_person for this rule version.';
            }
        }

        return $errors;
    }

    /**
     * Milestone 07.5 — an allowance this baseline cannot calculate is refused, not zeroed.
     *
     * Before M7.5 such a line was accepted, deducted nothing and warned. That is the one shape
     * of silence the closure audit forbids: the taxpayer asked for a deduction they had reason
     * to expect, and the response carried a lower deduction total without saying no. A zero
     * amount is still accepted, because it cannot change the tax either way.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    /**
     * เงินได้ที่ได้รับยกเว้น: the line must exist in the resolved version, and the filer must have
     * affirmed the printed conditions the engine cannot check.
     *
     * The affirmation is refused rather than assumed because these lines turn on facts no declared
     * figure carries — whether a building sits in เขตพัฒนาพิเศษเฉพาะกิจ, when a contract was signed,
     * whether a contractor is VAT-registered. Defaulting it to true would have the product assert
     * an entitlement on the filer's behalf, which it must never do.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function exemptionErrors(array $payload, TaxRuleVersion $version, string $prefix): array
    {
        $claims = is_array($payload['income_exemptions'] ?? null) ? $payload['income_exemptions'] : [];
        if ($claims === []) {
            return [];
        }
        $known = IncomeExemptionRule::where('rule_version_id', $version->id)
            ->where('active', true)->pluck('code')->all();
        $errors = [];

        foreach ($claims as $index => $claim) {
            if (! is_array($claim)) {
                continue;
            }
            $code = (string) ($claim['code'] ?? '');
            // A zero cannot change the tax, so it is never refused — the same rule the allowance
            // and credit checks follow.
            if ((new Money((string) ($claim['amount'] ?? '0')))->compare(new Money) <= 0) {
                continue;
            }
            if (! in_array($code, $known, true)) {
                $errors[$prefix."income_exemptions.$index.code"] = 'INCOME_EXEMPTION_UNSUPPORTED: '
                    .'รายการเงินได้ที่ได้รับยกเว้นนี้ไม่มีกฎที่ตรวจสอบแล้วในกฎภาษีฉบับที่ใช้อยู่';

                continue;
            }
            if (($claim['declarations_confirmed'] ?? false) !== true) {
                $errors[$prefix."income_exemptions.$index.declarations_confirmed"] = 'INCOME_EXEMPTION_DECLARATION_REQUIRED: '
                    .'ต้องยืนยันเงื่อนไขตามที่คำแนะนำการกรอกแบบระบุไว้ก่อน ระบบตรวจสอบเงื่อนไขเหล่านี้จากจำนวนเงินที่กรอกไม่ได้';
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function allowanceErrors(array $payload, string $prefix): array
    {
        $errors = [];
        foreach (is_array($payload['allowances'] ?? null) ? $payload['allowances'] : [] as $index => $allowance) {
            if (! is_array($allowance)) {
                continue;
            }
            $code = (string) ($allowance['code'] ?? '');
            if (! AllowanceCoverageCatalogue::blocks($code)
                || (new Money((string) ($allowance['amount'] ?? '0')))->compare(new Money) <= 0) {
                continue;
            }
            $errors[$prefix."allowances.$index.code"] = AllowanceCoverageCatalogue::errorCode($code)
                .': '.AllowanceCoverageCatalogue::message($code);
        }

        return $errors;
    }

    /**
     * Milestone 07.5 — the same closure for credits ข้อ 11 item 15 does not carry.
     *
     * ภ.ง.ด.90 item 13 prints a foreign tax credit whose limit wording is not specific enough
     * to apply, and `other_credit` has no counterpart on the form at all. Both used to be
     * accepted at zero with a warning; a positive amount is now refused.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function creditErrors(array $payload, string $formCode, string $prefix): array
    {
        $errors = [];
        foreach (is_array($payload['withholdings'] ?? null) ? $payload['withholdings'] : [] as $index => $credit) {
            if (! is_array($credit)) {
                continue;
            }
            $type = (string) ($credit['type'] ?? '');
            $positive = (new Money((string) ($credit['amount'] ?? '0')))->compare(new Money) > 0;

            /*
             * ภ.ง.ด.94 is a ภ.ง.ด.90 line and only a ภ.ง.ด.90 line.
             *
             * ภ.ง.ด.90 item 15 prints two prepaid lines — ภ.ง.ด.93 and ภ.ง.ด.94 — and ภ.ง.ด.91
             * line 15 prints only ภ.ง.ด.93. The string "94" does not occur anywhere in the
             * ภ.ง.ด.91 form, which follows from what the form is for: ภ.ง.ด.94 is the half-year
             * return for มาตรา 40 (5)–(8), and ภ.ง.ด.91 carries มาตรา 40 (1) alone, so a filer on
             * this form cannot have filed one.
             *
             * The engine accepted it and subtracted it in full, with no warning: 720,000 of salary
             * came back as 31,500 payable instead of 36,500. The rule had been carried across from
             * ภ.ง.ด.90 — `docs/tax/PND91_PRODUCTION_BASELINE_2568.md` justified it by citing
             * "ข้อ 11 item 15", and ข้อ 11 is a **ภ.ง.ด.90** section. ภ.ง.ด.91 has no ข้อ 11 at all.
             */
            if ($type === 'pnd94' && $formCode === 'PND91' && $positive) {
                $errors[$prefix."withholdings.$index.type"] =
                    'PND94_NOT_ON_THIS_FORM: แบบ ภ.ง.ด.91 ไม่มีรายการภาษีที่ชำระไว้ตามแบบ ภ.ง.ด.94 '
                    .'(ภ.ง.ด.94 เป็นแบบครึ่งปีสำหรับเงินได้ตามมาตรา 40 (5)-(8) ซึ่งไม่อยู่ในแบบนี้)';

                continue;
            }

            if (in_array($type, TaxCreditCalculator::PREPAID_TYPES, true)
                || ! in_array($type, TaxCreditCalculator::TYPES, true)
                || ! $positive) {
                continue;
            }
            $errors[$prefix."withholdings.$index.type"] = $type === 'foreign_tax_credit'
                ? 'FOREIGN_TAX_CREDIT_UNSUPPORTED: ภ.ง.ด.90 ข้อ 11 (13.) ไม่ได้พิมพ์หลักเกณฑ์และเพดานของเครดิตภาษีต่างประเทศ '
                    .'ไว้อย่างชัดเจนพอที่จะนำมาคำนวณได้'
                : 'TAX_CREDIT_TYPE_UNSUPPORTED: แบบ ภ.ง.ด.90/91 ไม่ได้พิมพ์รายการเครดิตภาษีประเภทนี้ไว้';
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $income
     * @return array<string, string>
     */
    private function subtypeErrors(string $code, array $income, int $index, string $prefix): array
    {
        $subtype = $income['income_subtype'] ?? null;
        $attribute = $prefix."incomes.$index.income_subtype";

        if (! IncomeSubtypeCatalogue::requiresSubtype($code)) {
            return $subtype === null || $subtype === ''
                ? []
                : [$attribute => 'This income type is printed as a single category and takes no subtype.'];
        }
        if (! is_string($subtype) || $subtype === '') {
            return [$attribute => 'This income type is printed as several subcategories; state which one applies ('
                .implode(', ', IncomeSubtypeCatalogue::codes($code)).').'];
        }
        if (! IncomeSubtypeCatalogue::knows($code, $subtype)) {
            return [$attribute => 'Unsupported income subtype for this income type ('
                .implode(', ', IncomeSubtypeCatalogue::codes($code)).').'];
        }

        return [];
    }

    /**
     * ข้อ 7 item 1 prints `(ระบุ)` and a blank percentage: the rate comes from ตารางที่ 2 on
     * page 17, keyed by the activity. The activity is required there and rejected everywhere
     * else, so it can never silently change another category's rule.
     *
     * @param  array<string, mixed>  $income
     * @return array<string, string>
     */
    private function activityErrors(string $code, array $income, int $index, string $prefix): array
    {
        $subtype = ($income['income_subtype'] ?? null) ?: null;
        $activity = ($income['expense_activity'] ?? null) ?: null;
        $attribute = $prefix."incomes.$index.expense_activity";

        if (! ExpenseActivityCatalogue::requiresActivity($code, $subtype)) {
            return $activity === null ? [] : [$attribute => 'This income category takes no expense activity.'];
        }
        if ($activity === null) {
            return [$attribute => 'ภ.ง.ด.90 ข้อ 7 ข้อย่อย 1 takes its expense rate from ตารางที่ 2 (คำแนะนำ หน้า 17); '
                .'state which of its 44 activities applies.'];
        }

        return ExpenseActivityCatalogue::knows($activity) ? []
            : [$attribute => 'Unsupported expense activity; it must be one of the 44 rows printed in ตารางที่ 2 (คำแนะนำ หน้า 17).'];
    }

    /**
     * ข้อ 7 item 3 (2) prints `จำนวนปีที่ถือครอง ………. ปี` beside its blank percentage, because the
     * rate comes from the holding-period table on page 3 of the filing instructions.
     *
     * @param  array<string, mixed>  $income
     * @return array<string, string>
     */
    private function holdingYearErrors(string $code, array $income, int $index, string $prefix): array
    {
        $subtype = ($income['income_subtype'] ?? null) ?: null;
        $years = $income['holding_years'] ?? null;
        $attribute = $prefix."incomes.$index.holding_years";

        if (! PropertyHoldingPeriodCatalogue::requiresHoldingYears($code, $subtype)) {
            return $years === null ? [] : [$attribute => 'This income category takes no holding period.'];
        }
        if ($years === null) {
            return [$attribute => 'ภ.ง.ด.90 ข้อ 7 ข้อย่อย 3 (2) takes its expense rate from จำนวนปีที่ถือครอง; state the number of years.'];
        }

        return PropertyHoldingPeriodCatalogue::knows(is_numeric($years) ? (int) $years : null) ? []
            : [$attribute => 'Holding period must be a whole number of years, counted as at least one (เศษของปีให้นับเป็น 1 ปี).'];
    }

    /**
     * ข้อ 9 prints only one category, so the separate-rate election may be offered only there.
     *
     * @param  array<string, mixed>  $income
     * @return array<string, string>
     */
    private function treatmentErrors(string $code, array $income, int $index, string $prefix): array
    {
        if (! SeparateTaxCalculator::elected($income)) {
            return [];
        }
        $subtype = ($income['income_subtype'] ?? null) ?: null;
        if (SeparateTaxCalculator::eligible($code, $subtype)) {
            return [];
        }

        return [$prefix."incomes.$index.tax_treatment" => 'Only gift income under มาตรา 42 (26) (27) (28) may elect the separate rate printed in ข้อ 9.'];
    }

    /** @param list<array<string, mixed>> $incomes */
    private function hasProgressiveLine(array $incomes): bool
    {
        foreach ($incomes as $income) {
            if (is_array($income) && ! SeparateTaxCalculator::elected($income)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $incomes
     * @return array<string, string>
     */
    private function expenseErrors(array $incomes, TaxRuleVersion $version, string $prefix): array
    {
        $errors = [];
        foreach ($this->resolver->unverifiedIncomeTypes($incomes, $version) as $index => $code) {
            $attribute = IncomeSubtypeCatalogue::requiresSubtype($code) ? 'income_subtype' : 'income_type';
            $errors[$prefix."incomes.$index.$attribute"] =
                'Expense rule for this income category has not been verified; simulation is not available for it yet.';
        }

        $declared = [];
        $elected = [];
        foreach ($incomes as $index => $income) {
            if (! is_array($income)) {
                continue;
            }
            $code = (string) ($income['income_type'] ?? '');
            $subtype = ($income['income_subtype'] ?? null) ?: null;
            if (isset($errors[$prefix."incomes.$index.income_type"]) || isset($errors[$prefix."incomes.$index.income_subtype"])
                || SeparateTaxCalculator::elected($income)) {
                continue;
            }
            $rule = $this->resolver->resolve($version, $code, $subtype,
                $this->resolver->activityOf($income), $this->resolver->holdingYearsOf($income));
            $selection = ($income['expense_method_selection'] ?? null) ?: null;

            if ($this->resolver->requiresSelection($rule)) {
                if ($selection === null) {
                    $errors[$prefix."incomes.$index.expense_method_selection"] =
                        'This income category offers a choice between the stated percentage and actual expense; state which one applies.';
                } elseif (! in_array($selection, ExpenseRuleResolver::SELECTIONS, true)) {
                    $errors[$prefix."incomes.$index.expense_method_selection"] = 'Expense method must be percentage or actual.';
                } elseif ($selection === 'actual' && ($income['actual_expense'] ?? null) === null) {
                    $errors[$prefix."incomes.$index.actual_expense"] = 'Electing actual expense requires the declared amount.';
                }
            } elseif ($selection !== null) {
                $errors[$prefix."incomes.$index.expense_method_selection"] =
                    'This income category does not offer a choice of expense method.';
            }

            // One category is deducted once, so its lines cannot elect different methods.
            $group = implode('|', [$code, $subtype ?? '', $this->resolver->activityOf($income) ?? '',
                $this->resolver->holdingYearsOf($income) ?? '']);
            if ($selection !== null && ($elected[$group] ?? $selection) !== $selection) {
                $errors[$prefix."incomes.$index.expense_method_selection"] =
                    'Income lines of one category must elect the same expense method.';
            }
            $elected[$group] ??= $selection;

            if (($income['actual_expense'] ?? null) === null) {
                continue;
            }
            if (! $this->resolver->allowsActualExpense($rule)) {
                $errors[$prefix."incomes.$index.actual_expense"] = 'Actual expense is not supported for this income category.';

                continue;
            }
            $key = $code.'|'.($subtype ?? '');
            $declared[$key] = ($declared[$key] ?? new Money)->add(new Money((string) $income['actual_expense']));
        }

        foreach ($declared as $key => $total) {
            $remaining = new Money;
            $last = null;
            foreach ($incomes as $index => $income) {
                if (((string) ($income['income_type'] ?? '')).'|'.(($income['income_subtype'] ?? null) ?: '') !== $key) {
                    continue;
                }
                $last = $index;
                $remaining = $remaining->add((new Money((string) ($income['gross_amount'] ?? '0')))
                    ->subtract(new Money((string) ($income['exempt_amount'] ?? '0'))));
            }
            if ($total->compare($remaining) > 0) {
                $errors[$prefix."incomes.$last.actual_expense"] =
                    'Declared actual expense must not exceed income after exemption for this income category.';
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function donationErrors(array $payload, TaxRuleVersion $version, string $prefix): array
    {
        $donations = is_array($payload['donations'] ?? null) ? $payload['donations'] : [];
        if ($donations === []) {
            return [];
        }
        $known = DonationRule::where('rule_version_id', $version->id)->where('active', true)
            ->whereIn('code', array_column($donations, 'code'))->pluck('code')->all();

        $errors = [];
        foreach ($donations as $index => $donation) {
            if (! in_array($donation['code'] ?? null, $known, true)) {
                $errors[$prefix."donations.$index.code"] = 'Unknown or unsupported donation code for the resolved rule version.';
            }
        }

        return $errors;
    }
}

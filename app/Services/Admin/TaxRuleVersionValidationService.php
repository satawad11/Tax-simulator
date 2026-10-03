<?php

namespace App\Services\Admin;

use App\Models\AllowanceCapGroup;
use App\Models\TaxRuleVersion;
use App\Services\Tax\ExpenseRuleResolver;
use App\Services\Tax\PercentageBaseResolver;
use App\Services\Tax\TaxRecommendationService;
use App\ValueObjects\Money;

/**
 * Structural validation of a draft rule version, before it may be published.
 *
 * Milestone 08. This is deliberately *structural*: it checks the shape of the stored rule data
 * against what the engine can execute, rather than running the engine over invented cases and
 * hoping something breaks. A random-case smoke test proves nothing about a rule that no random
 * case happened to touch.
 *
 * Errors block publication. Warnings do not — they describe the paths Milestone 7.5 classified
 * as PARTIAL_BLOCKED or UNSUPPORTED, which are guarded at runtime and expected to be absent.
 */
class TaxRuleVersionValidationService
{
    /** @return array{valid: bool, errors: list<array<string, string>>, warnings: list<array<string, string>>} */
    public function validate(TaxRuleVersion $version): array
    {
        $errors = [...$this->versionErrors($version), ...$this->bracketErrors($version),
            ...$this->mappingErrors($version), ...$this->expenseErrors($version),
            ...$this->allowanceErrors($version), ...$this->capGroupErrors($version),
            ...$this->donationErrors($version), ...$this->recommendationErrors($version)];

        return ['valid' => $errors === [], 'errors' => $errors, 'warnings' => $this->warnings($version)];
    }

    /** @return list<array<string, string>> */
    private function versionErrors(TaxRuleVersion $version): array
    {
        $errors = [];
        if ($version->taxYear === null) {
            $errors[] = $this->error('TAX_YEAR_MISSING', 'tax_year', 'The rule version is not attached to a tax year.');
        }
        if ($version->status !== 'draft') {
            $errors[] = $this->error('VERSION_NOT_DRAFT', 'status', 'Only a draft version can be validated for publication.');
        }
        $duplicate = TaxRuleVersion::where('tax_year_id', $version->tax_year_id)
            ->where('version', $version->version)->whereKeyNot($version->id)->exists();
        if ($duplicate) {
            $errors[] = $this->error('VERSION_NOT_UNIQUE', 'version', 'Another version of this tax year already uses this identifier.');
        }

        return $errors;
    }

    /**
     * The brackets must tile the whole income line: start at zero, leave no gap, never overlap,
     * and end open. A gap would silently tax nothing; an overlap would tax twice.
     *
     * @return list<array<string, string>>
     */
    private function bracketErrors(TaxRuleVersion $version): array
    {
        $brackets = $version->brackets()->orderBy('sort_order')->get();
        if ($brackets->isEmpty()) {
            return [$this->error('TAX_BRACKETS_MISSING', 'tax_brackets', 'A rule version needs at least one tax bracket.')];
        }
        $errors = [];
        $expectedOrder = range(1, $brackets->count());
        if ($brackets->pluck('sort_order')->map(fn ($order): int => (int) $order)->all() !== $expectedOrder) {
            $errors[] = $this->error('TAX_BRACKET_ORDER_INVALID', 'tax_brackets',
                'Bracket sort_order must run from 1 without gaps or duplicates.');
        }
        $previousMax = null;
        foreach ($brackets as $index => $bracket) {
            $min = new Money((string) $bracket->min_amount);
            $max = $bracket->max_amount === null ? null : new Money((string) $bracket->max_amount);
            $path = 'tax_brackets.'.$index;

            if ($previousMax === null && $min->compare(new Money) !== 0) {
                $errors[] = $this->error('TAX_BRACKET_START_INVALID', $path, 'The first bracket must start at zero.');
            }
            if ($previousMax !== null && $min->compare($previousMax) !== 0) {
                $errors[] = $this->error('TAX_BRACKET_GAP', $path,
                    'Bracket '.($index + 1).' must start where the previous one ends; a gap or overlap changes the tax.');
            }
            if ($max !== null && $max->compare($min) <= 0) {
                $errors[] = $this->error('TAX_BRACKET_RANGE_INVALID', $path, 'A bracket must end above where it starts.');
            }
            $rate = (string) $bracket->rate;
            if (! is_numeric($rate) || (float) $rate < 0 || (float) $rate > 100) {
                $errors[] = $this->error('TAX_BRACKET_RATE_INVALID', $path, 'A bracket rate must be between 0 and 100.');
            }
            if ($max === null && $index !== $brackets->count() - 1) {
                $errors[] = $this->error('TAX_BRACKET_OPEN_NOT_LAST', $path, 'Only the final bracket may be open-ended.');
            }
            $previousMax = $max;
        }
        if ($previousMax !== null) {
            $errors[] = $this->error('TAX_BRACKET_NOT_OPEN_ENDED', 'tax_brackets', 'The final bracket must be open-ended.');
        }

        return $errors;
    }

    /** @return list<array<string, string>> */
    private function mappingErrors(TaxRuleVersion $version): array
    {
        $year = $version->taxYear;
        if ($year === null) {
            return [];
        }
        $forms = $year->forms()->where('active', true)->get();
        if ($forms->isEmpty()) {
            return [$this->error('FORM_MAPPING_MISSING', 'forms', 'The tax year has no active form.')];
        }
        $errors = [];
        foreach ($forms as $form) {
            if ($form->incomeTypes()->count() === 0) {
                $errors[] = $this->error('FORM_INCOME_TYPES_MISSING', 'forms.'.$form->code,
                    'Form '.$form->code.' has no mapped income type, so nothing can be calculated on it.');
            }
        }

        return $errors;
    }

    /** @return list<array<string, string>> */
    private function expenseErrors(TaxRuleVersion $version): array
    {
        $errors = [];
        $seen = [];
        foreach ($version->expenseRules()->with('tiers')->get() as $rule) {
            $path = 'expense_rules.'.$rule->code;
            $key = implode('|', [$rule->income_type_id, $rule->income_subtype ?? '',
                $rule->expense_activity ?? '', $rule->holding_years_min ?? '']);
            if (isset($seen[$key])) {
                $errors[] = $this->error('EXPENSE_RULE_DUPLICATE', $path,
                    'Two expense rules resolve for the same income type, subtype, activity and holding band.');
            }
            $seen[$key] = true;

            if (! in_array($rule->method, ExpenseRuleResolver::SUPPORTED_METHODS, true)) {
                $errors[] = $this->error('EXPENSE_METHOD_UNSUPPORTED', $path,
                    'Expense method "'.$rule->method.'" has no approved mechanics.');
            }
            if (trim((string) $rule->source_reference) === '') {
                $errors[] = $this->error('EXPENSE_SOURCE_MISSING', $path,
                    'A production numeric expense rule must cite the source it was read from.');
            }
            if ($rule->method === 'tiered_or_actual') {
                $errors = [...$errors, ...$this->tierErrors($rule, $path)];
            }
        }

        return $errors;
    }

    /** @return list<array<string, string>> */
    private function tierErrors(object $rule, string $path): array
    {
        $tiers = $rule->tiers;
        if ($tiers->count() < 2) {
            return [$this->error('TIER_INCOMPLETE', $path, 'A tiered rule needs at least two bands.')];
        }
        $errors = [];
        if ($tiers->pluck('sort_order')->map(fn ($order): int => (int) $order)->all() !== range(1, $tiers->count())) {
            $errors[] = $this->error('TIER_ORDER_INVALID', $path, 'Tier sort_order must run from 1 without gaps.');
        }
        if ($tiers->last()->threshold_amount !== null) {
            $errors[] = $this->error('TIER_NOT_OPEN_ENDED', $path, 'The final tier must be open-ended.');
        }
        if ($tiers->whereNull('threshold_amount')->count() !== 1) {
            $errors[] = $this->error('TIER_OPEN_BAND_AMBIGUOUS', $path, 'Exactly one tier may be open-ended.');
        }

        return $errors;
    }

    /** @return list<array<string, string>> */
    private function allowanceErrors(TaxRuleVersion $version): array
    {
        $errors = [];
        $seen = [];
        foreach ($version->allowanceRules()->get() as $rule) {
            $path = 'allowance_rules.'.$rule->code;
            if (isset($seen[$rule->allowance_type_id])) {
                $errors[] = $this->error('ALLOWANCE_RULE_DUPLICATE', $path,
                    'An allowance type may carry at most one rule per version.');
            }
            $seen[$rule->allowance_type_id] = true;

            if (trim((string) $rule->source_reference) === '') {
                $errors[] = $this->error('ALLOWANCE_SOURCE_MISSING', $path,
                    'A production numeric allowance rule must cite the source it was read from.');
            }
            if ($rule->method === 'percentage_limit' && ! PercentageBaseResolver::knows($rule->percentage_base)) {
                $errors[] = $this->error('PERCENTAGE_BASE_INVALID', $path,
                    'A percentage allowance must name a supported percentage base ('
                        .implode(', ', PercentageBaseResolver::SUPPORTED).').');
            }
            if ($rule->method !== 'percentage_limit' && $rule->percentage_base !== null) {
                $errors[] = $this->error('PERCENTAGE_BASE_UNEXPECTED', $path,
                    'Only a percentage allowance may name a percentage base.');
            }
        }

        return $errors;
    }

    /** @return list<array<string, string>> */
    private function capGroupErrors(TaxRuleVersion $version): array
    {
        $errors = [];
        $ruled = $version->allowanceRules()->pluck('allowance_type_id')->all();
        $groups = AllowanceCapGroup::where('rule_version_id', $version->id)->with('allowanceTypes')->get();

        foreach ($groups as $group) {
            $path = 'allowance_cap_groups.'.$group->code;
            if ($group->allowanceTypes->isEmpty()) {
                $errors[] = $this->error('CAP_GROUP_EMPTY', $path, 'A combined cap group needs at least one member.');
            }
            if ($group->maximum_amount === null && $group->percentage === null) {
                $errors[] = $this->error('CAP_GROUP_CEILING_MISSING', $path,
                    'A combined cap group must state a ceiling or a percentage.');
            }
            foreach ($group->allowanceTypes as $type) {
                if (! in_array($type->id, $ruled, true)) {
                    $errors[] = $this->error('CAP_GROUP_MEMBER_UNRULED', $path.'.'.$type->code,
                        'A cap group member must have an allowance rule in this version.');
                }
            }
        }

        return $errors;
    }

    /** @return list<array<string, string>> */
    private function donationErrors(TaxRuleVersion $version): array
    {
        $errors = [];
        foreach ($version->donationRules()->get() as $rule) {
            $path = 'donation_rules.'.$rule->code;
            if (! in_array($rule->donation_type, ['special', 'general'], true)) {
                $errors[] = $this->error('DONATION_STAGE_INVALID', $path,
                    'A donation rule must belong to the special or general stage.');
            }
            if ($rule->max_percentage === null || (float) $rule->max_percentage <= 0) {
                $errors[] = $this->error('DONATION_CAP_MISSING', $path, 'A donation rule must state its percentage cap.');
            }
            if (trim((string) $rule->source_reference) === '') {
                $errors[] = $this->error('DONATION_SOURCE_MISSING', $path,
                    'A production numeric donation rule must cite the source it was read from.');
            }
        }

        return $errors;
    }

    /**
     * Recommendation conditions are matched by a closed vocabulary in TaxRecommendationService.
     * A condition outside it silently never fires, which is worse than refusing to publish.
     *
     * @return list<array<string, string>>
     */
    private function recommendationErrors(TaxRuleVersion $version): array
    {
        $errors = [];
        foreach ($version->recommendationRules()->get() as $rule) {
            $conditions = is_array($rule->conditions) ? $rule->conditions : json_decode((string) $rule->conditions, true);
            foreach (array_keys(is_array($conditions) ? $conditions : []) as $key) {
                if (! in_array($key, TaxRecommendationService::SUPPORTED_CONDITIONS, true)) {
                    $errors[] = $this->error('RECOMMENDATION_CONDITION_UNSUPPORTED', 'recommendation_rules.'.$rule->code,
                        'Condition "'.$key.'" is outside the supported vocabulary and would never fire.');
                }
            }
        }

        return $errors;
    }

    /** @return list<array<string, string>> */
    private function warnings(TaxRuleVersion $version): array
    {
        $warnings = [];
        $unsourced = $version->expenseRules()->whereNull('expense_activity')->count();
        if ($version->allowanceRules()->count() === 0) {
            $warnings[] = $this->error('ALLOWANCE_RULES_ABSENT', 'allowance_rules',
                'This version seeds no allowance rule; every declared allowance will be refused at runtime.');
        }
        if ($unsourced === 0 && $version->expenseRules()->count() === 0) {
            $warnings[] = $this->error('EXPENSE_RULES_ABSENT', 'expense_rules',
                'This version seeds no expense rule; every income category will be refused at runtime.');
        }

        return $warnings;
    }

    /** @return array<string, string> */
    private function error(string $code, string $path, string $message): array
    {
        return ['code' => $code, 'path' => $path, 'message' => $message];
    }
}

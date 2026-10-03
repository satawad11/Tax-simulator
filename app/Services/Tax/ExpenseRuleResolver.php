<?php

namespace App\Services\Tax;

use App\Exceptions\TaxMetadataConflictException;
use App\Models\ExpenseRule;
use App\Models\TaxRuleVersion;
use App\ValueObjects\Money;

/**
 * Resolves the single verified expense rule for one income type (and, where ภ.ง.ด.90 prints
 * subcategories with different treatment, one subtype) under one rule version.
 *
 * "Verified" means a stored, active rule that carries a source reference and whose mechanics
 * this engine can apply exactly. Nothing here infers a percentage, a cap or an eligibility:
 * an income type or subtype without a stored rule is reported as unverified, never defaulted.
 *
 * Two distinct failure modes are kept apart on purpose:
 *   - no stored rule at all      -> UNVERIFIED, reported to the caller as a 422 input error;
 *   - a stored rule that cannot be trusted or applied -> TaxMetadataConflictException (409),
 *     because that is a server-side rule-data problem the user cannot fix.
 */
class ExpenseRuleResolver
{
    /** Methods whose arithmetic is unambiguous. */
    public const SUPPORTED_METHODS = ['fixed', 'percentage', 'percentage_limit', 'actual', 'percentage_or_actual', 'tiered_or_actual'];

    /** Methods the schema allows but whose semantics are not yet approved. */
    public const UNAPPROVED_METHODS = ['custom'];

    /** Methods where ภ.ง.ด.90 prints two checkboxes and the taxpayer elects one. */
    public const ELECTION_METHODS = ['percentage_or_actual', 'tiered_or_actual'];

    public const SELECTIONS = ['percentage', 'actual'];

    /** @var array<string, ?ExpenseRule> */
    private array $cache = [];

    /**
     * Returns the verified rule, or null when this combination has no stored rule.
     *
     * ภ.ง.ด.90 ข้อ 7 needs two facts beyond the subtype before its rate is decidable — the
     * ตารางที่ 2 activity for item 1, and จำนวนปีที่ถือครอง for item 3 (2) — so they select the
     * rule alongside the subtype. Neither is ever inferred; an absent fact simply finds no rule.
     */
    public function resolve(TaxRuleVersion $version, string $incomeTypeCode, ?string $subtype = null,
        ?string $activity = null, ?int $holdingYears = null): ?ExpenseRule
    {
        $counted = $holdingYears === null ? null : PropertyHoldingPeriodCatalogue::counted($holdingYears);
        $key = implode('|', [$version->id, $incomeTypeCode, $subtype ?? '', $activity ?? '', $counted ?? '']);
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $rules = ExpenseRule::where('rule_version_id', $version->id)
            ->whereHas('incomeType', fn ($query) => $query->where('code', $incomeTypeCode))
            ->when($subtype === null,
                fn ($query) => $query->whereNull('income_subtype'),
                fn ($query) => $query->where('income_subtype', $subtype))
            ->when($activity === null,
                fn ($query) => $query->whereNull('expense_activity'),
                fn ($query) => $query->where('expense_activity', $activity))
            ->when($counted === null,
                fn ($query) => $query->whereNull('holding_years_min'),
                fn ($query) => $query->where('holding_years_min', '<=', $counted)
                    ->where(fn ($inner) => $inner->whereNull('holding_years_max')->orWhere('holding_years_max', '>=', $counted)))
            ->with('tiers')->get();

        if ($rules->isEmpty()) {
            return $this->cache[$key] = null;
        }
        if ($rules->count() !== 1) {
            throw new TaxMetadataConflictException('Exactly one expense rule may exist for an income type and subtype.');
        }
        $rule = $rules->sole();
        if (! $rule->active || trim((string) $rule->source_reference) === '') {
            throw new TaxMetadataConflictException('The stored expense rule is inactive or lacks a verified source reference.');
        }
        $this->assertMechanics($rule);

        return $this->cache[$key] = $rule;
    }

    /** True when the rule lets the taxpayer deduct a declared actual expense. */
    public function allowsActualExpense(?ExpenseRule $rule): bool
    {
        return $rule !== null && in_array($rule->method, ['actual', ...self::ELECTION_METHODS], true);
    }

    /** True when the taxpayer must state which of the form's two checkboxes they elect. */
    public function requiresSelection(?ExpenseRule $rule): bool
    {
        return $rule !== null && in_array($rule->method, self::ELECTION_METHODS, true);
    }

    /**
     * Income lines whose income type/subtype has no verified expense rule while the type
     * carries positive post-exemption income. Keys are the offending income row indexes.
     *
     * @param  list<array<string, mixed>>  $incomes
     * @return array<int, string> row index => income type code
     */
    public function unverifiedIncomeTypes(array $incomes, TaxRuleVersion $version): array
    {
        // ข้อ 9 income leaves the progressive flow entirely and has no expense line.
        $incomes = array_filter($incomes, fn (array $line): bool => ! SeparateTaxCalculator::elected($line));
        $positive = [];
        foreach ($incomes as $income) {
            $key = $this->groupKey($income);
            $remaining = (new Money((string) ($income['gross_amount'] ?? '0')))
                ->subtract(new Money((string) ($income['exempt_amount'] ?? '0')));
            $positive[$key] = ($positive[$key] ?? new Money)->add($remaining);
        }

        $offending = [];
        foreach ($incomes as $index => $income) {
            $code = (string) ($income['income_type'] ?? '');
            if ($positive[$this->groupKey($income)]->compare(new Money) > 0
                && $this->resolve($version, $code, $this->subtypeOf($income),
                    $this->activityOf($income), $this->holdingYearsOf($income)) === null) {
                $offending[$index] = $code;
            }
        }

        return $offending;
    }

    /** @param array<string, mixed> $income */
    private function groupKey(array $income): string
    {
        return implode('|', [(string) ($income['income_type'] ?? ''), $this->subtypeOf($income) ?? '',
            $this->activityOf($income) ?? '', $this->holdingYearsOf($income) ?? '']);
    }

    /** @param array<string, mixed> $income */
    private function subtypeOf(array $income): ?string
    {
        $subtype = $income['income_subtype'] ?? null;

        return is_string($subtype) && $subtype !== '' ? $subtype : null;
    }

    /** @param array<string, mixed> $income */
    public function activityOf(array $income): ?string
    {
        $activity = $income['expense_activity'] ?? null;

        return is_string($activity) && $activity !== '' ? $activity : null;
    }

    /** @param array<string, mixed> $income */
    public function holdingYearsOf(array $income): ?int
    {
        $years = $income['holding_years'] ?? null;

        return is_numeric($years) ? (int) $years : null;
    }

    private function assertMechanics(ExpenseRule $rule): void
    {
        if (in_array($rule->method, self::UNAPPROVED_METHODS, true)) {
            throw new TaxMetadataConflictException('Expense rule method "'.$rule->method.'" has no approved mechanics yet.');
        }
        if (! in_array($rule->method, self::SUPPORTED_METHODS, true) || $rule->conditions !== null || $rule->minimum_amount !== null) {
            throw new TaxMetadataConflictException('Expense rule mechanics are unsupported or incomplete.');
        }

        $percentage = $rule->percentage === null ? null : new Money($rule->percentage);
        if ($percentage !== null && ($percentage->compare(new Money) < 0 || $percentage->compare(new Money(100)) > 0)) {
            throw new TaxMetadataConflictException('Expense rule percentage must be between 0 and 100.');
        }
        foreach (['maximum_amount', 'fixed_amount'] as $field) {
            if ($rule->$field !== null && (new Money($rule->$field))->compare(new Money) < 0) {
                throw new TaxMetadataConflictException('Expense rule amounts must be nonnegative.');
            }
        }

        // percentage_limit states a cap; percentage_or_actual may or may not, because ภ.ง.ด.90
        // prints a cap for 40(3) but none for 40(5), 40(6) and 40(7).
        $required = match ($rule->method) {
            'fixed' => ['fixed_amount'],
            'percentage', 'percentage_or_actual' => ['percentage'],
            'percentage_limit' => ['percentage', 'maximum_amount'],
            // A tiered rate lives in expense_rule_tiers, so the rule row itself carries only
            // the combined ceiling the source prints across the bands.
            'tiered_or_actual' => ['maximum_amount'],
            default => [],
        };
        if ($rule->method === 'tiered_or_actual') {
            $tiers = $rule->tiers;
            if ($tiers->count() < 2 || $tiers->whereNull('threshold_amount')->count() !== 1
                || $tiers->last()->threshold_amount !== null) {
                throw new TaxMetadataConflictException('A tiered expense rule needs ordered bands ending in one open band.');
            }
        }
        $optional = $rule->method === 'percentage_or_actual' ? ['maximum_amount'] : [];
        $forbidden = array_diff(['percentage', 'maximum_amount', 'fixed_amount'], $required, $optional);
        foreach ($required as $field) {
            if ($rule->$field === null) {
                throw new TaxMetadataConflictException('Expense rule method "'.$rule->method.'" requires '.$field.'.');
            }
        }
        foreach ($forbidden as $field) {
            if ($rule->$field !== null) {
                throw new TaxMetadataConflictException('Expense rule method "'.$rule->method.'" must not define '.$field.'.');
            }
        }
    }

    /**
     * All rules sharing an expense group must agree, because the group is deducted once.
     *
     * @param  list<ExpenseRule>  $rules
     */
    public function assertGroupAgreement(array $rules): void
    {
        $first = $rules[0];
        foreach ($rules as $rule) {
            foreach (['method', 'percentage', 'maximum_amount', 'fixed_amount'] as $field) {
                if ((string) $rule->$field !== (string) $first->$field) {
                    throw new TaxMetadataConflictException(
                        'Expense rules sharing group "'.$first->expense_group.'" disagree at '.$field.'.');
                }
            }
        }
    }
}

<?php

namespace App\Services\Tax;

use App\Exceptions\TaxMetadataConflictException;
use App\Models\ExpenseRule;
use App\Models\TaxRuleVersion;
use App\ValueObjects\Money;

/**
 * Applies the verified expense rule of each expense group.
 *
 * An expense group is normally one income-type/subtype group, but ภ.ง.ด.90 ข้อ 1 deducts once
 * across 40(1) and 40(2) together, so a rule may declare an `expense_group` that spans income
 * types. The deduction — and any cap — is then applied once to the group's combined basis.
 *
 * Branching is by rule *method*, never by income type: adding a verified rule for another
 * Section 40 category is a seeding change, not a code change. An income type without a
 * verified rule never receives an invented deduction.
 */
class ExpenseCalculator
{
    public function __construct(private ?ExpenseRuleResolver $resolver = null) {}

    /**
     * @param  list<array<string, mixed>>  $groups  income-type groups from IncomeCalculator
     * @return array{items: list<array<string, mixed>>, total: Money, warnings: list<array<string, string>>}
     */
    public function calculate(array $groups, TaxRuleVersion $version): array
    {
        $resolver = $this->resolver ??= new ExpenseRuleResolver;
        $items = $warnings = [];
        $total = new Money;
        $buckets = [];

        foreach ($groups as $index => $group) {
            $rule = $resolver->resolve($version, $group['income_type'], $group['income_subtype'] ?? null,
                $group['expense_activity'] ?? null, $group['holding_years'] ?? null);
            $key = $rule?->expense_group ?? ('#'.$index);
            $buckets[$key] ??= ['rules' => [], 'groups' => [], 'index' => $index];
            $buckets[$key]['groups'][] = $group;
            if ($rule !== null) {
                $buckets[$key]['rules'][] = $rule;
            }
        }

        foreach ($buckets as $bucket) {
            $members = $bucket['groups'];
            $rule = $bucket['rules'][0] ?? null;
            $basis = array_reduce($members, fn (Money $carry, array $group): Money => $carry->add($group['gross_after_exemption']), new Money);
            $actual = $this->declaredActual($members);
            $selection = $this->election($members);

            if ($rule === null) {
                // Fail closed. The request layer rejects positive amounts for an unverified
                // income type with 422, so only a zero-value group can reach this branch.
                if ($basis->compare(new Money) > 0) {
                    throw new TaxMetadataConflictException('No verified expense rule exists for '.$members[0]['income_type'].'.');
                }
                $items[] = [...$this->item($members, $basis, null, $actual, $selection, new Money, 'UNVERIFIED'), 'tiers' => []];
                $warnings[] = ['code' => 'UNVERIFIED_EXPENSE_RULE',
                    'message' => 'No verified expense rule exists for this income category; no expense was deducted.',
                    'path' => 'expenses.items.'.(count($items) - 1)];

                continue;
            }
            if (count($bucket['rules']) !== count($members)) {
                throw new TaxMetadataConflictException('An expense group must cover every income category it contains.');
            }
            $resolver->assertGroupAgreement($bucket['rules']);
            if ($actual !== null && ! $resolver->allowsActualExpense($rule)) {
                throw new \InvalidArgumentException('Declared actual expense is not supported for '.$members[0]['income_type'].'.');
            }
            if ($resolver->requiresSelection($rule) && $selection === null) {
                throw new \InvalidArgumentException('An expense method election is required for '.$members[0]['income_type'].'.');
            }

            $tiers = $rule->method === 'tiered_or_actual' && $selection !== 'actual' ? $this->tiers($rule, $basis) : [];
            $eligible = $this->eligible($rule, $basis, $actual, $selection, $tiers);
            $total = $total->add($eligible);
            $items[] = [...$this->item($members, $basis, $rule, $actual, $selection, $eligible, 'VERIFIED'), 'tiers' => $tiers];
        }

        return ['items' => $items, 'total' => $total, 'warnings' => $warnings];
    }

    /**
     * Percentage-with-cap mechanics, retained as the M4 entry point used by unit tests.
     *
     * @return array{items: list<array<string, mixed>>, total: Money}
     */
    public function apply(Money $income, string $percentage, Money $maximum, string $code): array
    {
        $total = $this->percentageLimit($income, $percentage, $maximum);

        return ['items' => [['code' => $code, 'basis' => $income, 'percentage' => $percentage,
            'maximum_amount' => $maximum, 'eligible_amount' => $total]], 'total' => $total];
    }

    /**
     * Splits the basis across the printed bands of a tiered rate.
     *
     * Each band deducts its own percentage of the slice of income that falls inside it, so the
     * boundary is preserved rather than flattened into one blended rate.
     *
     * @return list<array<string, mixed>>
     */
    private function tiers(ExpenseRule $rule, Money $basis): array
    {
        $tiers = [];
        $consumed = new Money;
        foreach ($rule->tiers as $tier) {
            $upper = $tier->threshold_amount === null ? $basis : (new Money($tier->threshold_amount))->min($basis);
            $slice = $upper->subtract($consumed)->max(new Money);
            $consumed = $consumed->add($slice);
            $tiers[] = ['tier_code' => $tier->tier_code, 'sort_order' => (int) $tier->sort_order,
                'threshold_amount' => $tier->threshold_amount === null ? null : new Money($tier->threshold_amount),
                'percentage' => $tier->percentage, 'taxable_slice' => $slice,
                'eligible_amount' => $slice->percentage($tier->percentage)];
        }

        return $tiers;
    }

    /** @param list<array<string, mixed>> $tiers */
    private function eligible(ExpenseRule $rule, Money $basis, ?Money $actual, ?string $selection, array $tiers = []): Money
    {
        return match ($rule->method) {
            // ตารางที่ 2 row (1): the bands are summed, then the printed combined ceiling applies.
            'tiered_or_actual' => $selection === 'actual'
                ? ($actual ?? new Money)->min($basis)
                : array_reduce($tiers, fn (Money $carry, array $tier): Money => $carry->add($tier['eligible_amount']), new Money)
                    ->min(new Money($rule->maximum_amount))->min($basis),
            'fixed' => (new Money($rule->fixed_amount))->min($basis),
            'percentage' => $basis->percentage($rule->percentage),
            'percentage_limit' => $this->percentageLimit($basis, $rule->percentage, new Money($rule->maximum_amount)),
            // A declared actual expense may never exceed the income it is claimed against.
            'actual' => ($actual ?? new Money)->min($basis),
            // ภ.ง.ด.90 prints two checkboxes; the taxpayer's election decides, never the engine.
            'percentage_or_actual' => $selection === 'actual'
                ? ($actual ?? new Money)->min($basis)
                : ($rule->maximum_amount === null
                    ? $basis->percentage($rule->percentage)
                    : $this->percentageLimit($basis, $rule->percentage, new Money($rule->maximum_amount))),
            default => throw new TaxMetadataConflictException('Expense rule method "'.$rule->method.'" is not applicable.'),
        };
    }

    private function percentageLimit(Money $basis, string $percentage, Money $maximum): Money
    {
        return $basis->percentage($percentage)->min($maximum)->min($basis);
    }

    /** @param list<array<string, mixed>> $members */
    private function declaredActual(array $members): ?Money
    {
        $total = null;
        foreach ($members as $group) {
            if ($group['actual_expense'] !== null) {
                $total = ($total ?? new Money)->add($group['actual_expense']);
            }
        }

        return $total;
    }

    /** @param list<array<string, mixed>> $members */
    private function election(array $members): ?string
    {
        foreach ($members as $group) {
            if (($group['expense_method_selection'] ?? null) !== null) {
                return $group['expense_method_selection'];
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $members
     * @return array<string, mixed>
     */
    private function item(array $members, Money $basis, ?ExpenseRule $rule, ?Money $actual, ?string $selection, Money $eligible, string $status): array
    {
        $incomeTypes = array_values(array_unique(array_column($members, 'income_type')));

        return [
            'code' => $rule?->code,
            // Single-category groups keep the M7 field; a shared group reports null here and
            // lists its members in income_types.
            'income_type' => count($incomeTypes) === 1 ? $incomeTypes[0] : null,
            'income_types' => $incomeTypes,
            'income_subtype' => count($members) === 1 ? ($members[0]['income_subtype'] ?? null) : null,
            'expense_activity' => count($members) === 1 ? ($members[0]['expense_activity'] ?? null) : null,
            'holding_years' => count($members) === 1 ? ($members[0]['holding_years'] ?? null) : null,
            'expense_group' => $rule?->expense_group,
            'basis' => $basis,
            'gross_after_exemption' => $basis,
            'method' => $rule?->method,
            'expense_method_selection' => $selection,
            'percentage' => $rule?->percentage,
            'maximum_amount' => $rule?->maximum_amount === null ? null : new Money($rule->maximum_amount),
            'fixed_amount' => $rule?->fixed_amount === null ? null : new Money($rule->fixed_amount),
            'input_actual_expense' => $actual,
            'eligible_amount' => $eligible,
            'rule_status' => $status,
        ];
    }
}

<?php

namespace App\Services\Tax;

use App\ValueObjects\Money;

/**
 * Aggregates declared income lines into one group per income type and, where ภ.ง.ด.90 prints
 * subcategories with their own expense treatment, per subtype.
 *
 * An expense rule applies to a group's aggregate, not to each line, so grouping happens here.
 * This class holds no expense logic and no knowledge of which rules exist.
 */
class IncomeCalculator
{
    /**
     * @param  list<array{income_type?: string, income_subtype?: ?string, gross_amount: string|int, exempt_amount?: string|int, actual_expense?: string|int|null, expense_method_selection?: ?string, description?: ?string}>  $incomes
     * @return array{items: list<array<string, mixed>>, gross_income: Money, exempt_income: Money, gross_after_exemption: Money}
     */
    public function calculate(array $incomes): array
    {
        $gross = $exempt = new Money;
        $groups = [];

        foreach ($incomes as $index => $income) {
            $grossAmount = new Money($income['gross_amount']);
            $exemptAmount = new Money($income['exempt_amount'] ?? '0');
            if ($grossAmount->compare(new Money) < 0 || $exemptAmount->compare(new Money) < 0 || $exemptAmount->compare($grossAmount) > 0) {
                throw new \InvalidArgumentException('Income and exemptions must be nonnegative; exemptions cannot exceed gross income.');
            }
            $type = $income['income_type'] ?? null;
            if (! is_string($type) || $type === '') {
                throw new \InvalidArgumentException('Every income line requires an income type.');
            }
            $subtype = ($income['income_subtype'] ?? null) === '' ? null : ($income['income_subtype'] ?? null);
            $selection = ($income['expense_method_selection'] ?? null) === '' ? null : ($income['expense_method_selection'] ?? null);
            // ภ.ง.ด.90 ข้อ 7 items 1 and 3 (2) print a further fact beside the category, and it
            // changes the expense rate, so it groups income the same way a subtype does.
            $activity = ($income['expense_activity'] ?? null) === '' ? null : ($income['expense_activity'] ?? null);
            $holdingYears = ($income['holding_years'] ?? null) === null ? null : (int) $income['holding_years'];
            $actual = ($income['actual_expense'] ?? null) === null ? null : new Money($income['actual_expense']);
            if ($actual !== null && $actual->compare(new Money) < 0) {
                throw new \InvalidArgumentException('Declared actual expense must be nonnegative.');
            }

            $gross = $gross->add($grossAmount);
            $exempt = $exempt->add($exemptAmount);
            $key = implode('|', [$type, $subtype ?? '', $activity ?? '', $holdingYears ?? '']);
            $groups[$key] ??= ['income_type' => $type, 'income_subtype' => $subtype,
                'expense_activity' => $activity, 'holding_years' => $holdingYears,
                'expense_method_selection' => $selection, 'gross_income' => new Money,
                'exempt_income' => new Money, 'gross_after_exemption' => new Money,
                'actual_expense' => null, 'lines' => []];
            $group = $groups[$key];
            if ($selection !== null && $group['expense_method_selection'] !== null && $group['expense_method_selection'] !== $selection) {
                throw new \InvalidArgumentException('Income lines of one category must elect the same expense method.');
            }
            $group['expense_method_selection'] ??= $selection;
            $group['gross_income'] = $group['gross_income']->add($grossAmount);
            $group['exempt_income'] = $group['exempt_income']->add($exemptAmount);
            $group['gross_after_exemption'] = $group['gross_income']->subtract($group['exempt_income']);
            if ($actual !== null) {
                $group['actual_expense'] = ($group['actual_expense'] ?? new Money)->add($actual);
            }
            $group['lines'][] = ['index' => $index, 'description' => $income['description'] ?? null,
                'gross_amount' => $grossAmount, 'exempt_amount' => $exemptAmount, 'actual_expense' => $actual];
            $groups[$key] = $group;
        }

        // Groups keep first-appearance order so the response is deterministic.
        return ['items' => array_values($groups), 'gross_income' => $gross,
            'exempt_income' => $exempt, 'gross_after_exemption' => $gross->subtract($exempt)];
    }
}

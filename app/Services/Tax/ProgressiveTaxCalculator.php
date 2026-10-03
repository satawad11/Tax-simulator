<?php

namespace App\Services\Tax;

use App\Exceptions\TaxMetadataConflictException;
use App\ValueObjects\Money;

class ProgressiveTaxCalculator
{
    /**
     * @param  iterable<object{sort_order: int, min_amount: string, max_amount: ?string, rate: string}>  $brackets
     * @return array{brackets: list<array{sort_order: int, min_amount: Money, max_amount: ?Money, rate: string, taxable_amount: Money, tax: Money}>, total: Money, marginal_rate: string}
     */
    public function calculate(Money $net, iterable $brackets): array
    {
        $rows = [];
        $total = $expected = new Money;
        $open = false;
        $rate = '0.0000';
        $position = 0;
        foreach ($brackets as $bracket) {
            $min = new Money($bracket->min_amount);
            $max = $bracket->max_amount === null ? null : new Money($bracket->max_amount);
            if ($open || $min->compare($expected) !== 0 || ($max !== null && $max->compare($min) <= 0) ||
                $bracket->sort_order <= $position || (new Money($bracket->rate))->compare(new Money) < 0 ||
                (new Money($bracket->rate))->compare(new Money(100)) > 0) {
                throw new TaxMetadataConflictException('Tax brackets contain gaps, overlaps or invalid values.');
            }
            $position = $bracket->sort_order;
            $taxable = ($max === null ? $net : $net->min($max))->subtract($min)->max(new Money);
            $tax = $taxable->percentage($bracket->rate);
            $total = $total->add($tax);
            if ($taxable->compare(new Money) > 0) {
                $rate = $bracket->rate;
            }
            $rows[] = ['sort_order' => $position, 'min_amount' => $min, 'max_amount' => $max,
                'rate' => $bracket->rate, 'taxable_amount' => $taxable, 'tax' => $tax];
            $open = $max === null;
            $expected = $max ?? $min;
        }
        if (! $open) {
            throw new TaxMetadataConflictException('Tax brackets require a final open-ended bracket.');
        }

        return ['brackets' => $rows, 'total' => $total, 'marginal_rate' => $rate];
    }
}

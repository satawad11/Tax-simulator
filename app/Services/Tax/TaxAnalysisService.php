<?php

namespace App\Services\Tax;

use App\ValueObjects\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class TaxAnalysisService
{
    /** @param array{status: string, amount: Money} $result */
    public function analyze(Money $gross, Money $net, Money $tax, string $marginal, Money $withholding, array $result): array
    {
        // Display-only percentage rounding; this is never used in monetary calculations.
        $effective = $gross->compare(new Money) === 0 ? '0.000000' :
            (string) BigDecimal::of((string) $tax)->multipliedBy(100)->dividedBy((string) $gross, 6, RoundingMode::HalfUp);

        return ['gross_income' => $gross, 'net_income' => $net, 'calculated_tax' => $tax,
            'effective_tax_rate' => $effective, 'marginal_tax_rate' => $marginal,
            'withholding_total' => $withholding, 'result_status' => $result['status'], 'result_amount' => $result['amount']];
    }
}

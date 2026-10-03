<?php

namespace Tests\Unit;

use App\Services\Tax\ExpenseCalculator;
use App\Services\Tax\IncomeCalculator;
use App\ValueObjects\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ExpenseCalculatorTest extends TestCase
{
    public function test_invalid_exemption_cannot_reach_expense_calculation_outside_http(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new IncomeCalculator)->calculate([['gross_amount' => '100', 'exempt_amount' => '101']]);
    }

    public static function boundaries(): array
    {
        return [['0', '0.00'], ['100000', '50000.00'], ['200000', '100000.00'], ['200000.01', '100000.00'], ['720000', '100000.00']];
    }

    #[DataProvider('boundaries')]
    public function test_percentage_and_aggregate_cap(string $income, string $expected): void
    {
        $result = (new ExpenseCalculator)->apply(new Money($income), '50.0000', new Money('100000'), 'fixture');
        $this->assertSame($expected, (string) $result['total']);
    }

    public function test_mechanics_use_supplied_rule_values_and_keep_fractional_satang(): void
    {
        $result = (new ExpenseCalculator)->apply(new Money('0.01'), '7.5000', new Money('99'), 'fixture');
        $this->assertSame('0.00075', (string) $result['total']);
    }
}

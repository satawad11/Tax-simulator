<?php

namespace Tests\Unit;

use App\Exceptions\TaxMetadataConflictException;
use App\Services\Tax\ProgressiveTaxCalculator;
use App\ValueObjects\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProgressiveTaxCalculatorTest extends TestCase
{
    public static function boundaries(): array
    {
        return [
            ['0', '0.00', '0.0000'], ['150000', '0.00', '0.0000'], ['150000.01', '0.0005', '5.0000'],
            ['300000', '7500.00', '5.0000'], ['300000.01', '7500.001', '10.0000'],
            ['500000', '27500.00', '10.0000'], ['500000.01', '27500.0015', '15.0000'],
            ['750000', '65000.00', '15.0000'], ['750000.01', '65000.002', '20.0000'],
            ['1000000', '115000.00', '20.0000'], ['1000000.01', '115000.0025', '25.0000'],
            ['2000000', '365000.00', '25.0000'], ['2000000.01', '365000.003', '30.0000'],
            ['5000000', '1265000.00', '30.0000'], ['5000000.01', '1265000.0035', '35.0000'],
        ];
    }

    private function brackets(): array
    {
        $edges = ['0', '150000', '300000', '500000', '750000', '1000000', '2000000', '5000000', null];
        $rates = ['0.0000', '5.0000', '10.0000', '15.0000', '20.0000', '25.0000', '30.0000', '35.0000'];
        $rows = [];
        foreach ($rates as $i => $rate) {
            $rows[] = (object) ['sort_order' => $i + 1, 'min_amount' => $edges[$i], 'max_amount' => $edges[$i + 1], 'rate' => $rate];
        }

        return $rows;
    }

    #[DataProvider('boundaries')]
    public function test_exact_boundaries(string $net, string $expected, string $rate): void
    {
        $result = (new ProgressiveTaxCalculator)->calculate(new Money($net), $this->brackets());
        $this->assertSame($expected, (string) $result['total']);
        $this->assertSame($rate, $result['marginal_rate']);
        $sum = new Money;
        foreach ($result['brackets'] as $bracket) {
            $sum = $sum->add($bracket['taxable_amount']);
        }
        $this->assertSame((string) new Money($net), (string) $sum, 'Every baht is covered exactly once.');
    }

    public static function invalidBrackets(): array
    {
        return [['gap'], ['overlap'], ['missing_end'], ['after_open'], ['negative_rate'], ['empty']];
    }

    #[DataProvider('invalidBrackets')]
    public function test_invalid_brackets_fail_closed(string $case): void
    {
        $rows = $this->brackets();
        if ($case === 'gap') {
            $rows[1]->min_amount = '150001';
        }
        if ($case === 'overlap') {
            $rows[1]->min_amount = '149999';
        }
        if ($case === 'missing_end') {
            array_pop($rows);
        }
        if ($case === 'after_open') {
            $rows[] = clone $rows[7];
        }
        if ($case === 'negative_rate') {
            $rows[1]->rate = '-1';
        }
        if ($case === 'empty') {
            $rows = [];
        }
        $this->expectException(TaxMetadataConflictException::class);
        (new ProgressiveTaxCalculator)->calculate(new Money('200000'), $rows);
    }

    public function test_rates_are_supplied_by_rules(): void
    {
        $result = (new ProgressiveTaxCalculator)->calculate(new Money('100'), [
            (object) ['sort_order' => 1, 'min_amount' => '0', 'max_amount' => null, 'rate' => '7.2500'],
        ]);
        $this->assertSame('7.25', (string) $result['total']);
    }
}

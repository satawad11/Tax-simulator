<?php

namespace Tests\Unit;

use App\Services\Tax\MinimumTaxCalculator;
use App\ValueObjects\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * ภ.ง.ด.90 "คำนวณภาษีจาก 2 วิธี (แล้วให้ชำระภาษีจากยอดที่มากกว่า)".
 *
 * Source: docs/tax-source/PND90-2568-filing-instructions.pdf, page 6.
 */
class MinimumTaxCalculatorTest extends TestCase
{
    /** @param list<array{string, string}> $groups income_type => gross */
    private function items(array $groups): array
    {
        return array_map(fn (array $group): array => ['income_type' => $group[0], 'gross_income' => new Money($group[1])], $groups);
    }

    /** @return array<string, array{list<array{string, string}>, string, bool, string, string, string}> */
    public static function cases(): array
    {
        return [
            'no income at all' => [[], '0', false, '0.00', '0.00', '0.00'],
            'below the 120,000 threshold' => [[['SECTION_40_8', '119999.99']], '0', false, '119999.99', '0.00', '0.00'],
            'exactly at the threshold, below the 5,000 floor' => [
                [['SECTION_40_8', '120000']], '0', false, '120000.00', '600.00', '0.00'],
            'exactly at the 5,000 floor is still disregarded' => [
                [['SECTION_40_8', '1000000']], '0', false, '1000000.00', '5000.00', '0.00'],
            'one satang past the floor applies' => [
                [['SECTION_40_8', '1000000.02']], '0', true, '1000000.02', '5000.0001', '5000.0001'],
            'the greater of the two is paid' => [
                [['SECTION_40_8', '2000000']], '3000', true, '2000000.00', '10000.00', '10000.00'],
            'the progressive tax wins when it is larger' => [
                [['SECTION_40_8', '2000000']], '400000', true, '2000000.00', '10000.00', '400000.00'],
            'มาตรา 40 (1) is excluded from the base' => [
                [['SECTION_40_1', '5000000'], ['SECTION_40_8', '2000000']], '0', true, '2000000.00', '10000.00', '10000.00'],
            'salary alone never reaches the second method' => [
                [['SECTION_40_1', '5000000']], '900000', false, '0.00', '0.00', '900000.00'],
            'every non-40(1) category joins the base' => [
                [['SECTION_40_2', '400000'], ['SECTION_40_3', '400000'], ['SECTION_40_4', '400000'],
                    ['SECTION_40_5', '400000'], ['SECTION_40_6', '400000'], ['SECTION_40_7', '400000'],
                    ['SECTION_40_8', '400000']], '0', true, '2800000.00', '14000.00', '14000.00'],
        ];
    }

    #[DataProvider('cases')]
    public function test_pnd90(array $groups, string $progressive, bool $applicable, string $base, string $tax, string $payable): void
    {
        $result = (new MinimumTaxCalculator)->calculate('PND90', $this->items($groups), new Money($progressive));

        $this->assertSame($applicable, $result['applicable']);
        $this->assertSame($base, (string) $result['base']);
        $this->assertSame($tax, (string) $result['tax']);
        $this->assertSame($payable, (string) $result['payable']);
    }

    public function test_pnd91_never_computes_a_base_or_a_second_method(): void
    {
        $result = (new MinimumTaxCalculator)->calculate('PND91',
            $this->items([['SECTION_40_1', '5000000']]), new Money('900000'));

        $this->assertFalse($result['applicable']);
        $this->assertSame('0.00', (string) $result['base']);
        $this->assertSame('0.00', (string) $result['tax']);
        $this->assertSame('900000.00', (string) $result['payable']);
        $this->assertSame('PROGRESSIVE', $result['method']);
    }

    public function test_the_method_names_which_calculation_was_paid(): void
    {
        $calculator = new MinimumTaxCalculator;
        $items = $this->items([['SECTION_40_8', '2000000']]);

        $this->assertSame('MINIMUM_TAX', $calculator->calculate('PND90', $items, new Money('9999.99'))['method']);
        $this->assertSame('PROGRESSIVE', $calculator->calculate('PND90', $items, new Money('10000'))['method']);
    }
}

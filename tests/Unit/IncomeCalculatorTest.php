<?php

namespace Tests\Unit;

use App\Services\Tax\IncomeCalculator;
use PHPUnit\Framework\TestCase;

class IncomeCalculatorTest extends TestCase
{
    private function line(string $type, string $gross, string $exempt = '0', ?string $actual = null): array
    {
        return ['income_type' => $type, 'gross_amount' => $gross, 'exempt_amount' => $exempt,
            ...($actual === null ? [] : ['actual_expense' => $actual])];
    }

    public function test_it_groups_rows_of_the_same_income_type_into_one_aggregate(): void
    {
        $result = (new IncomeCalculator)->calculate([
            $this->line('SECTION_40_1', '400000', '40000'),
            $this->line('SECTION_40_1', '400000', '40000'),
        ]);

        $this->assertCount(1, $result['items']);
        $this->assertSame('SECTION_40_1', $result['items'][0]['income_type']);
        $this->assertSame('800000.00', (string) $result['items'][0]['gross_income']);
        $this->assertSame('80000.00', (string) $result['items'][0]['exempt_income']);
        $this->assertSame('720000.00', (string) $result['items'][0]['gross_after_exemption']);
        $this->assertSame('800000.00', (string) $result['gross_income']);
        $this->assertSame('720000.00', (string) $result['gross_after_exemption']);
    }

    public function test_it_keeps_one_group_per_income_type_in_first_appearance_order(): void
    {
        $result = (new IncomeCalculator)->calculate([
            $this->line('SECTION_40_8', '200000'),
            $this->line('SECTION_40_1', '600000'),
            $this->line('SECTION_40_8', '100000'),
        ]);

        $this->assertSame(['SECTION_40_8', 'SECTION_40_1'], array_column($result['items'], 'income_type'));
        $this->assertSame('300000.00', (string) $result['items'][0]['gross_after_exemption']);
        $this->assertSame('600000.00', (string) $result['items'][1]['gross_after_exemption']);
        $this->assertSame('900000.00', (string) $result['gross_income']);
    }

    public function test_it_preserves_the_source_line_breakdown(): void
    {
        $result = (new IncomeCalculator)->calculate([
            $this->line('SECTION_40_1', '600000'),
            ['income_type' => 'SECTION_40_8', 'gross_amount' => '200000', 'description' => 'Synthetic trade'],
        ]);

        $this->assertSame([0], array_column($result['items'][0]['lines'], 'index'));
        $this->assertSame([1], array_column($result['items'][1]['lines'], 'index'));
        $this->assertSame('Synthetic trade', $result['items'][1]['lines'][0]['description']);
    }

    public function test_it_sums_declared_actual_expense_per_income_type(): void
    {
        $result = (new IncomeCalculator)->calculate([
            $this->line('SECTION_40_5', '100000', '0', '30000'),
            $this->line('SECTION_40_5', '100000', '0', '20000'),
            $this->line('SECTION_40_1', '600000'),
        ]);

        $this->assertSame('50000.00', (string) $result['items'][0]['actual_expense']);
        $this->assertNull($result['items'][1]['actual_expense']);
    }

    public function test_it_rejects_a_line_without_an_income_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new IncomeCalculator)->calculate([['gross_amount' => '100']]);
    }

    public function test_it_rejects_a_negative_actual_expense(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new IncomeCalculator)->calculate([$this->line('SECTION_40_5', '100', '0', '-1')]);
    }

    public function test_it_keeps_fractional_satang_while_aggregating(): void
    {
        $result = (new IncomeCalculator)->calculate([
            $this->line('SECTION_40_1', '0.01'),
            $this->line('SECTION_40_1', '0.02'),
        ]);

        $this->assertSame('0.03', (string) $result['gross_after_exemption']);
    }
}

<?php

namespace Tests\Unit;

use App\Services\Tax\TaxAnalysisService;
use App\ValueObjects\Money;
use PHPUnit\Framework\TestCase;

class TaxAnalysisServiceTest extends TestCase
{
    public function test_zero_income_does_not_divide_by_zero(): void
    {
        $result = (new TaxAnalysisService)->analyze(new Money, new Money, new Money, '0.0000', new Money, ['status' => 'ZERO', 'amount' => new Money]);
        $this->assertSame('0.000000', $result['effective_tax_rate']);
    }

    public function test_effective_rate_uses_gross_and_only_display_rate_is_rounded(): void
    {
        $result = (new TaxAnalysisService)->analyze(new Money('3'), new Money('2'), new Money('1'), '35.0000', new Money('0.25'), ['status' => 'PAYABLE', 'amount' => new Money('0.75')]);
        $this->assertSame('33.333333', $result['effective_tax_rate']);
        $this->assertSame('35.0000', $result['marginal_tax_rate']);
        $this->assertSame('1.00', (string) $result['calculated_tax']);
        $this->assertSame('0.75', (string) $result['result_amount']);
    }
}

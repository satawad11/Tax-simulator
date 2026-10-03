<?php

namespace Tests\Unit;

use App\Services\Tax\TaxCreditCalculator;
use App\Services\Tax\TaxRefundService;
use App\ValueObjects\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TaxRefundServiceTest extends TestCase
{
    public static function cases(): array
    {
        return [['25', 'PAYABLE', '75.00'], ['100', 'ZERO', '0.00'], ['125', 'REFUND', '25.00']];
    }

    #[DataProvider('cases')]
    public function test_result_direction_and_nonnegative_amount(string $paid, string $status, string $amount): void
    {
        $credits = (new TaxCreditCalculator)->calculate([['type' => 'withholding', 'amount' => $paid]])['totals'];
        $result = (new TaxRefundService)->calculate(new Money('100'), $credits);
        $this->assertSame($status, $result['status']);
        $this->assertSame($amount, (string) $result['amount']);
        $this->assertSame('SIMULATED_'.$status, $result['reason_code']);
    }

    public function test_negative_credit_is_rejected_even_outside_http(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new TaxCreditCalculator)->calculate([['type' => 'withholding', 'amount' => '-1']]);
    }
}

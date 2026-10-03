<?php

namespace Tests\Unit;

use App\Services\Tax\TaxRefundGuidanceService;
use PHPUnit\Framework\TestCase;

class TaxRefundGuidanceServiceTest extends TestCase
{
    private function outcome(string $status, string $amount, string $withholding, ?string $credits = null): array
    {
        return [
            'progressive_tax' => ['total' => '45500.00'],
            'credits' => ['withholding' => $withholding, 'total' => $credits ?? $withholding],
            'result' => ['status' => $status, 'amount' => $amount],
        ];
    }

    public function test_it_returns_refund_guidance_from_the_calculation_result(): void
    {
        $guidance = (new TaxRefundGuidanceService)->guide($this->outcome('REFUND', '4500.00', '50000.00'))['refund_guidance'];

        $this->assertSame('4500.00', (string) $guidance['estimated_refund']);
        $this->assertSame('CREDITS_EXCEED_CALCULATED_TAX', $guidance['reason_code']);
        $this->assertSame('45500.00', (string) $guidance['components']['calculated_tax']);
        $this->assertSame('50000.00', (string) $guidance['components']['withholding']);
        $this->assertSame('0.00', (string) $guidance['components']['other_credits']);
        $this->assertSame(['VERIFY_WITHHOLDING', 'VERIFY_SUPPORTING_DOCUMENTS', 'VERIFY_REFUND_CHANNEL'],
            array_column($guidance['checklist'], 'code'));
    }

    public function test_refund_guidance_states_an_estimate_and_never_certainty(): void
    {
        $guidance = (new TaxRefundGuidanceService)->guide($this->outcome('REFUND', '4500.00', '50000.00'))['refund_guidance'];

        $this->assertStringContainsString('ประมาณการ', $guidance['disclaimer']);
        foreach (['ได้รับคืนแน่นอน', 'แน่นอน', 'รับรอง'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $guidance['reason']);
        }
        $this->assertStringNotContainsString('ได้รับคืนแน่นอน', $guidance['disclaimer']);
    }

    public function test_payable_returns_payment_guidance_and_no_refund_guidance(): void
    {
        $guidance = (new TaxRefundGuidanceService)->guide($this->outcome('PAYABLE', '20500.00', '25000.00'));

        $this->assertNull($guidance['refund_guidance']);
        $this->assertSame('20500.00', (string) $guidance['payment_guidance']['amount']);
        $this->assertSame('WITHHOLDING_BELOW_CALCULATED_TAX', $guidance['payment_guidance']['reason_code']);
    }

    public function test_zero_creates_no_false_refund_or_payment(): void
    {
        $guidance = (new TaxRefundGuidanceService)->guide($this->outcome('ZERO', '0.00', '45500.00'));

        $this->assertNull($guidance['refund_guidance']);
        $this->assertNull($guidance['payment_guidance']);
    }

    public function test_other_credits_are_reported_separately_from_withholding(): void
    {
        $guidance = (new TaxRefundGuidanceService)->guide($this->outcome('REFUND', '4500.00', '50000.00', '50250.00'));

        $this->assertSame('250.00', (string) $guidance['refund_guidance']['components']['other_credits']);
    }
}

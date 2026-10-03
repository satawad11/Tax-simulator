<?php

namespace App\Services\Tax;

use App\ValueObjects\Money;

/**
 * Explains an existing simulated result. It never decides legal refund entitlement and
 * never adds, removes or re-derives any tax amount produced by TaxCalculationService.
 */
class TaxRefundGuidanceService
{
    public const DISCLAIMER = 'จำนวนเงินเป็นเพียงประมาณการจากข้อมูลในระบบทดลอง ไม่ใช่การรับรองสิทธิหรือการยืนยันการคืนภาษีจริง';

    /**
     * @param  array<string, mixed>  $result  calculation output (live engine array or stored snapshot)
     * @return array{refund_guidance: ?array<string, mixed>, payment_guidance: ?array<string, mixed>}
     */
    public function guide(array $result): array
    {
        $status = (string) ($result['result']['status'] ?? '');
        $amount = $this->amount($result['result']['amount'] ?? '0');
        $components = [
            'calculated_tax' => $this->amount($result['progressive_tax']['total'] ?? '0'),
            'withholding' => $this->amount($result['credits']['withholding'] ?? '0'),
            'other_credits' => $this->amount($result['credits']['total'] ?? '0')
                ->subtract($this->amount($result['credits']['withholding'] ?? '0')),
        ];

        return match ($status) {
            'REFUND' => [
                'refund_guidance' => [
                    'estimated_refund' => $amount,
                    'reason_code' => 'CREDITS_EXCEED_CALCULATED_TAX',
                    'reason' => 'ภาษีที่ถูกหักหรือเครดิตที่กรอกสูงกว่าภาษีที่คำนวณได้ในระบบจำลอง',
                    'components' => $components,
                    'checklist' => [
                        ['code' => 'VERIFY_WITHHOLDING', 'label' => 'ตรวจสอบยอดภาษีหัก ณ ที่จ่าย'],
                        ['code' => 'VERIFY_SUPPORTING_DOCUMENTS', 'label' => 'ตรวจสอบเอกสารประกอบข้อมูลที่กรอก'],
                        ['code' => 'VERIFY_REFUND_CHANNEL', 'label' => 'ตรวจสอบช่องทางรับเงินคืนสำหรับการยื่นจริง'],
                    ],
                    'disclaimer' => self::DISCLAIMER,
                ],
                'payment_guidance' => null,
            ],
            'PAYABLE' => [
                'refund_guidance' => null,
                'payment_guidance' => [
                    'amount' => $amount,
                    'reason_code' => 'WITHHOLDING_BELOW_CALCULATED_TAX',
                    'reason' => 'ภาษีหัก ณ ที่จ่ายที่กรอกต่ำกว่าภาษีที่คำนวณได้ในระบบจำลอง',
                    'components' => $components,
                    'checklist' => [
                        ['code' => 'VERIFY_WITHHOLDING', 'label' => 'ตรวจสอบยอดภาษีหัก ณ ที่จ่าย'],
                        ['code' => 'VERIFY_SUPPORTING_DOCUMENTS', 'label' => 'ตรวจสอบเอกสารประกอบข้อมูลที่กรอก'],
                    ],
                    'disclaimer' => self::DISCLAIMER,
                ],
            ],
            default => ['refund_guidance' => null, 'payment_guidance' => null],
        };
    }

    private function amount(mixed $value): Money
    {
        return $value instanceof Money ? $value : new Money((string) $value);
    }
}

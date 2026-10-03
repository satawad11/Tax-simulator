<?php

namespace App\Services\Tax;

use App\ValueObjects\Money;

/**
 * Turns the tax and the credits into the simulated balance ภ.ง.ด.90 ข้อ 11 reports.
 *
 *   item 13–14  less foreign tax credit
 *   item 15     less ภาษีหัก ณ ที่จ่าย / ภ.ง.ด.93 / ภ.ง.ด.94
 *   item 16     คงเหลือ ภาษีที่ ชำระเพิ่มเติม / ชำระไว้เกิน
 *   item 19     บวก ภาษีที่ชำระเพิ่มเติม (ยกมาจาก ข้อ 9)
 *
 * The separately taxed ข้อ 9 amount is therefore added after the credits, not before them.
 */
class TaxRefundService
{
    /**
     * @param  array{withholding: Money, foreign_tax_credit: Money, pnd93: Money, pnd94: Money, other_credit: Money}  $credits
     * @return array{status: string, amount: Money, reason_code: string, components: array<string, Money>}
     */
    public function calculate(Money $tax, array $credits, ?Money $separateTax = null): array
    {
        $separateTax ??= new Money;
        $afterForeign = $tax->subtract($credits['foreign_tax_credit'])->max(new Money);
        $prepaid = array_reduce(TaxCreditCalculator::PREPAID_TYPES,
            fn (Money $carry, string $type): Money => $carry->add($credits[$type] ?? new Money), new Money);
        $balance = $afterForeign->subtract($prepaid)->add($separateTax);
        $direction = $balance->compare(new Money);
        $status = $direction > 0 ? 'PAYABLE' : ($direction < 0 ? 'REFUND' : 'ZERO');

        return ['status' => $status, 'amount' => $balance->absolute(), 'reason_code' => 'SIMULATED_'.$status,
            'components' => ['calculated_tax' => $tax, 'foreign_tax_credit' => $credits['foreign_tax_credit'],
                'tax_after_foreign_credit' => $afterForeign, 'withholding_and_prepaid' => $prepaid,
                'separate_tax' => $separateTax]];
    }
}

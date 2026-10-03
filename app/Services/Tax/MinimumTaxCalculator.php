<?php

namespace App\Services\Tax;

use App\ValueObjects\Money;

/**
 * ภ.ง.ด.90's second calculation method — the 0.5% minimum tax.
 *
 * docs/tax-source/PND90-2568-filing-instructions.pdf, page 6, "คำนวณภาษีจาก 2 วิธี
 * (แล้วให้ชำระภาษีจากยอดที่มากกว่า)", verbatim:
 *
 *   1. ภาษีที่คำนวณจากเงินได้สุทธิ ให้คำนวณตามอัตราภาษีเงินได้บุคคลธรรมดา
 *      (ให้ดูอัตราภาษีในตารางที่ 1 หน้า 17)
 *   2. ภาษีที่คำนวณจากเงินได้พึงประเมิน หากเงินได้พึงประเมินมีจำนวนตั้งแต่ 120,000 บาทขึ้นไป
 *      ให้นำผลลัพธ์ที่ได้จากการนำยอดรวมเงินได้พึงประเมินตาม ข้อ 1 ถึง ข้อ 7 1. ถึง 4.
 *      (ไม่รวมเงินได้พึงประเมินมาตรา 40 (1)) คูณด้วย 0.005 เว้นแต่คำนวณแล้วไม่เกิน 5,000 บาท
 *      ให้ชำระภาษีจากวิธีที่ 1.
 *
 * The base wording is what earlier reconciliations could not resolve. It is settled by the
 * booklet's own citation style, which writes a form box and then the lines inside it —
 * "ข้อ 11 23." and "ข้อ 11 24." on the same page mean ข้อ 11 lines 23 and 24. So "ข้อ 7 1. ถึง 4."
 * means ข้อ 7 lines 1 through 4, and page 3 shows ข้อ 7 has exactly four lines. The range is
 * therefore the whole of ข้อ 1 to ข้อ 7 — มาตรา 40 (1) through 40 (8) — less มาตรา 40 (1).
 *
 * ข้อ 8, ข้อ 9 and ข้อ 10 are outside the range, which matches the engine already routing
 * ข้อ 9 elections through SeparateTaxCalculator rather than the progressive base.
 */
class MinimumTaxCalculator
{
    /** Only ภ.ง.ด.90 prints the two-method calculation; ภ.ง.ด.91 carries มาตรา 40 (1) alone. */
    public const FORM = 'PND90';

    /** เงินได้พึงประเมินมีจำนวนตั้งแต่ 120,000 บาทขึ้นไป */
    public const THRESHOLD = '120000';

    /** คูณด้วย 0.005 */
    public const RATE = '0.5';

    /** เว้นแต่คำนวณแล้วไม่เกิน 5,000 บาท ให้ชำระภาษีจากวิธีที่ 1. */
    public const DE_MINIMIS = '5000';

    /** มาตรา 40 (1) is excluded from the base. */
    public const EXCLUDED_INCOME_TYPE = 'SECTION_40_1';

    /**
     * @param  list<array<string, mixed>>  $items  IncomeCalculator groups for the ข้อ 1–ข้อ 7 base
     * @return array{applicable: bool, base: Money, tax: Money, payable: Money, method: string}
     */
    public function calculate(string $formCode, array $items, Money $progressiveTax): array
    {
        $base = new Money;
        if ($formCode === self::FORM) {
            foreach ($items as $group) {
                if (($group['income_type'] ?? null) !== self::EXCLUDED_INCOME_TYPE) {
                    $base = $base->add($group['gross_income']);
                }
            }
        }
        $notApplicable = ['applicable' => false, 'base' => $base, 'tax' => new Money,
            'payable' => $progressiveTax, 'method' => 'PROGRESSIVE'];
        if ($formCode !== self::FORM || $base->compare(new Money(self::THRESHOLD)) < 0) {
            return $notApplicable;
        }
        $tax = $base->percentage(self::RATE);
        // "เว้นแต่คำนวณแล้วไม่เกิน 5,000 บาท ให้ชำระภาษีจากวิธีที่ 1." — the second method is
        // computed but disregarded, so a small base never raises the tax above method 1.
        if ($tax->compare(new Money(self::DE_MINIMIS)) <= 0) {
            return [...$notApplicable, 'tax' => $tax];
        }

        return ['applicable' => true, 'base' => $base, 'tax' => $tax,
            'payable' => $progressiveTax->max($tax),
            'method' => $tax->compare($progressiveTax) > 0 ? 'MINIMUM_TAX' : 'PROGRESSIVE'];
    }
}

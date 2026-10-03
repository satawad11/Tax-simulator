<?php

namespace App\Services\Tax;

use App\ValueObjects\Money;

/**
 * ภ.ง.ด.90 ข้อ 9 — เงินได้จากการให้หรือการรับ
 * (โดยเลือกเสียภาษีในอัตราร้อยละ 5 ของเงินได้เฉพาะส่วนที่ไม่ได้รับยกเว้นตามมาตรา 42 (26) (27) (28))
 *
 * Income the taxpayer routes here is taxed at the printed 5% and does not join the progressive
 * base; ข้อ 11 item 19 then adds its tax to the balance ("บวก ภาษีที่ชำระเพิ่มเติม (ยกมาจาก ข้อ 9)").
 *
 * The exempt portion under มาตรา 42 (26) (27) (28) is not quantified anywhere in the repository
 * sources, so the taxpayer declares the non-exempt amount — exactly as the paper form asks —
 * and this calculator applies only the rate the form prints.
 */
class SeparateTaxCalculator
{
    public const TREATMENT = 'SEPARATE_RATE';

    public const PROGRESSIVE = 'PROGRESSIVE';

    public const TREATMENTS = [self::PROGRESSIVE, self::TREATMENT];

    /** The only category ข้อ 9 prints. */
    public const ELIGIBLE = ['SECTION_40_8' => 'GIFT_OR_SUPPORT_RECEIVED'];

    public const RATE = '5';

    public const SOURCE = 'docs/tax-source/ภงด.90.pdf, page 4, ข้อ 9';

    /** True when this income line elects the separate rate rather than the progressive base. */
    public static function elected(array $income): bool
    {
        return ($income['tax_treatment'] ?? self::PROGRESSIVE) === self::TREATMENT;
    }

    /** True when ข้อ 9 prints this category, so the election may be offered at all. */
    public static function eligible(?string $incomeType, ?string $subtype): bool
    {
        return $incomeType !== null && array_key_exists($incomeType, self::ELIGIBLE)
            && self::ELIGIBLE[$incomeType] === $subtype;
    }

    /**
     * @param  list<array<string, mixed>>  $incomes  only the lines electing the separate rate
     * @return array{items: list<array<string, mixed>>, base: Money, tax: Money, rate: string}
     */
    public function calculate(array $incomes): array
    {
        $items = [];
        $base = $tax = new Money;

        foreach ($incomes as $income) {
            $amount = (new Money((string) $income['gross_amount']))
                ->subtract(new Money((string) ($income['exempt_amount'] ?? '0')));
            if ($amount->compare(new Money) < 0) {
                throw new \InvalidArgumentException('Separately taxed income must be nonnegative.');
            }
            $lineTax = $amount->percentage(self::RATE);
            $base = $base->add($amount);
            $tax = $tax->add($lineTax);
            $items[] = ['income_type' => $income['income_type'], 'income_subtype' => $income['income_subtype'] ?? null,
                'description' => $income['description'] ?? null, 'base' => $amount,
                'rate' => self::RATE, 'tax' => $lineTax];
        }

        return ['items' => $items, 'base' => $base, 'tax' => $tax, 'rate' => self::RATE];
    }
}

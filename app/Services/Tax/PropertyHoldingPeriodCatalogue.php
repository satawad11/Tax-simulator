<?php

namespace App\Services\Tax;

/**
 * The จำนวนปีที่ถือครอง rate table for ภ.ง.ด.90 ข้อ 7 item 3 (2).
 *
 * Source: docs/tax-source/PND90-2568-filing-instructions.pdf, page 3, ข้อ 7 item 3 (2):
 *
 *   วิธีที่ 1 หักค่าใช้จ่ายจริงตามความจำเป็นและสมควร
 *   วิธีที่ 2 หักค่าใช้จ่ายเป็นการเหมาในอัตราดังนี้
 *     จำนวนปีที่ถือครอง*  1 ปี  2 ปี  3 ปี  4 ปี  5 ปี  6 ปี  7 ปี  8 ปีขึ้นไป
 *     ร้อยละของเงินได้      92    84    77    71    65    60    55    50
 *   * จำนวนปีที่ถือครอง หมายถึง จำนวนปีนับตั้งแต่ปีที่ได้กรรมสิทธิ์ หรือสิทธิครอบครองในอสังหาริมทรัพย์
 *     ถึงปีที่โอนกรรมสิทธิ์หรือสิทธิครอบครองในอสังหาริมทรัพย์นั้น ถ้าเกิน 10 ปี ให้นับเพียง 10 ปี
 *     เศษของปีให้นับเป็น 1 ปี การนับจำนวนปีที่ถือครองให้ถือตามปีปฏิทิน
 *
 * ภ.ง.ด.90 page 3 prints a field for it — `จำนวนปีที่ถือครอง ………. ปี` — so the number of years
 * is a fact the taxpayer states, exactly like the expense-method election beside it.
 */
final class PropertyHoldingPeriodCatalogue
{
    public const INCOME_TYPE = 'SECTION_40_8';

    public const INCOME_SUBTYPE = 'IMMOVABLE_PROPERTY_NON_TRADE';

    /** "ถ้าเกิน 10 ปี ให้นับเพียง 10 ปี" — a stated year beyond this is counted as this. */
    public const MAXIMUM_COUNTED_YEARS = 10;

    /**
     * [minimum years, maximum years or null for the open band, percentage]
     *
     * @var list<array{int, ?int, string}>
     */
    public const BANDS = [
        [1, 1, '92.0000'],
        [2, 2, '84.0000'],
        [3, 3, '77.0000'],
        [4, 4, '71.0000'],
        [5, 5, '65.0000'],
        [6, 6, '60.0000'],
        [7, 7, '55.0000'],
        [8, null, '50.0000'],
    ];

    public static function requiresHoldingYears(string $incomeType, ?string $subtype): bool
    {
        return $incomeType === self::INCOME_TYPE && $subtype === self::INCOME_SUBTYPE;
    }

    /** "เศษของปีให้นับเป็น 1 ปี" makes any stated year at least 1; the table starts there. */
    public static function knows(?int $years): bool
    {
        return $years !== null && $years >= 1;
    }

    /** The year the rate table is read at, after the printed ten-year ceiling. */
    public static function counted(int $years): int
    {
        return min($years, self::MAXIMUM_COUNTED_YEARS);
    }
}

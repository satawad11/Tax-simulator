<?php

namespace App\Services\Tax;

/**
 * The income subcategories ภ.ง.ด.90 prints as separate lines with their own expense treatment.
 *
 * This catalogue records which subcategories the form *defines*. Whether a subcategory has a
 * usable expense rule is a separate question answered by `expense_rules` — a subtype listed
 * here with no seeded rule is a documented gap, not an unknown value.
 *
 * Labels are the source wording, kept verbatim so a reader can find the line on the form.
 * Income types absent from this list are printed on the form as a single category and take
 * no subtype.
 */
final class IncomeSubtypeCatalogue
{
    /**
     * income type code => [subtype code => source wording, with the form location]
     *
     * @var array<string, array<string, string>>
     */
    public const SUBTYPES = [
        // ข้อ 2 — ภ.ง.ด.90 page 2
        'SECTION_40_3' => [
            'ANNUITY_FROM_WILL_OR_JUDGMENT' => 'ข้อ 2 ข้อย่อย 1 — เงินได้มีลักษณะเป็นเงินรายปีอันได้มาจากพินัยกรรม นิติกรรมอย่างอื่น หรือคำพิพากษาของศาล ฯลฯ',
            'COPYRIGHT_GOODWILL_OTHER_RIGHTS' => 'ข้อ 2 ข้อย่อย 2 — ค่าแห่งลิขสิทธิ์ / ค่าแห่งกู๊ดวิลล์ ค่าสิทธิอย่างอื่น',
        ],
        // ข้อ 4 — ภ.ง.ด.90 pages 2–3
        'SECTION_40_5' => [
            // ข้อ 4 item 1 prints (1) with its rate and leaves (2)–(4) as `อื่นๆ (ระบุ)` with a
            // blank percentage, because the rate depends on which asset is let. The booklet
            // (วิธีการกรอกแบบ ภ.ง.ด.90 page 3, ข้อ 4 การหักค่าใช้จ่าย (1) วิธีที่ 2) names all five
            // classes and their rates, so each is its own subtype rather than one "other".
            'RENT_BUILDING_OR_RAFT' => 'ข้อ 4 ข้อย่อย 1 (1) — บ้าน โรงเรือน สิ่งปลูกสร้างอย่างอื่น หรือแพ (คำแนะนำ หน้า 3 (ก))',
            'RENT_LAND_AGRICULTURAL' => 'ข้อ 4 ข้อย่อย 1 (2)–(4) อื่นๆ (ระบุ) — ที่ดินที่ใช้ในการเกษตรกรรม (คำแนะนำ หน้า 3 (ข))',
            'RENT_LAND_NON_AGRICULTURAL' => 'ข้อ 4 ข้อย่อย 1 (2)–(4) อื่นๆ (ระบุ) — ที่ดินที่มิได้ใช้ในการเกษตรกรรม (คำแนะนำ หน้า 3 (ค))',
            'RENT_VEHICLE' => 'ข้อ 4 ข้อย่อย 1 (2)–(4) อื่นๆ (ระบุ) — ยานพาหนะ (คำแนะนำ หน้า 3 (ง))',
            'RENT_OTHER_PROPERTY' => 'ข้อ 4 ข้อย่อย 1 (2)–(4) อื่นๆ (ระบุ) — ทรัพย์สินอย่างอื่น (คำแนะนำ หน้า 3 (จ))',
            'HIRE_PURCHASE_BREACH' => 'ข้อ 4 ข้อย่อย 2 — การผิดสัญญาเช่าซื้อทรัพย์สิน/ซื้อขายเงินผ่อนฯ',
        ],
        // ข้อ 5 — ภ.ง.ด.90 page 3
        'SECTION_40_6' => [
            'MEDICAL_PRACTICE' => 'ข้อ 5 ข้อย่อย 1 — การประกอบโรคศิลปะ',
            'FINE_ARTS' => 'ข้อ 5 ข้อย่อย 2 — ประณีตศิลปกรรม',
            'OTHER_LIBERAL_PROFESSION' => 'ข้อ 5 ข้อย่อย 3–4 — อื่นๆ (วิชากฎหมาย วิศวกรรม สถาปัตยกรรม การบัญชี)',
        ],
        // ข้อ 7 — ภ.ง.ด.90 page 3
        'SECTION_40_8' => [
            'BUSINESS_COMMERCE_OTHER' => 'ข้อ 7 ข้อย่อย 1 (1)–(4) — เงินได้จากการธุรกิจ การพาณิชย์ การเกษตร การอุตสาหกรรม การขนส่งหรือการอื่นๆ',
            'MUTUAL_FUND_PROFIT_SHARE' => 'ข้อ 7 ข้อย่อย 2 — เงินส่วนแบ่งของกำไรจากกองทุนรวมตามประกาศคณะปฏิวัติฯ',
            'IMMOVABLE_PROPERTY_INHERITED_OR_GIFTED' => 'ข้อ 7 ข้อย่อย 3 (1) — ขายอสังหาริมทรัพย์ที่เป็นมรดก หรือได้รับโดยเสน่หา',
            'IMMOVABLE_PROPERTY_NON_TRADE' => 'ข้อ 7 ข้อย่อย 3 (2) — ขายอสังหาริมทรัพย์ที่ได้มาโดยมิได้มุ่งในทางการค้าหรือหากำไร',
            'GIFT_OR_SUPPORT_RECEIVED' => 'ข้อ 7 ข้อย่อย 4 — เงินได้จากการให้หรือการรับตามมาตรา 42 (26) (27) (28)',
        ],
    ];

    public static function requiresSubtype(string $incomeType): bool
    {
        return isset(self::SUBTYPES[$incomeType]);
    }

    public static function knows(string $incomeType, string $subtype): bool
    {
        return isset(self::SUBTYPES[$incomeType][$subtype]);
    }

    /** @return list<string> */
    public static function codes(string $incomeType): array
    {
        return array_keys(self::SUBTYPES[$incomeType] ?? []);
    }
}

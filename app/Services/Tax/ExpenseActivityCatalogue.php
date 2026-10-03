<?php

namespace App\Services\Tax;

/**
 * ตารางที่ 2 — ตารางอัตราการหักค่าใช้จ่ายเป็นการเหมาสำหรับเงินได้พึงประเมินตามมาตรา 40 (8).
 *
 * Source: docs/tax-source/PND90-2568-filing-instructions.pdf, page 17, table 2. The table
 * numbers 44 activities; ภ.ง.ด.90 ข้อ 7 item 1 prints `(ระบุ)` and a blank percentage because
 * the rate is a property of the activity, not of the income category. Page 3 of the same
 * booklet points at the table explicitly: "(การหักค่าใช้จ่ายดูตารางที่ 2 หน้า 17)".
 *
 * Labels are the source wording, kept verbatim so a reader can find the row on page 17. The
 * activity code is a stable identifier for that row; it is never matched against free text.
 *
 * Two rows do not carry a plain percentage:
 *   (1)  is banded — 60% of the first 300,000 and 40% above it, together capped at 600,000;
 *   (44) "เงินได้ประเภทที่มิได้ระบุใน (1) ถึง (43) ให้หักค่าใช้จ่ายจริงตามความจำเป็นและสมควร".
 * Both are marked here with a null rate and given their own rule shape by the seeder.
 */
final class ExpenseActivityCatalogue
{
    /** The income type and subtype ตารางที่ 2 belongs to. */
    public const INCOME_TYPE = 'SECTION_40_8';

    public const INCOME_SUBTYPE = 'BUSINESS_COMMERCE_OTHER';

    public const PERFORMER = 'TABLE2_01_PERFORMER';

    public const UNLISTED = 'TABLE2_44_UNLISTED';

    /**
     * activity code => [table row number, source label, flat percentage or null]
     *
     * @var array<string, array{int, string, ?string}>
     */
    public const ACTIVITIES = [
        self::PERFORMER => [1, 'การแสดงของนักแสดงละคร ภาพยนตร์ วิทยุหรือโทรทัศน์ นักร้อง นักดนตรี นักกีฬาอาชีพ หรือนักแสดงเพื่อความบันเทิงใดๆ', null],
        'TABLE2_02_LAND_INSTALMENT_SALE' => [2, 'การขายที่ดินเงินผ่อนหรือการให้เช่าซื้อที่ดิน', '60.0000'],
        'TABLE2_03_GAMBLING_TABLE_FEES' => [3, 'การเก็บค่าต๋งหรือค่าเกมจากการพนัน การแข่งขันหรือการเล่นต่างๆ', '60.0000'],
        'TABLE2_04_PHOTOGRAPHY' => [4, 'การถ่าย ล้าง อัด หรือขยายรูป ภาพยนตร์ รวมทั้งการขายส่วนประกอบ', '60.0000'],
        'TABLE2_05_SHIPYARD' => [5, 'การทำกิจการคานเรือ อู่เรือ หรือซ่อมเรือที่มิใช่ซ่อมเครื่องจักร เครื่องกล', '60.0000'],
        'TABLE2_06_FOOTWEAR_AND_LEATHER' => [6, 'การทำรองเท้า และเครื่องหนังแท้หรือหนังเทียม รวมทั้งการขายส่วนประกอบ', '60.0000'],
        'TABLE2_07_GARMENT_MAKING' => [7, 'การตัด เย็บ ถัก ปักเสื้อผ้า หรือสิ่งอื่นๆ รวมทั้งการขายส่วนประกอบ', '60.0000'],
        'TABLE2_08_FURNITURE' => [8, 'การทำ ตกแต่ง หรือซ่อมแซมเครื่องเรือน รวมทั้งการขายส่วนประกอบ', '60.0000'],
        'TABLE2_09_HOTEL_OR_RESTAURANT' => [9, 'การทำกิจการโรงแรมหรือภัตตาคาร หรือการปรุงอาหาร หรือเครื่องดื่มจำหน่าย', '60.0000'],
        'TABLE2_10_HAIRDRESSING' => [10, 'การดัด ตัด แต่งผม หรือตกแต่งร่างกาย', '60.0000'],
        'TABLE2_11_SOAP_SHAMPOO_COSMETICS' => [11, 'การทำสบู่ แชมพู หรือเครื่องสำอาง', '60.0000'],
        'TABLE2_12_LITERARY_WORK' => [12, 'การทำวรรณกรรม', '60.0000'],
        'TABLE2_13_PRECIOUS_METAL_AND_GEM_TRADE' => [13, 'การค้าเครื่องเงิน ทอง นาก เพชร พลอย หรืออัญมณีอื่นๆ รวมทั้งการขายส่วนประกอบ', '60.0000'],
        'TABLE2_14_INPATIENT_MEDICAL_FACILITY' => [14, 'การทำกิจการสถานพยาบาลตามกฎหมายว่าด้วยสถานพยาบาลเฉพาะที่มีเตียงรับผู้ป่วยไว้ค้างคืน รวมทั้งการรักษาพยาบาลและการจำหน่ายยา', '60.0000'],
        'TABLE2_15_STONE_MILLING' => [15, 'การโม่หรือย่อยหิน', '60.0000'],
        'TABLE2_16_FORESTRY_AND_PLANTATION' => [16, 'การทำป่าไม้ สวนยาง หรือไม้ยืนต้น', '60.0000'],
        'TABLE2_17_TRANSPORT_BY_VEHICLE' => [17, 'การขนส่งหรือรับจ้างด้วยยานพาหนะ', '60.0000'],
        'TABLE2_18_PRINTING_AND_BOOKBINDING' => [18, 'การทำบล็อก และตรา การรับพิมพ์ หรือเย็บสมุด เอกสาร รวมทั้งการขายส่วนประกอบ', '60.0000'],
        'TABLE2_19_MINING' => [19, 'การทำเหมืองแร่', '60.0000'],
        'TABLE2_20_EXCISE_BEVERAGES' => [20, 'การทำเครื่องดื่มตามกฎหมายว่าด้วยภาษีสรรพสามิต', '60.0000'],
        'TABLE2_21_CERAMICS_AND_CEMENT' => [21, 'การทำเครื่องกระเบื้อง เครื่องเคลือบ เครื่องซีเมนต์ หรือดินเผา', '60.0000'],
        'TABLE2_22_ELECTRICITY' => [22, 'การทำหรือจำหน่ายกระแสไฟฟ้า', '60.0000'],
        'TABLE2_23_ICE_MAKING' => [23, 'การทำน้ำแข็ง', '60.0000'],
        'TABLE2_24_GLUE_AND_STARCH' => [24, 'การทำกาว แป้งเปียกหรือสิ่งที่มีลักษณะทำนองเดียวกัน และการทำแป้งชนิดต่าง ๆ ที่มิใช่เครื่องสำอาง', '60.0000'],
        'TABLE2_25_BALLOONS_GLASS_PLASTIC_RUBBER' => [25, 'การทำลูกโป่ง เครื่องแก้ว เครื่องพลาสติก หรือเครื่องยางสำเร็จรูป', '60.0000'],
        'TABLE2_26_LAUNDRY_OR_DYEING' => [26, 'การซักรีด หรือย้อมสี', '60.0000'],
        'TABLE2_27_RESELLING_GOODS' => [27, 'การขายของนอกจากที่ระบุไว้ในข้ออื่นซึ่งผู้ขายมิได้เป็นผู้ผลิต', '60.0000'],
        'TABLE2_28_RACEHORSE_PRIZES' => [28, 'รางวัลที่เจ้าของม้าได้จากการส่งม้าเข้าแข่ง', '60.0000'],
        'TABLE2_29_SALE_WITH_RIGHT_OF_REDEMPTION' => [29, 'การรับสินไถ่ทรัพย์สินที่ขายฝากหรือการได้กรรมสิทธิ์ในทรัพย์สินโดยเด็ดขาดจากการขายฝาก', '60.0000'],
        'TABLE2_30_RUBBER_SMOKING_AND_SHEETING' => [30, 'การรมยาง การทำยางแผ่น หรือยางอย่างอื่นที่มิใช่ยางสำเร็จรูป', '60.0000'],
        'TABLE2_31_TANNING' => [31, 'การฟอกหนัง', '60.0000'],
        'TABLE2_32_SUGAR_MAKING' => [32, 'การทำน้ำตาล หรือน้ำเหลืองของน้ำตาล', '60.0000'],
        'TABLE2_33_FISHING' => [33, 'การจับสัตว์น้ำ', '60.0000'],
        'TABLE2_34_SAWMILL' => [34, 'การทำกิจการโรงเลื่อย', '60.0000'],
        'TABLE2_35_OIL_REFINING_OR_PRESSING' => [35, 'การกลั่นหรือหีบน้ำมัน', '60.0000'],
        'TABLE2_36_HIRE_PURCHASE_OF_MOVABLE_PROPERTY' => [36, 'การให้เช่าซื้อสังหาริมทรัพย์ที่ไม่เข้าลักษณะตามมาตรา 40 (5) แห่งประมวลรัษฎากร', '60.0000'],
        'TABLE2_37_RICE_MILL' => [37, 'การทำกิจการโรงสีข้าว', '60.0000'],
        'TABLE2_38_ANNUAL_CROPS_AND_CEREALS' => [38, 'การทำเกษตรกรรมประเภทไม้ล้มลุกและธัญชาติ', '60.0000'],
        'TABLE2_39_TOBACCO_CURING' => [39, 'การอบหรือบ่มใบยาสูบ', '60.0000'],
        'TABLE2_40_LIVESTOCK' => [40, 'การเลี้ยงสัตว์ทุกชนิด รวมทั้งการขายวัตถุพลอยได้', '60.0000'],
        'TABLE2_41_SLAUGHTERING' => [41, 'การฆ่าสัตว์จำหน่าย รวมทั้งการขายวัตถุพลอยได้', '60.0000'],
        'TABLE2_42_SALT_FARMING' => [42, 'การทำนาเกลือ', '60.0000'],
        'TABLE2_43_SALE_OF_SHIPS_AND_RAFTS' => [43, 'การขายเรือกำปั่นหรือเรือมีระวางตั้งแต่ 6 ตันขึ้นไป เรือกลไฟหรือเรือยนต์มีระวางตั้งแต่ 5 ตันขึ้นไป หรือแพ', '60.0000'],
        self::UNLISTED => [44, 'เงินได้ประเภทที่มิได้ระบุใน (1) ถึง (43) ให้หักค่าใช้จ่ายจริงตามความจำเป็นและสมควร', null],
    ];

    /** ตารางที่ 2 row (1) (ก) — 60% of the income up to this amount, 40% above it. */
    public const PERFORMER_BAND_THRESHOLD = '300000.00';

    public const PERFORMER_FIRST_BAND_PERCENTAGE = '60.0000';

    public const PERFORMER_SECOND_BAND_PERCENTAGE = '40.0000';

    /** "การหักค่าใช้จ่ายตาม (ก) และ (ข) รวมกันต้องไม่เกิน 600,000 บาท" */
    public const PERFORMER_MAXIMUM = '600000.00';

    public static function requiresActivity(string $incomeType, ?string $subtype): bool
    {
        return $incomeType === self::INCOME_TYPE && $subtype === self::INCOME_SUBTYPE;
    }

    public static function knows(?string $activity): bool
    {
        return $activity !== null && array_key_exists($activity, self::ACTIVITIES);
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::ACTIVITIES);
    }

    public static function label(string $activity): string
    {
        return self::ACTIVITIES[$activity][1];
    }

    public static function row(string $activity): int
    {
        return self::ACTIVITIES[$activity][0];
    }

    public static function percentage(string $activity): ?string
    {
        return self::ACTIVITIES[$activity][2];
    }
}

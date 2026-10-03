<?php

namespace App\Services\Tax;

/**
 * Milestone 07.5 — the final status of every allowance master code, and the stable error code
 * a blocked one reports.
 *
 * Until M7.5 a declared allowance with no verified rule was accepted, deducted nothing, and
 * said so in a warning. That is silent zeroing: the taxpayer asked for a deduction they had a
 * reason to expect, and the response carried a smaller tax without refusing the request.
 * M7.5 closes that — a positive amount on a code this baseline cannot calculate is a 422.
 *
 * Only codes that are **neither** family-derived **nor** backed by a seeded rule appear here;
 * every other code is SUPPORTED and never reaches this catalogue. A test asserts the two sets
 * agree, so a future seeded rule cannot leave a stale entry behind.
 *
 * A zero amount is never blocked: it cannot change the tax, so refusing it would be noise.
 *
 * **`printed` is what decides whether a reader is told the line exists.** Status says whether the
 * engine can calculate it; `printed` says whether ใบแนบ prints it as a line at all. The two are
 * independent, and conflating them is what once hid four printed deduction lines from the product
 * entirely while showing three umbrella categories the attachment never prints.
 */
final class AllowanceCoverageCatalogue
{
    /**
     * Some semantics are known but a required rule is missing, so the amount cannot be
     * calculated without guessing.
     */
    public const PARTIAL_BLOCKED = 'PARTIAL_BLOCKED';

    /** This baseline does not support the line at all. */
    public const UNSUPPORTED = 'UNSUPPORTED';

    /**
     * code => [status, stable error code, message, printed on ใบแนบ]
     *
     * @var array<string, array{string, string, string, bool}>
     */
    public const BLOCKED = [
        'PENSION_INSURANCE' => [self::PARTIAL_BLOCKED, 'PENSION_INSURANCE_RULE_PARTIAL',
            'ใบแนบ ข้อ 7.6 พิมพ์ทั้ง "ไม่เกิน 90,000 บาท" และ "เพิ่มขึ้นอีก … ร้อยละ 15 … แต่ไม่เกิน 200,000 บาท" '
                .'โดยไม่ได้ระบุว่าจำนวนแรกรวมอยู่ในจำนวนหลังหรือไม่ ระบบจึงยังคำนวณเบี้ยประกันชีวิตแบบบำนาญไม่ได้', true],
        'SOCIAL_SECURITY' => [self::PARTIAL_BLOCKED, 'SOCIAL_SECURITY_RULE_UNSUPPORTED',
            'ใบแนบ ข้อ 12 ระบุว่า "หักลดหย่อนได้ตามที่จ่ายจริงตามกฎหมายว่าด้วยการประกันสังคม" '
                .'ซึ่งเพดานตัวเลขอยู่ในกฎหมายฉบับอื่นที่ไม่ได้อยู่ในเอกสารต้นทางของโครงการ', true],

        /*
         * The four printed lines this baseline cannot calculate.
         *
         * Each has a stated amount in the filing instructions; what blocks every one of them is a
         * *condition the engine cannot establish* from a declared figure — a geographic zone, a
         * registration status, a contract date, or which tier of city a hotel stands in. Saying so
         * per line is the point: a reader learns the deduction exists, that this simulator will not
         * guess at it, and exactly what stands in the way.
         */
        /*
         * ใบแนบ ข้อ 13 and ข้อ 20 are calculable, but not here. Both say the amount comes off
         * เงินได้พึงประเมิน *after* expenses, so they are เงินได้ที่ได้รับยกเว้น and are claimed in
         * that section — not as ค่าลดหย่อน. They stay listed with a reason that points the reader
         * at the right field, because saying nothing would send them looking for a line that is
         * on the form but not in this part of it.
         */
        'CCTV_SYSTEM' => [self::UNSUPPORTED, 'CCTV_SYSTEM_NOT_AN_ALLOWANCE',
            'ใบแนบ ข้อ 13 — รายการนี้ไม่ใช่ค่าลดหย่อน คำแนะนำ ข้อ 13.4 ระบุให้นำไปหักจากเงินได้พึงประเมิน '
                .'หลังหักค่าใช้จ่ายตามมาตรา 42 ทวิ ถึงมาตรา 46 แล้ว จึงกรอกได้ในหัวข้อ '
                .'"เงินได้ที่ได้รับยกเว้นหลังหักค่าใช้จ่าย" ซึ่งระบบคำนวณให้ร้อยละ 100 ของที่จ่ายจริง', true],
        'SOCIAL_ENTERPRISE_INVESTMENT' => [self::UNSUPPORTED, 'SOCIAL_ENTERPRISE_RULE_UNSUPPORTED',
            'ใบแนบ ข้อ 16 — คำแนะนำระบุเพดาน "ไม่เกินกรณีละ 100,000 บาท สำหรับปีภาษีนั้น" '
                .'แต่ไม่ได้ระบุว่าหากลงทุนหลายกรณีในปีเดียวกันจะนับเพดานแยกรายกรณีหรือรวมกัน '
                .'อีกทั้งสิทธิยังขึ้นกับการที่กิจการจดทะเบียนเป็นวิสาหกิจเพื่อสังคม '
                .'และผู้มีเงินได้ต้องถือหุ้นหรือเป็นหุ้นส่วนจนกว่าวิสาหกิจนั้นเลิกกัน ซึ่งเป็นสถานะที่ระบบยืนยันไม่ได้', true],
        'NEW_HOME_CONSTRUCTION' => [self::UNSUPPORTED, 'NEW_HOME_CONSTRUCTION_NOT_AN_ALLOWANCE',
            'ใบแนบ ข้อ 20 — รายการนี้ไม่ใช่ค่าลดหย่อน คำแนะนำ ข้อ 20.4 ระบุให้นำไปหักจากเงินได้พึงประเมิน '
                .'หลังหักค่าใช้จ่ายตามมาตรา 42 ทวิ ถึงมาตรา 46 แล้ว จึงกรอกได้ในหัวข้อ '
                .'"เงินได้ที่ได้รับยกเว้นหลังหักค่าใช้จ่าย" ซึ่งระบบคำนวณให้ 10,000 บาท ต่อทุกจำนวน 1,000,000 บาทที่จ่ายจริง '
                .'รวมไม่เกิน 100,000 บาท', true],
        /*
         * เมืองหลัก is now two supported codes of its own. What stays blocked is เมืองรอง, and only
         * because ข้อ 22.2 prints a 1.5× multiplier for the part above 10,000 with no ceiling
         * beside it. That is the whole of the remaining gap, so the message says exactly that
         * rather than describing the line as unsupported.
         */
        'DOMESTIC_TRAVEL' => [self::UNSUPPORTED, 'DOMESTIC_TRAVEL_SECONDARY_CITY_UNSUPPORTED',
            'ใบแนบ ข้อ 22 — ส่วนของ "เมืองหลัก" กรอกได้แล้วที่รายการค่าท่องเที่ยวในเมืองหลัก '
                .'(ใช้ได้เฉพาะค่าที่พักหรือค่าบริการร้านอาหารระหว่างวันที่ 29 ต.ค. 2568 ถึง 15 ธ.ค. 2568) '
                .'ส่วนของ "เมืองรอง" ยังคำนวณไม่ได้ เพราะคำแนะนำ ข้อ 22.2 ระบุให้หักส่วนที่เกิน 10,000 บาท '
                .'ได้ 1.5 เท่าของจำนวนที่จ่ายจริง โดยไม่ได้ระบุเพดานไว้', true],

        // FR-007 umbrella categories. ใบแนบ prints no line for them, so there is nothing to
        // reconcile — they exist as master categories only, and are not shown to a reader as
        // though the form carried them.
        'INSURANCE' => [self::UNSUPPORTED, 'ALLOWANCE_RULE_UNSUPPORTED',
            'หมวดหมู่รวมที่ใบแนบไม่ได้พิมพ์เป็นรายการ ให้เลือกใช้รหัสของรายการที่ใบแนบพิมพ์ไว้แทน', false],
        'ANNUAL_TAX_MEASURES' => [self::UNSUPPORTED, 'ALLOWANCE_RULE_UNSUPPORTED',
            'หมวดหมู่รวมที่ใบแนบไม่ได้พิมพ์เป็นรายการ ให้เลือกใช้รหัสของรายการที่ใบแนบพิมพ์ไว้แทน', false],
        'OTHER' => [self::UNSUPPORTED, 'ALLOWANCE_RULE_UNSUPPORTED',
            'หมวดหมู่รวมที่ใบแนบไม่ได้พิมพ์เป็นรายการ ให้เลือกใช้รหัสของรายการที่ใบแนบพิมพ์ไว้แทน', false],
    ];

    public static function blocks(string $code): bool
    {
        return array_key_exists($code, self::BLOCKED);
    }

    public static function status(string $code): ?string
    {
        return self::BLOCKED[$code][0] ?? null;
    }

    public static function errorCode(string $code): ?string
    {
        return self::BLOCKED[$code][1] ?? null;
    }

    public static function message(string $code): ?string
    {
        return self::BLOCKED[$code][2] ?? null;
    }

    /** Whether ใบแนบ prints this as a line — which is what decides if a reader is told it exists. */
    public static function printedOnForm(string $code): bool
    {
        return self::BLOCKED[$code][3] ?? true;
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::BLOCKED);
    }
}

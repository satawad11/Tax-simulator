<?php

namespace Database\Seeders;

use App\Models\AllowanceType;
use Illuminate\Database\Seeder;

class AllowanceTypeSeeder extends Seeder
{
    public function run(): void
    {
        // Master categories from FR-007 only; no limits or eligibility are inferred.
        $types = [
            ['PERSONAL', 'ส่วนตัว', 'personal'],
            ['SPOUSE', 'คู่สมรส', 'spouse'],
            ['CHILD', 'บุตร', 'child'],
            ['PARENT', 'บิดามารดา', 'parent'],
            ['DISABLED_PERSON', 'ผู้พิการหรือทุพพลภาพ', 'disabled_person'],
            ['INSURANCE', 'ประกันภัย', 'insurance'],
            ['LIFE_INSURANCE', 'เบี้ยประกันชีวิต', 'insurance'],
            ['HEALTH_INSURANCE', 'เบี้ยประกันสุขภาพ', 'insurance'],
            ['PENSION_INSURANCE', 'เบี้ยประกันชีวิตแบบบำนาญ', 'insurance'],
            ['NSF', 'กองทุนการออมแห่งชาติ', 'nsf'],
            ['EASY_E_RECEIPT', 'Easy E-Receipt', 'annual_tax_measures'],
            ['PROVIDENT_FUND', 'กองทุนสำรองเลี้ยงชีพ', 'provident_fund'],
            ['RMF', 'RMF', 'rmf'],
            ['THAI_ESG', 'Thai ESG', 'thai_esg'],
            ['THAI_ESGX', 'Thai ESGX', 'thai_esgx'],
            ['HOME_LOAN_INTEREST', 'ดอกเบี้ยกู้ยืมเพื่อที่อยู่อาศัย', 'home_loan_interest'],
            ['SOCIAL_SECURITY', 'ประกันสังคม', 'social_security'],
            ['ANNUAL_TAX_MEASURES', 'มาตรการภาษีประจำปี', 'annual_tax_measures'],
            // M7.3 — ใบแนบ lines the 2568 filing instructions print a ceiling for and that had
            // no master category yet. Categories only; the ceilings live in AllowanceRuleSeeder.
            ['PARENT_HEALTH_INSURANCE', 'เบี้ยประกันสุขภาพบิดามารดา', 'insurance'],
            ['MATERNITY', 'ค่าฝากครรภ์และค่าคลอดบุตร', 'maternity'],
            ['POLITICAL_PARTY_SUPPORT', 'เงินบริจาคแก่พรรคการเมือง', 'political_party'],
            ['ART_PURCHASE', 'ค่าซื้องานศิลปะ', 'annual_tax_measures'],
            // M7.4 — ใบแนบ prints these two lines as their own boxes with their own ceilings,
            // so each is its own master category rather than a share of its neighbour.
            ['EASY_E_RECEIPT_OTOP', 'Easy E-Receipt — OTOP วิสาหกิจชุมชน และวิสาหกิจเพื่อสังคม', 'annual_tax_measures'],
            ['THAI_ESGX_SWITCH', 'Thai ESGX — มูลค่าการสับเปลี่ยนหน่วยลงทุนจากกองทุน LTF', 'thai_esgx'],
            /*
             * The four ใบแนบ lines that had no master category at all.
             *
             * Items 13, 16, 20 and 22 are printed on the 2568 attachment and were missing from
             * this list, so they existed nowhere in the product — not in the form, and not even in
             * the "รายการที่มีในแบบแต่ยังไม่รองรับอัตโนมัติ" section, which can only list codes that
             * exist. A filer entitled to one of them was quoted a higher tax than the form gives
             * and was never told why.
             *
             * They are categories only. None carries an `AllowanceRule`, so each resolves through
             * `AllowanceCoverageCatalogue` as UNSUPPORTED with its own source-cited reason, and a
             * positive amount is refused rather than silently zeroed. Adding them changes no
             * calculation; it ends the silence.
             */
            ['CCTV_SYSTEM', 'ค่าซื้อและค่าติดตั้งระบบกล้องโทรทัศน์วงจรปิด', 'annual_tax_measures'],
            ['SOCIAL_ENTERPRISE_INVESTMENT', 'เงินลงทุนในวิสาหกิจเพื่อสังคม', 'annual_tax_measures'],
            ['NEW_HOME_CONSTRUCTION', 'ค่าจ้างก่อสร้างอาคารเพื่ออยู่อาศัยขึ้นใหม่', 'annual_tax_measures'],
            ['DOMESTIC_TRAVEL', 'ค่าท่องเที่ยวภายในประเทศ', 'annual_tax_measures'],
            /*
             * ใบแนบ ข้อ 22.1 grants เมืองหลัก as two separate entitlements — the first 10,000 on any
             * full tax invoice, and a further amount for the part above 10,000 only where that
             * invoice is electronic — so they are two codes rather than one with a hidden branch.
             * เมืองรอง keeps the `DOMESTIC_TRAVEL` code above and stays uncalculable: its 1.5×
             * multiplier for the part above 10,000 is printed with no ceiling.
             */
            ['DOMESTIC_TRAVEL_MAIN_CITY', 'ค่าท่องเที่ยวในเมืองหลัก', 'annual_tax_measures'],
            ['DOMESTIC_TRAVEL_MAIN_CITY_ETAX', 'ค่าท่องเที่ยวในเมืองหลัก ส่วนที่เกิน 10,000 บาท (e-Tax Invoice)', 'annual_tax_measures'],
            ['OTHER', 'อื่น ๆ', 'other'],
        ];

        foreach ($types as [$code, $name, $category]) {
            AllowanceType::firstOrCreate(['code' => $code], ['name' => $name, 'category' => $category, 'is_active' => true]);
        }
    }
}

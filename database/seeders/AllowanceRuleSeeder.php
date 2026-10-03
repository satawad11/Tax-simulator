<?php

namespace Database\Seeders;

use App\Services\Tax\PercentageBaseResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Allowance ceilings for rule version 2568.1.
 *
 * Milestone 07.2 could read only the ใบแนบ attachment itself (ภ.ง.ด.90 page 5 / ภ.ง.ด.91
 * page 3), which prints an amount on four of its twenty-three lines, so exactly one line —
 * item 8 — was seeded. Milestone 07.3 adds the filing-instruction booklet
 * (docs/tax-source/PND90-2568-filing-instructions.pdf), which explains every line, and the
 * ceilings below are read from it.
 *
 * What is seeded here, and only here, is a line whose ceiling is a plain
 * "ตามจำนวนที่จ่ายจริงแต่ไม่เกิน X" that AllowanceCalculator's `actual` method expresses
 * exactly. Three other shapes appear in the booklet and are deliberately absent:
 *
 *   percentage ceilings   RMF / Thai ESG / ประกันชีวิตแบบบำนาญ — ร้อยละ 30 (or 15) ของเงินได้
 *                         พึงประเมิน, which needs a base this rule row cannot name;
 *   combined ceilings     เบี้ยประกันชีวิต 100,000 shared with เบี้ยประกันสุขภาพ 25,000, and the
 *                         500,000 retirement basket shared by PVD / กบข. / RMF / NSF / บำนาญ —
 *                         a cap across codes, which no single rule row can enforce;
 *   tiered ceilings       Easy E-Receipt 50,000 + 30,000 and ค่าท่องเที่ยวภายในประเทศ, whose two
 *                         bands depend on what was bought.
 *
 * ใบแนบ items 1–5 (ผู้มีเงินได้, คู่สมรส, บุตร, บิดามารดา, คนพิการฯ) are not rule rows at all:
 * they are per-person entitlements derived by FamilyAllowanceResolver from declared family
 * facts, so no ceiling can express them.
 *
 * An absent rule still means "not established", which the engine reports; it is deliberately
 * different from a zero rule.
 */
class AllowanceRuleSeeder extends Seeder
{
    public const SOURCE = 'docs/tax-source/ภงด.90.pdf';

    /** M7.3 — วิธีการกรอกแบบ ภ.ง.ด.90 ปีภาษี 2568, the 18-page filing-instruction booklet. */
    public const INSTRUCTIONS = 'docs/tax-source/PND90-2568-filing-instructions.pdf';

    /** @return list<array<string, mixed>> */
    public static function approvedRules(): array
    {
        return [
            [
                'allowance_code' => 'PROVIDENT_FUND',
                'code' => 'PND90_2568_PROVIDENT_FUND',
                'method' => 'actual',
                'fixed_amount' => null,
                'percentage' => null,
                'maximum_amount' => '10000.00',
                'source' => self::SOURCE.', page 5, ใบแนบ item 8 — เงินสะสมกองทุนสำรองเลี้ยงชีพ (ส่วนที่ไม่เกิน 10,000 บาท)',
            ],
            // Milestone 07.3 — ceilings read from the filing instructions themselves. Only the
            // lines whose ceiling is a plain "ตามจำนวนที่จ่ายจริงแต่ไม่เกิน X" are here: a line
            // whose ceiling is a percentage, or shared with another line, is left unseeded so
            // the engine keeps reporting it rather than applying half a rule.
            [
                'allowance_code' => 'PARENT_HEALTH_INSURANCE',
                'code' => 'PND90_2568_PARENT_HEALTH_INSURANCE',
                'method' => 'actual',
                'fixed_amount' => null,
                'percentage' => null,
                'maximum_amount' => '15000.00',
                'source' => self::INSTRUCTIONS.', page 9, ใบแนบ item 6.3 — ผู้มีเงินได้จ่ายค่าเบี้ยประกันสุขภาพให้บิดามารดาของตน'
                    .'และบิดามารดาของคู่สมรสที่ไม่มีเงินได้ ให้ยกเว้นภาษีเงินได้ตามจำนวนที่จ่ายจริงแต่ไม่เกิน 15,000 บาท',
            ],
            [
                'allowance_code' => 'HOME_LOAN_INTEREST',
                'code' => 'PND90_2568_HOME_LOAN_INTEREST',
                'method' => 'actual',
                'fixed_amount' => null,
                'percentage' => null,
                'maximum_amount' => '100000.00',
                'source' => self::INSTRUCTIONS.', page 12, ใบแนบ item 11 — ดอกเบี้ยเงินกู้ยืมจากการกู้ยืมเงินเพื่อซื้อ เช่าซื้อ '
                    .'หรือสร้างอาคารอยู่อาศัย … ตามจำนวนเงินที่จ่ายจริงในปีภาษีนี้ แต่ไม่เกิน 100,000 บาท',
            ],
            [
                'allowance_code' => 'MATERNITY',
                'code' => 'PND90_2568_MATERNITY',
                'method' => 'actual',
                'fixed_amount' => null,
                'percentage' => null,
                'maximum_amount' => '60000.00',
                'source' => self::INSTRUCTIONS.', page 12, ใบแนบ item 14 — ค่าฝากครรภ์และค่าคลอดบุตร ตามจำนวนที่จ่ายจริง'
                    .'สำหรับการตั้งครรภ์แต่ละคราว แต่ไม่เกินหกหมื่นบาท',
            ],
            [
                'allowance_code' => 'POLITICAL_PARTY_SUPPORT',
                'code' => 'PND90_2568_POLITICAL_PARTY_SUPPORT',
                'method' => 'actual',
                'fixed_amount' => null,
                'percentage' => null,
                'maximum_amount' => '10000.00',
                'source' => self::INSTRUCTIONS.', page 12, ใบแนบ item 15 — เงินที่บริจาคแก่พรรคการเมือง … '
                    .'ตามจำนวนที่จ่ายจริงแต่รวมกันไม่เกินหนึ่งหมื่นบาท',
            ],
            [
                'allowance_code' => 'ART_PURCHASE',
                'code' => 'PND90_2568_ART_PURCHASE',
                'method' => 'actual',
                'fixed_amount' => null,
                'percentage' => null,
                'maximum_amount' => '100000.00',
                'source' => self::INSTRUCTIONS.', page 16, ใบแนบ item 21 — ค่าซื้องานศิลปะ ตามจำนวนที่จ่ายจริง '
                    .'แต่ไม่เกิน 100,000 บาท ในปีภาษี',
            ],
            // ---------------------------------------------------------------- Milestone 07.4
            // Lines whose individual ceiling is now expressible, because the schema can name a
            // percentage's base and can carry a ceiling shared across codes. The shared
            // ceilings themselves live in AllowanceCapGroupSeeder, not here.
            [
                'allowance_code' => 'LIFE_INSURANCE',
                'code' => 'PND90_2568_LIFE_INSURANCE',
                'method' => 'actual',
                'fixed_amount' => null,
                'percentage' => null,
                'percentage_base' => null,
                'maximum_amount' => '100000.00',
                'source' => self::INSTRUCTIONS.', pages 9–10, ใบแนบ item 7.2 (3) — กรณีคู่สมรสต่างฝ่ายต่างมีเงินได้ '
                    .'ให้คู่สมรสต่างฝ่ายต่างหักลดหย่อนและยกเว้นภาษีตามจำนวนที่จ่ายจริงแต่ไม่เกิน 100,000 บาท '
                    .'(รวมกับเบี้ยประกันสุขภาพตามข้อ 7.4 ต้องไม่เกิน 100,000 บาท — ดูกลุ่มเพดานรวม)',
            ],
            [
                'allowance_code' => 'HEALTH_INSURANCE',
                'code' => 'PND90_2568_HEALTH_INSURANCE',
                'method' => 'actual',
                'fixed_amount' => null,
                'percentage' => null,
                'percentage_base' => null,
                'maximum_amount' => '25000.00',
                'source' => self::INSTRUCTIONS.', page 10, ใบแนบ item 7.4 — เบี้ยประกันสุขภาพ … '
                    .'ตามจำนวนที่จ่ายจริงแต่ไม่เกิน 25,000 บาท ซึ่งเมื่อรวมกับค่าลดหย่อนตามมาตรา 47 (1) (ง) … '
                    .'ต้องไม่เกิน 100,000 บาท',
            ],
            [
                'allowance_code' => 'NSF',
                'code' => 'PND90_2568_NSF',
                'method' => 'actual',
                'fixed_amount' => null,
                'percentage' => null,
                'percentage_base' => null,
                'maximum_amount' => '500000.00',
                'source' => self::INSTRUCTIONS.', page 10, ใบแนบ item 9 — เงินได้เท่าที่สมาชิกกองทุนการออมแห่งชาติ'
                    .'จ่ายเป็นเงินสะสม … ตามจำนวนที่จ่ายจริง แต่ไม่เกิน 500,000 บาท สำหรับปีภาษีนั้น',
            ],
            [
                'allowance_code' => 'RMF',
                'code' => 'PND90_2568_RMF',
                'method' => 'percentage_limit',
                'fixed_amount' => null,
                'percentage' => '30.0000',
                'percentage_base' => PercentageBaseResolver::GROSS_AFTER_EXEMPTION,
                'maximum_amount' => '500000.00',
                'source' => self::INSTRUCTIONS.', page 11, ใบแนบ item 10.4 — ให้ยกเว้นเท่าที่ได้จ่ายเป็นค่าซื้อหน่วยลงทุน'
                    .'ในกองทุนรวมเพื่อการเลี้ยงชีพ … ในอัตราไม่เกินร้อยละ 30 ของเงินได้พึงประเมินที่ได้รับซึ่งต้องเสียภาษีเงินได้'
                    .'ในปีภาษีนั้น ทั้งนี้ เฉพาะส่วนที่ไม่เกิน 500,000 บาท สำหรับปีภาษีนั้น',
            ],
            [
                'allowance_code' => 'THAI_ESG',
                'code' => 'PND90_2568_THAI_ESG',
                'method' => 'percentage_limit',
                'fixed_amount' => null,
                'percentage' => '30.0000',
                'percentage_base' => PercentageBaseResolver::GROSS_AFTER_EXEMPTION,
                'maximum_amount' => '300000.00',
                'source' => self::INSTRUCTIONS.', page 14, ใบแนบ item 18.1 — หากผู้มีเงินได้มีการซื้อหน่วยลงทุนระหว่าง'
                    .'วันที่ 1 มกราคม พ.ศ. 2567 ถึงวันที่ 31 ธันวาคม พ.ศ. 2569 … ไม่เกินร้อยละ 30 ของเงินได้พึงประเมิน'
                    .'ที่ได้รับซึ่งต้องเสียภาษีเงินได้ในปีภาษีนั้น ทั้งนี้ เฉพาะส่วนที่ไม่เกิน 300,000 บาท '
                    .'(ปีภาษี 2568 อยู่ภายในช่วงดังกล่าวทั้งปี จึงใช้เพดาน 300,000 บาท)',
            ],
            [
                'allowance_code' => 'THAI_ESGX',
                'code' => 'PND90_2568_THAI_ESGX',
                'method' => 'percentage_limit',
                'fixed_amount' => null,
                'percentage' => '30.0000',
                'percentage_base' => PercentageBaseResolver::GROSS_AFTER_EXEMPTION,
                'maximum_amount' => '300000.00',
                'source' => self::INSTRUCTIONS.', page 14, ใบแนบ item 19.1 — ได้รับยกเว้นไม่ต้องนำมารวมคำนวณ'
                    .'เพื่อเสียภาษีเงินได้ ในอัตราไม่เกินร้อยละ 30 ของเงินได้พึงประเมินเฉพาะส่วนที่ไม่เกิน 300,000 บาท '
                    .'สำหรับปีภาษีนั้น',
            ],
            [
                'allowance_code' => 'THAI_ESGX_SWITCH',
                'code' => 'PND90_2568_THAI_ESGX_SWITCH',
                'method' => 'actual',
                'fixed_amount' => null,
                'percentage' => null,
                'percentage_base' => null,
                'maximum_amount' => '300000.00',
                'source' => self::INSTRUCTIONS.', page 15, ใบแนบ item 19.2 — ยกเว้นภาษีเงินได้เท่ากับจำนวนมูลค่า'
                    .'หน่วยลงทุนที่สับเปลี่ยนดังกล่าวแต่ไม่เกิน 500,000 บาท เป็นระยะเวลา ดังนี้ '
                    .'(1) ปีภาษี 2568 ให้ได้รับยกเว้นภาษีเงินได้เฉพาะส่วนที่ไม่เกิน 300,000 บาท',
            ],
            [
                'allowance_code' => 'EASY_E_RECEIPT',
                'code' => 'PND90_2568_EASY_E_RECEIPT',
                'method' => 'actual',
                'fixed_amount' => null,
                'percentage' => null,
                'percentage_base' => null,
                'maximum_amount' => '30000.00',
                'source' => self::INSTRUCTIONS.', page 13, ใบแนบ item 17.1 — การใช้สิทธิยกเว้นภาษีเงินได้สำหรับเงินได้'
                    .'เท่าที่ได้จ่ายเป็นค่าซื้อสินค้าหรือค่าบริการ … ตามจำนวนที่จ่ายจริง แต่ไม่เกิน 30,000 บาท '
                    .'(ใบแนบ ข้อ 17.1–17.3)',
            ],
            [
                'allowance_code' => 'EASY_E_RECEIPT_OTOP',
                'code' => 'PND90_2568_EASY_E_RECEIPT_OTOP',
                'method' => 'actual',
                'fixed_amount' => null,
                'percentage' => null,
                'percentage_base' => null,
                'maximum_amount' => '20000.00',
                'source' => self::INSTRUCTIONS.', page 13, ใบแนบ item 17.2 — หักลดหย่อนได้อีกตามจำนวนที่จ่ายจริง '
                    .'แต่ไม่เกิน 20,000 บาท สำหรับค่าซื้อสินค้าหนึ่งตำบลหนึ่งผลิตภัณฑ์ (OTOP) … วิสาหกิจชุมชน … '
                    .'วิสาหกิจเพื่อสังคม (ใบแนบ ข้อ 17.4)',
            ],
        ];
    }

    public function run(): void
    {
        DB::transaction(function (): void {
            $year = DB::table('tax_years')->where('year', 2568)->first();
            $version = $year ? DB::table('tax_rule_versions')->where('tax_year_id', $year->id)
                ->where('version', '2568.1')->lockForUpdate()->first() : null;
            if (! $version || ! in_array($version->status, ['published', 'archived'], true)) {
                throw new RuntimeException('Published rule version 2568.1 must exist before seeding allowance rules.');
            }

            foreach (self::approvedRules() as $rule) {
                $type = DB::table('allowance_types')->where('code', $rule['allowance_code'])->first();
                if (! $type) {
                    throw new RuntimeException('Unknown allowance type '.$rule['allowance_code'].'.');
                }
                $values = [
                    'tax_year_id' => $year->id, 'rule_version_id' => $version->id,
                    'allowance_type_id' => $type->id, 'code' => $rule['code'], 'method' => $rule['method'],
                    'fixed_amount' => $rule['fixed_amount'], 'percentage' => $rule['percentage'],
                    'maximum_amount' => $rule['maximum_amount'], 'limit_amount' => $rule['maximum_amount'],
                    'percentage_base' => $rule['percentage_base'] ?? null,
                    'minimum_amount' => null, 'conditions' => null, 'active' => true,
                    'source_reference' => $rule['source'],
                ];
                $existing = DB::table('allowance_rules')->where('rule_version_id', $version->id)
                    ->where('allowance_type_id', $type->id)->get();
                if ($existing->isNotEmpty()) {
                    if ($existing->count() !== 1) {
                        throw new RuntimeException('Conflicting allowance rules require review: '.$rule['allowance_code']);
                    }
                    foreach (['method', 'code', 'source_reference', 'percentage_base'] as $key) {
                        if ((string) $existing->sole()->$key !== (string) $values[$key]) {
                            throw new RuntimeException("Existing allowance rule {$rule['code']} differs at $key; refusing to overwrite.");
                        }
                    }
                    foreach (['fixed_amount', 'percentage', 'maximum_amount'] as $key) {
                        $actual = $existing->sole()->$key;
                        if (($values[$key] === null) !== ($actual === null)
                            || ($values[$key] !== null && $actual != $values[$key])) {
                            throw new RuntimeException("Existing allowance rule {$rule['code']} differs at $key; refusing to overwrite.");
                        }
                    }

                    continue;
                }
                // Explicit M7.2-approved completion of the published 2568.1 dataset.
                DB::table('allowance_rules')->insert([...$values, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
    }
}

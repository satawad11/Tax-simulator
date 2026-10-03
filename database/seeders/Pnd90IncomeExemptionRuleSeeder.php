<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * เงินได้ที่ได้รับยกเว้น that ใบแนบ takes after expenses, and the ข้อ 22 เมืองหลัก ลดหย่อน.
 *
 * Every number below is quoted from `docs/tax-source/PND90-2568-filing-instructions.pdf`. Nothing
 * is inferred, and a line whose ceiling the booklet does not state is deliberately absent — see
 * `docs/tax/BLOCKED_ALLOWANCE_FEASIBILITY_2568.md` for the reading of all six blocked lines and
 * why ข้อ 7.6, ข้อ 12, ข้อ 16 and ข้อ 22 เมืองรอง stay out.
 *
 * These rules belong to version 2568.3, never to the archived 2568.1 baseline: a rule change is a
 * new version, which is the guarantee `ProtectsPublishedRules` exists to keep.
 *
 * What the engine does **not** do is assert entitlement. Each line carries conditions the filer
 * must affirm — a construction date, a registration status, a geographic zone — that no declared
 * figure can establish. The engine applies the printed arithmetic to what the filer states, the
 * same footing as exempt income, dependants and every other allowance in this product.
 */
class Pnd90IncomeExemptionRuleSeeder extends Seeder
{
    public const VERSION = '2568.3';

    /**
     * ใบแนบ lines deducted from เงินได้พึงประเมิน after มาตรา 42 ทวิ ถึง มาตรา 46.
     *
     * @return list<array<string, mixed>>
     */
    public static function exemptionRules(): array
    {
        return [
            [
                'code' => 'CCTV_SYSTEM',
                'name' => 'ค่าซื้อและค่าติดตั้งระบบกล้องโทรทัศน์วงจรปิด',
                // "เป็นจำ�นวนร้อยละหนึ่งร้อยของเงินได้เท่าที่ได้จ่ายเป็นค่าซื้อและค่าติดตั้ง…"
                'method' => 'percentage',
                'percentage' => '100.0000',
                'step_amount' => null,
                'grant_per_step' => null,
                // The booklet prints no ceiling for this line.
                'maximum_amount' => null,
                'declarations' => [
                    'ข้าพเจ้ามีเงินได้พึงประเมินตามมาตรา 40 (5) (6) (7) หรือ (8) และยื่นโดยคำนวณหักค่าใช้จ่ายตามความจำเป็นและสมควร',
                    'ระบบกล้องโทรทัศน์วงจรปิดไม่ผ่านการใช้งานมาก่อน และติดตั้ง ณ สถานประกอบกิจการที่ตั้งอยู่ในเขตพัฒนาพิเศษเฉพาะกิจ',
                    'ค่าซื้อและค่าติดตั้งจ่ายระหว่างวันที่ 1 มกราคม 2567 ถึงวันที่ 31 ธันวาคม 2569 และมีหลักฐานพร้อมให้เจ้าพนักงานประเมินตรวจสอบ',
                ],
                'source' => 'PND90-2568-filing-instructions.pdf ใบแนบ ข้อ 13 (13.1–13.4)',
            ],
            [
                'code' => 'NEW_HOME_CONSTRUCTION',
                'name' => 'ค่าจ้างก่อสร้างอาคารเพื่ออยู่อาศัยขึ้นใหม่',
                // "จำ�นวน 10,000 บาท ต่อทุกจำ�นวน 1,000,000 บาท ตามจำ�นวนที่จ่ายจริง
                //  แต่รวมแล้วไม่เกิน 100,000 บาท และไม่เกินหนึ่งหลัง"
                'method' => 'stepped_grant',
                'percentage' => null,
                'step_amount' => '1000000.00',
                'grant_per_step' => '10000.00',
                'maximum_amount' => '100000.00',
                'declarations' => [
                    'เป็นค่าจ้างก่อสร้างอาคารเพื่ออยู่อาศัยขึ้นใหม่ไม่เกินหนึ่งหลัง ไม่ใช่การต่อเติม ดัดแปลง ซ่อมแซม หรือรื้อถอน',
                    'สัญญาจ้างทำขึ้นและเริ่มดำเนินการก่อสร้างตั้งแต่วันที่ 9 เมษายน 2567 ถึงวันที่ 31 ธันวาคม 2568 และการก่อสร้างแล้วเสร็จในปีภาษีนี้',
                    'ผู้รับจ้างเป็นผู้ประกอบการจดทะเบียนภาษีมูลค่าเพิ่ม ไม่ใช่ผู้ประกอบกิจการขายอสังหาริมทรัพย์เป็นทางค้าหรือหากำไร และแยกสัญญาซื้อขายที่ดินออกจากสัญญาจ้างก่อสร้าง',
                    'ได้เสียอากรแสตมป์เป็นตัวเงินผ่านระบบอินเทอร์เน็ต และมีใบกำกับภาษี สัญญาจ้าง และใบอนุญาตก่อสร้างพร้อมให้ตรวจสอบ',
                    'จำนวนที่กรอกเป็นส่วนของข้าพเจ้าเองแล้ว หากร่วมทำสัญญากับผู้มีเงินได้รายอื่นได้เฉลี่ยตามส่วนของจำนวนผู้มีเงินได้',
                ],
                'source' => 'PND90-2568-filing-instructions.pdf ใบแนบ ข้อ 20 (20.1–20.6)',
            ],
        ];
    }

    /**
     * ใบแนบ ข้อ 22 เมืองหลัก, which the booklet writes as หักลดหย่อน and therefore belongs in the
     * allowance block rather than the exemption stage above.
     *
     * It is two lines because the booklet grants two: the first 10,000 on any full tax invoice,
     * and a further amount for the part above 10,000 **only** where the invoice is electronic.
     * เมืองรอง is absent — its 1.5× multiplier for the part above 10,000 is printed with no
     * ceiling at all, and inventing one is the single thing this project forbids.
     *
     * @return list<array<string, mixed>>
     */
    public static function travelAllowanceRules(): array
    {
        return [
            [
                'allowance_code' => 'DOMESTIC_TRAVEL_MAIN_CITY',
                'name' => 'ค่าท่องเที่ยวในเมืองหลัก (ใบกำกับภาษีกระดาษ หรือ e-Tax Invoice)',
                'code' => 'PND90_DOMESTIC_TRAVEL_MAIN_CITY',
                // "สามารถหักลดหย่อนได้ตามจำ�นวนที่จ่ายจริง แต่ไม่เกิน 10,000 บาท"
                'method' => 'actual',
                'maximum_amount' => '10000.00',
                'source' => 'PND90-2568-filing-instructions.pdf ใบแนบ ข้อ 22.1 วรรคหนึ่ง',
            ],
            [
                'allowance_code' => 'DOMESTIC_TRAVEL_MAIN_CITY_ETAX',
                'name' => 'ค่าท่องเที่ยวในเมืองหลัก ส่วนที่เกิน 10,000 บาท (เฉพาะ e-Tax Invoice)',
                'code' => 'PND90_DOMESTIC_TRAVEL_MAIN_CITY_ETAX',
                // "สามารถหักลดหย่อนได้อีกสำ�หรับส่วนที่เกิน 10,000 บาท ตามจำ�นวนที่จ่ายจริง
                //  แต่ไม่เกิน 10,000 บาท"
                'method' => 'actual',
                'maximum_amount' => '10000.00',
                'source' => 'PND90-2568-filing-instructions.pdf ใบแนบ ข้อ 22.1 วรรคสอง',
            ],
        ];
    }

    public function run(): void
    {
        DB::transaction(function (): void {
            $year = DB::table('tax_years')->where('year', 2568)->first();
            $version = $year ? DB::table('tax_rule_versions')->where('tax_year_id', $year->id)
                ->where('version', self::VERSION)->lockForUpdate()->first() : null;
            if (! $version) {
                throw new RuntimeException('Rule version '.self::VERSION.' must exist before seeding its rules.');
            }

            foreach (self::exemptionRules() as $rule) {
                $values = [
                    'tax_year_id' => $year->id, 'rule_version_id' => $version->id,
                    'code' => $rule['code'], 'name' => $rule['name'], 'method' => $rule['method'],
                    'percentage' => $rule['percentage'], 'step_amount' => $rule['step_amount'],
                    'grant_per_step' => $rule['grant_per_step'], 'maximum_amount' => $rule['maximum_amount'],
                    'active' => true,
                    'conditions' => json_encode(['declarations' => $rule['declarations']],
                        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'source_reference' => $rule['source'],
                ];
                $existing = DB::table('income_exemption_rules')
                    ->where('rule_version_id', $version->id)->where('code', $rule['code'])->first();

                if ($existing) {
                    // Idempotent like every other rule seeder, and equally unwilling to paper over
                    // a difference: a changed number is a decision, not a reseed.
                    foreach (['method', 'percentage', 'step_amount', 'grant_per_step', 'maximum_amount'] as $key) {
                        $actual = $existing->$key;
                        if (($values[$key] === null) !== ($actual === null)
                            || ($values[$key] !== null && $actual != $values[$key])) {
                            throw new RuntimeException("Existing exemption rule {$rule['code']} differs at $key; refusing to overwrite.");
                        }
                    }

                    continue;
                }

                DB::table('income_exemption_rules')->insert([...$values, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
    }
}

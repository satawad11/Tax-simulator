<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Milestone 06 approved rule-based recommendations for published version 2568.1.
 *
 * Only reminders that ask the user to verify something, plus explanations of the
 * simulated result, are seeded. No rule grants eligibility or computes a benefit.
 */
class RecommendationRuleSeeder extends Seeder
{
    /** @return list<array<string, mixed>> */
    public static function approvedRules(): array
    {
        return [
            [
                'code' => 'CHECK_SOCIAL_SECURITY',
                'type' => 'POTENTIAL_ALLOWANCE',
                'priority' => 'medium',
                'title' => 'ตรวจสอบเงินสมทบประกันสังคม',
                // M7.5 — the wording no longer implies the simulator will apply this line.
                // ใบแนบ ข้อ 12 refers its numeric ceiling to the social security act, which is
                // not a repository source, so SOCIAL_SECURITY is PARTIAL_BLOCKED and a positive
                // amount is refused with 422. Telling the taxpayer to add it here would send
                // them at a door the API closes; telling them to check it outside the simulator
                // is still useful and is all the source supports.
                'message_template' => 'หากคุณมีการจ่ายเงินสมทบประกันสังคมในปีภาษีนี้ ควรตรวจสอบกับเอกสารของคุณเอง '
                    .'ทั้งนี้ระบบจำลองยังไม่รองรับการคำนวณรายการนี้ เนื่องจากใบแนบ ข้อ 12 อ้างอิงเพดานไปยังกฎหมายว่าด้วยการประกันสังคม '
                    .'ซึ่งไม่ได้อยู่ในเอกสารต้นทางของโครงการ',
                'conditions' => ['income_types_only' => 'SECTION_40_1', 'allowance_not_present' => 'SOCIAL_SECURITY'],
                'action_type' => 'OPEN_ALLOWANCE',
            ],
            [
                'code' => 'PAYABLE_DUE_TO_LOW_WITHHOLDING',
                'type' => 'PAYMENT',
                'priority' => 'high',
                'title' => 'ภาษีที่ต้องชำระเพิ่มจากการจำลอง',
                'message_template' => 'จากข้อมูลที่กรอก ภาษีหัก ณ ที่จ่ายและเครดิตที่ระบบรับรู้ ({{withholding}} บาท) ต่ำกว่าภาษีที่คำนวณได้ ({{calculated_tax}} บาท) จึงมีผลเป็นภาษีที่ต้องชำระเพิ่ม {{result_amount}} บาท ควรตรวจสอบยอดภาษีหัก ณ ที่จ่ายและข้อมูลที่กรอกอีกครั้ง',
                'conditions' => ['result_status' => 'PAYABLE', 'withholding_less_than_tax' => true],
                'action_type' => 'REVIEW_WITHHOLDING',
            ],
            [
                'code' => 'REFUND_DUE_TO_EXCESS_WITHHOLDING',
                'type' => 'REFUND',
                'priority' => 'medium',
                'title' => 'ประมาณการภาษีชำระไว้เกิน',
                'message_template' => 'จากข้อมูลที่กรอก ภาษีหัก ณ ที่จ่ายและเครดิตที่ระบบรับรู้สูงกว่าภาษีที่คำนวณได้ ({{calculated_tax}} บาท) จึงมีผลเป็นประมาณการชำระไว้เกิน {{result_amount}} บาท ควรตรวจสอบเอกสารประกอบก่อนนำผลไปใช้จริง',
                'conditions' => ['result_status' => 'REFUND'],
                'action_type' => 'REVIEW_REFUND_GUIDANCE',
            ],
            [
                'code' => 'VERIFY_UNVERIFIED_ALLOWANCE_RULE',
                'type' => 'MISSING_INFORMATION',
                'priority' => 'high',
                'title' => 'ตรวจสอบข้อมูลอ้างอิงของค่าลดหย่อนที่กรอก',
                'message_template' => 'ระบบยังไม่มีกฎตัวเลขที่ยืนยันแล้วสำหรับรายการลดหย่อนที่กรอก จึงยังไม่นำมาคำนวณเป็นสิทธิลดหย่อนในระบบจำลอง ควรตรวจสอบเอกสารและแหล่งอ้างอิงของรายการดังกล่าว',
                'conditions' => ['warning_present' => 'UNVERIFIED_ALLOWANCE_RULE'],
                'action_type' => 'REVIEW_ALLOWANCE_SOURCE',
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
                throw new RuntimeException('Published rule version 2568.1 must exist before seeding recommendation rules.');
            }
            foreach (self::approvedRules() as $rule) {
                $values = [
                    'tax_year_id' => $year->id,
                    'rule_version_id' => $version->id,
                    'code' => $rule['code'],
                    'category' => $rule['type'],
                    'type' => $rule['type'],
                    'priority' => $rule['priority'],
                    'title' => $rule['title'],
                    'message' => $rule['message_template'],
                    'message_template' => $rule['message_template'],
                    'conditions' => json_encode($rule['conditions'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'action_type' => $rule['action_type'],
                    'active' => true,
                    'source_reference' => 'MILESTONE_06_PROMPT.md: Seed Recommendation Rules',
                ];
                $existing = DB::table('recommendation_rules')->where('rule_version_id', $version->id)
                    ->where('code', $rule['code'])->get();
                if ($existing->isNotEmpty()) {
                    if ($existing->count() !== 1) {
                        throw new RuntimeException('Conflicting recommendation rules require review: '.$rule['code']);
                    }
                    // A recommendation's wording is guidance, not a tax rule, and M7.5 had to
                    // correct one message that pointed at a path the API now refuses. Text is
                    // therefore brought up to the approved value; everything that decides when
                    // a recommendation fires still refuses to be overwritten.
                    $text = ['title', 'message', 'message_template'];
                    foreach (array_diff_key($values, array_flip($text)) as $key => $value) {
                        $actual = $existing->sole()->$key;
                        if ($key === 'conditions'
                            ? json_decode((string) $actual, true) !== $rule['conditions']
                            : (string) $actual !== (string) (is_bool($value) ? (int) $value : $value)) {
                            throw new RuntimeException("Existing recommendation rule differs at $key; refusing to overwrite.");
                        }
                    }
                    if (array_diff_assoc(array_intersect_key($values, array_flip($text)),
                        array_intersect_key((array) $existing->sole(), array_flip($text))) !== []) {
                        DB::table('recommendation_rules')->where('id', $existing->sole()->id)
                            ->update([...array_intersect_key($values, array_flip($text)), 'updated_at' => now()]);
                    }

                    continue;
                }
                // Explicit M6-approved, narrowly scoped amendment to published 2568.1.
                // The query builder bypass is confined here; model immutability guards remain unchanged.
                DB::table('recommendation_rules')->insert([...$values, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
    }
}

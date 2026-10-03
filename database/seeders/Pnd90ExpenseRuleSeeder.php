<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Milestone 07.1 — PND90 expense rules reconciled from ภ.ง.ด.90.
 *
 * Every row below is a value printed on the form. A subcategory whose form line shows a blank
 * percentage ("หักค่าใช้จ่าย ร้อยละ ............") is deliberately absent: the rate comes from
 * outside this document and guessing it is forbidden.
 *
 * Source: docs/tax-source/ภงด.90.pdf (pages cited per rule).
 */
class Pnd90ExpenseRuleSeeder extends Seeder
{
    public const SOURCE = 'docs/tax-source/ภงด.90.pdf';

    /**
     * Source-verified rules only.
     *
     * @return list<array<string, mixed>>
     */
    public static function approvedRules(): array
    {
        return [
            // ข้อ 1 combines 40(1) and 40(2) into one base, then deducts once. Both rows carry
            // the same mechanics and the same expense_group; the resolver verifies they agree.
            [
                'code' => 'PND90_SECTION_40_2_EXPENSE', 'income_type' => 'SECTION_40_2', 'income_subtype' => null,
                'expense_group' => 'SECTION_40_1_2', 'method' => 'percentage_limit',
                'percentage' => '50.0000', 'maximum_amount' => '100000.00', 'fixed_amount' => null,
                'source' => self::SOURCE.', page 2, ข้อ 1 item 5 — หักค่าใช้จ่าย (ร้อยละ 50 แต่ไม่เกิน 100,000 บาท) applied to item 4 = 40(1) less exempt items plus 40(2)',
            ],
            [
                'code' => 'PND90_SECTION_40_3_ANNUITY_EXPENSE', 'income_type' => 'SECTION_40_3',
                'income_subtype' => 'ANNUITY_FROM_WILL_OR_JUDGMENT', 'expense_group' => null,
                'method' => 'fixed', 'percentage' => null, 'maximum_amount' => null, 'fixed_amount' => '0.00',
                'source' => self::SOURCE.', page 2, ข้อ 2 item 1 — the form prints no หักค่าใช้จ่าย line for this subcategory; the amount carries straight to คงเหลือ',
            ],
            [
                'code' => 'PND90_SECTION_40_3_COPYRIGHT_EXPENSE', 'income_type' => 'SECTION_40_3',
                'income_subtype' => 'COPYRIGHT_GOODWILL_OTHER_RIGHTS', 'expense_group' => null,
                'method' => 'percentage_or_actual', 'percentage' => '50.0000', 'maximum_amount' => '100000.00', 'fixed_amount' => null,
                'source' => self::SOURCE.', page 2, ข้อ 2 item 2 — หักค่าใช้จ่าย ☐ ร้อยละ 50 (แต่ไม่เกิน 100,000 บาท) ☐ จริง',
            ],
            [
                'code' => 'PND90_SECTION_40_4_EXPENSE', 'income_type' => 'SECTION_40_4', 'income_subtype' => null,
                'expense_group' => null, 'method' => 'fixed',
                'percentage' => null, 'maximum_amount' => null, 'fixed_amount' => '0.00',
                'source' => self::SOURCE.', page 2, ข้อ 3 — the form prints no หักค่าใช้จ่าย line anywhere in this block; every item carries straight to รวม',
            ],
            [
                'code' => 'PND90_SECTION_40_5_RENT_BUILDING_EXPENSE', 'income_type' => 'SECTION_40_5',
                'income_subtype' => 'RENT_BUILDING_OR_RAFT', 'expense_group' => null,
                'method' => 'percentage_or_actual', 'percentage' => '30.0000', 'maximum_amount' => null, 'fixed_amount' => null,
                'source' => self::SOURCE.', page 2, ข้อ 4 item 1 (1) — บ้าน โรงเรือน สิ่งปลูกสร้างอย่างอื่น หรือแพ: หักค่าใช้จ่าย ☐ ร้อยละ 30 ☐ จริง',
            ],
            [
                'code' => 'PND90_SECTION_40_5_HIRE_PURCHASE_EXPENSE', 'income_type' => 'SECTION_40_5',
                'income_subtype' => 'HIRE_PURCHASE_BREACH', 'expense_group' => null,
                'method' => 'percentage', 'percentage' => '20.0000', 'maximum_amount' => null, 'fixed_amount' => null,
                'source' => self::SOURCE.', page 3, ข้อ 4 item 2 — การผิดสัญญาเช่าซื้อทรัพย์สิน/ซื้อขายเงินผ่อนฯ: หักค่าใช้จ่ายร้อยละ 20 (no จริง option printed)',
            ],
            [
                'code' => 'PND90_SECTION_40_6_MEDICAL_EXPENSE', 'income_type' => 'SECTION_40_6',
                'income_subtype' => 'MEDICAL_PRACTICE', 'expense_group' => null,
                'method' => 'percentage_or_actual', 'percentage' => '60.0000', 'maximum_amount' => null, 'fixed_amount' => null,
                'source' => self::SOURCE.', page 3, ข้อ 5 item 1 — การประกอบโรคศิลปะ: หักค่าใช้จ่าย ☐ ร้อยละ 60 ☐ จริง',
            ],
            [
                'code' => 'PND90_SECTION_40_6_FINE_ARTS_EXPENSE', 'income_type' => 'SECTION_40_6',
                'income_subtype' => 'FINE_ARTS', 'expense_group' => null,
                'method' => 'percentage_or_actual', 'percentage' => '60.0000', 'maximum_amount' => null, 'fixed_amount' => null,
                'source' => self::SOURCE.', page 3, ข้อ 5 item 2 — ประณีตศิลปกรรม: หักค่าใช้จ่าย ☐ ร้อยละ 60 ☐ จริง',
            ],
            [
                'code' => 'PND90_SECTION_40_6_OTHER_EXPENSE', 'income_type' => 'SECTION_40_6',
                'income_subtype' => 'OTHER_LIBERAL_PROFESSION', 'expense_group' => null,
                'method' => 'percentage_or_actual', 'percentage' => '30.0000', 'maximum_amount' => null, 'fixed_amount' => null,
                'source' => self::SOURCE.', page 3, ข้อ 5 items 3–4 — อื่นๆ: หักค่าใช้จ่าย ☐ ร้อยละ 30 ☐ จริง',
            ],
            [
                'code' => 'PND90_SECTION_40_7_EXPENSE', 'income_type' => 'SECTION_40_7', 'income_subtype' => null,
                'expense_group' => null, 'method' => 'percentage_or_actual',
                'percentage' => '60.0000', 'maximum_amount' => null, 'fixed_amount' => null,
                'source' => self::SOURCE.', page 3, ข้อ 6 — เงินได้จากการรับเหมาฯ: หักค่าใช้จ่าย ☐ ร้อยละ 60 ☐ จริง',
            ],
            [
                'code' => 'PND90_SECTION_40_8_MUTUAL_FUND_EXPENSE', 'income_type' => 'SECTION_40_8',
                'income_subtype' => 'MUTUAL_FUND_PROFIT_SHARE', 'expense_group' => null,
                'method' => 'fixed', 'percentage' => null, 'maximum_amount' => null, 'fixed_amount' => '0.00',
                'source' => self::SOURCE.', page 3, ข้อ 7 item 2 — the form prints no หักค่าใช้จ่าย line for this subcategory',
            ],
            [
                'code' => 'PND90_SECTION_40_8_INHERITED_PROPERTY_EXPENSE', 'income_type' => 'SECTION_40_8',
                'income_subtype' => 'IMMOVABLE_PROPERTY_INHERITED_OR_GIFTED', 'expense_group' => null,
                'method' => 'percentage', 'percentage' => '50.0000', 'maximum_amount' => null, 'fixed_amount' => null,
                'source' => self::SOURCE.', page 3, ข้อ 7 item 3 (1) — เป็นมรดก หรือได้รับโดยเสน่หา: หักค่าใช้จ่ายร้อยละ 50 (no จริง option printed)',
            ],
            [
                'code' => 'PND90_SECTION_40_8_GIFT_RECEIVED_EXPENSE', 'income_type' => 'SECTION_40_8',
                'income_subtype' => 'GIFT_OR_SUPPORT_RECEIVED', 'expense_group' => null,
                'method' => 'fixed', 'percentage' => null, 'maximum_amount' => null, 'fixed_amount' => '0.00',
                'source' => self::SOURCE.', page 3, ข้อ 7 item 4 — the form prints no หักค่าใช้จ่าย line for this subcategory',
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
                throw new RuntimeException('Published rule version 2568.1 must exist before seeding PND90 expense rules.');
            }

            // ข้อ 1 deducts once across 40(1) and 40(2); the existing approved 40(1) row joins
            // that group. Mechanics stay exactly as approved in Milestone 04.
            $employment = DB::table('expense_rules')->where('rule_version_id', $version->id)
                ->where('code', 'PND91_SECTION_40_1_EXPENSE')->first();
            if (! $employment) {
                throw new RuntimeException('The approved SECTION_40_1 expense rule must be seeded before the PND90 rules.');
            }
            // Only the grouping is set here; EmploymentExpenseRuleSeeder owns every other
            // field of that row, so the two seeders never contend over the same value.
            if ($employment->expense_group === null) {
                DB::table('expense_rules')->where('id', $employment->id)
                    ->update(['expense_group' => 'SECTION_40_1_2', 'updated_at' => now()]);
            } elseif ($employment->expense_group !== 'SECTION_40_1_2') {
                throw new RuntimeException('The SECTION_40_1 expense rule belongs to an unexpected expense group; review before seeding.');
            }

            foreach (self::approvedRules() as $rule) {
                $incomeType = DB::table('income_types')->where('code', $rule['income_type'])->first();
                if (! $incomeType) {
                    throw new RuntimeException('Unknown income type '.$rule['income_type'].'.');
                }
                $values = [
                    'tax_year_id' => $year->id, 'rule_version_id' => $version->id,
                    'income_type_id' => $incomeType->id, 'income_subtype' => $rule['income_subtype'],
                    'expense_group' => $rule['expense_group'], 'code' => $rule['code'],
                    'method' => $rule['method'], 'percentage' => $rule['percentage'],
                    'maximum_amount' => $rule['maximum_amount'], 'limit_amount' => $rule['maximum_amount'],
                    'fixed_amount' => $rule['fixed_amount'], 'minimum_amount' => null,
                    'conditions' => null, 'active' => true, 'source_reference' => $rule['source'],
                ];

                $existing = DB::table('expense_rules')->where('rule_version_id', $version->id)
                    ->where('code', $rule['code'])->get();
                if ($existing->isNotEmpty()) {
                    if ($existing->count() !== 1) {
                        throw new RuntimeException('Conflicting expense rules require review: '.$rule['code']);
                    }
                    foreach (['method', 'income_subtype', 'expense_group', 'source_reference'] as $key) {
                        if ((string) $existing->sole()->$key !== (string) $values[$key]) {
                            throw new RuntimeException("Existing expense rule {$rule['code']} differs at $key; refusing to overwrite.");
                        }
                    }
                    foreach (['percentage', 'maximum_amount', 'fixed_amount'] as $key) {
                        $actual = $existing->sole()->$key;
                        if (($values[$key] === null) !== ($actual === null)
                            || ($values[$key] !== null && $actual != $values[$key])) {
                            throw new RuntimeException("Existing expense rule {$rule['code']} differs at $key; refusing to overwrite.");
                        }
                    }

                    continue;
                }
                // Explicit M7.1-approved completion of the published 2568.1 dataset. The query
                // builder bypass is confined here; model immutability guards stay unchanged.
                DB::table('expense_rules')->insert([...$values, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
    }
}

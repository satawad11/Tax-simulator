<?php

namespace Database\Seeders;

use App\Services\Tax\ExpenseActivityCatalogue;
use App\Services\Tax\PropertyHoldingPeriodCatalogue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Milestone 07.4 — the three ภ.ง.ด.90 expense subcategories the form prints with a blank
 * percentage, resolved from the filing instructions.
 *
 * Source: docs/tax-source/PND90-2568-filing-instructions.pdf.
 *
 * Each of the three prints `หักค่าใช้จ่าย ☐ ร้อยละ ............ ☐ จริง` on the form because the
 * rate is not a property of the income category. M7.1 could read only the form and therefore
 * left all three unseeded. The booklet supplies every missing rate:
 *
 *   ข้อ 4 item 1 (2)–(4)  page 3 — five asset classes at 30/20/15/30/10, one subtype each;
 *   ข้อ 7 item 1          page 17 ตารางที่ 2 — 44 numbered activities, keyed by expense_activity;
 *   ข้อ 7 item 3 (2)      page 3 — eight holding-period bands at 92/84/77/71/65/60/55/50.
 *
 * Every rule keeps the form's `จริง` checkbox, so each is an election method: the taxpayer
 * chooses the printed rate or their actual expense, and the engine never chooses for them.
 */
class Pnd90SubcategoryExpenseRuleSeeder extends Seeder
{
    public const SOURCE = 'docs/tax-source/PND90-2568-filing-instructions.pdf';

    /**
     * ข้อ 4 การหักค่าใช้จ่าย (1) วิธีที่ 2, booklet page 3.
     *
     * @var array<string, array{string, string, string}> subtype => [code suffix, rate, source label]
     */
    public const RENT_CLASSES = [
        'RENT_LAND_AGRICULTURAL' => ['RENT_LAND_AGRICULTURAL', '20.0000', '(ข) ที่ดินที่ใช้ในการเกษตรกรรม ร้อยละ 20'],
        'RENT_LAND_NON_AGRICULTURAL' => ['RENT_LAND_NON_AGRICULTURAL', '15.0000', '(ค) ที่ดินที่มิได้ใช้ในการเกษตรกรรม ร้อยละ 15'],
        'RENT_VEHICLE' => ['RENT_VEHICLE', '30.0000', '(ง) ยานพาหนะ ร้อยละ 30'],
        'RENT_OTHER_PROPERTY' => ['RENT_OTHER_PROPERTY', '10.0000', '(จ) ทรัพย์สินอย่างอื่น ร้อยละ 10'],
    ];

    /** @return list<array<string, mixed>> */
    public static function approvedRules(): array
    {
        $rules = [];

        foreach (self::RENT_CLASSES as $subtype => [$suffix, $percentage, $label]) {
            $rules[] = [
                'code' => 'PND90_SECTION_40_5_'.$suffix.'_EXPENSE',
                'income_type' => 'SECTION_40_5', 'income_subtype' => $subtype,
                'expense_activity' => null, 'holding_years_min' => null, 'holding_years_max' => null,
                'method' => 'percentage_or_actual', 'percentage' => $percentage,
                'maximum_amount' => null, 'fixed_amount' => null, 'tiers' => [],
                'source' => self::SOURCE.', page 3, ข้อ 4 การหักค่าใช้จ่าย (1) — วิธีที่ 1 หักค่าใช้จ่ายจริง '
                    .'วิธีที่ 2 หักค่าใช้จ่ายเป็นการเหมาในอัตราดังนี้ '.$label,
            ];
        }

        foreach (ExpenseActivityCatalogue::ACTIVITIES as $activity => [$row, $label, $percentage]) {
            $base = [
                'code' => 'PND90_SECTION_40_8_'.$activity.'_EXPENSE',
                'income_type' => ExpenseActivityCatalogue::INCOME_TYPE,
                'income_subtype' => ExpenseActivityCatalogue::INCOME_SUBTYPE,
                'expense_activity' => $activity, 'holding_years_min' => null, 'holding_years_max' => null,
                'fixed_amount' => null, 'tiers' => [],
                'source' => self::SOURCE.', page 17, ตารางที่ 2 row ('.$row.') — '.$label,
            ];
            if ($activity === ExpenseActivityCatalogue::PERFORMER) {
                // Row (1) prints two bands and one combined ceiling, so it is stored as bands.
                $rules[] = [...$base, 'method' => 'tiered_or_actual', 'percentage' => null,
                    'maximum_amount' => ExpenseActivityCatalogue::PERFORMER_MAXIMUM,
                    'tiers' => [
                        ['tier_code' => 'FIRST_300000', 'sort_order' => 1,
                            'threshold_amount' => ExpenseActivityCatalogue::PERFORMER_BAND_THRESHOLD,
                            'percentage' => ExpenseActivityCatalogue::PERFORMER_FIRST_BAND_PERCENTAGE,
                            'source' => self::SOURCE.', page 17, ตารางที่ 2 row (1) (ก) — สำหรับเงินได้ส่วนที่ไม่เกิน 300,000 บาท ร้อยละ 60'],
                        ['tier_code' => 'ABOVE_300000', 'sort_order' => 2, 'threshold_amount' => null,
                            'percentage' => ExpenseActivityCatalogue::PERFORMER_SECOND_BAND_PERCENTAGE,
                            'source' => self::SOURCE.', page 17, ตารางที่ 2 row (1) (ข) — สำหรับเงินได้ส่วนที่เกิน 300,000 บาท ร้อยละ 40'],
                    ],
                ];

                continue;
            }
            if ($percentage === null) {
                // Row (44) prints no rate at all: "ให้หักค่าใช้จ่ายจริงตามความจำเป็นและสมควร".
                $rules[] = [...$base, 'method' => 'actual', 'percentage' => null, 'maximum_amount' => null];

                continue;
            }
            $rules[] = [...$base, 'method' => 'percentage_or_actual', 'percentage' => $percentage, 'maximum_amount' => null];
        }

        foreach (PropertyHoldingPeriodCatalogue::BANDS as [$min, $max, $percentage]) {
            $label = $max === null ? $min.' ปีขึ้นไป' : $min.' ปี';
            $rules[] = [
                'code' => 'PND90_SECTION_40_8_NON_TRADE_PROPERTY_'.$min.'Y_EXPENSE',
                'income_type' => PropertyHoldingPeriodCatalogue::INCOME_TYPE,
                'income_subtype' => PropertyHoldingPeriodCatalogue::INCOME_SUBTYPE,
                'expense_activity' => null, 'holding_years_min' => $min, 'holding_years_max' => $max,
                'method' => 'percentage_or_actual', 'percentage' => $percentage,
                'maximum_amount' => null, 'fixed_amount' => null, 'tiers' => [],
                'source' => self::SOURCE.', page 3, ข้อ 7 item 3 (2) — วิธีที่ 1 หักค่าใช้จ่ายจริง '
                    .'วิธีที่ 2 หักค่าใช้จ่ายเป็นการเหมา: จำนวนปีที่ถือครอง '.$label.' ร้อยละของเงินได้ '
                    .rtrim(rtrim($percentage, '0'), '.'),
            ];
        }

        return $rules;
    }

    public function run(): void
    {
        DB::transaction(function (): void {
            $year = DB::table('tax_years')->where('year', 2568)->first();
            $version = $year ? DB::table('tax_rule_versions')->where('tax_year_id', $year->id)
                ->where('version', '2568.1')->lockForUpdate()->first() : null;
            if (! $version || ! in_array($version->status, ['published', 'archived'], true)) {
                throw new RuntimeException('Published rule version 2568.1 must exist before seeding PND90 subcategory expense rules.');
            }
            $incomeTypes = DB::table('income_types')->pluck('id', 'code');

            foreach (self::approvedRules() as $rule) {
                if (! isset($incomeTypes[$rule['income_type']])) {
                    throw new RuntimeException('Unknown income type '.$rule['income_type'].'.');
                }
                $values = [
                    'tax_year_id' => $year->id, 'rule_version_id' => $version->id,
                    'income_type_id' => $incomeTypes[$rule['income_type']], 'income_subtype' => $rule['income_subtype'],
                    'expense_activity' => $rule['expense_activity'],
                    'holding_years_min' => $rule['holding_years_min'], 'holding_years_max' => $rule['holding_years_max'],
                    'expense_group' => null, 'code' => $rule['code'], 'method' => $rule['method'],
                    'percentage' => $rule['percentage'], 'maximum_amount' => $rule['maximum_amount'],
                    'limit_amount' => $rule['maximum_amount'], 'fixed_amount' => $rule['fixed_amount'],
                    'minimum_amount' => null, 'conditions' => null, 'active' => true,
                    'source_reference' => $rule['source'],
                ];

                $existing = DB::table('expense_rules')->where('rule_version_id', $version->id)
                    ->where('code', $rule['code'])->get();
                if ($existing->isNotEmpty()) {
                    if ($existing->count() !== 1) {
                        throw new RuntimeException('Conflicting expense rules require review: '.$rule['code']);
                    }
                    $this->assertUnchanged($existing->sole(), $values, $rule['code']);
                    $this->seedTiers((int) $existing->sole()->id, $rule['tiers'], $rule['code']);

                    continue;
                }
                // Explicit M7.4-approved completion of the published 2568.1 dataset.
                $id = DB::table('expense_rules')->insertGetId([...$values, 'created_at' => now(), 'updated_at' => now()]);
                $this->seedTiers($id, $rule['tiers'], $rule['code']);
            }
        });
    }

    /** @param array<string, mixed> $values */
    private function assertUnchanged(object $existing, array $values, string $code): void
    {
        foreach (['method', 'income_subtype', 'expense_activity', 'source_reference'] as $key) {
            if ((string) $existing->$key !== (string) $values[$key]) {
                throw new RuntimeException("Existing expense rule $code differs at $key; refusing to overwrite.");
            }
        }
        foreach (['percentage', 'maximum_amount', 'fixed_amount', 'holding_years_min', 'holding_years_max'] as $key) {
            $actual = $existing->$key;
            if (($values[$key] === null) !== ($actual === null)
                || ($values[$key] !== null && $actual != $values[$key])) {
                throw new RuntimeException("Existing expense rule $code differs at $key; refusing to overwrite.");
            }
        }
    }

    /** @param list<array<string, mixed>> $tiers */
    private function seedTiers(int $ruleId, array $tiers, string $code): void
    {
        foreach ($tiers as $tier) {
            $existing = DB::table('expense_rule_tiers')->where('expense_rule_id', $ruleId)
                ->where('tier_code', $tier['tier_code'])->first();
            $values = ['expense_rule_id' => $ruleId, 'tier_code' => $tier['tier_code'],
                'sort_order' => $tier['sort_order'], 'threshold_amount' => $tier['threshold_amount'],
                'percentage' => $tier['percentage'], 'source_reference' => $tier['source']];
            if ($existing) {
                foreach (['sort_order', 'percentage'] as $key) {
                    if ((string) $existing->$key !== (string) $values[$key] && $values[$key] != $existing->$key) {
                        throw new RuntimeException("Existing expense tier {$tier['tier_code']} of $code differs at $key; refusing to overwrite.");
                    }
                }
                if (($values['threshold_amount'] === null) !== ($existing->threshold_amount === null)
                    || ($values['threshold_amount'] !== null && $existing->threshold_amount != $values['threshold_amount'])) {
                    throw new RuntimeException("Existing expense tier {$tier['tier_code']} of $code differs at threshold_amount; refusing to overwrite.");
                }

                continue;
            }
            DB::table('expense_rule_tiers')->insert([...$values, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}

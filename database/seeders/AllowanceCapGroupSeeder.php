<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Milestone 07.4 — the ceilings the 2568 filing instructions state *across* allowance codes.
 *
 * Source: docs/tax-source/PND90-2568-filing-instructions.pdf.
 *
 * A group is seeded only where the booklet prints one ceiling covering named lines. The
 * booklet never says which member gives way when the ceiling bites, so no priority is seeded
 * either; CombinedAllowanceCapResolver caps the group total and documents its per-member split
 * as presentation only.
 */
class AllowanceCapGroupSeeder extends Seeder
{
    public const SOURCE = 'docs/tax-source/PND90-2568-filing-instructions.pdf';

    /** @return list<array<string, mixed>> */
    public static function approvedGroups(): array
    {
        return [
            [
                'code' => 'LIFE_AND_HEALTH_INSURANCE_2568',
                'name' => 'เบี้ยประกันชีวิตและเบี้ยประกันสุขภาพของผู้มีเงินได้',
                'maximum_amount' => '100000.00',
                'members' => ['LIFE_INSURANCE', 'HEALTH_INSURANCE'],
                'source' => self::SOURCE.', page 10, ใบแนบ item 7.4 — เบี้ยประกันสุขภาพ … ตามจำนวนที่จ่ายจริง'
                    .'แต่ไม่เกิน 25,000 บาท ซึ่งเมื่อรวมกับค่าลดหย่อนตามมาตรา 47 (1) (ง) แห่งประมวลรัษฎากร '
                    .'… ต้องไม่เกิน 100,000 บาท',
            ],
            [
                'code' => 'RETIREMENT_SAVINGS_2568',
                'name' => 'เงินสะสมและค่าซื้อหน่วยลงทุนเพื่อการเลี้ยงชีพ',
                'maximum_amount' => '500000.00',
                'members' => ['PROVIDENT_FUND', 'NSF', 'RMF'],
                // The booklet states the same 500,000 three times, each from the perspective of
                // one line and each naming the others: item 9 (NSF with PVD/กบข./สงเคราะห์/RMF/
                // บำนาญ), item 10.4 (RMF with PVD/กบข./สงเคราะห์) and item 7.6 (บำนาญ with the
                // same funds). The members below are the codes this simulator holds; กบข.,
                // กองทุนสงเคราะห์ครูโรงเรียนเอกชน and เบี้ยประกันชีวิตแบบบำนาญ have no seeded
                // rule, so they cannot join the basket yet — see ALLOWANCE_CAP_MODEL_2568.md.
                'source' => self::SOURCE.', page 10, ใบแนบ item 9 — เงินได้ที่ได้รับยกเว้นตามวรรคหนึ่ง เมื่อรวมกับ'
                    .'เงินได้ที่ได้รับยกเว้น … กองทุนสำรองเลี้ยงชีพ … กองทุนรวมเพื่อการเลี้ยงชีพ … '
                    .'ต้องไม่เกิน 500,000 บาท ในปีภาษีเดียวกัน; and page 11, ใบแนบ item 10.4 — '
                    .'เมื่อรวมกับเงินสะสมที่จ่ายเข้ากองทุนสำรองเลี้ยงชีพ … ต้องไม่เกิน 500,000 บาท',
            ],
            [
                'code' => 'EASY_E_RECEIPT_2568',
                'name' => 'ค่าซื้อสินค้าหรือค่าบริการ Easy E-Receipt 2.0',
                'maximum_amount' => '50000.00',
                'members' => ['EASY_E_RECEIPT', 'EASY_E_RECEIPT_OTOP'],
                'source' => self::SOURCE.', page 13, ใบแนบ item 17 — สามารถใช้สิทธิยกเว้นภาษีเงินได้เท่าที่ได้จ่าย'
                    .'เป็นค่าซื้อสินค้าหรือค่าบริการ … ตามจำนวนที่จ่ายจริงแต่ไม่เกิน 50,000 บาท '
                    .'(แยกเป็น 17.1 ไม่เกิน 30,000 บาท และ 17.2 อีกไม่เกิน 20,000 บาท)',
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
                throw new RuntimeException('Published rule version 2568.1 must exist before seeding allowance cap groups.');
            }
            $types = DB::table('allowance_types')->pluck('id', 'code');

            foreach (self::approvedGroups() as $group) {
                $values = ['tax_year_id' => $year->id, 'rule_version_id' => $version->id,
                    'code' => $group['code'], 'name' => $group['name'],
                    'maximum_amount' => $group['maximum_amount'], 'percentage' => null,
                    'percentage_base' => null, 'conditions' => null, 'active' => true,
                    'source_reference' => $group['source']];
                $existing = DB::table('allowance_cap_groups')->where('rule_version_id', $version->id)
                    ->where('code', $group['code'])->first();

                if ($existing) {
                    foreach (['name', 'source_reference'] as $key) {
                        if ((string) $existing->$key !== (string) $values[$key]) {
                            throw new RuntimeException("Existing cap group {$group['code']} differs at $key; refusing to overwrite.");
                        }
                    }
                    if ($existing->maximum_amount != $values['maximum_amount']) {
                        throw new RuntimeException("Existing cap group {$group['code']} differs at maximum_amount; refusing to overwrite.");
                    }
                    $id = (int) $existing->id;
                } else {
                    $id = DB::table('allowance_cap_groups')->insertGetId([...$values, 'created_at' => now(), 'updated_at' => now()]);
                }

                foreach ($group['members'] as $order => $code) {
                    if (! isset($types[$code])) {
                        throw new RuntimeException('Unknown allowance type '.$code.'.');
                    }
                    $member = DB::table('allowance_cap_group_members')
                        ->where('allowance_cap_group_id', $id)->where('allowance_type_id', $types[$code])->first();
                    if ($member) {
                        continue;
                    }
                    DB::table('allowance_cap_group_members')->insert(['allowance_cap_group_id' => $id,
                        'allowance_type_id' => $types[$code], 'sort_order' => $order + 1,
                        'created_at' => now(), 'updated_at' => now()]);
                }
            }
        });
    }
}

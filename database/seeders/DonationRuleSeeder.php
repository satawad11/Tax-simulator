<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Milestone 07.2 — donation rules reconciled from ภ.ง.ด.90 ข้อ 11 items 4 and 6.
 *
 * The form prints two donation lines and states the arithmetic for each:
 *
 *   4. หัก เงินบริจาค (2 เท่าของจำนวนที่ได้จ่ายไปจริง แต่ไม่เกินร้อยละ 10 ของ 3.)
 *   5. คงเหลือ (3. - 4.)
 *   6. หัก เงินบริจาค (ไม่เกินร้อยละ 10 ของ 5.)
 *
 * ภ.ง.ด.91 items 8 and 10 state the same rule against its own line numbers.
 *
 * The two codes mirror the two printed lines. Deciding which line a particular donation
 * belongs on is the taxpayer's declaration on the paper form, and remains the user's
 * declaration here — this seeder states no eligibility, only the arithmetic the form gives.
 */
class DonationRuleSeeder extends Seeder
{
    public const SOURCE = 'docs/tax-source/ภงด.90.pdf';

    /** @return list<array<string, mixed>> */
    public static function approvedRules(): array
    {
        return [
            [
                'code' => 'SPECIAL_DONATION',
                'donation_type' => 'special',
                'name' => 'เงินบริจาคที่หักได้ 2 เท่า (ภ.ง.ด.90 ข้อ 11 รายการ 4)',
                'multiplier' => '2.000',
                'max_percentage' => '10.0000',
                'source' => self::SOURCE.', page 4, ข้อ 11 item 4 — หัก เงินบริจาค (2 เท่าของจำนวนที่ได้จ่ายไปจริง แต่ไม่เกินร้อยละ 10 ของ 3.)',
            ],
            [
                'code' => 'GENERAL_DONATION',
                'donation_type' => 'general',
                'name' => 'เงินบริจาคทั่วไป (ภ.ง.ด.90 ข้อ 11 รายการ 6)',
                'multiplier' => '1.000',
                'max_percentage' => '10.0000',
                'source' => self::SOURCE.', page 4, ข้อ 11 item 6 — หัก เงินบริจาค (ไม่เกินร้อยละ 10 ของ 5.)',
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
                throw new RuntimeException('Published rule version 2568.1 must exist before seeding donation rules.');
            }

            foreach (self::approvedRules() as $rule) {
                $values = [
                    'tax_year_id' => $year->id, 'rule_version_id' => $version->id,
                    'code' => $rule['code'], 'donation_type' => $rule['donation_type'], 'name' => $rule['name'],
                    'multiplier' => $rule['multiplier'], 'max_percentage' => $rule['max_percentage'],
                    'cap_percentage' => $rule['max_percentage'], 'limit_amount' => null,
                    'conditions' => null, 'active' => true, 'source_reference' => $rule['source'],
                ];
                $existing = DB::table('donation_rules')->where('rule_version_id', $version->id)
                    ->where('code', $rule['code'])->get();
                if ($existing->isNotEmpty()) {
                    if ($existing->count() !== 1) {
                        throw new RuntimeException('Conflicting donation rules require review: '.$rule['code']);
                    }
                    foreach (['donation_type', 'source_reference'] as $key) {
                        if ((string) $existing->sole()->$key !== (string) $values[$key]) {
                            throw new RuntimeException("Existing donation rule {$rule['code']} differs at $key; refusing to overwrite.");
                        }
                    }
                    foreach (['multiplier', 'max_percentage'] as $key) {
                        if ($existing->sole()->$key === null || $existing->sole()->$key != $values[$key]) {
                            throw new RuntimeException("Existing donation rule {$rule['code']} differs at $key; refusing to overwrite.");
                        }
                    }

                    continue;
                }
                // Explicit M7.2-approved completion of the published 2568.1 dataset. The query
                // builder bypass is confined here; model immutability guards stay unchanged.
                DB::table('donation_rules')->insert([...$values, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * ใบแนบ ข้อ 22.1 เมืองหลัก, seeded into rule version 2568.3.
 *
 * The booklet writes this line as หักลดหย่อน, not as เงินได้ที่ได้รับยกเว้น, so it belongs in the
 * allowance block rather than the exemption stage — the two sit at different points in the
 * calculation and are not interchangeable.
 *
 * เมืองรอง is deliberately absent. Its first 10,000 is printed as 1.5×, but the part above 10,000
 * is printed as 1.5× with **no ceiling at all**, and supplying one would be inventing a rule.
 */
class DomesticTravelAllowanceRuleSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $year = DB::table('tax_years')->where('year', 2568)->first();
            $version = $year ? DB::table('tax_rule_versions')->where('tax_year_id', $year->id)
                ->where('version', Pnd90IncomeExemptionRuleSeeder::VERSION)->lockForUpdate()->first() : null;
            if (! $version) {
                throw new RuntimeException('Rule version '.Pnd90IncomeExemptionRuleSeeder::VERSION
                    .' must exist before seeding its allowance rules.');
            }

            foreach (Pnd90IncomeExemptionRuleSeeder::travelAllowanceRules() as $rule) {
                $type = DB::table('allowance_types')->where('code', $rule['allowance_code'])->first();
                if (! $type) {
                    throw new RuntimeException('Unknown allowance type '.$rule['allowance_code'].'.');
                }
                $existing = DB::table('allowance_rules')->where('rule_version_id', $version->id)
                    ->where('allowance_type_id', $type->id)->first();

                if ($existing) {
                    if ($existing->method !== $rule['method'] || $existing->maximum_amount != $rule['maximum_amount']) {
                        throw new RuntimeException("Existing allowance rule {$rule['code']} differs; refusing to overwrite.");
                    }

                    continue;
                }

                DB::table('allowance_rules')->insert([
                    'tax_year_id' => $year->id, 'rule_version_id' => $version->id,
                    'allowance_type_id' => $type->id, 'code' => $rule['code'],
                    'method' => $rule['method'], 'fixed_amount' => null, 'percentage' => null,
                    'maximum_amount' => $rule['maximum_amount'], 'limit_amount' => $rule['maximum_amount'],
                    'minimum_amount' => null, 'percentage_base' => null, 'conditions' => null,
                    'active' => true, 'source_reference' => $rule['source'],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
    }
}

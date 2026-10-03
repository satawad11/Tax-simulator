<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EmploymentExpenseRuleSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $year = DB::table('tax_years')->where('year', 2568)->first();
            $version = $year ? DB::table('tax_rule_versions')->where('tax_year_id', $year->id)
                ->where('version', '2568.1')->lockForUpdate()->first() : null;
            $income = DB::table('income_types')->where('code', 'SECTION_40_1')->first();
            if (! $version || ! in_array($version->status, ['published', 'archived'], true) || ! $income) {
                throw new RuntimeException('Published 2568.1 and SECTION_40_1 must exist before the approved expense amendment.');
            }
            $values = ['tax_year_id' => $year->id, 'rule_version_id' => $version->id,
                'income_type_id' => $income->id, 'code' => 'PND91_SECTION_40_1_EXPENSE',
                'method' => 'percentage_limit', 'percentage' => '50.0000', 'maximum_amount' => '100000.00',
                'limit_amount' => '100000.00', 'fixed_amount' => null, 'minimum_amount' => null,
                'conditions' => null, 'active' => true,
                'source_reference' => 'MILESTONE_04_PROMPT.md: Verified Employment Expense; confirmed by docs/tax-source/'
                    .'ภงด.90.pdf, page 2, ข้อ 1 item 5 — หักค่าใช้จ่าย (ร้อยละ 50 แต่ไม่เกิน 100,000 บาท)'];
            $existing = DB::table('expense_rules')->where('rule_version_id', $version->id)
                ->where(function ($query) use ($income): void {
                    $query->where('income_type_id', $income->id)->orWhere('code', 'PND91_SECTION_40_1_EXPENSE');
                })->get();
            if ($existing->isNotEmpty()) {
                if ($existing->count() !== 1) {
                    throw new RuntimeException('Conflicting employment expense rules require review.');
                }
                foreach ($values as $key => $value) {
                    $actual = $existing->sole()->$key;
                    if (($value === null && $actual !== null) || ($value !== null && ($actual === null || $actual != $value))) {
                        throw new RuntimeException("Existing expense rule differs at $key; refusing to overwrite.");
                    }
                }

                return;
            }
            // Explicit M4-approved, narrowly scoped amendment to published 2568.1.
            // Query builder bypass is confined here; model immutability guards remain unchanged.
            DB::table('expense_rules')->insert([...$values, 'created_at' => now(), 'updated_at' => now()]);
        });
    }
}

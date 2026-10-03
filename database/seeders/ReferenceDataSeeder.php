<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Milestone 09.1 — every master/reference row the application needs to function.
 *
 * This seeder introduces no new data of its own. It is a named grouping of the approved
 * Milestone 02–08 seeders so that a real environment can be populated with one command, and so
 * that development/demo data (accounts, CMS copy, demo tax returns) stays clearly separate from
 * the reference data a deployment genuinely requires.
 *
 * Every seeder called here is idempotent: each writes with firstOrCreate/updateOrCreate keyed on
 * a natural key, so rerunning adds nothing and deletes nothing.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TaxYearSeeder::class,
            TaxFormSeeder::class,
            IncomeTypeSeeder::class,
            TaxFormIncomeTypeSeeder::class,
            TaxRuleVersionSeeder::class,
            TaxBracketSeeder::class,
            AllowanceTypeSeeder::class,
            EmploymentExpenseRuleSeeder::class,
            Pnd90ExpenseRuleSeeder::class,
            Pnd90SubcategoryExpenseRuleSeeder::class,
            AllowanceRuleSeeder::class,
            // Groups reference the allowance types above and are read after the rules that
            // produce the amounts they cap.
            AllowanceCapGroupSeeder::class,
            DonationRuleSeeder::class,
            RecommendationRuleSeeder::class,
            /*
             * Runs last among the rule seeders: it carries everything above forward into 2568.3,
             * adds ใบแนบ ข้อ 13, ข้อ 20 and ข้อ 22.1, and publishes it. 2568.1 keeps every rule it
             * was published with and is archived, never edited.
             */
            TaxRuleVersion2568_3Seeder::class,
            // M8 evidence registry. Documents only; it establishes no numeric rule.
            TaxSourceSeeder::class,
        ]);
    }
}

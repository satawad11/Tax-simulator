<?php

namespace Database\Seeders;

use App\Models\TaxRuleVersion;
use App\Models\TaxYear;
use Illuminate\Database\Seeder;

class TaxRuleVersionSeeder extends Seeder
{
    public function run(): void
    {
        TaxRuleVersion::firstOrCreate(
            ['tax_year_id' => TaxYear::where('year', 2568)->firstOrFail()->id, 'version' => '2568.1'],
            ['status' => 'draft', 'description' => 'Milestone 02 approved brackets; other quantitative rules await verification.'],
        );
    }
}

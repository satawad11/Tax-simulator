<?php

namespace Database\Seeders;

use App\Models\TaxBracket;
use App\Models\TaxRuleVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TaxBracketSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $version = TaxRuleVersion::whereHas('taxYear', fn ($query) => $query->where('year', 2568))
                ->where('version', '2568.1')->lockForUpdate()->firstOrFail();

            /*
             * Only a draft is built and published here. The guard used to read `=== 'published'`,
             * which meant that once 2568.1 was retired in favour of a successor, the next reseed
             * found it "not published", rebuilt its brackets and published it a second time —
             * leaving the tax year with two published versions and every calculation failing with
             * the conflict PublishedTaxRuleResolver raises. A retired version is finished, not
             * unfinished.
             */
            if ($version->status !== 'draft') {
                return;
            }

            $brackets = [
                ['0.00', '150000.00', '0.0000'],
                ['150000.00', '300000.00', '5.0000'],
                ['300000.00', '500000.00', '10.0000'],
                ['500000.00', '750000.00', '15.0000'],
                ['750000.00', '1000000.00', '20.0000'],
                ['1000000.00', '2000000.00', '25.0000'],
                ['2000000.00', '5000000.00', '30.0000'],
                ['5000000.00', null, '35.0000'],
            ];

            foreach ($brackets as $index => [$lower, $upper, $rate]) {
                TaxBracket::updateOrCreate(
                    ['rule_version_id' => $version->id, 'position' => $index + 1],
                    ['tax_year_id' => $version->tax_year_id, 'lower_bound' => $lower,
                        'upper_bound' => $upper, 'rate' => $rate,
                        'source_reference' => 'User-approved Milestone 02 bracket table for tax year 2568'],
                );
            }

            $version->update(['status' => 'published']);
        });
    }
}

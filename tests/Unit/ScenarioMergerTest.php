<?php

namespace Tests\Unit;

use App\Services\Tax\ScenarioMerger;
use PHPUnit\Framework\TestCase;

class ScenarioMergerTest extends TestCase
{
    private function base(): array
    {
        return [
            'tax_year' => 2568,
            'form_code' => 'PND91',
            'incomes' => [['income_type' => 'SECTION_40_1', 'gross_amount' => '720000.00', 'exempt_amount' => '0.00']],
            'allowances' => [['code' => 'PERSONAL', 'amount' => '60000.00'], ['code' => 'RMF', 'amount' => '1000.00']],
            'donations' => [],
            'withholdings' => [['type' => 'withholding', 'amount' => '10000.00'], ['type' => 'withholding', 'amount' => '15000.00']],
        ];
    }

    public function test_it_upserts_in_place_and_collapses_duplicate_keys(): void
    {
        $merged = (new ScenarioMerger)->apply($this->base(), [
            'withholdings' => ['upsert' => [['type' => 'withholding', 'amount' => '40000.00']]],
        ]);

        $this->assertSame([['type' => 'withholding', 'amount' => '40000.00']], $merged['withholdings']);
    }

    public function test_it_appends_keys_absent_from_the_base_in_declared_order(): void
    {
        $merged = (new ScenarioMerger)->apply($this->base(), [
            'allowances' => ['upsert' => [
                ['code' => 'SOCIAL_SECURITY', 'input_amount' => '9000.00'],
                ['code' => 'PERSONAL', 'amount' => '1.00'],
                ['code' => 'NSF', 'amount' => '500.00'],
            ]],
        ]);

        $this->assertSame([
            ['code' => 'PERSONAL', 'amount' => '1.00'],
            ['code' => 'RMF', 'amount' => '1000.00'],
            ['code' => 'SOCIAL_SECURITY', 'amount' => '9000.00'],
            ['code' => 'NSF', 'amount' => '500.00'],
        ], $merged['allowances']);
    }

    public function test_it_removes_every_base_entry_sharing_a_removed_key(): void
    {
        $merged = (new ScenarioMerger)->apply($this->base(), [
            'allowances' => ['remove' => ['RMF']],
            'withholdings' => ['remove' => ['withholding']],
        ]);

        $this->assertSame([['code' => 'PERSONAL', 'amount' => '60000.00']], $merged['allowances']);
        $this->assertSame([], $merged['withholdings']);
    }

    public function test_it_accepts_amount_and_input_amount_identically(): void
    {
        $merger = new ScenarioMerger;
        $withAmount = $merger->apply($this->base(), ['allowances' => ['upsert' => [['code' => 'RMF', 'amount' => '7.00']]]]);
        $withInput = $merger->apply($this->base(), ['allowances' => ['upsert' => [['code' => 'RMF', 'input_amount' => '7.00']]]]);

        $this->assertSame($withAmount, $withInput);
    }

    public function test_it_never_mutates_the_base_payload(): void
    {
        $base = $this->base();
        $snapshot = $base;
        (new ScenarioMerger)->apply($base, [
            'allowances' => ['remove' => ['PERSONAL'], 'upsert' => [['code' => 'NSF', 'amount' => '1.00']]],
            'withholdings' => ['upsert' => [['type' => 'pnd93', 'amount' => '2.00']]],
        ]);

        $this->assertSame($snapshot, $base);
    }

    public function test_an_empty_scenario_leaves_every_collection_unchanged(): void
    {
        $merged = (new ScenarioMerger)->apply($this->base(), []);

        $this->assertSame($this->base()['allowances'], $merged['allowances']);
        $this->assertSame($this->base()['withholdings'], $merged['withholdings']);
        $this->assertSame($this->base()['incomes'], $merged['incomes']);
    }
}

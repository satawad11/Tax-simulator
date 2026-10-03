<?php

namespace Tests\Feature;

use Database\Seeders\TaxBaselineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ใบแนบ ข้อ 13 and ข้อ 20 end to end, through the API a client actually calls.
 *
 * Both lines print their arithmetic in full, and both say the amount comes off เงินได้พึงประเมิน
 * after expenses — so they are a stage of their own, not ค่าลดหย่อน. What neither prints is a way
 * for the engine to establish entitlement, so the filer affirms the printed conditions and the
 * engine applies the arithmetic to what they state, asserting nothing itself.
 */
class IncomeExemptionApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    /** @param list<array<string, mixed>> $exemptions */
    private function payload(array $exemptions, string $gross = '3000000'): array
    {
        return ['tax_year' => 2568, 'form_code' => 'PND90',
            'incomes' => [['income_type' => 'SECTION_40_1', 'gross_amount' => $gross]],
            'allowances' => [], 'donations' => [], 'withholdings' => [],
            'income_exemptions' => $exemptions];
    }

    /** @param list<array<string, mixed>> $exemptions */
    private function calculate(array $exemptions, string $gross = '3000000')
    {
        return $this->postJson('/api/v1/tax/calculate', $this->payload($exemptions, $gross));
    }

    public function test_the_catalogue_endpoint_lists_the_lines_with_the_conditions_to_affirm(): void
    {
        $response = $this->getJson('/api/v1/tax-years/2568/income-exemptions')->assertOk();

        $this->assertSame(['CCTV_SYSTEM', 'NEW_HOME_CONSTRUCTION'],
            array_column($response->json('data'), 'code'));
        foreach ($response->json('data') as $line) {
            $this->assertNotEmpty($line['declarations'],
                $line['code'].' turns on facts no amount carries, so it must state them.');
        }
    }

    public function test_the_stepped_grant_of_item_20_matches_the_printed_arithmetic(): void
    {
        // "10,000 บาท ต่อทุกจำ�นวน 1,000,000 บาท … แต่รวมแล้วไม่เกิน 100,000 บาท"
        foreach ([['900000', '0.00'], ['1000000', '10000.00'], ['2400000', '20000.00'],
            ['12000000', '100000.00']] as [$paid, $granted]) {
            $this->calculate([['code' => 'NEW_HOME_CONSTRUCTION', 'amount' => $paid,
                'declarations_confirmed' => true]], '13000000')->assertOk()
                ->assertJsonPath('data.income_exemptions.total', $granted);
        }
    }

    public function test_item_13_grants_the_whole_amount_declared(): void
    {
        // "เป็นจำ�นวนร้อยละหนึ่งร้อยของเงินได้เท่าที่ได้จ่าย…", with no printed ceiling.
        $this->calculate([['code' => 'CCTV_SYSTEM', 'amount' => '450000',
            'declarations_confirmed' => true]])->assertOk()
            ->assertJsonPath('data.income_exemptions.total', '450000.00');
    }

    public function test_the_deduction_lands_after_expenses_and_before_allowances(): void
    {
        $data = $this->calculate([['code' => 'CCTV_SYSTEM', 'amount' => '200000',
            'declarations_confirmed' => true]], '1000000')->assertOk()->json('data');

        // 1,000,000 less the 100,000 expense ceiling, then the exemption.
        $this->assertSame('900000.00', $data['income_after_expense']);
        $this->assertSame('700000.00', $data['income_after_income_exemptions']);
        $codes = array_column($data['trace'], 'code');
        $this->assertLessThan(array_search('INCOME_EXEMPTIONS', $codes, true),
            array_search('INCOME_AFTER_EXPENSE', $codes, true));
        $this->assertLessThan(array_search('ALLOWANCES', $codes, true),
            array_search('INCOME_EXEMPTIONS', $codes, true));
    }

    public function test_the_minimum_tax_base_is_unchanged_by_an_exemption(): void
    {
        // The ภ.ง.ด.90 second method is charged on the ข้อ 1–ข้อ 7 boxes, which are gross figures
        // upstream of this stage — the same reason a declared exempt amount does not reduce it.
        $without = $this->calculate([], '3000000')->assertOk()->json('data.minimum_tax.base');
        $with = $this->calculate([['code' => 'CCTV_SYSTEM', 'amount' => '500000',
            'declarations_confirmed' => true]], '3000000')->assertOk()->json('data.minimum_tax.base');

        $this->assertSame($without, $with);
    }

    public function test_a_return_claiming_nothing_keeps_the_trace_it_always_had(): void
    {
        $codes = array_column($this->calculate([])->assertOk()->json('data.trace'), 'code');

        $this->assertNotContains('INCOME_EXEMPTIONS', $codes);
        $this->assertNotContains('INCOME_AFTER_INCOME_EXEMPTIONS', $codes);
    }

    public function test_an_unaffirmed_declaration_is_refused(): void
    {
        $this->calculate([['code' => 'NEW_HOME_CONSTRUCTION', 'amount' => '2000000']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('income_exemptions.0.declarations_confirmed');

        $this->calculate([['code' => 'NEW_HOME_CONSTRUCTION', 'amount' => '2000000',
            'declarations_confirmed' => false]])->assertStatus(422)
            ->assertJsonValidationErrors('income_exemptions.0.declarations_confirmed');
    }

    public function test_a_line_with_no_rule_in_this_version_is_refused(): void
    {
        // ใบแนบ ข้อ 16 and ข้อ 22 เมืองรอง stay out: the booklet leaves "กรณีละ" and the เมืองรอง
        // excess ceiling unresolved, and the engine must not supply either.
        $this->calculate([['code' => 'SOCIAL_ENTERPRISE_INVESTMENT', 'amount' => '50000',
            'declarations_confirmed' => true]])->assertStatus(422)
            ->assertJsonValidationErrors('income_exemptions.0.code');
    }

    public function test_a_zero_is_accepted_without_an_affirmation(): void
    {
        // A zero cannot change the tax, so refusing it would be noise.
        $this->calculate([['code' => 'NEW_HOME_CONSTRUCTION', 'amount' => '0']])->assertOk()
            ->assertJsonPath('data.income_exemptions.total', '0.00');
    }

    public function test_an_exemption_cannot_exceed_the_income_left_after_expenses(): void
    {
        $data = $this->calculate([['code' => 'CCTV_SYSTEM', 'amount' => '5000000',
            'declarations_confirmed' => true]], '1000000')->assertOk()->json('data');

        $this->assertSame('900000.00', $data['income_exemptions']['total']);
        $this->assertSame('0.00', $data['income_after_income_exemptions']);
        $this->assertContains('EXEMPTION_EXCEEDS_INCOME', array_column($data['warnings'], 'code'));
    }

    public function test_the_declared_amount_travels_beside_what_was_granted(): void
    {
        $item = $this->calculate([['code' => 'NEW_HOME_CONSTRUCTION', 'amount' => '2400000',
            'declarations_confirmed' => true]], '5000000')->assertOk()
            ->json('data.income_exemptions.items.0');

        $this->assertSame('2400000.00', $item['input_amount']);
        $this->assertSame('20000.00', $item['eligible_amount']);
        $this->assertStringContainsString('ข้อ 20', $item['source_reference']);
    }

    public function test_the_calculation_reports_the_version_that_carries_these_rules(): void
    {
        $this->calculate([])->assertOk()
            ->assertJsonPath('data.rule_version', TaxBaselineSeeder::BASELINE_VERSION);
    }

    public function test_the_frozen_regression_figure_is_unchanged_by_the_new_version(): void
    {
        // M4's reference case: no family, net 620,000, tax 45,500. Carrying the baseline forward
        // must move no existing figure, so this is asserted against the live version.
        $this->postJson('/api/v1/tax/calculate', ['tax_year' => 2568, 'form_code' => 'PND91',
            'incomes' => [['income_type' => 'SECTION_40_1', 'gross_amount' => '720000']],
            'allowances' => [], 'donations' => [], 'withholdings' => []])->assertOk()
            ->assertJsonPath('data.net_income', '620000.00')
            ->assertJsonPath('data.progressive_tax.total', '45500.00');
    }
}

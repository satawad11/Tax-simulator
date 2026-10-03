<?php

namespace Tests\Feature;

use App\Services\Tax\SeparateTaxCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Behavioural coverage for every expense rule reconciled from ภ.ง.ด.90.
 *
 * Each expectation is derived only from a percentage or cap printed on the form; see
 * docs/tax/PND90_RULE_MATRIX.md for the page and section behind each rule.
 */
class Pnd90SourceExpenseRuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function line(string $type, string $gross, ?string $subtype = null,
        ?string $selection = null, ?string $actual = null, string $exempt = '0.00'): array
    {
        return ['income_type' => $type, 'gross_amount' => $gross, 'exempt_amount' => $exempt,
            ...($subtype === null ? [] : ['income_subtype' => $subtype]),
            ...($selection === null ? [] : ['expense_method_selection' => $selection]),
            ...($actual === null ? [] : ['actual_expense' => $actual]),
            // ข้อ 9 income must state whether it is taxed with the rest or separately; the
            // expense rule under test is the same either way, so these cases declare the
            // ordinary choice rather than leaving the fact unstated.
            ...(SeparateTaxCalculator::eligible($type, $subtype)
                ? ['tax_treatment' => SeparateTaxCalculator::PROGRESSIVE] : [])];
    }

    /** @param list<array<string, string>> $incomes */
    private function calculate(array $incomes, string $withholding = '0.00'): TestResponse
    {
        return $this->postJson('/api/v1/tax/calculate', ['tax_year' => 2568, 'form_code' => 'PND90',
            'incomes' => $incomes, 'allowances' => [], 'donations' => [],
            'withholdings' => [['type' => 'withholding', 'amount' => $withholding]]]);
    }

    public static function verifiedRules(): array
    {
        return [
            // ข้อ 2 — 40(3)
            '40(3) annuity has no expense line' => ['SECTION_40_3', 'ANNUITY_FROM_WILL_OR_JUDGMENT', null, '500000', '0.00'],
            '40(3) copyright under the cap' => ['SECTION_40_3', 'COPYRIGHT_GOODWILL_OTHER_RIGHTS', 'percentage', '100000', '50000.00'],
            '40(3) copyright at the cap' => ['SECTION_40_3', 'COPYRIGHT_GOODWILL_OTHER_RIGHTS', 'percentage', '200000', '100000.00'],
            '40(3) copyright above the cap' => ['SECTION_40_3', 'COPYRIGHT_GOODWILL_OTHER_RIGHTS', 'percentage', '900000', '100000.00'],
            // ข้อ 3 — 40(4)
            '40(4) has no expense line' => ['SECTION_40_4', null, null, '750000', '0.00'],
            // ข้อ 4 — 40(5)
            '40(5) building rent at 30%' => ['SECTION_40_5', 'RENT_BUILDING_OR_RAFT', 'percentage', '500000', '150000.00'],
            '40(5) hire-purchase breach at 20%' => ['SECTION_40_5', 'HIRE_PURCHASE_BREACH', null, '500000', '100000.00'],
            // ข้อ 5 — 40(6)
            '40(6) medical practice at 60%' => ['SECTION_40_6', 'MEDICAL_PRACTICE', 'percentage', '500000', '300000.00'],
            '40(6) fine arts at 60%' => ['SECTION_40_6', 'FINE_ARTS', 'percentage', '500000', '300000.00'],
            '40(6) other liberal profession at 30%' => ['SECTION_40_6', 'OTHER_LIBERAL_PROFESSION', 'percentage', '500000', '150000.00'],
            // ข้อ 6 — 40(7)
            '40(7) contracting at 60%' => ['SECTION_40_7', null, 'percentage', '500000', '300000.00'],
            // ข้อ 7 — 40(8)
            '40(8) mutual fund share has no expense line' => ['SECTION_40_8', 'MUTUAL_FUND_PROFIT_SHARE', null, '500000', '0.00'],
            '40(8) inherited property at 50%' => ['SECTION_40_8', 'IMMOVABLE_PROPERTY_INHERITED_OR_GIFTED', null, '500000', '250000.00'],
            '40(8) gift received has no expense line' => ['SECTION_40_8', 'GIFT_OR_SUPPORT_RECEIVED', null, '500000', '0.00'],
        ];
    }

    #[DataProvider('verifiedRules')]
    public function test_each_verified_rule_deducts_the_amount_the_form_states(
        string $type, ?string $subtype, ?string $selection, string $gross, string $expected
    ): void {
        $this->calculate([$this->line($type, $gross, $subtype, $selection)])->assertOk()
            ->assertJsonPath('data.expenses.total', $expected)
            ->assertJsonPath('data.expenses.items.0.rule_status', 'VERIFIED')
            ->assertJsonPath('data.expenses.items.0.income_type', $type)
            ->assertJsonPath('data.expenses.items.0.income_subtype', $subtype);
    }

    #[DataProvider('verifiedRules')]
    public function test_each_verified_rule_deducts_nothing_from_zero_income(
        string $type, ?string $subtype, ?string $selection, string $gross = '0', string $expected = '0.00'
    ): void {
        $this->calculate([$this->line($type, '0', $subtype, $selection)])->assertOk()
            ->assertJsonPath('data.expenses.total', '0.00')
            ->assertJsonPath('data.net_income', '0.00')
            ->assertJsonPath('data.progressive_tax.total', '0.00');
    }

    public static function electionRules(): array
    {
        return [
            '40(3) copyright' => ['SECTION_40_3', 'COPYRIGHT_GOODWILL_OTHER_RIGHTS'],
            '40(5) building rent' => ['SECTION_40_5', 'RENT_BUILDING_OR_RAFT'],
            '40(6) medical practice' => ['SECTION_40_6', 'MEDICAL_PRACTICE'],
            '40(6) fine arts' => ['SECTION_40_6', 'FINE_ARTS'],
            '40(6) other liberal profession' => ['SECTION_40_6', 'OTHER_LIBERAL_PROFESSION'],
            '40(7) contracting' => ['SECTION_40_7', null],
        ];
    }

    #[DataProvider('electionRules')]
    public function test_an_election_category_requires_the_taxpayer_to_choose(string $type, ?string $subtype): void
    {
        $this->calculate([$this->line($type, '500000', $subtype)])->assertUnprocessable()
            ->assertJsonValidationErrors('incomes.0.expense_method_selection');
    }

    #[DataProvider('electionRules')]
    public function test_an_election_category_deducts_the_declared_actual_expense_when_elected(string $type, ?string $subtype): void
    {
        $this->calculate([$this->line($type, '500000', $subtype, 'actual', '420000')])->assertOk()
            ->assertJsonPath('data.expenses.total', '420000.00')
            ->assertJsonPath('data.expenses.items.0.expense_method_selection', 'actual')
            ->assertJsonPath('data.expenses.items.0.input_actual_expense', '420000.00');
    }

    #[DataProvider('electionRules')]
    public function test_electing_actual_expense_requires_the_amount(string $type, ?string $subtype): void
    {
        $this->calculate([$this->line($type, '500000', $subtype, 'actual')])->assertUnprocessable()
            ->assertJsonValidationErrors('incomes.0.actual_expense');
    }

    #[DataProvider('electionRules')]
    public function test_a_declared_actual_expense_may_not_exceed_the_income(string $type, ?string $subtype): void
    {
        $this->calculate([$this->line($type, '500000', $subtype, 'actual', '500000.01')])->assertUnprocessable()
            ->assertJsonValidationErrors('incomes.0.actual_expense');
    }

    public static function nonElectionRules(): array
    {
        return [
            '40(1) employment' => ['SECTION_40_1', null],
            '40(4) interest and dividends' => ['SECTION_40_4', null],
            '40(5) hire-purchase breach' => ['SECTION_40_5', 'HIRE_PURCHASE_BREACH'],
            '40(8) inherited property' => ['SECTION_40_8', 'IMMOVABLE_PROPERTY_INHERITED_OR_GIFTED'],
        ];
    }

    #[DataProvider('nonElectionRules')]
    public function test_a_category_without_a_printed_choice_rejects_an_election(string $type, ?string $subtype): void
    {
        $this->calculate([$this->line($type, '500000', $subtype, 'actual', '100000')])->assertUnprocessable()
            ->assertJsonValidationErrors('incomes.0.expense_method_selection');
    }

    #[DataProvider('nonElectionRules')]
    public function test_a_category_without_a_printed_choice_rejects_an_actual_expense(string $type, ?string $subtype): void
    {
        $this->calculate([$this->line($type, '500000', $subtype, null, '100000')])->assertUnprocessable()
            ->assertJsonValidationErrors('incomes.0.actual_expense');
    }

    /**
     * ข้อ 1 adds 40(1) and 40(2) together and then deducts once. Applying the cap per income
     * type instead would give 150000 here rather than 100000.
     */
    public function test_section_40_1_and_40_2_share_one_capped_deduction(): void
    {
        $response = $this->calculate([
            $this->line('SECTION_40_1', '150000'),
            $this->line('SECTION_40_2', '150000'),
        ])->assertOk()
            ->assertJsonPath('data.income.gross_income', '300000.00')
            ->assertJsonPath('data.expenses.total', '100000.00')
            ->assertJsonPath('data.income_after_expense', '200000.00');

        $items = $response->json('data.expenses.items');
        $this->assertCount(1, $items, 'The shared group is deducted once.');
        $this->assertSame(['SECTION_40_1', 'SECTION_40_2'], $items[0]['income_types']);
        $this->assertNull($items[0]['income_type']);
        $this->assertSame('SECTION_40_1_2', $items[0]['expense_group']);
        $this->assertSame('300000.00', $items[0]['basis']);
    }

    public static function sharedCapBoundaries(): array
    {
        // [40(1) gross, 40(2) gross, expected combined deduction]
        return [
            'well under the cap' => ['50000', '50000', '50000.00'],
            'one satang under the cap' => ['100000', '99999.98', '99999.99'],
            'exactly at the cap' => ['100000', '100000', '100000.00'],
            'one satang over the cap' => ['100000', '100000.02', '100000.00'],
            'far over the cap' => ['900000', '900000', '100000.00'],
            'only 40(2) present' => ['0', '400000', '100000.00'],
        ];
    }

    #[DataProvider('sharedCapBoundaries')]
    public function test_the_shared_employment_cap_applies_once_across_both_types(
        string $employment, string $fees, string $expected
    ): void {
        $this->calculate([
            $this->line('SECTION_40_1', $employment),
            $this->line('SECTION_40_2', $fees),
        ])->assertOk()->assertJsonPath('data.expenses.total', $expected);
    }

    public function test_section_40_2_alone_still_uses_the_shared_rule(): void
    {
        $this->calculate([$this->line('SECTION_40_2', '500000')])->assertOk()
            ->assertJsonPath('data.expenses.total', '100000.00')
            ->assertJsonPath('data.expenses.items.0.income_type', 'SECTION_40_2')
            ->assertJsonPath('data.expenses.items.0.expense_group', 'SECTION_40_1_2');
    }

    public function test_pnd91_is_untouched_by_the_shared_group(): void
    {
        $this->postJson('/api/v1/tax/calculate', ['tax_year' => 2568, 'form_code' => 'PND91',
            'incomes' => [$this->line('SECTION_40_1', '720000')],
            'withholdings' => [['type' => 'withholding', 'amount' => '25000.00']]])->assertOk()
            ->assertJsonPath('data.expenses.total', '100000.00')
            ->assertJsonPath('data.net_income', '620000.00')
            ->assertJsonPath('data.progressive_tax.total', '45500.00')
            ->assertJsonPath('data.result.amount', '20500.00');
    }

    public function test_a_mixed_verified_pnd90_return_runs_one_progressive_calculation(): void
    {
        $response = $this->calculate([
            $this->line('SECTION_40_1', '600000'),
            $this->line('SECTION_40_2', '200000'),
            $this->line('SECTION_40_5', '300000', 'RENT_BUILDING_OR_RAFT', 'percentage'),
            $this->line('SECTION_40_6', '400000', 'MEDICAL_PRACTICE', 'percentage'),
            $this->line('SECTION_40_8', '100000', 'IMMOVABLE_PROPERTY_INHERITED_OR_GIFTED'),
        ])->assertOk()
            ->assertJsonPath('data.income.gross_income', '1600000.00')
            // 100000 shared by 40(1)+40(2), 90000 for 40(5), 240000 for 40(6), 50000 for 40(8).
            ->assertJsonPath('data.expenses.total', '480000.00')
            ->assertJsonPath('data.income_after_expense', '1120000.00')
            ->assertJsonPath('data.net_income', '1120000.00')
            ->assertJsonCount(8, 'data.progressive_tax.brackets');

        $this->assertSame(['100000.00', '90000.00', '240000.00', '50000.00'],
            array_column($response->json('data.expenses.items'), 'eligible_amount'));
        // 150000@5% + 200000@10% + 250000@15% + 250000@20% + 120000@25% = 145000
        $this->assertSame('145000.00', $response->json('data.progressive_tax.total'));
    }

    public static function mixedResults(): array
    {
        return [['0', 'PAYABLE', '145000.00'], ['145000', 'ZERO', '0.00'], ['200000', 'REFUND', '55000.00']];
    }

    #[DataProvider('mixedResults')]
    public function test_a_mixed_verified_pnd90_return_reaches_each_result_status(
        string $paid, string $status, string $amount
    ): void {
        $this->calculate([
            $this->line('SECTION_40_1', '600000'),
            $this->line('SECTION_40_2', '200000'),
            $this->line('SECTION_40_5', '300000', 'RENT_BUILDING_OR_RAFT', 'percentage'),
            $this->line('SECTION_40_6', '400000', 'MEDICAL_PRACTICE', 'percentage'),
            $this->line('SECTION_40_8', '100000', 'IMMOVABLE_PROPERTY_INHERITED_OR_GIFTED'),
        ], $paid)->assertOk()
            ->assertJsonPath('data.result.status', $status)
            ->assertJsonPath('data.result.amount', $amount);
    }

    public function test_mixing_a_verified_and_an_unverified_category_is_rejected_clearly(): void
    {
        $this->calculate([
            $this->line('SECTION_40_1', '600000'),
            $this->line('SECTION_40_6', '400000', 'MEDICAL_PRACTICE', 'percentage'),
            $this->line('SECTION_40_5', '300000', 'RENT_OTHER'),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('incomes.2.income_subtype')
            ->assertJsonMissingValidationErrors('incomes.0.income_type')
            ->assertJsonMissingValidationErrors('incomes.1.income_subtype');
    }

    public function test_two_subcategories_of_one_income_type_are_deducted_separately(): void
    {
        $response = $this->calculate([
            $this->line('SECTION_40_6', '400000', 'MEDICAL_PRACTICE', 'percentage'),
            $this->line('SECTION_40_6', '400000', 'OTHER_LIBERAL_PROFESSION', 'percentage'),
        ])->assertOk()
            // 60% of 400000 plus 30% of 400000; never one blended rate.
            ->assertJsonPath('data.expenses.total', '360000.00')
            ->assertJsonCount(2, 'data.expenses.items')
            ->assertJsonCount(2, 'data.income.items');

        $this->assertSame(['MEDICAL_PRACTICE', 'OTHER_LIBERAL_PROFESSION'],
            array_column($response->json('data.expenses.items'), 'income_subtype'));
        $this->assertSame(['240000.00', '120000.00'], array_column($response->json('data.expenses.items'), 'eligible_amount'));
    }

    public function test_lines_of_one_subcategory_must_elect_the_same_expense_method(): void
    {
        $this->calculate([
            $this->line('SECTION_40_6', '400000', 'MEDICAL_PRACTICE', 'percentage'),
            $this->line('SECTION_40_6', '400000', 'MEDICAL_PRACTICE', 'actual', '100000'),
        ])->assertUnprocessable();
    }

    public function test_verified_rules_keep_fractional_satang(): void
    {
        $this->calculate([$this->line('SECTION_40_7', '0.01', null, 'percentage')])->assertOk()
            ->assertJsonPath('data.expenses.total', '0.006')
            ->assertJsonPath('data.income_after_expense', '0.004');
    }

    /**
     * M7.3 replaced this warning with the calculation itself, once the filing instructions
     * settled the base of วิธีที่ 2. The threshold it guarded is now an engine boundary.
     */
    public function test_pnd90_computes_the_minimum_tax_base_from_one_hundred_twenty_thousand(): void
    {
        $this->calculate([$this->line('SECTION_40_7', '120000', null, 'percentage')])->assertOk()
            ->assertJsonPath('data.minimum_tax.base', '120000.00')
            // 120,000 × 0.005 = 600, which the booklet's 5,000 floor disregards.
            ->assertJsonPath('data.minimum_tax.tax', '600.00')
            ->assertJsonPath('data.minimum_tax.applicable', false);

        $this->calculate([$this->line('SECTION_40_7', '119999.99', null, 'percentage')])->assertOk()
            ->assertJsonPath('data.minimum_tax.tax', '0.00')
            ->assertJsonPath('data.minimum_tax.applicable', false);
    }

    public function test_section_40_1_income_is_excluded_from_the_minimum_tax_base(): void
    {
        $response = $this->calculate([$this->line('SECTION_40_1', '9000000')])->assertOk();

        $this->assertNotContains('PND90_MINIMUM_TAX_NOT_APPLIED', array_column($response->json('data.warnings'), 'code'));
    }
}

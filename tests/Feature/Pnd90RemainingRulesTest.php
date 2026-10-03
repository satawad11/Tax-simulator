<?php

namespace Tests\Feature;

use App\Models\TaxReturn;
use App\Models\User;
use App\Services\Tax\SeparateTaxCalculator;
use Database\Seeders\AllowanceRuleSeeder;
use Database\Seeders\DonationRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Behavioural coverage for the rules reconciled in Milestone 07.2: the two donation lines of
 * ข้อ 11, the one attachment allowance whose amount is printed, the ข้อ 11 item 15 prepayments
 * and the ข้อ 9 separate-rate election.
 *
 * Every expectation traces to a value printed on ภ.ง.ด.90; see docs/tax/PND90_REMAINING_RULES_2568.md.
 */
class Pnd90RemainingRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function calculate(array $payload, string $form = 'PND91'): TestResponse
    {
        return $this->postJson('/api/v1/tax/calculate', ['tax_year' => 2568, 'form_code' => $form,
            'incomes' => [['income_type' => 'SECTION_40_1', 'gross_amount' => '720000.00', 'exempt_amount' => '0.00']],
            'allowances' => [], 'donations' => [], 'withholdings' => [], ...$payload]);
    }

    // ---------------------------------------------------------------- donations

    public function test_the_two_donation_lines_are_seeded_exactly_as_the_form_prints_them(): void
    {
        $rules = DB::table('donation_rules')->orderBy('code')->get()->keyBy('code');

        $this->assertSame(['GENERAL_DONATION', 'SPECIAL_DONATION'], $rules->keys()->all());
        $this->assertEquals('2.0000', $rules['SPECIAL_DONATION']->multiplier);
        $this->assertEquals('10.0000', $rules['SPECIAL_DONATION']->max_percentage);
        $this->assertEquals('1.0000', $rules['GENERAL_DONATION']->multiplier);
        $this->assertEquals('10.0000', $rules['GENERAL_DONATION']->max_percentage);
        foreach ($rules as $rule) {
            $this->assertStringContainsString('ภงด.90.pdf', (string) $rule->source_reference);
        }
    }

    public static function specialDonations(): array
    {
        // Income after expenses is 620000, so ข้อ 11 item 4 caps at 62000.
        return [
            'zero' => ['0', '0.00', '620000.00'],
            'below the cap' => ['10000', '20000.00', '600000.00'],
            'exactly at the cap' => ['31000', '62000.00', '558000.00'],
            'above the cap' => ['50000', '62000.00', '558000.00'],
            'far above the cap' => ['900000', '62000.00', '558000.00'],
        ];
    }

    #[DataProvider('specialDonations')]
    public function test_the_special_donation_doubles_then_caps_at_ten_percent(string $paid, string $eligible, string $net): void
    {
        $this->calculate(['donations' => [['code' => 'SPECIAL_DONATION', 'amount' => $paid]]])->assertOk()
            ->assertJsonPath('data.donations.total_eligible', $eligible)
            ->assertJsonPath('data.net_income', $net);
    }

    public static function generalDonations(): array
    {
        return [
            'zero' => ['0', '0.00', '620000.00'],
            'below the cap' => ['10000', '10000.00', '610000.00'],
            'exactly at the cap' => ['62000', '62000.00', '558000.00'],
            'above the cap' => ['90000', '62000.00', '558000.00'],
        ];
    }

    #[DataProvider('generalDonations')]
    public function test_the_general_donation_caps_at_ten_percent_without_a_multiplier(string $paid, string $eligible, string $net): void
    {
        $this->calculate(['donations' => [['code' => 'GENERAL_DONATION', 'amount' => $paid]]])->assertOk()
            ->assertJsonPath('data.donations.total_eligible', $eligible)
            ->assertJsonPath('data.net_income', $net);
    }

    public function test_the_general_cap_is_measured_after_the_special_deduction(): void
    {
        $response = $this->calculate(['donations' => [
            ['code' => 'SPECIAL_DONATION', 'amount' => '31000'],
            ['code' => 'GENERAL_DONATION', 'amount' => '900000'],
        ]])->assertOk()
            // item 4: min(31000 x 2, 10% of 620000) = 62000; item 5 leaves 558000;
            // item 6: min(900000, 10% of 558000) = 55800. A single combined cap would differ.
            ->assertJsonPath('data.donations.total_eligible', '117800.00')
            ->assertJsonPath('data.net_income', '502200.00');

        $items = $response->json('data.donations.items');
        $this->assertSame(['620000.00', '558000.00'], array_column($items, 'cap_base'));
        $this->assertSame(['62000.00', '55800.00'], array_column($items, 'eligible_amount'));
        $this->assertSame(['2.0000', '1.0000'], array_column($items, 'multiplier'));
    }

    public function test_the_donation_stages_appear_in_the_printed_order_in_the_trace(): void
    {
        $trace = $this->calculate(['donations' => [
            ['code' => 'GENERAL_DONATION', 'amount' => '1000'],
            ['code' => 'SPECIAL_DONATION', 'amount' => '1000'],
        ]])->assertOk()->json('data.trace');
        $codes = array_column($trace, 'code');

        // Submission order does not change the printed calculation order.
        $this->assertLessThan(array_search('GENERAL_DONATION', $codes, true), array_search('SPECIAL_DONATION', $codes, true));
        $this->assertSame('618000.00', $trace[array_search('INCOME_AFTER_SPECIAL_DONATION', $codes, true)]['amount']);
    }

    public function test_a_donation_line_is_capped_once_however_much_is_declared(): void
    {
        $this->calculate(['donations' => [['code' => 'SPECIAL_DONATION', 'amount' => '40000']]])->assertOk()
            // 40000 x 2 = 80000, capped at 10% of income after expense (620000) = 62000.
            ->assertJsonPath('data.donations.total_input', '40000.00')
            ->assertJsonPath('data.donations.total_eligible', '62000.00');
    }

    /**
     * The same line may no longer be declared twice.
     *
     * The engine has always shared one cap across a line, so two entries of 20000 and one of
     * 40000 produced the same result. M9.2 made the duplicate an input error instead: the form
     * prints one box per line, so two boxes of the same kind is a data-entry mistake worth
     * reporting rather than silently summing.
     */
    public function test_one_donation_line_may_be_declared_only_once(): void
    {
        $this->calculate(['donations' => [
            ['code' => 'SPECIAL_DONATION', 'amount' => '20000'],
            ['code' => 'SPECIAL_DONATION', 'amount' => '20000'],
        ]])->assertUnprocessable()->assertJsonValidationErrors('donations.1.code');
    }

    public function test_invalid_donations_are_rejected(): void
    {
        $this->calculate(['donations' => [['code' => 'NOT_A_LINE', 'amount' => '1']]])
            ->assertUnprocessable()->assertJsonValidationErrors('donations.0.code');
        $this->calculate(['donations' => [['code' => 'GENERAL_DONATION', 'amount' => '-1']]])
            ->assertUnprocessable()->assertJsonValidationErrors('donations.0.amount');
    }

    public function test_donation_seeding_is_repeatable_and_refuses_a_differing_rule(): void
    {
        $before = DB::table('donation_rules')->orderBy('id')->get()->toJson();
        $this->seed(DonationRuleSeeder::class);
        $this->assertSame($before, DB::table('donation_rules')->orderBy('id')->get()->toJson());

        DB::table('donation_rules')->where('code', 'SPECIAL_DONATION')->update(['multiplier' => '3.000']);
        $this->expectException(\RuntimeException::class);
        $this->seed(DonationRuleSeeder::class);
    }

    // ---------------------------------------------------------------- allowances

    public function test_only_the_printed_attachment_allowance_is_seeded(): void
    {
        // M7.3 added five further ceilings read from the filing-instruction booklet; this
        // M7.2 case still owns the attachment-only rule it seeded.
        $rule = DB::table('allowance_rules')->where('rule_version_id', $this->publishedVersionId())->where('code', 'PND90_2568_PROVIDENT_FUND')->sole();

        $this->assertSame('PND90_2568_PROVIDENT_FUND', $rule->code);
        $this->assertSame('actual', $rule->method);
        $this->assertEquals('10000.00', $rule->maximum_amount);
        $this->assertNull($rule->fixed_amount);
        $this->assertNull($rule->percentage);
        $this->assertStringContainsString('ภงด.90.pdf', (string) $rule->source_reference);
    }

    public static function providentFund(): array
    {
        return [
            'zero' => ['0', '0.00', '620000.00'],
            'below the cap' => ['4000', '4000.00', '616000.00'],
            'exactly at the cap' => ['10000', '10000.00', '610000.00'],
            'one satang above the cap' => ['10000.01', '10000.00', '610000.00'],
            'far above the cap' => ['750000', '10000.00', '610000.00'],
        ];
    }

    #[DataProvider('providentFund')]
    public function test_the_provident_fund_allowance_caps_at_ten_thousand(string $paid, string $eligible, string $net): void
    {
        $this->calculate(['allowances' => [['code' => 'PROVIDENT_FUND', 'amount' => $paid]]])->assertOk()
            ->assertJsonPath('data.allowances.total_eligible', $eligible)
            ->assertJsonPath('data.allowances.items.0.rule_status', 'VERIFIED')
            ->assertJsonPath('data.net_income', $net);
    }

    /**
     * M7.5 replaced "deducts nothing and warns" with an outright refusal. PENSION_INSURANCE is
     * PARTIAL_BLOCKED — ใบแนบ item 7.6 states a 90,000 amount and a further 15%/200,000
     * entitlement without saying whether the two nest — so a positive amount is a 422 and
     * cannot quietly shrink the deduction total.
     */
    public function test_an_allowance_whose_amount_the_form_does_not_print_is_refused(): void
    {
        $this->calculate(['allowances' => [
            ['code' => 'PENSION_INSURANCE', 'amount' => '60000'],
            ['code' => 'PROVIDENT_FUND', 'amount' => '10000'],
        ]])->assertUnprocessable()->assertJsonValidationErrors('allowances.0.code');

        // A zero line is still accepted: it cannot change the tax in either direction.
        $response = $this->calculate(['allowances' => [
            ['code' => 'PENSION_INSURANCE', 'amount' => '0'],
            ['code' => 'PROVIDENT_FUND', 'amount' => '10000'],
        ]])->assertOk()
            ->assertJsonPath('data.allowances.items.0.rule_status', 'UNVERIFIED')
            ->assertJsonPath('data.allowances.items.0.eligible_amount', '0.00')
            ->assertJsonPath('data.allowances.total_eligible', '10000.00');

        $this->assertContains('UNVERIFIED_ALLOWANCE_RULE', array_column($response->json('data.warnings'), 'code'));
    }

    public function test_allowance_seeding_is_repeatable_and_refuses_a_differing_rule(): void
    {
        $before = DB::table('allowance_rules')->orderBy('id')->get()->toJson();
        $this->seed(AllowanceRuleSeeder::class);
        $this->assertSame($before, DB::table('allowance_rules')->orderBy('id')->get()->toJson());

        DB::table('allowance_rules')->update(['maximum_amount' => '20000.00']);
        $this->expectException(\RuntimeException::class);
        $this->seed(AllowanceRuleSeeder::class);
    }

    // ---------------------------------------------------------------- credits

    public static function prepayments(): array
    {
        return [
            'withholding' => ['withholding'],
            'pnd93' => ['pnd93'],
            'pnd94' => ['pnd94'],
        ];
    }

    /**
     * The prepaid lines ภ.ง.ด.91 prints — and only those.
     *
     * This helper defaults to **PND91**, which is where the error crept in: the test was written
     * from ภ.ง.ด.90 item 15, which prints three prepaid lines, and pointed at a form that prints
     * two. ภ.ง.ด.94 is the half-year return for มาตรา 40 (5)–(8) and does not appear anywhere in
     * the ภ.ง.ด.91 form.
     */
    #[DataProvider('prepayments')]
    public function test_each_prepayment_on_item_15_reduces_the_balance_in_full(string $type): void
    {
        if ($type === 'pnd94') {
            // Its own form, its own test: see PrepaidCreditBelongsToItsFormTest.
            $this->calculate(['withholdings' => [['type' => $type, 'amount' => '1000']]])
                ->assertUnprocessable()->assertJsonValidationErrors('withholdings.0.type');

            return;
        }

        foreach ([['0', 'PAYABLE', '45500.00'], ['20000', 'PAYABLE', '25500.00'],
            ['45500', 'ZERO', '0.00'], ['50000', 'REFUND', '4500.00']] as [$paid, $status, $amount]) {
            $this->calculate(['withholdings' => [['type' => $type, 'amount' => $paid]]])->assertOk()
                ->assertJsonPath('data.credits.'.$type, number_format((float) $paid, 2, '.', ''))
                ->assertJsonPath('data.credits.total', number_format((float) $paid, 2, '.', ''))
                ->assertJsonPath('data.result.status', $status)
                ->assertJsonPath('data.result.amount', $amount);
        }
    }

    public function test_prepayments_accumulate_across_the_lines_the_form_prints(): void
    {
        // Two on ภ.ง.ด.91, not three. The old name said "the three checkboxes", which is a
        // ภ.ง.ด.90 fact that had been carried onto the wrong form.
        $this->calculate(['withholdings' => [
            ['type' => 'withholding', 'amount' => '30000'],
            ['type' => 'pnd93', 'amount' => '15500'],
        ]])->assertOk()->assertJsonPath('data.credits.total', '45500.00')
            ->assertJsonPath('data.result.status', 'ZERO');
    }

    public function test_all_three_prepaid_lines_accumulate_on_pnd90(): void
    {
        // ภ.ง.ด.90 item 15 does print all three, so they must still add up there.
        $this->calculate(['incomes' => [['income_type' => 'SECTION_40_5',
            'income_subtype' => 'RENT_BUILDING_OR_RAFT', 'gross_amount' => '720000.00',
            'exempt_amount' => '0.00', 'expense_method_selection' => 'percentage']],
            'withholdings' => [
                ['type' => 'withholding', 'amount' => '10000'],
                ['type' => 'pnd93', 'amount' => '10000'],
                ['type' => 'pnd94', 'amount' => '10000'],
            ]], 'PND90')->assertOk()->assertJsonPath('data.credits.total', '30000.00');
    }

    public function test_a_negative_prepayment_is_rejected(): void
    {
        $this->calculate(['withholdings' => [['type' => 'pnd94', 'amount' => '-1']]])
            ->assertUnprocessable()->assertJsonValidationErrors('withholdings.0.amount');
    }

    /**
     * M7.5 — a credit this baseline cannot calculate is refused rather than zeroed. ข้อ 11 item
     * 13's foreign-tax-credit limit is not printed specifically enough to apply, and
     * `other_credit` has no counterpart on the form at all.
     */
    public function test_foreign_tax_credit_and_other_credit_are_refused(): void
    {
        $this->calculate(['withholdings' => [['type' => 'foreign_tax_credit', 'amount' => '999999']]])
            ->assertUnprocessable()->assertJsonValidationErrors('withholdings.0.type');
        $this->calculate(['withholdings' => [['type' => 'other_credit', 'amount' => '999999']]])
            ->assertUnprocessable()->assertJsonValidationErrors('withholdings.0.type');

        // Zero still passes, still grants nothing, and still says so.
        $response = $this->calculate(['withholdings' => [
            ['type' => 'foreign_tax_credit', 'amount' => '0'],
            ['type' => 'other_credit', 'amount' => '0'],
        ]])->assertOk()
            ->assertJsonPath('data.credits.total', '0.00')
            ->assertJsonPath('data.result.amount', '45500.00');

        $this->assertContains('UNVERIFIED_FOREIGN_TAX_CREDIT_LIMIT', array_column($response->json('data.warnings'), 'code'));
        $this->assertContains('UNVERIFIED_TAX_CREDIT_RULE', array_column($response->json('data.warnings'), 'code'));
    }

    // ---------------------------------------------------------------- separate rate

    private function gift(string $gross, string $treatment = SeparateTaxCalculator::TREATMENT): array
    {
        return ['income_type' => 'SECTION_40_8', 'income_subtype' => 'GIFT_OR_SUPPORT_RECEIVED',
            'gross_amount' => $gross, 'exempt_amount' => '0.00', 'tax_treatment' => $treatment];
    }

    public function test_elected_gift_income_is_taxed_at_five_percent_outside_the_progressive_base(): void
    {
        $response = $this->calculate(['incomes' => [
            ['income_type' => 'SECTION_40_1', 'gross_amount' => '720000.00', 'exempt_amount' => '0.00'],
            $this->gift('400000'),
        ]], 'PND90')->assertOk()
            // The elected line stays out of ข้อ 1–ข้อ 7 entirely.
            ->assertJsonPath('data.income.gross_income', '720000.00')
            ->assertJsonPath('data.net_income', '620000.00')
            ->assertJsonPath('data.progressive_tax.total', '45500.00')
            ->assertJsonPath('data.separate_tax.base', '400000.00')
            ->assertJsonPath('data.separate_tax.rate', '5')
            ->assertJsonPath('data.separate_tax.tax', '20000.00')
            ->assertJsonPath('data.tax_components.progressive_tax', '45500.00')
            ->assertJsonPath('data.tax_components.separate_tax', '20000.00')
            ->assertJsonPath('data.tax_components.total', '65500.00')
            // ข้อ 11 item 19 adds it after the credits.
            ->assertJsonPath('data.result.status', 'PAYABLE')
            ->assertJsonPath('data.result.amount', '65500.00');

        $codes = array_column($response->json('data.trace'), 'code');
        $this->assertSame(['SEPARATE_TAX_BASE', 'SEPARATE_TAX', 'RESULT'], array_slice($codes, -3));
    }

    public function test_the_same_income_joins_the_progressive_base_when_not_elected(): void
    {
        $this->calculate(['incomes' => [
            ['income_type' => 'SECTION_40_1', 'gross_amount' => '720000.00', 'exempt_amount' => '0.00'],
            $this->gift('400000', SeparateTaxCalculator::PROGRESSIVE),
        ]], 'PND90')->assertOk()
            ->assertJsonPath('data.income.gross_income', '1120000.00')
            ->assertJsonPath('data.separate_tax.tax', '0.00')
            ->assertJsonPath('data.net_income', '1020000.00')
            ->assertJsonCount(16, 'data.trace');
    }

    public function test_the_separate_tax_is_added_after_the_prepayments(): void
    {
        $this->calculate(['incomes' => [
            ['income_type' => 'SECTION_40_1', 'gross_amount' => '720000.00', 'exempt_amount' => '0.00'],
            $this->gift('100000'),
        ], 'withholdings' => [['type' => 'withholding', 'amount' => '50000']]], 'PND90')->assertOk()
            // 45500 progressive less 50000 prepaid is a 4500 refund, plus 5000 separate tax.
            ->assertJsonPath('data.separate_tax.tax', '5000.00')
            ->assertJsonPath('data.result.status', 'PAYABLE')
            ->assertJsonPath('data.result.amount', '500.00');
    }

    public function test_a_separate_rate_election_is_refused_for_any_other_category(): void
    {
        foreach ([['SECTION_40_1', null], ['SECTION_40_7', null],
            ['SECTION_40_8', 'IMMOVABLE_PROPERTY_INHERITED_OR_GIFTED']] as [$code, $subtype]) {
            $this->calculate(['incomes' => [['income_type' => $code, 'gross_amount' => '100000', 'exempt_amount' => '0.00',
                'tax_treatment' => SeparateTaxCalculator::TREATMENT,
                ...($subtype === null ? [] : ['income_subtype' => $subtype]),
                ...($code === 'SECTION_40_7' ? ['expense_method_selection' => 'percentage'] : [])]]], 'PND90')
                ->assertUnprocessable()->assertJsonValidationErrors('incomes.0.tax_treatment');
        }
    }

    public function test_an_unknown_treatment_and_an_all_elected_return_are_rejected(): void
    {
        $this->calculate(['incomes' => [['income_type' => 'SECTION_40_1', 'gross_amount' => '1',
            'exempt_amount' => '0.00', 'tax_treatment' => 'SOMETHING_ELSE']]], 'PND90')
            ->assertUnprocessable()->assertJsonValidationErrors('incomes.0.tax_treatment');

        $this->calculate(['incomes' => [$this->gift('400000')]], 'PND90')
            ->assertUnprocessable()->assertJsonValidationErrors('incomes');
    }

    public function test_pnd91_cannot_reach_the_separate_rate_election(): void
    {
        $this->calculate(['incomes' => [
            ['income_type' => 'SECTION_40_1', 'gross_amount' => '720000.00', 'exempt_amount' => '0.00'],
            $this->gift('400000'),
        ]])->assertUnprocessable()->assertJsonValidationErrors('incomes.1.income_type');
    }

    public function test_elected_income_keeps_fractional_satang(): void
    {
        $this->calculate(['incomes' => [
            ['income_type' => 'SECTION_40_1', 'gross_amount' => '720000.00', 'exempt_amount' => '0.00'],
            $this->gift('0.01'),
        ]], 'PND90')->assertOk()->assertJsonPath('data.separate_tax.tax', '0.0005');
    }

    // ---------------------------------------------------------------- member and planning

    public function test_a_member_return_persists_and_calculates_every_new_rule(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $id = $this->postJson('/api/v1/tax-returns', ['tax_year' => 2568, 'form_code' => 'PND90', 'name' => 'M7.2 synthetic'])
            ->assertCreated()->json('data.id');
        $this->postJson("/api/v1/tax-returns/$id/incomes",
            ['income_type' => 'SECTION_40_1', 'gross_amount' => '720000.00', 'exempt_amount' => '0.00'])->assertCreated();
        $this->postJson("/api/v1/tax-returns/$id/incomes", ['income_type' => 'SECTION_40_8',
            'income_subtype' => 'GIFT_OR_SUPPORT_RECEIVED', 'gross_amount' => '400000.00',
            'tax_treatment' => SeparateTaxCalculator::TREATMENT])->assertCreated();
        $this->postJson("/api/v1/tax-returns/$id/allowances", ['code' => 'PROVIDENT_FUND', 'input_amount' => '25000'])->assertCreated();
        $this->postJson("/api/v1/tax-returns/$id/donations", ['donation_code' => 'SPECIAL_DONATION', 'input_amount' => '10000'])->assertCreated();
        $this->postJson("/api/v1/tax-returns/$id/withholdings", ['type' => 'pnd94', 'amount' => '10000'])->assertCreated();

        $this->getJson("/api/v1/tax-returns/$id")->assertOk()
            ->assertJsonPath('data.incomes.1.tax_treatment', SeparateTaxCalculator::TREATMENT);

        $member = $this->postJson("/api/v1/tax-returns/$id/calculate")->assertOk()->json('data.calculation');
        $guest = $this->postJson('/api/v1/tax/calculate', ['tax_year' => 2568, 'form_code' => 'PND90',
            'incomes' => [['income_type' => 'SECTION_40_1', 'gross_amount' => '720000.00', 'exempt_amount' => '0.00'],
                ['income_type' => 'SECTION_40_8', 'income_subtype' => 'GIFT_OR_SUPPORT_RECEIVED',
                    'gross_amount' => '400000.00', 'exempt_amount' => '0.00', 'tax_treatment' => SeparateTaxCalculator::TREATMENT]],
            'allowances' => [['code' => 'PROVIDENT_FUND', 'amount' => '25000']],
            'donations' => [['code' => 'SPECIAL_DONATION', 'amount' => '10000']],
            'withholdings' => [['type' => 'pnd94', 'amount' => '10000']]])->assertOk()->json('data');

        $this->assertJsonStringEqualsJsonString(json_encode($guest), json_encode($member));
        $this->assertSame('10000.00', $member['allowances']['total_eligible']);
        $this->assertSame('20000.00', $member['donations']['total_eligible']);
        $this->assertSame('20000.00', $member['separate_tax']['tax']);
        $this->assertSame('completed', TaxReturn::findOrFail(
            $this->postJson("/api/v1/tax-returns/$id/complete")->assertOk() ? $id : $id)->status);
    }

    public function test_planning_a_verified_allowance_changes_tax_through_the_shared_engine(): void
    {
        $base = ['incomes' => [['income_type' => 'SECTION_40_1', 'gross_amount' => '720000.00', 'exempt_amount' => '0.00']],
            'allowances' => [], 'donations' => [], 'withholdings' => [['type' => 'withholding', 'amount' => '25000.00']]];

        $plan = $this->postJson('/api/v1/tax/plan', ['tax_year' => 2568, 'form_code' => 'PND91', 'base' => $base,
            'scenario' => ['allowances' => ['upsert' => [['code' => 'PROVIDENT_FUND', 'input_amount' => '10000']]]]])
            ->assertOk()->json('data');

        // 620000 -> 610000 of net income moves 10000 out of the 15% bracket.
        $this->assertSame('620000.00', $plan['before']['net_income']);
        $this->assertSame('610000.00', $plan['after']['net_income']);
        $this->assertSame('45500.00', $plan['before']['calculated_tax']);
        $this->assertSame('44000.00', $plan['after']['calculated_tax']);
        $this->assertSame('1500.00', $plan['estimated_tax_saving']);
        $this->assertSame('-1500.00', $plan['difference']['calculated_tax']);
    }

    public function test_planning_a_verified_donation_changes_tax_through_the_shared_engine(): void
    {
        $base = ['incomes' => [['income_type' => 'SECTION_40_1', 'gross_amount' => '720000.00', 'exempt_amount' => '0.00']],
            'allowances' => [], 'donations' => [], 'withholdings' => []];

        $plan = $this->postJson('/api/v1/tax/plan', ['tax_year' => 2568, 'form_code' => 'PND91', 'base' => $base,
            'scenario' => ['donations' => ['upsert' => [['code' => 'SPECIAL_DONATION', 'input_amount' => '10000']]]]])
            ->assertOk()->json('data');

        $this->assertSame('600000.00', $plan['after']['net_income']);
        $this->assertSame('42500.00', $plan['after']['calculated_tax']);
        $this->assertSame('3000.00', $plan['estimated_tax_saving']);
    }

    public function test_recommendations_still_ask_the_user_to_verify_rather_than_asserting_eligibility(): void
    {
        $messages = array_column($this->calculate(['withholdings' => [['type' => 'withholding', 'amount' => '25000']]])
            ->assertOk()->json('data.recommendations'), 'message');

        $this->assertNotEmpty($messages);
        foreach ($messages as $message) {
            foreach (['มีสิทธิแน่นอน', 'ได้รับคืนแน่นอน', 'ควรซื้อ'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $message);
            }
        }
    }

    public function test_pnd91_without_any_new_input_is_unchanged(): void
    {
        $this->calculate(['withholdings' => [['type' => 'withholding', 'amount' => '25000.00']]])->assertOk()
            ->assertJsonPath('data.expenses.total', '100000.00')
            ->assertJsonPath('data.net_income', '620000.00')
            ->assertJsonPath('data.progressive_tax.total', '45500.00')
            ->assertJsonPath('data.result.amount', '20500.00')
            ->assertJsonPath('data.separate_tax.tax', '0.00')
            ->assertJsonCount(16, 'data.trace');
    }
}

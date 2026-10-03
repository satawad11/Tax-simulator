<?php

namespace Tests\Feature;

use App\Models\AllowanceType;
use App\Models\TaxCalculation;
use App\Models\TaxReturn;
use App\Models\User;
use App\Services\Tax\AllowanceCoverageCatalogue;
use App\Services\Tax\FamilyAllowanceResolver;
use App\Services\Tax\TaxCreditCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Milestone 07.5 — the frozen PND91 / PND90 production baseline.
 *
 * This is the closure suite for Milestone 7.x. Earlier suites each prove one milestone's rules;
 * this one asserts the shape of the finished baseline: that every supported path still produces
 * the figures the sources give, and that every path the baseline cannot calculate refuses
 * rather than quietly returning a smaller deduction.
 *
 * See docs/tax/TAX_ENGINE_BASELINE_2568.md for the classification this suite enforces.
 */
class TaxEngineBaselineClosureTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    /** @param list<array<string, mixed>> $overrides */
    private function calculate(array $overrides, string $form = 'PND91'): TestResponse
    {
        return $this->postJson('/api/v1/tax/calculate', ['tax_year' => 2568, 'form_code' => $form,
            'incomes' => [['income_type' => 'SECTION_40_1', 'gross_amount' => '720000.00']],
            'allowances' => [], 'donations' => [], 'withholdings' => [], ...$overrides]);
    }

    // ================================================================= PND91 baseline

    public function test_pnd91_no_family_baseline_is_unchanged(): void
    {
        // The regression figure carried since M4: 720,000 less the 100,000 employment expense
        // ceiling is 620,000 net, which the 2568 brackets tax at 45,500.
        $this->calculate([])->assertOk()
            ->assertJsonPath('data.income.gross_income', '720000.00')
            ->assertJsonPath('data.expenses.total', '100000.00')
            ->assertJsonPath('data.income_after_expense', '620000.00')
            ->assertJsonPath('data.net_income', '620000.00')
            ->assertJsonPath('data.progressive_tax.total', '45500.00')
            ->assertJsonPath('data.result.status', 'PAYABLE')
            ->assertJsonPath('data.result.amount', '45500.00');
    }

    /** @return array<string, array{string, string, string}> */
    public static function pnd91Outcomes(): array
    {
        return [
            'PAYABLE' => ['25000.00', 'PAYABLE', '20500.00'],
            'REFUND' => ['50000.00', 'REFUND', '4500.00'],
            'ZERO' => ['45500.00', 'ZERO', '0.00'],
        ];
    }

    #[DataProvider('pnd91Outcomes')]
    public function test_pnd91_reaches_every_result_status(string $withheld, string $status, string $amount): void
    {
        $this->calculate(['withholdings' => [['type' => 'withholding', 'amount' => $withheld]]])->assertOk()
            ->assertJsonPath('data.result.status', $status)
            ->assertJsonPath('data.result.amount', $amount);
    }

    public function test_pnd91_family_allowances_are_derived_and_reduce_the_tax(): void
    {
        // 60,000 ผู้มีเงินได้ + 60,000 คู่สมรส + 30,000 + 30,000 บุตร (คนที่สอง เกิดหลัง พ.ศ. 2561).
        $this->calculate([
            'profile' => ['marital_status' => 'married'],
            'spouse' => ['has_income' => false],
            'dependents' => [['relation_type' => 'child', 'child_type' => 'legitimate',
                'birth_order' => 2, 'birth_date' => '2020-05-12', 'eligible' => true]],
            'allowances' => [['code' => 'PERSONAL', 'amount' => '0'], ['code' => 'SPOUSE', 'amount' => '0'],
                ['code' => 'CHILD', 'amount' => '0']],
        ])->assertOk()
            ->assertJsonPath('data.allowances.total_eligible', '180000.00')
            ->assertJsonPath('data.net_income', '440000.00')
            ->assertJsonPath('data.progressive_tax.total', '21500.00');
    }

    public function test_pnd91_applies_a_supported_non_family_allowance_and_a_supported_donation(): void
    {
        $this->calculate(['allowances' => [['code' => 'HOME_LOAN_INTEREST', 'amount' => '120000.00']]])->assertOk()
            ->assertJsonPath('data.allowances.total_eligible', '100000.00')
            ->assertJsonPath('data.net_income', '520000.00');

        // ข้อ 11 item 4: twice the amount paid, capped at 10% of 620,000.
        $this->calculate(['donations' => [['code' => 'SPECIAL_DONATION', 'amount' => '20000.00']]])->assertOk()
            ->assertJsonPath('data.donations.total_eligible', '40000.00')
            ->assertJsonPath('data.net_income', '580000.00');
    }

    public function test_pnd91_refuses_an_allowance_the_baseline_cannot_calculate(): void
    {
        $this->calculate(['allowances' => [['code' => 'SOCIAL_SECURITY', 'amount' => '9000.00']]])
            ->assertUnprocessable()->assertJsonValidationErrors('allowances.0.code');
    }

    // ================================================================= PND90 baseline

    /**
     * One calculable line per Section 40 category, so the whole printed income structure is
     * exercised in a single progressive calculation.
     *
     * @return list<array<string, mixed>>
     */
    private function everySection(): array
    {
        return [
            ['income_type' => 'SECTION_40_1', 'gross_amount' => '100000.00'],
            ['income_type' => 'SECTION_40_2', 'gross_amount' => '100000.00'],
            ['income_type' => 'SECTION_40_3', 'gross_amount' => '100000.00',
                'income_subtype' => 'COPYRIGHT_GOODWILL_OTHER_RIGHTS', 'expense_method_selection' => 'percentage'],
            ['income_type' => 'SECTION_40_4', 'gross_amount' => '100000.00'],
            ['income_type' => 'SECTION_40_5', 'gross_amount' => '100000.00',
                'income_subtype' => 'RENT_BUILDING_OR_RAFT', 'expense_method_selection' => 'percentage'],
            ['income_type' => 'SECTION_40_6', 'gross_amount' => '100000.00',
                'income_subtype' => 'MEDICAL_PRACTICE', 'expense_method_selection' => 'percentage'],
            ['income_type' => 'SECTION_40_7', 'gross_amount' => '100000.00', 'expense_method_selection' => 'percentage'],
            ['income_type' => 'SECTION_40_8', 'gross_amount' => '100000.00',
                'income_subtype' => 'MUTUAL_FUND_PROFIT_SHARE'],
        ];
    }

    public function test_pnd90_calculates_every_section_40_category_in_one_return(): void
    {
        // 100,000 shared ข้อ 1 ceiling + 50,000 + 0 + 30,000 + 60,000 + 60,000 + 0 = 300,000.
        $response = $this->calculate(['incomes' => $this->everySection()], 'PND90')->assertOk()
            ->assertJsonPath('data.income.gross_income', '800000.00')
            ->assertJsonPath('data.expenses.total', '300000.00')
            ->assertJsonPath('data.income_after_expense', '500000.00')
            ->assertJsonPath('data.net_income', '500000.00')
            ->assertJsonPath('data.progressive_tax.total', '27500.00')
            // Non-40(1) gross is 700,000; 0.5% is 3,500, which the printed 5,000 floor disregards.
            ->assertJsonPath('data.minimum_tax.base', '700000.00')
            ->assertJsonPath('data.minimum_tax.applicable', false);

        $this->assertSame(array_fill(0, 7, 'VERIFIED'),
            array_column($response->json('data.expenses.items'), 'rule_status'));
    }

    public function test_pnd90_prices_the_m7_4_expense_subtypes_together(): void
    {
        $this->calculate(['incomes' => [
            ['income_type' => 'SECTION_40_5', 'gross_amount' => '200000.00',
                'income_subtype' => 'RENT_LAND_AGRICULTURAL', 'expense_method_selection' => 'percentage'],
            ['income_type' => 'SECTION_40_8', 'gross_amount' => '500000.00', 'income_subtype' => 'BUSINESS_COMMERCE_OTHER',
                'expense_activity' => 'TABLE2_17_TRANSPORT_BY_VEHICLE', 'expense_method_selection' => 'percentage'],
            ['income_type' => 'SECTION_40_8', 'gross_amount' => '400000.00',
                'income_subtype' => 'IMMOVABLE_PROPERTY_NON_TRADE', 'holding_years' => 5,
                'expense_method_selection' => 'percentage'],
        ]], 'PND90')->assertOk()
            // 40,000 (20%) + 300,000 (60%) + 260,000 (65%).
            ->assertJsonPath('data.expenses.total', '600000.00')
            ->assertJsonPath('data.income_after_expense', '500000.00');
    }

    public function test_pnd90_pays_the_minimum_tax_when_it_is_the_greater(): void
    {
        $response = $this->calculate(['incomes' => [['income_type' => 'SECTION_40_7', 'gross_amount' => '2000000.00',
            'expense_method_selection' => 'actual', 'actual_expense' => '1990000.00']]], 'PND90')->assertOk()
            ->assertJsonPath('data.progressive_tax.total', '0.00')
            ->assertJsonPath('data.minimum_tax.applicable', true)
            ->assertJsonPath('data.minimum_tax.method', 'MINIMUM_TAX')
            ->assertJsonPath('data.result.amount', '10000.00');

        $this->assertContains('PND90_MINIMUM_TAX_APPLIED', array_column($response->json('data.warnings'), 'code'));
    }

    public function test_pnd90_applies_a_percentage_allowance_and_a_combined_cap_together(): void
    {
        $this->calculate(['incomes' => [['income_type' => 'SECTION_40_1', 'gross_amount' => '1000000.00']],
            'allowances' => [
                ['code' => 'RMF', 'amount' => '400000.00'],
                ['code' => 'NSF', 'amount' => '400000.00'],
                ['code' => 'LIFE_INSURANCE', 'amount' => '100000.00'],
                ['code' => 'HEALTH_INSURANCE', 'amount' => '25000.00'],
            ]], 'PND90')->assertOk()
            // RMF: 30% of 1,000,000 = 300,000. Retirement basket 300,000 + 400,000 -> 500,000.
            ->assertJsonPath('data.allowances.items.0.eligible_amount', '300000.00')
            ->assertJsonPath('data.allowances.items.1.eligible_amount', '400000.00')
            // Life + health 125,000 -> 100,000. Total 500,000 + 100,000.
            ->assertJsonPath('data.allowances.total_eligible', '600000.00')
            ->assertJsonCount(2, 'data.allowances.combined_cap_groups');
    }

    /** @return array<string, array{string, string, string}> */
    public static function pnd90Outcomes(): array
    {
        // Gross 800,000 across every section, tax 27,500.
        return [
            'PAYABLE' => ['10000.00', 'PAYABLE', '17500.00'],
            'REFUND' => ['30000.00', 'REFUND', '2500.00'],
            'ZERO' => ['27500.00', 'ZERO', '0.00'],
        ];
    }

    #[DataProvider('pnd90Outcomes')]
    public function test_pnd90_reaches_every_result_status(string $withheld, string $status, string $amount): void
    {
        $this->calculate(['incomes' => $this->everySection(),
            'withholdings' => [['type' => 'withholding', 'amount' => $withheld]]], 'PND90')->assertOk()
            ->assertJsonPath('data.result.status', $status)
            ->assertJsonPath('data.result.amount', $amount);
    }

    // ================================================================= guarded paths

    /** @return array<string, array{string}> */
    public static function blockedAllowances(): array
    {
        return array_combine(AllowanceCoverageCatalogue::codes(),
            array_map(fn (string $code): array => [$code], AllowanceCoverageCatalogue::codes()));
    }

    #[DataProvider('blockedAllowances')]
    public function test_every_blocked_allowance_refuses_a_positive_amount_and_accepts_zero(string $code): void
    {
        $this->calculate(['allowances' => [['code' => $code, 'amount' => '1.00']]])
            ->assertUnprocessable()->assertJsonValidationErrors('allowances.0.code');

        $this->calculate(['allowances' => [['code' => $code, 'amount' => '0.00']]])->assertOk()
            ->assertJsonPath('data.allowances.total_eligible', '0.00')
            ->assertJsonPath('data.net_income', '620000.00');
    }

    #[DataProvider('blockedAllowances')]
    public function test_a_blocked_allowance_reports_its_stable_error_code(string $code): void
    {
        // The validation key itself contains dots, so it is read from the errors map directly.
        $errors = $this->calculate(['allowances' => [['code' => $code, 'amount' => '1.00']]])
            ->assertUnprocessable()->json('errors');

        $this->assertStringStartsWith(AllowanceCoverageCatalogue::errorCode($code).':', $errors['allowances.0.code'][0]);
    }

    /** @return array<string, array{string}> */
    public static function unsupportedCredits(): array
    {
        return ['foreign tax credit' => ['foreign_tax_credit'], 'other credit' => ['other_credit']];
    }

    #[DataProvider('unsupportedCredits')]
    public function test_an_unsupported_credit_refuses_a_positive_amount(string $type): void
    {
        $errors = $this->calculate(['withholdings' => [['type' => $type, 'amount' => '1.00']]])
            ->assertUnprocessable()->assertJsonValidationErrors('withholdings.0.type')->json('errors');

        $this->assertStringStartsWith($type === 'foreign_tax_credit'
            ? 'FOREIGN_TAX_CREDIT_UNSUPPORTED:' : 'TAX_CREDIT_TYPE_UNSUPPORTED:', $errors['withholdings.0.type'][0]);
    }

    /** @return array<string, array{string}> */
    public static function supportedPrepayments(): array
    {
        return array_combine(TaxCreditCalculator::PREPAID_TYPES,
            array_map(fn (string $type): array => [$type], TaxCreditCalculator::PREPAID_TYPES));
    }

    /**
     * Each prepaid line is exercised on the form that prints it.
     *
     * This ran every type against ภ.ง.ด.91, which prints only two prepaid lines — หัก ณ ที่จ่าย and
     * ภ.ง.ด.93. ภ.ง.ด.94 is the half-year return for มาตรา 40 (5)–(8) and appears nowhere in the
     * ภ.ง.ด.91 form; the test asserted it reduced the balance there, so it locked the defect in
     * rather than catching it. `PrepaidCreditBelongsToItsFormTest` now holds that boundary.
     */
    #[DataProvider('supportedPrepayments')]
    public function test_every_supported_prepayment_still_reduces_the_balance_in_full(string $type): void
    {
        $form = $type === 'pnd94' ? 'PND90' : 'PND91';
        $income = $form === 'PND90'
            ? ['income_type' => 'SECTION_40_5', 'income_subtype' => 'RENT_BUILDING_OR_RAFT',
                'gross_amount' => '720000.00', 'exempt_amount' => '0.00', 'expense_method_selection' => 'percentage']
            : ['income_type' => 'SECTION_40_1', 'gross_amount' => '720000.00', 'exempt_amount' => '0.00'];

        $this->calculate(['incomes' => [$income],
            'withholdings' => [['type' => $type, 'amount' => '5000.00']]], $form)->assertOk()
            ->assertJsonPath('data.credits.total', '5000.00')
            ->assertJsonPath('data.credits.'.$type, '5000.00');
    }

    public function test_a_blocked_path_is_refused_on_every_entry_point(): void
    {
        // Guest.
        $this->calculate(['allowances' => [['code' => 'PENSION_INSURANCE', 'amount' => '1.00']]])
            ->assertUnprocessable()->assertJsonValidationErrors('allowances.0.code');

        // Planning, through the same guard one level deeper.
        $this->postJson('/api/v1/tax/plan', ['tax_year' => 2568, 'form_code' => 'PND91',
            'base' => ['incomes' => [['income_type' => 'SECTION_40_1', 'gross_amount' => '720000.00']],
                'allowances' => [['code' => 'PENSION_INSURANCE', 'amount' => '1.00']]],
            'scenario' => []])->assertUnprocessable()->assertJsonValidationErrors('base.allowances.0.code');

        // Member, at calculate time on a saved draft.
        Sanctum::actingAs(User::factory()->create());
        $id = $this->postJson('/api/v1/tax-returns', ['tax_year' => 2568, 'form_code' => 'PND91',
            'name' => 'M7.5 guard'])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/tax-returns/$id/incomes",
            ['income_type' => 'SECTION_40_1', 'gross_amount' => '720000.00'])->assertCreated();
        $this->postJson("/api/v1/tax-returns/$id/allowances",
            ['code' => 'PENSION_INSURANCE', 'input_amount' => '1.00'])->assertCreated();

        $this->postJson("/api/v1/tax-returns/$id/calculate")->assertUnprocessable()
            ->assertJsonValidationErrors('allowances.0.code');
        $this->assertDatabaseCount('tax_calculations', 0);
        $this->assertSame('draft', TaxReturn::findOrFail($id)->status);
    }

    // ================================================================= classification integrity

    /**
     * The coverage catalogue must name exactly the master codes that are neither family-derived
     * nor backed by a seeded rule. Seeding a rule for a blocked code, or adding a master code
     * with no rule, therefore cannot silently leave the classification wrong.
     */
    public function test_every_allowance_master_code_has_exactly_one_final_status(): void
    {
        // Scoped to the live version: the retired predecessor holds its own copy of every rule,
        // so an unscoped join would report each ruled code twice and classify nothing.
        $ruled = array_values(array_unique(DB::table('allowance_rules')
            ->where('allowance_rules.rule_version_id', $this->publishedVersionId())
            ->join('allowance_types', 'allowance_types.id', '=', 'allowance_rules.allowance_type_id')
            ->pluck('allowance_types.code')->all()));
        $derived = app(FamilyAllowanceResolver::class)->codes();
        $blocked = AllowanceCoverageCatalogue::codes();
        $all = AllowanceType::orderBy('code')->pluck('code')->all();

        $this->assertSame([], array_intersect($blocked, $ruled), 'A blocked code must have no seeded rule.');
        $this->assertSame([], array_intersect($blocked, $derived), 'A blocked code must not be family-derived.');
        $this->assertSame([], array_intersect($derived, $ruled), 'A derived code must have no seeded rule.');

        $classified = [...$ruled, ...$derived, ...$blocked];
        sort($classified);
        $this->assertSame($all, $classified, 'Every allowance master code needs exactly one final status.');
    }

    public function test_every_credit_type_is_either_supported_or_explicitly_refused(): void
    {
        /*
         * On ภ.ง.ด.91, which prints two prepaid lines and not three: ภ.ง.ด.94 belongs to ภ.ง.ด.90
         * and is refused here for that reason, alongside the types no form prints at all.
         */
        foreach (TaxCreditCalculator::TYPES as $type) {
            $response = $this->calculate(['withholdings' => [['type' => $type, 'amount' => '1.00']]]);
            in_array($type, TaxCreditCalculator::PREPAID_TYPES, true) && $type !== 'pnd94'
                ? $response->assertOk()->assertJsonPath('data.credits.total', '1.00')
                : $response->assertUnprocessable()->assertJsonValidationErrors('withholdings.0.type');
        }
    }

    public function test_the_rounding_rule_remains_declared_unsupported_on_every_calculation(): void
    {
        // No repository source states a rounding unit, direction or stage, so exact decimals
        // are kept and every response says the legal rounding is not established.
        foreach (['PND91', 'PND90'] as $form) {
            $response = $this->calculate([], $form)->assertOk();
            $this->assertContains('ROUNDING_RULE_PENDING', array_column($response->json('data.warnings'), 'code'), $form);
        }

        // Fractional satang produced by a rate survives rather than being rounded away:
        // ข้อ 5 อื่นๆ deducts 30% of one satang.
        $this->calculate(['incomes' => [['income_type' => 'SECTION_40_6', 'gross_amount' => '0.01',
            'income_subtype' => 'OTHER_LIBERAL_PROFESSION', 'expense_method_selection' => 'percentage']]], 'PND90')
            ->assertOk()->assertJsonPath('data.expenses.total', '0.003');
    }

    // ================================================================= parity and history

    public function test_guest_and_member_agree_and_a_completed_return_stays_frozen(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $id = $this->postJson('/api/v1/tax-returns', ['tax_year' => 2568, 'form_code' => 'PND90',
            'name' => 'M7.5 baseline'])->assertCreated()->json('data.id');
        foreach ($this->everySection() as $line) {
            $this->postJson("/api/v1/tax-returns/$id/incomes", $line)->assertCreated();
        }
        $this->postJson("/api/v1/tax-returns/$id/withholdings",
            ['type' => 'withholding', 'amount' => '10000.00'])->assertCreated();

        $member = $this->postJson("/api/v1/tax-returns/$id/complete")->assertOk()->json('data.calculation');
        $guest = $this->calculate(['incomes' => $this->everySection(),
            'withholdings' => [['type' => 'withholding', 'amount' => '10000.00']]], 'PND90')->assertOk()->json('data');

        foreach (['income', 'expenses', 'allowances', 'donations', 'net_income', 'progressive_tax',
            'minimum_tax', 'tax_components', 'credits', 'result', 'trace'] as $section) {
            $this->assertSame($guest[$section], $member[$section], "Guest and member disagree on $section");
        }

        $stored = TaxCalculation::where('tax_return_id', $id)->sole();
        $snapshot = $stored->getRawOriginal('result_snapshot');
        $this->postJson("/api/v1/tax-returns/$id/incomes",
            ['income_type' => 'SECTION_40_1', 'gross_amount' => '1.00'])->assertConflict();
        $this->assertSame($snapshot, $stored->fresh()->getRawOriginal('result_snapshot'));

        // Ownership is unchanged: another member cannot read this history.
        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/tax-returns/$id/calculations/{$stored->id}")->assertNotFound();
    }

    public function test_planning_still_reuses_the_shared_engine_and_persists_nothing(): void
    {
        $this->postJson('/api/v1/tax/plan', ['tax_year' => 2568, 'form_code' => 'PND91',
            'base' => ['incomes' => [['income_type' => 'SECTION_40_1', 'gross_amount' => '720000.00']],
                'allowances' => []],
            'scenario' => ['allowances' => ['upsert' => [['code' => 'HOME_LOAN_INTEREST', 'input_amount' => '100000.00']]]]])
            ->assertOk()
            ->assertJsonPath('data.before.calculated_tax', '45500.00')
            // 620,000 - 100,000 = 520,000 net: 7,500 + 20,000 + 3,000.
            ->assertJsonPath('data.after.calculated_tax', '30500.00')
            ->assertJsonPath('data.estimated_tax_saving', '15000.00');

        $this->assertDatabaseCount('tax_calculations', 0);
        $this->assertDatabaseCount('tax_returns', 0);
    }
}

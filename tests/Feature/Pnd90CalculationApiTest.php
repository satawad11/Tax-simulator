<?php

namespace Tests\Feature;

use App\Models\IncomeType;
use App\Models\TaxForm;
use App\Models\TaxRuleVersion;
use App\Models\TaxYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class Pnd90CalculationApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A subcategory this test removes the production rule from, to exercise the unverified
     * path. Until M7.4 three ภ.ง.ด.90 subcategories were printed with a blank percentage and
     * could stand in for it; M7.4 resolved all three from the filing instructions, so the
     * state now has to be created deliberately rather than borrowed.
     */
    private const UNVERIFIED_TYPE = 'SECTION_40_6';

    private const UNVERIFIED_SUBTYPE = 'OTHER_LIBERAL_PROFESSION';

    /** The subcategory whose production rule mechanics fixtures replace. */
    private const FIXTURE_TYPE = 'SECTION_40_5';

    private const FIXTURE_SUBTYPE = 'RENT_OTHER_PROPERTY';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /** @param list<array<string, string>> $incomes */
    private function payload(array $incomes, string $withholding = '0.00', string $form = 'PND90'): array
    {
        return ['tax_year' => 2568, 'form_code' => $form, 'incomes' => $incomes,
            'allowances' => [], 'donations' => [],
            'withholdings' => [['type' => 'withholding', 'amount' => $withholding]]];
    }

    private function line(string $type, string $gross, string $exempt = '0.00', ?string $actual = null,
        ?string $subtype = null, ?string $selection = null): array
    {
        return ['income_type' => $type, 'gross_amount' => $gross, 'exempt_amount' => $exempt,
            ...($subtype === null ? [] : ['income_subtype' => $subtype]),
            ...($selection === null ? [] : ['expense_method_selection' => $selection]),
            ...($actual === null ? [] : ['actual_expense' => $actual])];
    }

    /**
     * Synthetic structure-only fixture on a subcategory production leaves unseeded. These
     * tests exercise the rule-driven mechanics, not Thai tax law.
     */
    private function fixtureRule(string $method, array $values = [],
        string $incomeType = self::FIXTURE_TYPE, ?string $subtype = self::FIXTURE_SUBTYPE): void
    {
        $version = TaxRuleVersion::where('version', '2568.3')->firstOrFail();
        $this->unverify($incomeType, $subtype);
        DB::table('expense_rules')->insert(['tax_year_id' => $version->tax_year_id, 'rule_version_id' => $version->id,
            'income_type_id' => IncomeType::where('code', $incomeType)->firstOrFail()->id,
            'income_subtype' => $subtype, 'expense_group' => $values['expense_group'] ?? null,
            'code' => 'TEST_'.$incomeType.'_'.($subtype ?? 'ALL'), 'method' => $method, 'active' => true,
            'source_reference' => 'Synthetic test fixture; not a production rule',
            'percentage' => $values['percentage'] ?? null,
            'maximum_amount' => $values['maximum_amount'] ?? null,
            'limit_amount' => $values['maximum_amount'] ?? null,
            'fixed_amount' => $values['fixed_amount'] ?? null,
            'minimum_amount' => $values['minimum_amount'] ?? null,
            'conditions' => $values['conditions'] ?? null,
            'created_at' => now(), 'updated_at' => now()]);
    }

    /** Removes the seeded production rule for one category, leaving it genuinely unverified. */
    private function unverify(string $incomeType = self::UNVERIFIED_TYPE, ?string $subtype = self::UNVERIFIED_SUBTYPE): void
    {
        DB::table('expense_rules')
            ->where('income_type_id', IncomeType::where('code', $incomeType)->firstOrFail()->id)
            ->where(fn ($query) => $subtype === null ? $query->whereNull('income_subtype') : $query->where('income_subtype', $subtype))
            ->delete();
    }

    public function test_pnd90_accepts_section_40_1_and_matches_the_equivalent_pnd91_result(): void
    {
        $incomes = [$this->line('SECTION_40_1', '720000.00')];
        $pnd90 = $this->postJson('/api/v1/tax/calculate', $this->payload($incomes, '25000.00'))->assertOk()->json('data');
        $pnd91 = $this->postJson('/api/v1/tax/calculate', $this->payload($incomes, '25000.00', 'PND91'))->assertOk()->json('data');

        $this->assertSame('PND90', $pnd90['form_code']);
        $this->assertSame('PND91', $pnd91['form_code']);
        foreach (['income', 'expenses', 'income_after_expense', 'net_income', 'progressive_tax', 'credits', 'result', 'analysis', 'trace', 'warnings'] as $section) {
            $this->assertSame($pnd91[$section], $pnd90[$section], "PND90 and PND91 disagree on $section");
        }
    }

    public function test_pnd90_aggregates_multiple_rows_of_one_income_type_under_a_single_expense_cap(): void
    {
        $this->postJson('/api/v1/tax/calculate', $this->payload([
            $this->line('SECTION_40_1', '400000', '40000'),
            $this->line('SECTION_40_1', '400000', '40000'),
        ]))->assertOk()
            ->assertJsonCount(1, 'data.income.items')
            ->assertJsonCount(2, 'data.income.items.0.lines')
            ->assertJsonPath('data.income.gross_income', '800000.00')
            ->assertJsonPath('data.income.exempt_income', '80000.00')
            ->assertJsonPath('data.income.gross_after_exemption', '720000.00')
            ->assertJsonCount(1, 'data.expenses.items')
            ->assertJsonPath('data.expenses.total', '100000.00')
            ->assertJsonPath('data.income_after_expense', '620000.00')
            ->assertJsonPath('data.net_income', '620000.00');
    }

    public function test_pnd90_combines_two_income_types_before_one_progressive_tax_calculation(): void
    {
        $response = $this->postJson('/api/v1/tax/calculate', $this->payload([
            $this->line('SECTION_40_1', '600000'),
            $this->line('SECTION_40_7', '200000', '0.00', null, null, 'percentage'),
        ]))->assertOk()
            ->assertJsonPath('data.income.gross_income', '800000.00')
            ->assertJsonPath('data.income_after_expense', '580000.00')
            ->assertJsonPath('data.net_income', '580000.00')
            // 600000 * 50% capped at 100000, plus 200000 * 60%.
            ->assertJsonPath('data.expenses.total', '220000.00')
            ->assertJsonCount(8, 'data.progressive_tax.brackets');

        $this->assertSame(['SECTION_40_1', 'SECTION_40_7'], array_column($response->json('data.expenses.items'), 'income_type'));
        $this->assertSame(['100000.00', '120000.00'], array_column($response->json('data.expenses.items'), 'eligible_amount'));
        $this->assertSame(['VERIFIED', 'VERIFIED'], array_column($response->json('data.expenses.items'), 'rule_status'));
        // One progressive calculation on combined net income, not one per income type.
        $this->assertSame('39500.00', $response->json('data.progressive_tax.total'));
    }

    public function test_multiple_rows_of_one_category_aggregate_before_the_cap_applies(): void
    {
        $this->postJson('/api/v1/tax/calculate', $this->payload([
            $this->line('SECTION_40_3', '150000', '0.00', null, 'COPYRIGHT_GOODWILL_OTHER_RIGHTS', 'percentage'),
            $this->line('SECTION_40_3', '150000', '0.00', null, 'COPYRIGHT_GOODWILL_OTHER_RIGHTS', 'percentage'),
        ]))->assertOk()
            ->assertJsonCount(1, 'data.expenses.items')
            // The cap applies once to the 300000 aggregate, not twice to 150000 each.
            ->assertJsonPath('data.expenses.total', '100000.00')
            ->assertJsonPath('data.income_after_expense', '200000.00');
    }

    public static function expenseMechanics(): array
    {
        return [
            'fixed below basis' => ['fixed', ['fixed_amount' => '30000.00'], '100000', null, '30000.00'],
            'fixed above basis is capped at income' => ['fixed', ['fixed_amount' => '150000.00'], '100000', null, '100000.00'],
            'percentage' => ['percentage', ['percentage' => '60.0000'], '100000', null, '60000.00'],
            'percentage under cap' => ['percentage_limit', ['percentage' => '60.0000', 'maximum_amount' => '60000.00'], '50000', null, '30000.00'],
            'percentage at cap' => ['percentage_limit', ['percentage' => '60.0000', 'maximum_amount' => '60000.00'], '100000', null, '60000.00'],
            'percentage above cap' => ['percentage_limit', ['percentage' => '60.0000', 'maximum_amount' => '60000.00'], '500000', null, '60000.00'],
            'actual expense' => ['actual', [], '100000', '30000', '30000.00'],
            'actual expense equal to income' => ['actual', [], '100000', '100000', '100000.00'],
            'actual expense omitted' => ['actual', [], '100000', null, '0.00'],
        ];
    }

    #[DataProvider('expenseMechanics')]
    public function test_expense_mechanics_are_driven_by_the_stored_rule_method(
        string $method, array $values, string $gross, ?string $actual, string $expected
    ): void {
        $this->fixtureRule($method, $values);

        $this->postJson('/api/v1/tax/calculate', $this->payload([
            $this->line(self::FIXTURE_TYPE, $gross, '0.00', $actual, self::FIXTURE_SUBTYPE),
        ]))->assertOk()->assertJsonPath('data.expenses.total', $expected)
            ->assertJsonPath('data.expenses.items.0.method', $method)
            ->assertJsonPath('data.expenses.items.0.rule_status', 'VERIFIED');
    }

    public function test_expense_mechanics_keep_fractional_satang(): void
    {
        $this->fixtureRule('percentage', ['percentage' => '7.5000']);

        $this->postJson('/api/v1/tax/calculate', $this->payload([
            $this->line(self::FIXTURE_TYPE, '0.01', '0.00', null, self::FIXTURE_SUBTYPE),
        ]))->assertOk()->assertJsonPath('data.expenses.total', '0.00075');
    }

    public function test_a_category_without_a_verified_expense_rule_is_rejected(): void
    {
        $this->unverify();
        $this->postJson('/api/v1/tax/calculate', $this->payload([
            $this->line('SECTION_40_1', '600000'),
            $this->line(self::UNVERIFIED_TYPE, '200000', '0.00', null, self::UNVERIFIED_SUBTYPE),
        ]))->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors('incomes.1.income_subtype')
            ->assertJsonMissingValidationErrors('incomes.0.income_type');
    }

    public function test_a_zero_valued_unverified_category_warns_instead_of_deducting(): void
    {
        $this->unverify();
        $response = $this->postJson('/api/v1/tax/calculate', $this->payload([
            $this->line('SECTION_40_1', '720000'),
            $this->line(self::UNVERIFIED_TYPE, '0', '0.00', null, self::UNVERIFIED_SUBTYPE),
        ]))->assertOk()
            ->assertJsonPath('data.expenses.total', '100000.00')
            ->assertJsonPath('data.expenses.items.1.rule_status', 'UNVERIFIED')
            ->assertJsonPath('data.expenses.items.1.eligible_amount', '0.00')
            ->assertJsonPath('data.net_income', '620000.00');

        $this->assertContains('UNVERIFIED_EXPENSE_RULE', array_column($response->json('data.warnings'), 'code'));
        $this->assertContains('ROUNDING_RULE_PENDING', array_column($response->json('data.warnings'), 'code'));
    }

    public function test_actual_expense_is_rejected_when_the_rule_does_not_allow_it(): void
    {
        $this->postJson('/api/v1/tax/calculate', $this->payload([$this->line('SECTION_40_1', '600000', '0.00', '50000')]))
            ->assertUnprocessable()->assertJsonValidationErrors('incomes.0.actual_expense');
    }

    public function test_actual_expense_may_not_exceed_income_after_exemption(): void
    {
        $this->fixtureRule('actual');

        $this->postJson('/api/v1/tax/calculate', $this->payload([
            $this->line(self::FIXTURE_TYPE, '100000', '40000', '70000', self::FIXTURE_SUBTYPE),
        ]))->assertUnprocessable()->assertJsonValidationErrors('incomes.0.actual_expense');
    }

    public static function unapprovedMechanics(): array
    {
        return [
            'custom has no defined mechanics' => ['custom', []],
            'percentage must not carry a cap' => ['percentage', ['percentage' => '60.0000', 'maximum_amount' => '1.00']],
            'percentage_limit requires a cap' => ['percentage_limit', ['percentage' => '60.0000']],
            'fixed requires an amount' => ['fixed', []],
            'conditions are not interpreted' => ['percentage', ['percentage' => '60.0000', 'conditions' => '{"a":1}']],
            'percentage_or_actual requires a percentage' => ['percentage_or_actual', []],
        ];
    }

    #[DataProvider('unapprovedMechanics')]
    public function test_a_rule_whose_mechanics_are_not_approved_fails_closed(string $method, array $values): void
    {
        $this->fixtureRule($method, $values);

        $this->postJson('/api/v1/tax/calculate', $this->payload([
            $this->line(self::FIXTURE_TYPE, '100000', '0.00', null, self::FIXTURE_SUBTYPE, 'percentage'),
        ]))->assertStatus(409)->assertJsonPath('success', false);
    }

    public function test_an_inactive_or_unsourced_rule_fails_closed_rather_than_deducting_nothing(): void
    {
        $this->fixtureRule('percentage', ['percentage' => '60.0000']);
        $code = 'TEST_'.self::FIXTURE_TYPE.'_'.self::FIXTURE_SUBTYPE;
        $income = [$this->line(self::FIXTURE_TYPE, '100000', '0.00', null, self::FIXTURE_SUBTYPE)];

        DB::table('expense_rules')->where('code', $code)->update(['active' => false]);
        $this->postJson('/api/v1/tax/calculate', $this->payload($income))->assertStatus(409);

        DB::table('expense_rules')->where('code', $code)->update(['active' => true, 'source_reference' => '']);
        $this->postJson('/api/v1/tax/calculate', $this->payload($income))->assertStatus(409);
    }

    public function test_pnd91_still_rejects_every_income_type_other_than_section_40_1(): void
    {
        foreach ([['SECTION_40_2', null], ['SECTION_40_6', 'MEDICAL_PRACTICE'], ['SECTION_40_7', null]] as [$code, $subtype]) {
            $this->postJson('/api/v1/tax/calculate',
                $this->payload([$this->line($code, '100000', '0.00', null, $subtype, 'percentage')], '0.00', 'PND91'))
                ->assertUnprocessable()->assertJsonValidationErrors('incomes.0.income_type');
        }
    }

    public static function payments(): array
    {
        return [['0', 'PAYABLE', '39500.00'], ['39500', 'ZERO', '0.00'], ['50000', 'REFUND', '10500.00']];
    }

    #[DataProvider('payments')]
    public function test_pnd90_result_statuses(string $paid, string $status, string $amount): void
    {
        $this->postJson('/api/v1/tax/calculate', $this->payload([
            $this->line('SECTION_40_1', '600000'),
            $this->line('SECTION_40_7', '200000', '0.00', null, null, 'percentage'),
        ], $paid))->assertOk()
            ->assertJsonPath('data.result.status', $status)
            ->assertJsonPath('data.result.amount', $amount);
    }

    public static function invalidPnd90Requests(): array
    {
        return [
            'unknown income type' => [['incomes' => [['income_type' => 'SECTION_40_9', 'gross_amount' => '1']]]],
            'unknown form' => [['form_code' => 'PND92']],
            'unknown tax year' => [['tax_year' => 9999]],
            'negative gross' => [['incomes' => [['income_type' => 'SECTION_40_1', 'gross_amount' => '-1']]]],
            'exempt above gross' => [['incomes' => [['income_type' => 'SECTION_40_1', 'gross_amount' => '100', 'exempt_amount' => '101']]]],
            'negative actual expense' => [['incomes' => [['income_type' => 'SECTION_40_1', 'gross_amount' => '100', 'actual_expense' => '-1']]]],
            'calculated field' => [['income_after_expense' => '1']],
            'subtype on a single-category type' => [['incomes' => [['income_type' => 'SECTION_40_1', 'gross_amount' => '100', 'income_subtype' => 'MEDICAL_PRACTICE']]]],
            'missing required subtype' => [['incomes' => [['income_type' => 'SECTION_40_6', 'gross_amount' => '100']]]],
            'unknown subtype' => [['incomes' => [['income_type' => 'SECTION_40_6', 'gross_amount' => '100', 'income_subtype' => 'NOT_A_SUBTYPE']]]],
            'unknown selection value' => [['incomes' => [['income_type' => 'SECTION_40_7', 'gross_amount' => '100', 'expense_method_selection' => 'whatever']]]],
        ];
    }

    #[DataProvider('invalidPnd90Requests')]
    public function test_invalid_pnd90_requests_return_sanitized_422(array $override): void
    {
        $payload = [...$this->payload([$this->line('SECTION_40_1', '720000')]), ...$override];

        $this->postJson('/api/v1/tax/calculate', $payload)->assertUnprocessable()
            ->assertJsonPath('success', false)->assertJsonStructure(['errors']);
    }

    public function test_pnd90_requires_an_active_year_form_and_published_rule_version(): void
    {
        $payload = $this->payload([$this->line('SECTION_40_1', '720000')]);
        TaxForm::where('code', 'PND90')->firstOrFail()->update(['active' => false]);
        $this->postJson('/api/v1/tax/calculate', $payload)->assertUnprocessable()->assertJsonValidationErrors('form_code');

        TaxForm::where('code', 'PND90')->firstOrFail()->update(['active' => true]);
        TaxYear::where('year', 2568)->firstOrFail()->update(['active' => false]);
        $this->postJson('/api/v1/tax/calculate', $payload)->assertUnprocessable()->assertJsonValidationErrors('tax_year');
    }

    public function test_an_unmapped_form_reports_the_form_rather_than_each_income_row(): void
    {
        TaxForm::where('code', 'PND90')->firstOrFail()->incomeTypes()->detach();

        $this->postJson('/api/v1/tax/calculate', $this->payload([$this->line('SECTION_40_1', '720000')]))
            ->assertUnprocessable()->assertJsonValidationErrors('form_code');
    }

    public function test_guest_pnd90_calculation_persists_nothing(): void
    {
        $tables = ['tax_returns', 'tax_return_incomes', 'tax_calculations', 'tax_calculation_brackets', 'tax_scenarios'];
        $counts = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()]);

        $this->postJson('/api/v1/tax/calculate', $this->payload([$this->line('SECTION_40_1', '720000')]))->assertOk();

        foreach ($counts as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
    }

    public function test_pnd90_planning_is_supported_for_verified_income_and_rejected_otherwise(): void
    {
        $base = ['incomes' => [$this->line('SECTION_40_1', '720000')], 'allowances' => [], 'donations' => [],
            'withholdings' => [['type' => 'withholding', 'amount' => '25000.00']]];

        $this->postJson('/api/v1/tax/plan', ['tax_year' => 2568, 'form_code' => 'PND90', 'base' => $base,
            'scenario' => ['withholdings' => ['upsert' => [['type' => 'withholding', 'amount' => '50000.00']]]]])
            ->assertOk()->assertJsonPath('data.form_code', 'PND90')
            ->assertJsonPath('data.before.result.status', 'PAYABLE')
            ->assertJsonPath('data.after.result.status', 'REFUND')
            ->assertJsonPath('data.estimated_tax_saving', '0.00');

        $this->unverify();
        $base['incomes'][] = $this->line(self::UNVERIFIED_TYPE, '200000', '0.00', null, self::UNVERIFIED_SUBTYPE);
        $this->postJson('/api/v1/tax/plan', ['tax_year' => 2568, 'form_code' => 'PND90', 'base' => $base, 'scenario' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('base.incomes.1.income_subtype');
    }

    public function test_pnd90_recommendations_and_guidance_follow_the_shared_rules(): void
    {
        $response = $this->postJson('/api/v1/tax/calculate', $this->payload([$this->line('SECTION_40_1', '720000')], '25000.00'))
            ->assertOk()->assertJsonPath('data.payment_guidance.reason_code', 'WITHHOLDING_BELOW_CALCULATED_TAX');

        $this->assertContains('PAYABLE_DUE_TO_LOW_WITHHOLDING', array_column($response->json('data.recommendations'), 'code'));
    }
}

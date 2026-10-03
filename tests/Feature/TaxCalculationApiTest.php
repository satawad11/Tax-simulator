<?php

namespace Tests\Feature;

use App\Models\ExpenseRule;
use App\Models\TaxForm;
use App\Models\TaxRuleVersion;
use App\Models\TaxYear;
use App\ValueObjects\Money;
use Database\Seeders\EmploymentExpenseRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Unit\ProgressiveTaxCalculatorTest;

class TaxCalculationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function payload(): array
    {
        return ['tax_year' => 2568, 'form_code' => 'PND91', 'profile' => ['birth_date' => '1990-05-20', 'marital_status' => 'single'],
            'incomes' => [['income_type' => 'SECTION_40_1', 'gross_amount' => '720000.00', 'exempt_amount' => '0.00']],
            'allowances' => [], 'donations' => [], 'withholdings' => []];
    }

    /**
     * The same payload with no family facts declared.
     *
     * Milestone 09.1 — a declared profile now claims ใบแนบ item 1 for the filer, which moves net
     * income by 60,000. The cases below are about the bracket table, the exemption arithmetic and
     * exact-decimal handling: they choose a gross so that net lands on a precise boundary, and an
     * allowance in the middle of that would only obscure what they are checking. They therefore
     * declare no family facts, which is also what the M4 regression baseline does.
     */
    private function payloadWithoutFamily(): array
    {
        $payload = $this->payload();
        unset($payload['profile']);

        return $payload;
    }

    public function test_public_calculation_returns_complete_trace_without_persistence(): void
    {
        $tables = ['tax_returns', 'tax_return_profiles', 'tax_return_incomes', 'tax_calculations', 'tax_calculation_brackets', 'tax_scenarios'];
        $counts = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()]);
        $response = $this->postJson('/api/v1/tax/calculate', $this->payload())->assertOk()
            ->assertJsonPath('success', true)->assertJsonPath('message', null)
            ->assertJsonPath('data.rule_version', '2568.3')->assertJsonPath('data.income.gross_income', '720000.00')
            ->assertJsonPath('data.expenses.total', '100000.00')->assertJsonCount(1, 'data.expenses.items')
            ->assertJsonPath('data.net_income', '560000.00')->assertJsonCount(8, 'data.progressive_tax.brackets')
            ->assertJsonPath('data.progressive_tax.total', '36500.00')
            ->assertJsonPath('data.result.status', 'PAYABLE')->assertJsonPath('data.result.amount', '36500.00')
            ->assertJsonPath('data.analysis.effective_tax_rate', '5.069444')
            ->assertJsonPath('data.analysis.marginal_tax_rate', '15.0000')
            ->assertJsonCount(16, 'data.trace')->assertJsonPath('data.warnings.0.code', 'ROUNDING_RULE_PENDING');
        $this->assertSame(range(1, 16), array_column($response->json('data.trace'), 'step'));
        $this->assertSame(['GROSS_INCOME', 'EXEMPT_INCOME', 'INCOME_AFTER_EXEMPTION', 'EXPENSE', 'INCOME_AFTER_EXPENSE',
            'ALLOWANCES', 'INCOME_AFTER_ALLOWANCES', 'SPECIAL_DONATION', 'INCOME_AFTER_SPECIAL_DONATION', 'GENERAL_DONATION',
            'NET_INCOME', 'PROGRESSIVE_TAX', 'FOREIGN_TAX_CREDIT', 'TAX_AFTER_FOREIGN_CREDIT', 'WITHHOLDING_AND_PREPAID', 'RESULT'],
            array_column($response->json('data.trace'), 'code'));
        foreach ($counts as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
    }

    public static function payments(): array
    {
        return [['25000', 'PAYABLE', '11500.00'], ['36500', 'ZERO', '0.00'], ['50000', 'REFUND', '13500.00']];
    }

    #[DataProvider('payments')]
    public function test_withholding_outcomes(string $paid, string $status, string $amount): void
    {
        $payload = $this->payload();
        $payload['withholdings'] = [['type' => 'withholding', 'amount' => $paid]];
        $this->postJson('/api/v1/tax/calculate', $payload)->assertOk()
            ->assertJsonPath('data.result.status', $status)->assertJsonPath('data.result.amount', $amount);
    }

    public function test_multiple_employers_and_withholdings_aggregate_before_one_expense_cap(): void
    {
        $payload = $this->payload();
        $payload['incomes'] = array_fill(0, 2, ['income_type' => 'SECTION_40_1', 'gross_amount' => '400000', 'exempt_amount' => '40000']);
        $payload['withholdings'] = [['type' => 'withholding', 'amount' => '10000'], ['type' => 'withholding', 'amount' => '15000']];
        $this->postJson('/api/v1/tax/calculate', $payload)->assertOk()->assertJsonPath('data.income.gross_income', '800000.00')
            ->assertJsonPath('data.income.exempt_income', '80000.00')->assertJsonPath('data.income.gross_after_exemption', '720000.00')
            ->assertJsonPath('data.expenses.total', '100000.00')->assertJsonPath('data.credits.total', '25000.00')
            ->assertJsonPath('data.result.amount', '11500.00');
    }

    public static function exemptions(): array
    {
        return [['0', '0', '0.00'], ['100000', '0', '50000.00'], ['100000', '40000', '30000.00'], ['100000', '100000', '0.00']];
    }

    #[DataProvider('exemptions')]
    public function test_exemption_and_zero_income(string $gross, string $exempt, string $net): void
    {
        $payload = $this->payloadWithoutFamily();
        $payload['incomes'][0]['gross_amount'] = $gross;
        $payload['incomes'][0]['exempt_amount'] = $exempt;
        $this->postJson('/api/v1/tax/calculate', $payload)->assertOk()->assertJsonPath('data.net_income', $net)
            ->assertJsonPath('data.progressive_tax.total', '0.00')->assertJsonPath('data.analysis.effective_tax_rate', '0.000000');
    }

    public function test_sub_satang_is_not_silently_rounded(): void
    {
        $payload = $this->payloadWithoutFamily();
        $payload['incomes'][0]['gross_amount'] = '250000.01';
        $this->postJson('/api/v1/tax/calculate', $payload)->assertOk()->assertJsonPath('data.result.amount', '0.0005');
    }

    /**
     * M7.5 — a positive amount on an allowance or credit this baseline cannot calculate is
     * refused; only a zero line still reaches the engine, where it deducts nothing and says so.
     * PENSION_INSURANCE and the two credits are the paths that remain in that state.
     */
    public function test_unverified_allowances_and_credits_are_zero_with_specific_warnings(): void
    {
        $payload = $this->payloadWithoutFamily();
        $payload['allowances'] = [['code' => 'PENSION_INSURANCE', 'amount' => '60000']];
        $payload['withholdings'] = array_map(fn ($type) => ['type' => $type, 'amount' => '999999'], ['foreign_tax_credit', 'other_credit']);
        $this->postJson('/api/v1/tax/calculate', $payload)->assertUnprocessable()
            ->assertJsonValidationErrors(['allowances.0.code', 'withholdings.0.type', 'withholdings.1.type']);

        $payload['allowances'] = [['code' => 'PENSION_INSURANCE', 'amount' => '0']];
        $payload['withholdings'] = array_map(fn ($type) => ['type' => $type, 'amount' => '0'], ['foreign_tax_credit', 'other_credit']);
        $response = $this->postJson('/api/v1/tax/calculate', $payload)->assertOk()
            ->assertJsonPath('data.allowances.total_input', '0.00')->assertJsonPath('data.allowances.total_eligible', '0.00')
            ->assertJsonPath('data.credits.total', '0.00')->assertJsonPath('data.result.amount', '45500.00');
        $this->assertSame(['UNVERIFIED_ALLOWANCE_RULE', 'UNVERIFIED_FOREIGN_TAX_CREDIT_LIMIT', 'UNVERIFIED_TAX_CREDIT_RULE', 'ROUNDING_RULE_PENDING'],
            array_column($response->json('data.warnings'), 'code'));
    }

    public static function invalidInputs(): array
    {
        return [
            ['form_code', 'PND92'], ['incomes.0.income_type', 'SECTION_40_2'], ['incomes.0.income_type', 'SECTION_40_8'],
            ['incomes.0.income_type', 'UNKNOWN'], ['tax_year', 9999], ['tax_year', 'wrong'],
            ['incomes', []], ['incomes', 'invalid'], ['incomes.0', 'invalid'], ['allowances', 'invalid'],
            ['donations', 'invalid'], ['withholdings', 'invalid'], ['profile', 'invalid'],
            ['incomes.0.gross_amount', '-1'], ['incomes.0.gross_amount', '1e10'], ['incomes.0.gross_amount', '0.001'],
            ['incomes.0.gross_amount', '10000000000000'], ['incomes.0.gross_amount', 1.25],
            ['incomes.0.exempt_amount', '-1'], ['incomes.0.exempt_amount', '720000.01'],
            ['allowances', [['code' => 'UNKNOWN', 'amount' => '1']]], ['allowances', [['code' => 'PERSONAL', 'amount' => '-1']]], ['allowances', [['code' => 'PERSONAL', 'amount' => '1', 'eligible_amount' => '999']]], ['donations', [['code' => 'UNKNOWN', 'amount' => '1']]], ['withholdings', [['type' => 'withholding', 'amount' => '-1']]], ['withholdings', [['type' => 'UNKNOWN', 'amount' => '1']]], ['credits', ['total' => '999']], ['net_income', '0'], ['incomes.0.expense_amount', '999'],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_input_returns_sanitized_422(string $path, mixed $value): void
    {
        $payload = $this->payload();
        data_set($payload, $path, $value);
        $this->postJson('/api/v1/tax/calculate', $payload)->assertUnprocessable()->assertJsonPath('success', false)->assertJsonStructure(['errors']);
    }

    public function test_inactive_year_is_rejected(): void
    {
        TaxYear::where('year', 2568)->firstOrFail()->update(['active' => false]);
        $this->postJson('/api/v1/tax/calculate', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('tax_year');
    }

    public function test_inactive_form_is_rejected(): void
    {
        TaxForm::where('code', 'PND91')->firstOrFail()->update(['active' => false]);
        $this->postJson('/api/v1/tax/calculate', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('form_code');
    }

    public function test_missing_published_version_returns_metadata_conflict(): void
    {
        $year = TaxYear::factory()->create(['year' => 2570, 'active' => true]);
        $payload = $this->payload();
        $payload['tax_year'] = $year->year;
        $this->postJson('/api/v1/tax/calculate', $payload)->assertStatus(409)->assertJsonPath('success', false);
    }

    public function test_seed_is_idempotent_and_preserves_published_version_and_brackets(): void
    {
        $version = DB::table('tax_rule_versions')->where('version', '2568.3')->first();
        $brackets = DB::table('tax_brackets')->where('rule_version_id', $version->id)->orderBy('sort_order')->get()->toJson();
        $expense = DB::table('expense_rules')->where('rule_version_id', $version->id)->get()->toJson();
        $this->seed(EmploymentExpenseRuleSeeder::class);
        $this->assertEquals($version, DB::table('tax_rule_versions')->find($version->id));
        $this->assertSame($brackets, DB::table('tax_brackets')->where('rule_version_id', $version->id)->orderBy('sort_order')->get()->toJson());
        $this->assertSame($expense, DB::table('expense_rules')->where('rule_version_id', $version->id)->get()->toJson());
        $this->assertSame(140, DB::table('expense_rules')->count());
        $rule = ExpenseRule::where('rule_version_id', $version->id)->firstOrFail();
        $this->assertSame('percentage_limit', $rule->method);
        $this->assertSame('50.0000', $rule->percentage);
        $this->assertSame('100000.00', $rule->maximum_amount);
        $this->assertSame($version->id, $rule->rule_version_id);
        $this->assertSame(17, DB::table('allowance_rules')->where('rule_version_id', $version->id)->count());
        $this->assertSame(2, DB::table('donation_rules')->where('rule_version_id', $version->id)->count());
    }

    #[DataProvider('databaseBoundaries')]
    public function test_published_database_bracket_boundaries(string $net, string $expected, string $rate): void
    {
        $payload = $this->payloadWithoutFamily();
        $payload['incomes'][0]['gross_amount'] = $net === '0' ? '0' : (string) (new Money($net))->add(new Money('100000'));
        $this->postJson('/api/v1/tax/calculate', $payload)->assertOk()
            ->assertJsonPath('data.progressive_tax.total', $expected)->assertJsonPath('data.analysis.marginal_tax_rate', $rate);
    }

    public static function databaseBoundaries(): array
    {
        return ProgressiveTaxCalculatorTest::boundaries();
    }

    public function test_missing_and_duplicate_published_rules_fail_closed(): void
    {
        $version = TaxRuleVersion::factory()->create(['tax_year_id' => TaxYear::where('year', 2568)->firstOrFail()->id]);
        $version->update(['status' => 'published']);
        $this->postJson('/api/v1/tax/calculate', $this->payload())->assertStatus(409);
    }

    public function test_missing_expense_source_fails_closed(): void
    {
        // Deliberately corrupt an isolated test database, bypassing normal immutable model writes.
        DB::table('expense_rules')->update(['source_reference' => null]);
        $this->postJson('/api/v1/tax/calculate', $this->payload())->assertStatus(409);
    }

    public function test_seed_refuses_conflicting_existing_rule(): void
    {
        DB::table('expense_rules')->update(['percentage' => '49.0000']);
        $this->expectException(\RuntimeException::class);
        $this->seed(EmploymentExpenseRuleSeeder::class);
    }

    public function test_known_but_unverified_donation_does_not_grant_a_deduction(): void
    {
        $version = TaxRuleVersion::where('version', '2568.3')->firstOrFail();
        // Synthetic structure-only fixture; no production donation values are seeded.
        DB::table('donation_rules')->insert(['tax_year_id' => $version->tax_year_id, 'rule_version_id' => $version->id,
            'code' => 'TEST_UNVERIFIED', 'donation_type' => 'special', 'active' => true]);
        $payload = $this->payload();
        $payload['donations'] = [['code' => 'TEST_UNVERIFIED', 'amount' => '1000']];
        $this->postJson('/api/v1/tax/calculate', $payload)->assertOk()
            ->assertJsonPath('data.donations.total_input', '1000.00')->assertJsonPath('data.donations.total_eligible', '0.00')
            ->assertJsonPath('data.warnings.0.code', 'UNVERIFIED_DONATION_RULE')->assertJsonPath('data.net_income', '560000.00');
    }

    public function test_form_mapping_cannot_be_bypassed(): void
    {
        TaxForm::where('code', 'PND91')->firstOrFail()->incomeTypes()->detach();
        $this->postJson('/api/v1/tax/calculate', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('form_code');
    }

    public function test_required_fields_and_nonlist_objects_are_rejected(): void
    {
        $this->postJson('/api/v1/tax/calculate', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['tax_year', 'form_code', 'incomes']);
        $payload = $this->payload();
        unset($payload['incomes'][0]['gross_amount']);
        $this->postJson('/api/v1/tax/calculate', $payload)->assertUnprocessable()->assertJsonValidationErrors('incomes.0.gross_amount');
        $payload = $this->payload();
        $payload['incomes'] = ['employer' => $payload['incomes'][0]];
        $this->postJson('/api/v1/tax/calculate', $payload)->assertUnprocessable()->assertJsonValidationErrors('incomes');
    }

    public function test_large_decimal_inputs_retain_full_precision(): void
    {
        $payload = $this->payload();
        $payload['incomes'][0]['gross_amount'] = '9999999999999.99';
        $payload['incomes'][0]['exempt_amount'] = '9999999279999.99';
        $this->postJson('/api/v1/tax/calculate', $payload)->assertOk()
            ->assertJsonPath('data.income.gross_income', '9999999999999.99')
            ->assertJsonPath('data.income.gross_after_exemption', '720000.00')
            ->assertJsonPath('data.result.amount', '36500.00');
    }
}

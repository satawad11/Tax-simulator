<?php

namespace Tests\Feature;

use App\Services\Tax\TaxCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TaxPlanningApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function base(string $withholding = '25000.00'): array
    {
        return ['profile' => ['birth_date' => '1990-05-20', 'marital_status' => 'single'],
            'incomes' => [['income_type' => 'SECTION_40_1', 'gross_amount' => '720000.00', 'exempt_amount' => '0.00']],
            'allowances' => [], 'donations' => [],
            'withholdings' => [['type' => 'withholding', 'amount' => $withholding]]];
    }

    private function request(array $scenario, string $withholding = '25000.00'): array
    {
        return ['tax_year' => 2568, 'form_code' => 'PND91', 'base' => $this->base($withholding), 'scenario' => $scenario];
    }

    public function test_guest_planning_needs_no_authentication_and_persists_nothing(): void
    {
        $tables = ['tax_returns', 'tax_return_incomes', 'tax_calculations', 'tax_calculation_brackets', 'tax_scenarios'];
        $counts = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()]);

        $this->postJson('/api/v1/tax/plan', $this->request([]))->assertOk()
            ->assertJsonPath('success', true)->assertJsonPath('message', null)
            ->assertJsonPath('data.tax_year', 2568)->assertJsonPath('data.form_code', 'PND91')
            ->assertJsonPath('data.rule_version', '2568.3');

        foreach ($counts as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
    }

    public function test_both_sides_are_produced_by_the_shared_calculation_service(): void
    {
        $engine = app(TaxCalculationService::class);
        $this->instance(TaxCalculationService::class, Mockery::mock(TaxCalculationService::class,
            function (MockInterface $mock) use ($engine): void {
                $mock->shouldReceive('calculate')->twice()
                    ->andReturnUsing(fn (...$arguments) => $engine->calculate(...$arguments));
            }));

        $this->postJson('/api/v1/tax/plan', $this->request([
            'withholdings' => ['upsert' => [['type' => 'withholding', 'amount' => '50000.00']]],
        ]))->assertOk();
    }

    public function test_an_empty_scenario_reproduces_the_base_calculation_exactly(): void
    {
        $calculation = $this->postJson('/api/v1/tax/calculate',
            ['tax_year' => 2568, 'form_code' => 'PND91', ...$this->base()])->assertOk()->json('data');
        $plan = $this->postJson('/api/v1/tax/plan', $this->request([]))->assertOk()->json('data');

        $this->assertSame($calculation['net_income'], $plan['before']['net_income']);
        $this->assertSame($calculation['progressive_tax']['total'], $plan['before']['calculated_tax']);
        $this->assertSame($calculation['result']['status'], $plan['before']['result']['status']);
        $this->assertSame($calculation['result']['amount'], $plan['before']['result']['amount']);
        $this->assertSame($plan['before'], $plan['after']);
        $this->assertSame(['net_income' => '0.00', 'calculated_tax' => '0.00', 'result_amount' => '0.00'], $plan['difference']);
        $this->assertSame('0.00', $plan['estimated_tax_saving']);
    }

    public function test_tax_saving_follows_tax_liability_not_payment_timing(): void
    {
        $plan = $this->postJson('/api/v1/tax/plan', $this->request([
            'withholdings' => ['upsert' => [['type' => 'withholding', 'amount' => '50000.00']]],
        ]))->assertOk()->json('data');

        $this->assertSame('36500.00', $plan['before']['calculated_tax']);
        $this->assertSame('36500.00', $plan['after']['calculated_tax']);
        // The liability did not move, so nothing was saved even though the balance flipped.
        $this->assertSame('0.00', $plan['estimated_tax_saving']);
        $this->assertSame('0.00', $plan['difference']['calculated_tax']);
        $this->assertSame(['status' => 'PAYABLE', 'amount' => '11500.00'], $plan['before']['result']);
        $this->assertSame(['status' => 'REFUND', 'amount' => '13500.00'], $plan['after']['result']);
        $this->assertSame('-25000.00', $plan['difference']['result_amount']);
    }

    public function test_removing_a_withholding_increases_the_payable_balance_without_a_saving(): void
    {
        $plan = $this->postJson('/api/v1/tax/plan', $this->request([
            'withholdings' => ['remove' => ['withholding']],
        ]))->assertOk()->json('data');

        $this->assertSame(['status' => 'PAYABLE', 'amount' => '36500.00'], $plan['after']['result']);
        $this->assertSame('25000.00', $plan['difference']['result_amount']);
        $this->assertSame('0.00', $plan['estimated_tax_saving']);
    }

    public function test_an_unverified_allowance_scenario_creates_no_tax_saving_and_warns(): void
    {
        $plan = $this->postJson('/api/v1/tax/plan', $this->request([
            'allowances' => ['upsert' => [['code' => 'SOCIAL_SECURITY', 'input_amount' => '9000.00']], 'remove' => []],
        ]))->assertOk()->json('data');

        $this->assertSame($plan['before']['net_income'], $plan['after']['net_income']);
        $this->assertSame('0.00', $plan['estimated_tax_saving']);
        $this->assertContains('UNVERIFIED_ALLOWANCE_RULE', array_column($plan['warnings'], 'code'));
    }

    public function test_m4_warnings_are_propagated_into_the_planning_response(): void
    {
        $plan = $this->postJson('/api/v1/tax/plan', $this->request([]))->assertOk()->json('data');

        $this->assertContains('ROUNDING_RULE_PENDING', array_column($plan['warnings'], 'code'));
        $this->assertStringContainsString('ระบบจำลอง', $plan['disclaimer']);
    }

    public function test_planning_returns_recommendations_for_the_simulated_scenario(): void
    {
        $plan = $this->postJson('/api/v1/tax/plan', $this->request([
            'allowances' => ['upsert' => [['code' => 'SOCIAL_SECURITY', 'input_amount' => '9000.00']]],
        ]))->assertOk()->json('data');

        $codes = array_column($plan['recommendations'], 'code');
        $this->assertContains('VERIFY_UNVERIFIED_ALLOWANCE_RULE', $codes);
        $this->assertNotContains('CHECK_SOCIAL_SECURITY', $codes);
    }

    public static function invalidRequests(): array
    {
        return [
            'unknown form is unsupported' => [['form_code' => 'PND92']],
            'income type without a verified expense rule' => [['form_code' => 'PND90',
                'base' => ['incomes' => [['income_type' => 'SECTION_40_8', 'gross_amount' => '100000']]]]],
            'unknown tax year' => [['tax_year' => 9999]],
            'missing base' => [['base' => null]],
            'missing scenario' => [['scenario' => null]],
            'unknown allowance code' => [['scenario' => ['allowances' => ['upsert' => [['code' => 'UNKNOWN', 'amount' => '1']]]]]],
            'unknown withholding type' => [['scenario' => ['withholdings' => ['upsert' => [['type' => 'UNKNOWN', 'amount' => '1']]]]]],
            'unknown donation code' => [['scenario' => ['donations' => ['upsert' => [['code' => 'UNKNOWN', 'amount' => '1']]]]]],
            'negative amount' => [['scenario' => ['allowances' => ['upsert' => [['code' => 'RMF', 'amount' => '-1']]]]]],
            'both amount spellings' => [['scenario' => ['allowances' => ['upsert' => [['code' => 'RMF', 'amount' => '1', 'input_amount' => '1']]]]]],
            'neither amount spelling' => [['scenario' => ['allowances' => ['upsert' => [['code' => 'RMF']]]]]],
            'remove and upsert the same key' => [['scenario' => ['allowances' => ['upsert' => [['code' => 'RMF', 'amount' => '1']], 'remove' => ['RMF']]]]],
            'duplicate upsert key' => [['scenario' => ['allowances' => ['upsert' => [['code' => 'RMF', 'amount' => '1'], ['code' => 'RMF', 'amount' => '2']]]]]],
            'unknown scenario collection' => [['scenario' => ['incomes' => ['upsert' => []]]]],
            'server calculated field' => [['estimated_tax_saving' => '100.00']],
            'base negative income' => [['base' => ['incomes' => [['income_type' => 'SECTION_40_1', 'gross_amount' => '-1']]]]],
            'base unsupported income type' => [['base' => ['incomes' => [['income_type' => 'SECTION_40_8', 'gross_amount' => '1']]]]],
        ];
    }

    #[DataProvider('invalidRequests')]
    public function test_invalid_planning_requests_return_sanitized_422(array $override): void
    {
        $request = $this->request([]);
        foreach ($override as $key => $value) {
            if ($value === null) {
                unset($request[$key]);

                continue;
            }
            $request[$key] = is_array($value) && is_array($request[$key] ?? null) ? [...$request[$key], ...$value] : $value;
        }

        $this->postJson('/api/v1/tax/plan', $request)->assertUnprocessable()
            ->assertJsonPath('success', false)->assertJsonStructure(['errors']);
    }

    public function test_planning_requires_a_published_rule_version(): void
    {
        DB::table('tax_rule_versions')->where('version', '2568.3')->update(['status' => 'draft']);

        $this->postJson('/api/v1/tax/plan', $this->request([]))->assertStatus(409)->assertJsonPath('success', false);
    }
}

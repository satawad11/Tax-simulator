<?php

namespace Tests\Feature;

use App\Models\AllowanceRule;
use App\Models\AllowanceType;
use App\Models\IncomeRule;
use App\Models\IncomeType;
use App\Models\TaxReturn;
use App\Models\TaxRuleVersion;
use App\Models\TaxYear;
use App\Models\User;
use Database\Seeders\AllowanceTypeSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SchemaReconciliationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected $seed = true;

    private const APPROVED_CODES = [
        'PERSONAL', 'SPOUSE', 'CHILD', 'PARENT', 'DISABLED_PERSON', 'LIFE_INSURANCE',
        'HEALTH_INSURANCE', 'PENSION_INSURANCE', 'PROVIDENT_FUND', 'NSF', 'RMF',
        'HOME_LOAN_INTEREST', 'SOCIAL_SECURITY', 'EASY_E_RECEIPT', 'THAI_ESG', 'THAI_ESGX', 'OTHER',
        // ใบแนบ ข้อ 13, 16, 20, 22 — printed deduction lines this baseline cannot yet calculate.
        // They are masters all the same: the form prints them, so the reader must be told they
        // exist, and re-seeding must not duplicate them.
        'CCTV_SYSTEM', 'SOCIAL_ENTERPRISE_INVESTMENT', 'NEW_HOME_CONSTRUCTION', 'DOMESTIC_TRAVEL',
    ];

    public function test_all_approved_columns_exist(): void
    {
        $expected = [
            'users' => ['role'],
            'tax_years' => ['filing_start_date', 'filing_end_date', 'active'],
            'tax_forms' => ['active'],
            'tax_rule_versions' => ['effective_from', 'effective_to'],
            'tax_brackets' => ['min_amount', 'max_amount', 'sort_order'],
            'income_types' => ['section_code'],
            'income_rules' => ['active', 'metadata'],
            'expense_rules' => ['maximum_amount', 'minimum_amount', 'active'],
            'allowance_rules' => ['method', 'fixed_amount', 'maximum_amount', 'minimum_amount', 'active'],
            'donation_rules' => ['name', 'max_percentage', 'active'],
            'recommendation_rules' => ['type', 'title', 'message_template', 'action_type', 'active'],
            'tax_returns' => ['name', 'current_step'],
            'tax_return_profiles' => ['filing_status', 'extra_data'],
            'tax_return_spouses' => ['filing_status', 'extra_data'],
            'tax_return_dependents' => ['relation_type', 'eligible', 'allowance_amount', 'metadata'],
            'tax_return_incomes' => ['description', 'gross_amount', 'expense_method', 'calculated_expense', 'net_amount', 'metadata'],
            'tax_return_allowances' => ['input_amount', 'eligible_amount', 'metadata'],
            'tax_return_donations' => ['donation_code', 'input_amount', 'eligible_amount', 'metadata'],
            'tax_return_withholdings' => ['type', 'payer_name', 'payer_tax_id', 'metadata'],
            'tax_calculations' => ['tax_credit', 'withholding_tax', 'prepaid_tax', 'final_tax', 'calculation_trace'],
            'tax_calculation_brackets' => ['from_amount', 'to_amount', 'sort_order'],
            'tax_scenarios' => ['user_id', 'source_tax_return_id', 'tax_year_id', 'rule_version_id', 'payload', 'calculation_result'],
            'content_posts' => ['category_id', 'cover_image', 'source_name', 'source_url'],
        ];
        foreach ($expected as $table => $columns) {
            foreach ($columns as $column) {
                $this->assertTrue(Schema::hasColumn($table, $column), $table.'.'.$column);
            }
        }
    }

    public function test_approved_masters_are_unique_and_reseeding_preserves_published_rules(): void
    {
        $version = TaxRuleVersion::where('version', '2568.1')->firstOrFail();
        $beforeVersion = $version->getAttributes();
        $beforeBrackets = $version->brackets()->orderBy('id')->get()->map->getAttributes()->all();
        $beforeMappings = DB::table('tax_form_income_types')->orderBy('id')->get()->toJson();
        $this->seed(AllowanceTypeSeeder::class);
        $this->seed(AllowanceTypeSeeder::class);

        foreach (self::APPROVED_CODES as $code) {
            $this->assertSame(1, AllowanceType::where('code', $code)->count(), $code);
        }
        $this->assertDatabaseCount('allowance_types', 31);
        $this->assertSame(31, AllowanceType::distinct()->count('code'));
        $this->assertSame($beforeVersion, $version->fresh()->getAttributes());
        $this->assertSame($beforeBrackets, $version->brackets()->orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame($beforeMappings, DB::table('tax_form_income_types')->orderBy('id')->get()->toJson());
        // $version is the retired predecessor: reseeding must leave the rules it was published
        // with exactly as they were, so these counts are its own and not the live version's.
        $this->assertSame(15, $version->allowanceRules()->count());
        $this->assertSame(70, $version->expenseRules()->count());
    }

    public function test_all_approved_allowance_endpoints_return_null_rules(): void
    {
        foreach (self::APPROVED_CODES as $code) {
            $response = $this->getJson('/api/v1/tax-years/2568/allowances/'.$code)->assertOk()
                ->assertJsonPath('data.code', $code);
            // PROVIDENT_FUND is the one attachment line whose amount the form itself prints;
            // the rest of these ceilings came from the M7.3/M7.4 filing instructions. The
            // codes still absent are the ones no repository source settles.
            $ceilings = ['PROVIDENT_FUND' => '10000.00', 'HOME_LOAN_INTEREST' => '100000.00',
                'LIFE_INSURANCE' => '100000.00', 'HEALTH_INSURANCE' => '25000.00',
                'NSF' => '500000.00', 'RMF' => '500000.00', 'THAI_ESG' => '300000.00',
                'THAI_ESGX' => '300000.00', 'EASY_E_RECEIPT' => '30000.00'];
            array_key_exists($code, $ceilings)
                ? $response->assertJsonPath('data.rule.maximum_amount', $ceilings[$code])
                : $response->assertJsonPath('data.rule', null);
        }
        $this->getJson('/api/v1/tax-years/2568/allowances')->assertOk()->assertJsonCount(31, 'data');
    }

    public function test_new_metadata_is_read_from_storage(): void
    {
        $year = TaxYear::where('year', 2568)->firstOrFail();
        $year->update(['filing_start_date' => '2026-01-02', 'filing_end_date' => '2026-03-04']);
        $this->getJson('/api/v1/tax-years/2568')->assertOk()
            ->assertJsonPath('data.filing_start_date', '2026-01-02')->assertJsonPath('data.filing_end_date', '2026-03-04');
        $this->getJson('/api/v1/tax-years/2568/income-types/SECTION_40_1')->assertOk()->assertJsonPath('data.section_code', '40(1)');
    }

    public function test_verified_allowance_resource_supports_approved_fields_without_float_conversion(): void
    {
        $year = TaxYear::factory()->create(['year' => 2571, 'active' => true]);
        $version = TaxRuleVersion::factory()->create(['tax_year_id' => $year->id]);
        $rule = $version->allowanceRules()->create([
            'tax_year_id' => $year->id, 'allowance_type_id' => AllowanceType::where('code', 'PERSONAL')->firstOrFail()->id,
            'code' => 'synthetic', 'method' => 'fixed', 'fixed_amount' => '123.45',
            'maximum_amount' => '234.56', 'minimum_amount' => '12.34', 'source_reference' => 'Synthetic test fixture',
        ]);
        $version->update(['status' => 'published']);
        // M7.4 adds percentage_base: a percentage rule must name the income it is a rate of.
        $this->getJson('/api/v1/tax-years/2571/allowances/PERSONAL')->assertOk()->assertJsonPath('data.rule', [
            'method' => 'fixed', 'fixed_amount' => '123.45', 'percentage' => null, 'percentage_base' => null,
            'maximum_amount' => '234.56', 'minimum_amount' => '12.34', 'conditions' => null,
        ]);
        $this->assertSame('234.56', $rule->fresh()->limit_amount);
    }

    public function test_inactive_numeric_rules_are_not_exposed(): void
    {
        $year = TaxYear::factory()->create(['year' => 2571, 'active' => true]);
        $version = TaxRuleVersion::factory()->create(['tax_year_id' => $year->id]);
        $version->allowanceRules()->create([
            'tax_year_id' => $year->id, 'allowance_type_id' => AllowanceType::where('code', 'PERSONAL')->firstOrFail()->id,
            'code' => 'inactive', 'active' => false, 'fixed_amount' => '123.45', 'source_reference' => 'Synthetic test fixture',
        ]);
        $version->update(['status' => 'published']);
        $this->getJson('/api/v1/tax-years/2571/allowances/PERSONAL')->assertOk()->assertJsonPath('data.rule', null);
    }

    public static function uniqueRules(): array
    {
        return [[IncomeRule::class, 'income_type_id'], [AllowanceRule::class, 'allowance_type_id']];
    }

    #[DataProvider('uniqueRules')]
    public function test_rule_type_unique_constraint_rejects_duplicate_codes(string $model, string $foreignKey): void
    {
        $version = TaxRuleVersion::factory()->create();
        $typeId = $foreignKey === 'income_type_id' ? IncomeType::firstOrFail()->id : AllowanceType::firstOrFail()->id;
        $attributes = ['tax_year_id' => $version->tax_year_id, 'rule_version_id' => $version->id,
            $foreignKey => $typeId, 'code' => 'first', 'name' => 'Synthetic'];
        $model::create($attributes);
        $this->expectException(QueryException::class);
        $model::create(array_replace($attributes, ['code' => 'second']));
    }

    public function test_scenarios_have_explicit_source_owner_year_and_version_relationships(): void
    {
        $return = TaxReturn::factory()->create();
        $scenario = $return->scenarios()->create(['name' => 'Synthetic', 'payload' => ['incomes' => []]]);
        $this->assertTrue($scenario->user->is($return->user));
        $this->assertTrue($scenario->sourceTaxReturn->is($return));
        $this->assertTrue($scenario->taxYear->is($return->taxYear));
        $this->assertTrue($scenario->ruleVersion->is($return->ruleVersion));
        $this->assertTrue($return->user->taxScenarios->contains($scenario));
        $this->assertSame(['incomes' => []], $scenario->fresh()->input_overrides);
    }

    public function test_new_user_defaults_to_member_and_role_is_not_mass_assignable(): void
    {
        $user = User::factory()->create();
        $this->assertSame('member', $user->fresh()->role);
        $this->assertFalse($user->isFillable('role'));
    }

    public function test_corrective_migration_preserves_legacy_data_and_supports_rollback(): void
    {
        $originalConnection = DB::getDefaultConnection();
        config(['database.connections.reconciliation' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
        DB::setDefaultConnection('reconciliation');
        try {
            foreach (glob(database_path('migrations/*.php')) as $file) {
                if (basename($file) < '2026_09_11_144046_reconcile_approved_m2_schema.php') {
                    (require $file)->up();
                }
            }
            DB::table('tax_years')->insert(['id' => 1, 'year' => 2568, 'name' => 'Synthetic', 'is_active' => false]);
            DB::table('tax_rule_versions')->insert(['id' => 1, 'tax_year_id' => 1, 'version' => '2568.1',
                'status' => 'published', 'published_at' => '2026-01-01 00:00:00']);
            DB::table('tax_brackets')->insert(['tax_year_id' => 1, 'rule_version_id' => 1, 'position' => 1,
                'lower_bound' => '0.00', 'upper_bound' => '123.45', 'rate' => '1.2345']);
            DB::table('income_types')->insert(['code' => 'SECTION_40_1', 'name' => 'Synthetic']);
            DB::table('users')->insert(['id' => 1, 'name' => 'Synthetic user', 'email' => 'synthetic@example.test', 'password' => 'unused']);
            DB::table('tax_forms')->insert(['id' => 1, 'tax_year_id' => 1, 'code' => 'PND91', 'name' => 'Synthetic']);
            DB::table('tax_returns')->insert(['id' => 1, 'user_id' => 1, 'tax_year_id' => 1, 'tax_form_id' => 1,
                'rule_version_id' => 1, 'title' => 'Preserved draft']);
            DB::table('tax_return_incomes')->insert(['tax_return_id' => 1, 'income_type_id' => 1,
                'amount' => '123.45', 'details' => '{"synthetic":true}']);
            DB::table('tax_scenarios')->insert(['tax_return_id' => 1, 'name' => 'Preserved scenario',
                'input_overrides' => '{"synthetic":true}']);
            $before = (array) DB::table('tax_rule_versions')->first();
            $bracketBefore = (array) DB::table('tax_brackets')->first();
            $migration = require database_path('migrations/2026_09_11_144046_reconcile_approved_m2_schema.php');
            $migration->up();
            $this->assertSame($before, array_intersect_key((array) DB::table('tax_rule_versions')->first(), $before));
            $this->assertSame($bracketBefore, array_intersect_key((array) DB::table('tax_brackets')->first(), $bracketBefore));
            $this->assertSame(0, DB::table('tax_years')->value('active'));
            $this->assertSame('40(1)', DB::table('income_types')->value('section_code'));
            $this->assertSame(1, DB::table('tax_brackets')->value('sort_order'));
            $this->assertSame('Preserved draft', DB::table('tax_returns')->value('name'));
            $income = DB::table('tax_return_incomes')->first();
            $this->assertSame($income->amount, $income->gross_amount);
            $this->assertSame($income->details, $income->metadata);
            $scenario = DB::table('tax_scenarios')->first();
            $this->assertSame(1, $scenario->user_id);
            $this->assertSame(1, $scenario->source_tax_return_id);
            $this->assertSame(1, $scenario->tax_year_id);
            $this->assertSame(1, $scenario->rule_version_id);
            $this->assertSame($scenario->input_overrides, $scenario->payload);
            $migration->down();
            $this->assertFalse(Schema::hasColumn('tax_years', 'active'));
            $this->assertSame($before, (array) DB::table('tax_rule_versions')->first());
            $migration->up();
            $this->assertSame(1, DB::table('tax_brackets')->count());
        } finally {
            DB::setDefaultConnection($originalConnection);
            DB::purge('reconciliation');
        }
    }
}

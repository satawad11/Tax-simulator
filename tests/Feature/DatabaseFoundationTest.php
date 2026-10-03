<?php

namespace Tests\Feature;

use App\Models\AllowanceType;
use App\Models\ContentPost;
use App\Models\ContentTag;
use App\Models\IncomeType;
use App\Models\TaxBracket;
use App\Models\TaxForm;
use App\Models\TaxFormIncomeType;
use App\Models\TaxReturn;
use App\Models\TaxRuleVersion;
use App\Models\TaxYear;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DatabaseFoundationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected $seed = true;

    public function test_migrations_create_all_27_domain_tables(): void
    {
        $tables = [
            'tax_years', 'tax_forms', 'tax_rule_versions', 'tax_brackets', 'income_types',
            'tax_form_income_types', 'income_rules', 'expense_rules', 'allowance_types',
            'allowance_rules', 'donation_rules', 'recommendation_rules', 'tax_returns',
            'tax_return_profiles', 'tax_return_spouses', 'tax_return_dependents',
            'tax_return_incomes', 'tax_return_allowances', 'tax_return_donations',
            'tax_return_withholdings', 'tax_calculations', 'tax_calculation_brackets',
            'tax_scenarios', 'content_categories', 'content_tags', 'content_posts', 'content_post_tags',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
        }
    }

    public function test_seeded_forms_and_rule_version_belong_to_2568(): void
    {
        $year = TaxYear::where('year', 2568)->firstOrFail();

        $this->assertSame(['PND90', 'PND91'], $year->forms()->orderBy('code')->pluck('code')->all());
        $version = $year->ruleVersions()->where('version', '2568.3')->firstOrFail();
        $this->assertTrue($version->taxYear->is($year));
        $this->assertSame('published', $version->status);
        $this->assertNotNull($version->published_at);
        // Counted per version: the retired predecessor keeps its own copy of every rule, so a
        // table-wide count would now say nothing about the version under test.
        $this->assertSame(8, $version->brackets()->count());
        $this->assertSame(17, $version->allowanceRules()->count());
        $this->assertDatabaseCount('allowance_types', 31);
    }

    public function test_approved_brackets_are_seeded_in_order(): void
    {
        $year = TaxYear::where('year', 2568)->firstOrFail();
        $version = $year->ruleVersions()->where('version', '2568.3')->firstOrFail();
        $brackets = TaxBracket::where('rule_version_id', $version->id)->orderBy('position')->get();

        $this->assertSame([
            [1, '0.00', '150000.00', '0.0000'],
            [2, '150000.00', '300000.00', '5.0000'],
            [3, '300000.00', '500000.00', '10.0000'],
            [4, '500000.00', '750000.00', '15.0000'],
            [5, '750000.00', '1000000.00', '20.0000'],
            [6, '1000000.00', '2000000.00', '25.0000'],
            [7, '2000000.00', '5000000.00', '30.0000'],
            [8, '5000000.00', null, '35.0000'],
        ], $brackets->map(fn (TaxBracket $bracket): array => [
            $bracket->position, $bracket->lower_bound, $bracket->upper_bound, $bracket->rate,
        ])->all());

        foreach ($brackets as $bracket) {
            $this->assertTrue($bracket->taxYear->is($year));
        }
    }

    public function test_pnd91_supports_only_section_40_1(): void
    {
        $form = TaxForm::where('code', 'PND91')->firstOrFail();

        $this->assertSame(['SECTION_40_1'], $form->incomeTypes()->pluck('code')->all());
        $this->assertTrue($form->incomeTypeMappings->first()->taxForm->is($form));
        $this->assertSame('SECTION_40_1', $form->incomeTypeMappings->first()->incomeType->code);
    }

    public function test_pnd90_supports_all_eight_income_types(): void
    {
        $form = TaxForm::where('code', 'PND90')->firstOrFail();

        $this->assertSame([
            'SECTION_40_1', 'SECTION_40_2', 'SECTION_40_3', 'SECTION_40_4',
            'SECTION_40_5', 'SECTION_40_6', 'SECTION_40_7', 'SECTION_40_8',
        ], $form->incomeTypes()->orderBy('code')->pluck('code')->all());
        $this->assertSame(['PND90', 'PND91'], IncomeType::where('code', 'SECTION_40_1')->firstOrFail()->taxForms()->orderBy('code')->pluck('code')->all());
    }

    public function test_seeding_is_repeatable_without_creating_guest_returns(): void
    {
        TaxYear::count();
        $this->seed();

        $this->assertDatabaseCount('tax_years', 1);
        $this->assertDatabaseCount('tax_forms', 2);
        $this->assertDatabaseCount('tax_rule_versions', 2);
        $this->assertDatabaseCount('tax_brackets', 16);
        $this->assertDatabaseCount('income_types', 8);
        $this->assertDatabaseCount('tax_form_income_types', 9);
        $this->assertDatabaseCount('allowance_types', 31);
        $this->assertDatabaseCount('tax_returns', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_member_return_graph_preserves_relationships_and_money(): void
    {
        $return = TaxReturn::factory()->create();
        $profile = $return->profile()->create(['birth_date' => '1990-01-01', 'marital_status' => 'married']);
        $spouse = $return->spouse()->create(['has_income' => false]);
        $dependent = $return->dependents()->create(['relationship' => 'child']);
        $income = $return->incomes()->create(['income_type_id' => IncomeType::firstOrFail()->id, 'amount' => '1234.56']);
        $allowance = $return->allowances()->create(['allowance_type_id' => AllowanceType::firstOrFail()->id, 'amount' => '12.34']);
        $donation = $return->donations()->create(['donation_type' => 'general', 'amount' => '10.00']);
        $withholding = $return->withholdings()->create(['credit_type' => 'withholding', 'amount' => '20.00']);

        foreach ([$profile, $spouse, $dependent, $income, $allowance, $donation, $withholding] as $child) {
            $this->assertTrue($child->taxReturn->is($return));
        }
        $this->assertTrue($return->fresh()->profile->is($profile));
        $this->assertTrue($return->spouse->is($spouse));
        $this->assertTrue($return->user->taxReturns->contains($return));
        $this->assertTrue($return->taxYear->is($return->taxForm->taxYear));
        $this->assertTrue($return->ruleVersion->taxYear->is($return->taxYear));
        $this->assertSame('1234.56', $income->fresh()->amount);
        $this->assertTrue($income->incomeType->returnIncomes->contains($income));
        $this->assertTrue($allowance->allowanceType->returnAllowances->contains($allowance));
    }

    public function test_calculation_and_scenario_relationships_preserve_source_return(): void
    {
        $return = TaxReturn::factory()->create();
        $amounts = array_fill_keys(['gross_income', 'exempt_income', 'total_expense', 'income_after_expense', 'total_allowance', 'total_donation', 'net_income', 'calculated_tax', 'credits', 'withholding', 'final_amount'], '0.00');
        $calculation = $return->calculations()->create($amounts + [
            'rule_version_id' => $return->rule_version_id, 'result_status' => 'ZERO',
            'input_snapshot' => [], 'trace' => [], 'calculated_at' => now(),
        ]);
        $bracket = TaxBracket::create([
            'tax_year_id' => $return->tax_year_id, 'rule_version_id' => $return->rule_version_id,
            'position' => 1, 'lower_bound' => '0.00', 'upper_bound' => null, 'rate' => '1.2345',
        ]);
        $row = $calculation->brackets()->create([
            'tax_bracket_id' => $bracket->id, 'position' => 1, 'lower_bound' => '0.00',
            'rate' => '1.2345', 'taxable_amount' => '0.00', 'tax_amount' => '0.00',
        ]);
        $scenario = $return->scenarios()->create(['base_calculation_id' => $calculation->id, 'name' => 'Synthetic scenario', 'input_overrides' => ['incomes' => []]]);

        $this->assertTrue($calculation->taxReturn->is($return));
        $this->assertTrue($calculation->ruleVersion->is($return->ruleVersion));
        $this->assertTrue($row->taxCalculation->is($calculation));
        $this->assertTrue($row->taxBracket->is($bracket));
        $this->assertTrue($scenario->baseCalculation->is($calculation));
        $this->assertTrue($scenario->taxReturn->is($return));
        $this->assertSame('draft', $return->fresh()->status);
    }

    public function test_soft_deletes_preserve_member_data_and_content_tag_relationships(): void
    {
        $return = TaxReturn::factory()->create();
        $profile = $return->profile()->create([]);
        $post = ContentPost::factory()->create();
        $tag = ContentTag::create(['name' => 'Test tag', 'slug' => 'test-tag']);
        $post->tags()->attach($tag);

        $this->assertTrue($post->category->posts->contains($post));
        $this->assertTrue($post->author->contentPosts->contains($post));
        $this->assertTrue($tag->posts->contains($post));
        $this->assertTrue($post->tagMappings->first()->contentTag->is($tag));

        $return->delete();
        $post->delete();

        $this->assertSoftDeleted($return);
        $this->assertSoftDeleted($post);
        $this->assertModelExists($profile);
        $this->assertDatabaseCount('content_post_tags', 1);
        $this->assertNull(TaxReturn::find($return->id));
        $this->assertNull(ContentPost::find($post->id));
    }

    public static function uniqueMasters(): array
    {
        return [
            'year' => [TaxYear::class], 'form per year' => [TaxForm::class],
            'income code' => [IncomeType::class], 'form income mapping' => [TaxFormIncomeType::class],
            'version per year' => [TaxRuleVersion::class], 'allowance code' => [AllowanceType::class],
        ];
    }

    #[DataProvider('uniqueMasters')]
    public function test_duplicate_master_keys_are_rejected(string $model): void
    {
        $duplicate = $model::firstOrFail()->replicate();

        if ($duplicate instanceof TaxRuleVersion) {
            $duplicate->status = 'draft';
            $duplicate->published_at = null;
        }

        $this->expectException(QueryException::class);
        $duplicate->save();
    }

    public function test_profile_is_unique_per_return(): void
    {
        $return = TaxReturn::factory()->create();
        $return->profile()->create([]);

        $this->expectException(QueryException::class);
        $return->profile()->create([]);
    }

    public function test_duplicate_content_slugs_are_rejected(): void
    {
        $post = ContentPost::factory()->create();

        $this->expectException(QueryException::class);
        $post->replicate()->save();
    }

    public function test_returns_cannot_reference_a_form_from_another_year(): void
    {
        $return = TaxReturn::factory()->create();
        $form = TaxForm::factory()->create();

        $this->expectException(QueryException::class);
        $return->update(['tax_form_id' => $form->id]);
    }

    public function test_returns_cannot_reference_a_rule_version_from_another_year(): void
    {
        $return = TaxReturn::factory()->create();
        $version = TaxRuleVersion::factory()->create();

        $this->expectException(QueryException::class);
        $return->update(['rule_version_id' => $version->id]);
    }

    public function test_foreign_keys_reject_orphan_incomes(): void
    {
        $return = TaxReturn::factory()->create();

        $this->expectException(QueryException::class);
        $return->incomes()->create(['income_type_id' => 999999, 'amount' => '1.00']);
    }

    public function test_mysql_uses_unsigned_bigint_primary_keys_and_exact_money(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL-specific storage check runs with phpunit.mysql.xml.');
        }

        $columns = collect(DB::select('SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name, COLUMN_TYPE AS column_type FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()'));
        $domainNames = ['tax_', 'income_', 'expense_', 'allowance_', 'donation_', 'recommendation_', 'content_'];
        foreach ($columns as $column) {
            if (! str($column->table_name)->startsWith($domainNames)) {
                continue;
            }
            if ($column->column_name === 'id') {
                $this->assertSame('bigint unsigned', $column->column_type, $column->table_name);
            }
            $this->assertNotContains($column->column_type, ['float', 'double']);
        }
        $this->assertSame('decimal(15,2)', $columns->first(fn ($column) => $column->table_name === 'tax_return_incomes' && $column->column_name === 'amount')->column_type);
    }
}

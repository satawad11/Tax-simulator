<?php

namespace Tests\Feature;

use App\Models\AllowanceType;
use App\Models\IncomeType;
use App\Models\TaxForm;
use App\Models\TaxRuleVersion;
use App\Models\TaxYear;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TaxMetadataApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected $seed = true;

    public function test_calculable_years_are_listed_newest_first_with_only_public_fields(): void
    {
        /*
         * This asserted that *active* years are listed, and a bare year with no rule version was
         * enough to satisfy it. That is the assumption the live defect came from: opening next
         * year's tax year put an uncalculable year at the top of the public list, the wizard took
         * it, and every metadata request for it answered 409.
         *
         * A public list means "these are the years you can calculate", so the fixture now gives
         * the new year what makes that true. The exclusion of an unpublished year has its own test
         * in `OpenedTaxYearDoesNotBreakTheSimulatorTest`.
         */
        $next = TaxYear::factory()->create(['year' => 2569, 'name' => 'New year', 'is_active' => true]);
        // Draft first, then publish: `ProtectsPublishedRules` refuses a version created already
        // published, which is the guard the whole tax engine's immutability rests on.
        TaxRuleVersion::factory()->create(['tax_year_id' => $next->id, 'status' => 'draft'])
            ->update(['status' => 'published']);
        TaxYear::factory()->create(['year' => 2570, 'is_active' => false]);

        $this->get('/api/v1/tax-years')->assertOk()->assertExactJson([
            'success' => true, 'message' => null, 'data' => [
                ['year' => 2569, 'name' => 'New year', 'active' => true],
                ['year' => 2568, 'name' => 'ปีภาษี 2568', 'active' => true],
            ],
        ]);
    }

    public function test_year_detail_exposes_the_published_version_and_unknown_dates(): void
    {
        $this->getJson('/api/v1/tax-years/2568')->assertOk()->assertExactJson([
            'success' => true, 'message' => null, 'data' => [
                'year' => 2568, 'name' => 'ปีภาษี 2568', 'active' => true,
                'filing_start_date' => null, 'filing_end_date' => null,
                'rule_version' => ['version' => '2568.3', 'status' => 'published'],
            ],
        ]);
    }

    public static function missingResources(): array
    {
        return [
            'unknown year' => ['/tax-years/9999'],
            'malformed year' => ['/tax-years/not-a-year'],
            'unknown form' => ['/tax-years/2568/forms/UNKNOWN'],
            'unknown income' => ['/tax-years/2568/income-types/UNKNOWN'],
            'unknown allowance' => ['/tax-years/2568/allowances/UNKNOWN'],
            'unknown filter form' => ['/tax-years/2568/income-types?form=UNKNOWN'],
            'unknown parent' => ['/tax-years/9999/tax-brackets'],
            'injection form' => ['/tax-years/2568/income-types?form=%27%20OR%201%3D1'],
        ];
    }

    #[DataProvider('missingResources')]
    public function test_missing_resources_return_the_standard_404(string $path): void
    {
        $this->get('/api/v1'.$path)->assertNotFound()->assertExactJson([
            'success' => false, 'message' => 'Resource not found', 'errors' => null,
        ]);
    }

    public static function contextPaths(): array
    {
        return [
            'detail' => [''], 'forms' => ['/forms'], 'form' => ['/forms/PND91'],
            'incomes' => ['/income-types'], 'income' => ['/income-types/SECTION_40_1'],
            'allowances' => ['/allowances'], 'allowance' => ['/allowances/PERSONAL'],
            'brackets' => ['/tax-brackets'],
        ];
    }

    #[DataProvider('contextPaths')]
    public function test_no_endpoint_falls_back_to_a_draft_version(string $suffix): void
    {
        $year = TaxYear::factory()->create(['year' => 2570, 'is_active' => true]);
        TaxRuleVersion::factory()->create(['tax_year_id' => $year->id]);

        $this->getJson('/api/v1/tax-years/2570'.$suffix)->assertConflict()->assertExactJson([
            'success' => false, 'message' => 'No published rule version is available for this tax year.', 'errors' => null,
        ]);
    }

    #[DataProvider('contextPaths')]
    public function test_inactive_years_are_not_publicly_accessible(string $suffix): void
    {
        TaxYear::where('year', 2568)->firstOrFail()->update(['is_active' => false]);

        $this->getJson('/api/v1/tax-years/2568'.$suffix)->assertNotFound();
    }

    public function test_multiple_published_versions_fail_clearly(): void
    {
        $version = TaxRuleVersion::factory()->create(['tax_year_id' => TaxYear::where('year', 2568)->firstOrFail()->id]);
        $version->update(['status' => 'published']);

        $this->getJson('/api/v1/tax-years/2568/tax-brackets')->assertConflict()->assertExactJson([
            'success' => false, 'message' => 'Multiple published rule versions exist for this tax year.', 'errors' => null,
        ]);
    }

    public function test_forms_are_active_and_scoped_to_the_requested_year(): void
    {
        TaxForm::factory()->create(['tax_year_id' => TaxYear::where('year', 2568)->firstOrFail()->id, 'code' => 'INACTIVE', 'is_active' => false]);
        TaxForm::factory()->create(['code' => 'FOREIGN', 'is_active' => true]);

        $response = $this->getJson('/api/v1/tax-years/2568/forms')->assertOk();
        $this->assertSame(['PND90', 'PND91'], array_column($response->json('data'), 'code'));
        $this->getJson('/api/v1/tax-years/2568/forms/INACTIVE')->assertNotFound();
        $this->getJson('/api/v1/tax-years/2568/forms/FOREIGN')->assertNotFound();
        $this->getJson('/api/v1/tax-years/2568/income-types?form=FOREIGN')->assertNotFound();
    }

    public static function formMappings(): array
    {
        return [
            'salary' => ['PND91', ['SECTION_40_1']],
            'general' => ['PND90', ['SECTION_40_1', 'SECTION_40_2', 'SECTION_40_3', 'SECTION_40_4', 'SECTION_40_5', 'SECTION_40_6', 'SECTION_40_7', 'SECTION_40_8']],
        ];
    }

    #[DataProvider('formMappings')]
    public function test_form_detail_and_income_filter_follow_stored_mappings(string $form, array $expected): void
    {
        $detail = $this->getJson('/api/v1/tax-years/2568/forms/'.$form)->assertOk()->assertJsonPath('data.code', $form);
        $this->assertSame($expected, array_column($detail->json('data.supported_income_types'), 'code'));

        $filtered = $this->getJson('/api/v1/tax-years/2568/income-types?form='.$form)->assertOk();
        $this->assertSame($expected, array_column($filtered->json('data'), 'code'));
    }

    public function test_income_list_excludes_unmapped_master_records(): void
    {
        IncomeType::create(['code' => 'UNMAPPED', 'name' => 'Synthetic']);

        $this->getJson('/api/v1/tax-years/2568/income-types')->assertOk()->assertJsonCount(8, 'data');
        $this->getJson('/api/v1/tax-years/2568/income-types/UNMAPPED')->assertNotFound();
    }

    public function test_income_detail_exposes_only_the_approved_employment_expense_rule(): void
    {
        $this->getJson('/api/v1/tax-years/2568/income-types/SECTION_40_1')->assertOk()->assertExactJson([
            'success' => true, 'message' => null, 'data' => [
                'code' => 'SECTION_40_1', 'section_code' => '40(1)', 'name' => 'เงินได้ตามมาตรา 40(1)',
                'description' => null,
                'plain_language_name' => 'เงินเดือน ค่าจ้าง โบนัส บำนาญ และเงินได้จากการจ้างแรงงาน',
                'examples' => 'ตัวอย่าง: เงินเดือน โบนัส ค่าจ้าง หรือบำนาญจากนายจ้าง',
                'form_sections' => ['PND90' => 'ข้อ 1 รายการเงินได้ตามมาตรา 40(1) และ 40(2)', 'PND91' => 'ข้อ ก เงินได้ตามมาตรา 40(1)'],
                'rule' => ['active' => true], 'expense_rule' => [
                    'method' => 'percentage_limit', 'fixed_amount' => null, 'percentage' => '50.0000',
                    'maximum_amount' => '100000.00', 'minimum_amount' => null, 'conditions' => null,
                ],
                'expense_rules' => [[
                    'income_subtype' => null, 'label' => null, 'status' => 'VERIFIED',
                    'method' => 'percentage_limit', 'percentage' => '50.0000', 'maximum_amount' => '100000.00',
                    'fixed_amount' => null, 'expense_group' => 'SECTION_40_1_2',
                    'actual_expense_supported' => false, 'expense_method_selection_required' => false,
                ]],
            ],
        ]);
    }

    public function test_allowances_expose_masters_without_inventing_numeric_rules(): void
    {
        $response = $this->getJson('/api/v1/tax-years/2568/allowances')->assertOk()->assertJsonCount(31, 'data');
        // Only lines whose ceiling is printed as a plain amount carry a rule; the rest stay
        // null. M7.3 added five of these from the filing instructions to the form's own one.
        $ceilings = ['PROVIDENT_FUND' => '10000.00', 'PARENT_HEALTH_INSURANCE' => '15000.00',
            'HOME_LOAN_INTEREST' => '100000.00', 'MATERNITY' => '60000.00',
            'POLITICAL_PARTY_SUPPORT' => '10000.00', 'ART_PURCHASE' => '100000.00',
            // M7.4 expressed the percentage and shared-cap shapes, which unblocked these.
            'LIFE_INSURANCE' => '100000.00', 'HEALTH_INSURANCE' => '25000.00', 'NSF' => '500000.00',
            'RMF' => '500000.00', 'THAI_ESG' => '300000.00', 'THAI_ESGX' => '300000.00',
            'THAI_ESGX_SWITCH' => '300000.00', 'EASY_E_RECEIPT' => '30000.00',
            'EASY_E_RECEIPT_OTOP' => '20000.00',
            // ใบแนบ ข้อ 22.1 เมืองหลัก, granted as two lines of 10,000 each.
            'DOMESTIC_TRAVEL_MAIN_CITY' => '10000.00', 'DOMESTIC_TRAVEL_MAIN_CITY_ETAX' => '10000.00'];
        foreach ($response->json('data') as $allowance) {
            if (array_key_exists($allowance['code'], $ceilings)) {
                $this->assertSame($ceilings[$allowance['code']], $allowance['rule']['maximum_amount']);

                continue;
            }
            $this->assertNull($allowance['rule'], $allowance['code']);
        }
        $this->getJson('/api/v1/tax-years/2568/allowances/PERSONAL')->assertOk()
            ->assertJsonPath('data.code', 'PERSONAL')->assertJsonPath('data.rule', null);
    }

    public function test_category_filter_uses_stored_values_and_hides_inactive_allowances(): void
    {
        AllowanceType::where('code', 'SPOUSE')->firstOrFail()->update(['category' => 'family']);
        AllowanceType::where('code', 'CHILD')->firstOrFail()->update(['category' => 'family', 'is_active' => false]);

        $response = $this->getJson('/api/v1/tax-years/2568/allowances?category=family')->assertOk();
        $this->assertSame(['SPOUSE'], array_column($response->json('data'), 'code'));
        $this->getJson('/api/v1/tax-years/2568/allowances/CHILD')->assertNotFound();
        $this->getJson('/api/v1/tax-years/2568/allowances?category=unknown')->assertOk()->assertJsonPath('data', []);
    }

    public static function invalidFilters(): array
    {
        return [
            'form array' => ['/income-types?form[]=PND91', 'form'],
            'empty form' => ['/income-types?form=', 'form'],
            'category array' => ['/allowances?category[]=family', 'category'],
            'empty category' => ['/allowances?category=', 'category'],
        ];
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_query_filters_return_422(string $path, string $field): void
    {
        $this->getJson('/api/v1/tax-years/2568'.$path)->assertUnprocessable()
            ->assertJsonPath('success', false)->assertJsonPath('message', 'Validation failed')->assertJsonValidationErrors($field);
    }

    public function test_brackets_are_ordered_precise_and_ignore_drafts(): void
    {
        $draft = TaxRuleVersion::factory()->create(['tax_year_id' => TaxYear::where('year', 2568)->firstOrFail()->id]);
        $draft->brackets()->create(['tax_year_id' => $draft->tax_year_id, 'position' => 1, 'lower_bound' => '123.45', 'rate' => '1.2345']);
        $response = $this->getJson('/api/v1/tax-years/2568/tax-brackets')->assertOk()
            ->assertJsonPath('data.rule_version', '2568.3')->assertJsonCount(8, 'data.brackets');

        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8], array_column($response->json('data.brackets'), 'sort_order'));
        $this->assertSame(['0.0000', '5.0000', '10.0000', '15.0000', '20.0000', '25.0000', '30.0000', '35.0000'], array_column($response->json('data.brackets'), 'rate'));
        $response->assertJsonPath('data.brackets.0.from', '0.00')
            ->assertJsonPath('data.brackets.0.to', '150000.00')
            ->assertJsonPath('data.brackets.7.from', '5000000.00')
            ->assertJsonPath('data.brackets.7.to', null);
    }

    /** @return array{TaxYear, TaxRuleVersion} */
    private function syntheticContext(): array
    {
        $year = TaxYear::factory()->create(['year' => 2570, 'is_active' => true]);
        $version = TaxRuleVersion::factory()->create(['tax_year_id' => $year->id]);
        $form = TaxForm::factory()->create(['tax_year_id' => $year->id, 'code' => 'PND91', 'is_active' => true]);
        $form->incomeTypes()->attach(IncomeType::where('code', 'SECTION_40_1')->firstOrFail());

        return [$year, $version];
    }

    public function test_verified_rules_are_scoped_and_keep_decimal_strings(): void
    {
        [$year, $version] = $this->syntheticContext();
        $version->expenseRules()->create([
            'tax_year_id' => $year->id, 'income_type_id' => IncomeType::where('code', 'SECTION_40_1')->firstOrFail()->id,
            'code' => 'synthetic', 'method' => 'percentage_limit', 'percentage' => '7.1234',
            'limit_amount' => '123456.78', 'source_reference' => 'Synthetic verified fixture',
        ]);
        $version->allowanceRules()->create([
            'tax_year_id' => $year->id, 'allowance_type_id' => AllowanceType::where('code', 'PERSONAL')->firstOrFail()->id,
            'code' => 'synthetic', 'percentage' => '1.2345', 'limit_amount' => '999999.99',
            'source_reference' => 'Synthetic verified fixture',
        ]);
        $version->update(['status' => 'published']);

        $this->getJson('/api/v1/tax-years/2570/income-types/SECTION_40_1')->assertOk()
            ->assertJsonPath('data.expense_rule.percentage', '7.1234')
            ->assertJsonPath('data.expense_rule.maximum_amount', '123456.78');
        $this->getJson('/api/v1/tax-years/2570/allowances/PERSONAL')->assertOk()
            ->assertJsonPath('data.rule.maximum_amount', '999999.99');
        $this->getJson('/api/v1/tax-years/2568/allowances/PERSONAL')->assertOk()->assertJsonPath('data.rule', null);
    }

    public function test_unverified_and_draft_rules_are_not_exposed(): void
    {
        [$year, $version] = $this->syntheticContext();
        $attributes = ['tax_year_id' => $year->id, 'code' => 'synthetic', 'limit_amount' => '123.45'];
        $income = IncomeType::where('code', 'SECTION_40_1')->firstOrFail();
        $allowance = AllowanceType::where('code', 'PERSONAL')->firstOrFail();
        $version->expenseRules()->create($attributes + ['income_type_id' => $income->id]);
        $version->allowanceRules()->create($attributes + ['allowance_type_id' => $allowance->id]);
        $version->update(['status' => 'published']);
        $draft = TaxRuleVersion::factory()->create(['tax_year_id' => $year->id]);
        $draft->expenseRules()->create($attributes + ['income_type_id' => $income->id, 'source_reference' => 'Synthetic draft']);
        $draft->allowanceRules()->create($attributes + ['allowance_type_id' => $allowance->id, 'source_reference' => 'Synthetic draft']);

        $this->getJson('/api/v1/tax-years/2570/income-types/SECTION_40_1')->assertOk()->assertJsonPath('data.expense_rule', null);
        $this->getJson('/api/v1/tax-years/2570/allowances/PERSONAL')->assertOk()->assertJsonPath('data.rule', null);
    }

    public static function recommendations(): array
    {
        return [
            'salary only' => [['SECTION_40_1'], 'PND91', 'ONLY_SECTION_40_1'],
            'mixed' => [['SECTION_40_1', 'SECTION_40_8'], 'PND90', 'OTHER_INCOME_TYPES'],
            'non salary only' => [['SECTION_40_8'], 'PND90', 'OTHER_INCOME_TYPES'],
        ];
    }

    #[DataProvider('recommendations')]
    public function test_recommendation_is_public_and_does_not_persist_guest_data(array $incomes, string $form, string $reason): void
    {
        $this->postJson('/api/v1/tax/forms/recommend', ['tax_year' => 2568, 'income_types' => $incomes])
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('message', null)
            ->assertJsonPath('data.recommended_form.code', $form)->assertJsonPath('data.reason_code', $reason)
            ->assertCookieMissing(config('session.cookie'));
        $this->assertDatabaseCount('tax_returns', 0);
        $this->assertDatabaseCount('tax_calculations', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public static function invalidRecommendations(): array
    {
        return [
            'missing fields' => [[], ['tax_year', 'income_types']],
            'empty incomes' => [['tax_year' => 2568, 'income_types' => []], ['income_types']],
            'not array' => [['tax_year' => 2568, 'income_types' => 'SECTION_40_1'], ['income_types']],
            'not list' => [['tax_year' => 2568, 'income_types' => ['salary' => 'SECTION_40_1']], ['income_types']],
            'unknown income' => [['tax_year' => 2568, 'income_types' => ['UNKNOWN']], ['income_types.0']],
            'duplicate income' => [['tax_year' => 2568, 'income_types' => ['SECTION_40_1', 'SECTION_40_1']], ['income_types.0']],
            'non string income' => [['tax_year' => 2568, 'income_types' => [1]], ['income_types.0']],
            'unknown year' => [['tax_year' => 9999, 'income_types' => ['SECTION_40_1']], ['tax_year']],
            'non integer year' => [['tax_year' => 2568.5, 'income_types' => ['SECTION_40_1']], ['tax_year']],
        ];
    }

    #[DataProvider('invalidRecommendations')]
    public function test_invalid_recommendations_return_422(array $payload, array $fields): void
    {
        $this->postJson('/api/v1/tax/forms/recommend', $payload)->assertUnprocessable()
            ->assertJsonPath('success', false)->assertJsonPath('message', 'Validation failed')->assertJsonValidationErrors($fields);
    }

    public function test_recommendation_rejects_income_not_supported_in_the_requested_year(): void
    {
        [, $version] = $this->syntheticContext();
        $version->update(['status' => 'published']);

        $this->postJson('/api/v1/tax/forms/recommend', ['tax_year' => 2570, 'income_types' => ['SECTION_40_8']])
            ->assertUnprocessable()->assertJsonValidationErrors('income_types.0')
            ->assertJsonFragment(['income_types.0' => ['This income type is not supported for the requested tax year.']]);
    }

    public function test_recommendation_rejects_inactive_years(): void
    {
        TaxYear::where('year', 2568)->firstOrFail()->update(['is_active' => false]);
        $this->postJson('/api/v1/tax/forms/recommend', ['tax_year' => 2568, 'income_types' => ['SECTION_40_1']])
            ->assertUnprocessable()->assertJsonValidationErrors('tax_year');
    }

    public function test_recommendation_does_not_use_an_inactive_recommended_form(): void
    {
        TaxForm::where('code', 'PND91')->firstOrFail()->update(['is_active' => false]);
        $this->postJson('/api/v1/tax/forms/recommend', ['tax_year' => 2568, 'income_types' => ['SECTION_40_1']])
            ->assertConflict()->assertJsonPath('message', 'The recommended form is unavailable for the selected income types.');
    }

    public function test_recommendation_requires_a_published_context(): void
    {
        $this->syntheticContext();
        $this->postJson('/api/v1/tax/forms/recommend', ['tax_year' => 2570, 'income_types' => ['SECTION_40_1']])
            ->assertConflict()->assertJsonPath('message', 'No published rule version is available for this tax year.');
    }

    public function test_ambiguous_verified_rules_fail_instead_of_choosing_arbitrarily(): void
    {
        [$year, $version] = $this->syntheticContext();
        foreach (['first', 'second'] as $code) {
            $attributes = ['tax_year_id' => $year->id, 'code' => $code, 'source_reference' => 'Synthetic fixture'];
            $version->expenseRules()->create($attributes + ['income_type_id' => IncomeType::where('code', 'SECTION_40_1')->firstOrFail()->id]);
        }
        $version->update(['status' => 'published']);

        $this->getJson('/api/v1/tax-years/2570/income-types/SECTION_40_1')->assertConflict()
            ->assertJsonPath('message', 'Multiple verified expense rules exist for one income subcategory.');
    }

    public function test_mysql_metadata_preserves_full_money_precision(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Full DECIMAL(15,2) storage precision is verified on MySQL.');
        }
        [$year, $version] = $this->syntheticContext();
        $version->expenseRules()->create([
            'tax_year_id' => $year->id, 'income_type_id' => IncomeType::where('code', 'SECTION_40_1')->firstOrFail()->id,
            'code' => 'precision', 'limit_amount' => '9999999999999.99', 'source_reference' => 'Synthetic fixture',
        ]);
        $version->update(['status' => 'published']);

        $this->getJson('/api/v1/tax-years/2570/income-types/SECTION_40_1')->assertOk()
            ->assertJsonPath('data.expense_rule.maximum_amount', '9999999999999.99');
    }

    public static function publicPaths(): array
    {
        return [
            ['/tax-years'], ['/tax-years/2568'], ['/tax-years/2568/forms'],
            ['/tax-years/2568/forms/PND90'], ['/tax-years/2568/income-types'],
            ['/tax-years/2568/income-types/SECTION_40_1'], ['/tax-years/2568/allowances'],
            ['/tax-years/2568/allowances/PERSONAL'], ['/tax-years/2568/tax-brackets'],
        ];
    }

    #[DataProvider('publicPaths')]
    public function test_public_resources_do_not_leak_internal_fields(string $path): void
    {
        $response = $this->get('/api/v1'.$path)->assertOk()->assertJsonPath('success', true)
            ->assertJsonPath('message', null)->assertCookieMissing(config('session.cookie'));
        foreach (['id', 'tax_year_id', 'rule_version_id', 'created_at', 'updated_at', 'pivot', 'email', 'password', 'token', 'source_reference'] as $key) {
            $this->assertStringNotContainsString('"'.$key.'":', $response->getContent());
        }
    }
}

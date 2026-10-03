<?php

namespace Tests\Feature;

use App\Models\IncomeType;
use App\Models\TaxCalculation;
use App\Models\TaxReturn;
use App\Models\TaxRuleVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberPnd90ApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A subcategory this test removes the production rule from. M7.4 resolved every ภ.ง.ด.90
     * subcategory from the filing instructions, so the unverified state is now created here.
     */
    private const UNVERIFIED_TYPE = 'SECTION_40_6';

    private const UNVERIFIED_SUBTYPE = 'OTHER_LIBERAL_PROFESSION';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /** Removes the seeded production rule for one category, leaving it genuinely unverified. */
    private function unverify(string $incomeType = self::UNVERIFIED_TYPE, ?string $subtype = self::UNVERIFIED_SUBTYPE): void
    {
        DB::table('expense_rules')
            ->where('income_type_id', IncomeType::where('code', $incomeType)->firstOrFail()->id)
            ->where(fn ($query) => $subtype === null ? $query->whereNull('income_subtype') : $query->where('income_subtype', $subtype))
            ->delete();
    }

    /** Synthetic structure-only fixture; no production expense value is seeded here. */
    private function fixtureRule(string $incomeType, ?string $subtype, string $method, array $values = []): void
    {
        $version = TaxRuleVersion::where('version', '2568.3')->firstOrFail();
        $this->unverify($incomeType, $subtype);
        DB::table('expense_rules')->insert(['tax_year_id' => $version->tax_year_id, 'rule_version_id' => $version->id,
            'income_type_id' => IncomeType::where('code', $incomeType)->firstOrFail()->id,
            'income_subtype' => $subtype, 'code' => 'TEST_'.$incomeType.'_'.($subtype ?? 'ALL'),
            'method' => $method, 'active' => true,
            'source_reference' => 'Synthetic test fixture; not a production rule',
            'percentage' => $values['percentage'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function draft(?User $user = null, string $name = 'PND90 synthetic'): int
    {
        Sanctum::actingAs($user ?? User::factory()->create());

        return $this->postJson('/api/v1/tax-returns', ['tax_year' => 2568, 'form_code' => 'PND90', 'name' => $name])
            ->assertCreated()->assertJsonPath('data.form_code', 'PND90')->json('data.id');
    }

    private function income(int $return, string $type, string $gross, string $exempt = '0.00',
        ?string $subtype = null, ?string $selection = null): int
    {
        return $this->postJson("/api/v1/tax-returns/$return/incomes", ['income_type' => $type,
            'gross_amount' => $gross, 'exempt_amount' => $exempt,
            ...($subtype === null ? [] : ['income_subtype' => $subtype]),
            ...($selection === null ? [] : ['expense_method_selection' => $selection])])->assertCreated()->json('data.id');
    }

    public function test_a_member_can_create_a_pnd90_draft_and_save_several_income_types(): void
    {
        $id = $this->draft();
        $this->income($id, 'SECTION_40_1', '600000.00');
        $this->income($id, 'SECTION_40_7', '200000.00', '0.00', null, 'percentage');
        $this->postJson("/api/v1/tax-returns/$id/withholdings", ['type' => 'withholding', 'amount' => '10000.00'])->assertCreated();

        $detail = $this->getJson("/api/v1/tax-returns/$id")->assertOk()
            ->assertJsonPath('data.form_code', 'PND90')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonCount(2, 'data.incomes')->json('data.incomes');
        $this->assertSame(['SECTION_40_1', 'SECTION_40_7'], array_column($detail, 'income_type'));
        $this->assertSame([null, 'percentage'], array_column($detail, 'expense_method_selection'));
    }

    public function test_a_member_can_save_a_subcategory_and_it_round_trips(): void
    {
        $id = $this->draft();
        $this->income($id, 'SECTION_40_6', '300000.00', '0.00', 'MEDICAL_PRACTICE', 'percentage');

        $this->getJson("/api/v1/tax-returns/$id")->assertOk()
            ->assertJsonPath('data.incomes.0.income_subtype', 'MEDICAL_PRACTICE')
            ->assertJsonPath('data.incomes.0.expense_method_selection', 'percentage');
    }

    public function test_a_saved_income_type_with_subcategories_requires_one(): void
    {
        $id = $this->draft();

        $this->postJson("/api/v1/tax-returns/$id/incomes", ['income_type' => 'SECTION_40_6', 'gross_amount' => '100'])
            ->assertUnprocessable()->assertJsonValidationErrors('income_subtype');
        $this->postJson("/api/v1/tax-returns/$id/incomes",
            ['income_type' => 'SECTION_40_6', 'gross_amount' => '100', 'income_subtype' => 'NOT_A_SUBTYPE'])
            ->assertUnprocessable()->assertJsonValidationErrors('income_subtype');
        $this->postJson("/api/v1/tax-returns/$id/incomes",
            ['income_type' => 'SECTION_40_1', 'gross_amount' => '100', 'income_subtype' => 'MEDICAL_PRACTICE'])
            ->assertUnprocessable()->assertJsonValidationErrors('income_subtype');
        $this->assertDatabaseCount('tax_return_incomes', 0);
    }

    public function test_a_pnd91_return_still_refuses_income_types_it_does_not_map(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $id = $this->postJson('/api/v1/tax-returns', ['tax_year' => 2568, 'form_code' => 'PND91', 'name' => 'PND91 synthetic'])
            ->assertCreated()->json('data.id');

        $this->postJson("/api/v1/tax-returns/$id/incomes", ['income_type' => 'SECTION_40_7', 'gross_amount' => '100'])
            ->assertUnprocessable()->assertJsonValidationErrors('income_type');
        $this->assertDatabaseCount('tax_return_incomes', 0);
    }

    public function test_a_saved_pnd90_return_calculates_and_persists_history_and_brackets(): void
    {
        $id = $this->draft();
        $this->income($id, 'SECTION_40_1', '600000.00');
        $this->income($id, 'SECTION_40_7', '200000.00', '0.00', null, 'percentage');
        $this->postJson("/api/v1/tax-returns/$id/withholdings", ['type' => 'withholding', 'amount' => '10000.00'])->assertCreated();

        $first = $this->postJson("/api/v1/tax-returns/$id/calculate")->assertOk()
            ->assertJsonPath('data.calculation.form_code', 'PND90')
            ->assertJsonPath('data.calculation.expenses.total', '220000.00')
            ->assertJsonPath('data.calculation.net_income', '580000.00')
            ->assertJsonPath('data.calculation.result.status', 'PAYABLE')
            ->assertJsonPath('data.calculation.result.amount', '29500.00')->json('data.id');

        $this->assertDatabaseCount('tax_calculations', 1);
        $this->assertDatabaseCount('tax_calculation_brackets', 8);

        $second = $this->postJson("/api/v1/tax-returns/$id/calculate")->assertOk()->json('data.id');
        $this->assertNotSame($first, $second);
        $this->assertDatabaseCount('tax_calculations', 2);
        $this->getJson("/api/v1/tax-returns/$id/calculations")->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson("/api/v1/tax-returns/$id/calculations/$first")->assertOk()
            ->assertJsonPath('data.calculation.net_income', '580000.00');
    }

    public function test_guest_and_member_pnd90_results_match_for_identical_input(): void
    {
        $id = $this->draft();
        $this->income($id, 'SECTION_40_1', '600000.00');
        $this->income($id, 'SECTION_40_7', '200000.00', '0.00', null, 'percentage');
        $this->postJson("/api/v1/tax-returns/$id/withholdings", ['type' => 'withholding', 'amount' => '10000.00'])->assertCreated();

        $guest = $this->postJson('/api/v1/tax/calculate', ['tax_year' => 2568, 'form_code' => 'PND90',
            'incomes' => [['income_type' => 'SECTION_40_1', 'gross_amount' => '600000.00', 'exempt_amount' => '0.00'],
                ['income_type' => 'SECTION_40_7', 'gross_amount' => '200000.00', 'exempt_amount' => '0.00',
                    'expense_method_selection' => 'percentage']],
            'withholdings' => [['type' => 'withholding', 'amount' => '10000.00']]])->assertOk()->json('data');
        $member = $this->postJson("/api/v1/tax-returns/$id/calculate")->assertOk()->json('data.calculation');

        $this->assertJsonStringEqualsJsonString(json_encode($guest), json_encode($member));
    }

    public function test_calculation_is_blocked_while_a_saved_category_has_no_verified_expense_rule(): void
    {
        $this->unverify();
        $id = $this->draft();
        $this->income($id, 'SECTION_40_1', '600000.00');
        $this->income($id, self::UNVERIFIED_TYPE, '200000.00', '0.00', self::UNVERIFIED_SUBTYPE);

        $this->postJson("/api/v1/tax-returns/$id/calculate")->assertUnprocessable()
            ->assertJsonPath('success', false)->assertJsonValidationErrors('incomes.1.income_subtype');
        $this->postJson("/api/v1/tax-returns/$id/complete")->assertUnprocessable()
            ->assertJsonValidationErrors('incomes.1.income_subtype');

        $this->assertDatabaseCount('tax_calculations', 0);
        $this->assertSame('draft', TaxReturn::findOrFail($id)->status);
        $this->assertNull(TaxReturn::findOrFail($id)->completed_at);
    }

    public function test_a_blocked_pnd90_return_calculates_once_the_rule_becomes_verified(): void
    {
        $this->unverify();
        $id = $this->draft();
        $this->income($id, 'SECTION_40_1', '600000.00');
        $this->income($id, self::UNVERIFIED_TYPE, '200000.00', '0.00', self::UNVERIFIED_SUBTYPE);
        $this->postJson("/api/v1/tax-returns/$id/calculate")->assertUnprocessable();

        $this->fixtureRule(self::UNVERIFIED_TYPE, self::UNVERIFIED_SUBTYPE, 'percentage', ['percentage' => '60.0000']);
        $this->postJson("/api/v1/tax-returns/$id/calculate")->assertOk()
            ->assertJsonPath('data.calculation.expenses.total', '220000.00');
    }

    public function test_a_pnd90_simulation_completes_and_then_freezes_its_input(): void
    {
        $id = $this->draft();
        $this->income($id, 'SECTION_40_1', '720000.00');

        $this->postJson("/api/v1/tax-returns/$id/complete")->assertOk()
            ->assertJsonPath('message', 'Simulation completed — เสร็จสิ้นการทดลอง')
            ->assertJsonPath('data.calculation.form_code', 'PND90');

        $return = TaxReturn::findOrFail($id);
        $this->assertSame('completed', $return->status);
        $this->assertNotNull($return->completed_at);
        $this->postJson("/api/v1/tax-returns/$id/incomes", ['income_type' => 'SECTION_40_1', 'gross_amount' => '1'])->assertStatus(409);
    }

    public function test_duplicating_a_pnd90_return_copies_input_but_not_history(): void
    {
        $id = $this->draft();
        $this->income($id, 'SECTION_40_1', '600000.00');
        $this->income($id, 'SECTION_40_6', '200000.00', '0.00', 'FINE_ARTS', 'percentage');
        $this->postJson("/api/v1/tax-returns/$id/calculate")->assertOk();

        $copy = $this->postJson("/api/v1/tax-returns/$id/duplicate", ['name' => 'PND90 copy'])->assertCreated()
            ->assertJsonPath('data.form_code', 'PND90')->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.latest_calculation', null)->json('data.id');

        $source = TaxReturn::findOrFail($id);
        $new = TaxReturn::findOrFail($copy);
        $this->assertSame(2, $new->incomes()->count());
        $this->assertSame(['SECTION_40_1', 'SECTION_40_6'],
            $new->incomes()->with('incomeType')->get()->pluck('incomeType.code')->sort()->values()->all());
        $this->assertSame(['FINE_ARTS'], $new->incomes()->whereNotNull('income_subtype')->pluck('income_subtype')->all());
        $this->assertSame(0, $new->calculations()->count());
        $this->assertSame(1, $source->calculations()->count());
        $this->assertSame($source->tax_form_id, $new->tax_form_id);
        $this->assertSame($source->rule_version_id, $new->rule_version_id);
    }

    public function test_a_pnd90_return_keeps_its_rule_version_when_a_newer_one_is_published(): void
    {
        $id = $this->draft();
        $this->income($id, 'SECTION_40_1', '720000.00');
        $return = TaxReturn::findOrFail($id);
        TaxRuleVersion::factory()->create(['tax_year_id' => $return->tax_year_id, 'version' => '2568.2'])
            ->update(['status' => 'published']);

        $this->postJson("/api/v1/tax-returns/$id/calculate")->assertOk()
            ->assertJsonPath('data.calculation.rule_version', '2568.3');
        $this->assertSame($return->rule_version_id, TaxCalculation::firstOrFail()->rule_version_id);
    }

    public function test_another_member_cannot_reach_a_pnd90_return(): void
    {
        $id = $this->draft();
        $this->income($id, 'SECTION_40_1', '720000.00');
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/tax-returns/$id")->assertNotFound();
        $this->postJson("/api/v1/tax-returns/$id/calculate")->assertNotFound();
        $this->postJson("/api/v1/tax-returns/$id/incomes", ['income_type' => 'SECTION_40_1', 'gross_amount' => '1'])->assertNotFound();
        $this->getJson("/api/v1/tax-returns/$id/recommendations")->assertNotFound();
        $this->assertSame(1, TaxReturn::findOrFail($id)->incomes()->count());
    }

    public function test_a_member_pnd90_scenario_reuses_the_shared_planning_engine(): void
    {
        $id = $this->draft();
        $this->income($id, 'SECTION_40_1', '720000.00');
        $this->postJson("/api/v1/tax-returns/$id/withholdings", ['type' => 'withholding', 'amount' => '25000.00'])->assertCreated();

        $scenario = $this->postJson("/api/v1/tax-returns/$id/scenarios", ['name' => 'PND90 scenario',
            'payload' => ['withholdings' => ['upsert' => [['type' => 'withholding', 'amount' => '50000.00']]]]])
            ->assertCreated()->json('data.id');

        $this->postJson("/api/v1/tax-returns/$id/scenarios/$scenario/calculate")->assertOk()
            ->assertJsonPath('data.calculation_result.form_code', 'PND90')
            ->assertJsonPath('data.calculation_result.before.result.status', 'PAYABLE')
            ->assertJsonPath('data.calculation_result.after.result.status', 'REFUND')
            ->assertJsonPath('data.calculation_result.estimated_tax_saving', '0.00');
    }

    public function test_a_saved_actual_expense_is_rejected_when_no_rule_permits_it(): void
    {
        $id = $this->draft();
        $this->postJson("/api/v1/tax-returns/$id/incomes",
            ['income_type' => 'SECTION_40_1', 'gross_amount' => '600000.00', 'actual_expense' => '50000.00'])->assertCreated();

        $this->getJson("/api/v1/tax-returns/$id")->assertOk()->assertJsonPath('data.incomes.0.actual_expense', '50000.00');
        $this->postJson("/api/v1/tax-returns/$id/calculate")->assertUnprocessable()
            ->assertJsonValidationErrors('incomes.0.actual_expense');
    }

    public function test_a_saved_actual_expense_may_not_exceed_income_after_exemption(): void
    {
        $id = $this->draft();

        $this->postJson("/api/v1/tax-returns/$id/incomes", ['income_type' => 'SECTION_40_6',
            'income_subtype' => 'MEDICAL_PRACTICE', 'expense_method_selection' => 'actual',
            'gross_amount' => '100000.00', 'exempt_amount' => '40000.00', 'actual_expense' => '70000.00'])
            ->assertUnprocessable()->assertJsonValidationErrors('actual_expense');
    }

    public function test_a_member_may_elect_actual_expense_where_the_form_offers_the_choice(): void
    {
        $id = $this->draft();
        $this->postJson("/api/v1/tax-returns/$id/incomes", ['income_type' => 'SECTION_40_6',
            'income_subtype' => 'MEDICAL_PRACTICE', 'expense_method_selection' => 'actual',
            'gross_amount' => '300000.00', 'actual_expense' => '250000.00'])->assertCreated();

        $this->postJson("/api/v1/tax-returns/$id/calculate")->assertOk()
            ->assertJsonPath('data.calculation.expenses.total', '250000.00')
            ->assertJsonPath('data.calculation.expenses.items.0.expense_method_selection', 'actual')
            ->assertJsonPath('data.calculation.net_income', '50000.00');
    }
}

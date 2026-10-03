<?php

namespace Tests\Feature;

use App\Models\TaxReturn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A member's saved return carries its ใบแนบ ข้อ 13 / ข้อ 20 lines, affirmation included.
 *
 * The affirmation is the part that could easily have been lost. The guard refuses a positive
 * exemption without it, so a draft that saved only the amount would recalculate itself into a 422
 * the member never caused — or, worse, would have been "fixed" by defaulting it to true, which
 * would have the product assert an entitlement on their behalf.
 */
class MemberIncomeExemptionPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function member(): User
    {
        $user = User::factory()->create(['role' => 'member']);
        Sanctum::actingAs($user);

        return $user;
    }

    private function draft(): int
    {
        $this->member();

        return $this->postJson('/api/v1/tax-returns',
            ['tax_year' => 2568, 'form_code' => 'PND90', 'name' => 'ใบแนบ ข้อ 20'])
            ->assertCreated()->json('data.id');
    }

    private function withIncome(int $id): void
    {
        $this->postJson("/api/v1/tax-returns/$id/incomes",
            ['income_type' => 'SECTION_40_1', 'gross_amount' => '5000000', 'exempt_amount' => '0.00'])
            ->assertCreated();
    }

    public function test_a_member_saves_an_exemption_and_it_comes_back_with_the_affirmation(): void
    {
        $id = $this->draft();

        $this->postJson("/api/v1/tax-returns/$id/income-exemptions", ['code' => 'NEW_HOME_CONSTRUCTION',
            'input_amount' => '2400000', 'declarations_confirmed' => true])
            ->assertCreated()
            ->assertJsonPath('data.code', 'NEW_HOME_CONSTRUCTION')
            ->assertJsonPath('data.declarations_confirmed', true);

        $this->getJson("/api/v1/tax-returns/$id")->assertOk()
            ->assertJsonPath('data.income_exemptions.0.code', 'NEW_HOME_CONSTRUCTION')
            ->assertJsonPath('data.income_exemptions.0.input_amount', '2400000.00')
            ->assertJsonPath('data.income_exemptions.0.declarations_confirmed', true);
    }

    public function test_the_saved_return_recalculates_with_the_exemption_applied(): void
    {
        $id = $this->draft();
        $this->withIncome($id);
        $this->postJson("/api/v1/tax-returns/$id/income-exemptions", ['code' => 'NEW_HOME_CONSTRUCTION',
            'input_amount' => '2400000', 'declarations_confirmed' => true])->assertCreated();

        // 5,000,000 less the 100,000 expense ceiling, then two completed 1,000,000 steps.
        $this->postJson("/api/v1/tax-returns/$id/calculate")->assertOk()
            ->assertJsonPath('data.calculation.income_after_expense', '4900000.00')
            ->assertJsonPath('data.calculation.income_exemptions.total', '20000.00')
            ->assertJsonPath('data.calculation.income_after_income_exemptions', '4880000.00');
    }

    public function test_an_unaffirmed_saved_line_is_refused_at_recalculation_rather_than_assumed(): void
    {
        $id = $this->draft();
        $this->withIncome($id);
        $this->postJson("/api/v1/tax-returns/$id/income-exemptions",
            ['code' => 'NEW_HOME_CONSTRUCTION', 'input_amount' => '2400000'])->assertCreated();

        // Saving is permitted — a member may fill the amount first and affirm afterwards — but the
        // calculation refuses it, and the product never supplies the affirmation itself.
        $this->postJson("/api/v1/tax-returns/$id/calculate")->assertStatus(422);
        $this->assertFalse((bool) DB::table('tax_return_income_exemptions')->where('tax_return_id', $id)
            ->value('declarations_confirmed'));
    }

    public function test_the_affirmation_can_be_given_later_by_editing_the_saved_line(): void
    {
        $id = $this->draft();
        $this->withIncome($id);
        $child = $this->postJson("/api/v1/tax-returns/$id/income-exemptions",
            ['code' => 'NEW_HOME_CONSTRUCTION', 'input_amount' => '2400000'])->assertCreated()->json('data.id');

        $this->patchJson("/api/v1/tax-returns/$id/income-exemptions/$child",
            ['declarations_confirmed' => true])->assertOk()
            ->assertJsonPath('data.declarations_confirmed', true);
        $this->postJson("/api/v1/tax-returns/$id/calculate")->assertOk()
            ->assertJsonPath('data.calculation.income_exemptions.total', '20000.00');
    }

    public function test_the_same_line_cannot_be_saved_twice(): void
    {
        $id = $this->draft();
        $this->postJson("/api/v1/tax-returns/$id/income-exemptions", ['code' => 'CCTV_SYSTEM',
            'input_amount' => '100000', 'declarations_confirmed' => true])->assertCreated();

        $this->postJson("/api/v1/tax-returns/$id/income-exemptions", ['code' => 'CCTV_SYSTEM',
            'input_amount' => '50000', 'declarations_confirmed' => true])
            ->assertStatus(422)->assertJsonValidationErrors('code');
    }

    public function test_a_line_the_saved_version_has_no_rule_for_is_refused(): void
    {
        $id = $this->draft();

        // ใบแนบ ข้อ 16 stays uncalculable: the booklet's "กรณีละ" leaves its ceiling unsettled.
        $this->postJson("/api/v1/tax-returns/$id/income-exemptions", ['code' => 'SOCIAL_ENTERPRISE_INVESTMENT',
            'input_amount' => '50000', 'declarations_confirmed' => true])
            ->assertStatus(422)->assertJsonValidationErrors('code');
    }

    public function test_a_saved_line_can_be_removed(): void
    {
        $id = $this->draft();
        $child = $this->postJson("/api/v1/tax-returns/$id/income-exemptions", ['code' => 'CCTV_SYSTEM',
            'input_amount' => '100000', 'declarations_confirmed' => true])->assertCreated()->json('data.id');

        $this->deleteJson("/api/v1/tax-returns/$id/income-exemptions/$child")->assertNoContent();
        $this->getJson("/api/v1/tax-returns/$id")->assertOk()->assertJsonCount(0, 'data.income_exemptions');
    }

    public function test_another_member_cannot_reach_this_returns_lines(): void
    {
        $id = $this->draft();
        $this->member();

        // 404, not 403 — the same answer every other member resource gives. Confirming the return
        // exists would leak that another member has one, which a refusal has no need to reveal.
        $this->postJson("/api/v1/tax-returns/$id/income-exemptions", ['code' => 'CCTV_SYSTEM',
            'input_amount' => '100000', 'declarations_confirmed' => true])->assertNotFound();
    }

    public function test_the_wizard_saves_and_restores_the_affirmation(): void
    {
        $persistence = file_get_contents(resource_path('js/member-return.js'));
        $dashboard = file_get_contents(resource_path('js/member-dashboard.js'));

        // Writing the amount without the affirmation would save a draft that cannot recalculate.
        $this->assertStringContainsString("['income_exemptions', 'income-exemptions'", $persistence);
        $this->assertStringContainsString('declarations_confirmed: Boolean(item.declarations_confirmed)', $persistence);
        $this->assertStringContainsString('income_exemptions: (item.income_exemptions || [])', $dashboard);
        $this->assertStringContainsString("'income_exemptions', 'donations'", $dashboard);
    }

    public function test_a_completed_return_keeps_the_lines_it_was_calculated_with(): void
    {
        $id = $this->draft();
        $this->withIncome($id);
        $this->postJson("/api/v1/tax-returns/$id/income-exemptions", ['code' => 'NEW_HOME_CONSTRUCTION',
            'input_amount' => '2400000', 'declarations_confirmed' => true])->assertCreated();
        $this->postJson("/api/v1/tax-returns/$id/calculate")->assertOk();

        $snapshot = TaxReturn::findOrFail($id)->latestCalculation->input_snapshot;
        $this->assertSame([['code' => 'NEW_HOME_CONSTRUCTION', 'amount' => '2400000.00',
            'declarations_confirmed' => true]], $snapshot['income_exemptions']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\TaxCalculation;
use App\Models\TaxReturn;
use App\Models\TaxRuleVersion;
use App\Models\TaxYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Milestone 09.1 — what happens when a later tax year changes the rates.
 *
 * The guarantee this milestone has to keep is that opening a new year, carrying the baseline into
 * it and publishing new rates leaves every earlier year calculating exactly as it did. These cases
 * walk the whole administrative workflow and then check the old year from both directions: a new
 * simulation for the old year, and a simulation saved before the change.
 *
 * The workflow itself is: open the year (copying its form structure) → clone the previous
 * baseline into it → edit the rates that changed → validate → publish.
 */
class NextTaxYearWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function admin(): User
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        return $admin;
    }

    /** The published 2568 result for a fixed salary, before anything changes. */
    private function baselineResult(string $gross = '720000.00'): array
    {
        return $this->postJson('/api/v1/tax/calculate', ['tax_year' => 2568, 'form_code' => 'PND91',
            'incomes' => [['income_type' => 'SECTION_40_1', 'gross_amount' => $gross, 'exempt_amount' => '0.00']],
            'withholdings' => [['type' => 'withholding', 'amount' => '25000.00']]])->assertOk()->json('data');
    }

    public function test_opening_a_year_copies_the_form_structure_it_needs(): void
    {
        $this->admin();

        $created = $this->postJson('/api/v1/admin/tax-years',
            ['year' => 2569, 'name' => 'ปีภาษี 2569', 'copy_forms_from_year' => 2568])
            ->assertCreated()->json('data');

        $this->assertSame(2569, $created['year']);
        // A year without forms cannot start a simulation at all, so the structure comes with it.
        $this->assertSame(TaxYear::where('year', 2568)->sole()->forms()->count(), $created['form_count']);

        $forms = TaxYear::where('year', 2569)->sole()->forms()->with('incomeTypes')->get();
        $this->assertEqualsCanonicalizing(['PND90', 'PND91'], $forms->pluck('code')->all());
        // And each form accepts the same income types it accepts in the source year.
        $this->assertSame(1, $forms->firstWhere('code', 'PND91')->incomeTypes->count());
        $this->assertSame(8, $forms->firstWhere('code', 'PND90')->incomeTypes->count());
    }

    public function test_a_year_cannot_be_opened_twice(): void
    {
        $this->admin();

        $this->postJson('/api/v1/admin/tax-years', ['year' => 2569])->assertCreated();
        $this->postJson('/api/v1/admin/tax-years', ['year' => 2569])
            ->assertUnprocessable()->assertJsonValidationErrors('year');
    }

    public function test_a_baseline_cloned_into_the_next_year_carries_every_rule(): void
    {
        $this->admin();
        $this->postJson('/api/v1/admin/tax-years', ['year' => 2569, 'copy_forms_from_year' => 2568])->assertCreated();

        $source = TaxRuleVersion::where('version', '2568.3')->sole();
        $draft = $this->postJson("/api/v1/admin/tax-rule-versions/{$source->id}/clone",
            ['version' => '2569.1', 'tax_year' => 2569])->assertCreated()->json('data');

        $this->assertSame('draft', $draft['status']);
        $this->assertSame(2569, $draft['tax_year']);

        // Every rule table the engine reads came across, and belongs to the new year.
        $clone = TaxRuleVersion::find($draft['id']);
        foreach (['brackets', 'expenseRules', 'allowanceRules', 'donationRules', 'recommendationRules'] as $relation) {
            $this->assertSame($source->$relation()->count(), $clone->$relation()->count(),
                "The cloned draft is missing rows in {$relation}.");
        }
        $this->assertSame(2569, (int) $clone->taxYear->year);
        $this->assertSame(0, $clone->brackets()->where('tax_year_id', $source->tax_year_id)->count(),
            'Cloned rules must belong to the destination year, not the source year.');
    }

    public function test_cloning_into_a_year_without_forms_is_refused(): void
    {
        $this->admin();
        $this->postJson('/api/v1/admin/tax-years', ['year' => 2569])->assertCreated();

        $source = TaxRuleVersion::where('version', '2568.3')->sole();

        // A baseline copied into a year that cannot start a simulation would be unreachable.
        $this->postJson("/api/v1/admin/tax-rule-versions/{$source->id}/clone",
            ['version' => '2569.1', 'tax_year' => 2569])
            ->assertUnprocessable()->assertJsonValidationErrors('tax_year');
    }

    /**
     * The whole point: a new year's rates do not reach back into an earlier year.
     */
    public function test_publishing_next_years_rates_leaves_the_previous_year_calculating_identically(): void
    {
        $before = $this->baselineResult();
        $this->admin();

        // Open 2569, carry the baseline over, change a rate, publish.
        $this->postJson('/api/v1/admin/tax-years', ['year' => 2569, 'copy_forms_from_year' => 2568])->assertCreated();
        $source = TaxRuleVersion::where('version', '2568.3')->sole();
        $draftId = $this->postJson("/api/v1/admin/tax-rule-versions/{$source->id}/clone",
            ['version' => '2569.1', 'tax_year' => 2569])->assertCreated()->json('data.id');

        $bracket = TaxRuleVersion::find($draftId)->brackets()->orderByDesc('sort_order')->first();
        $this->patchJson("/api/v1/admin/tax-rule-versions/{$draftId}/tax-brackets/{$bracket->id}",
            ['rate' => 40])->assertOk();

        $this->postJson("/api/v1/admin/tax-rule-versions/{$draftId}/publish", [])->assertOk();

        // 2568 still has exactly one published version, and it is still 2568.1.
        $published = TaxYear::where('year', 2568)->sole()->ruleVersions()->where('status', 'published')->get();
        $this->assertCount(1, $published);
        $this->assertSame('2568.3', $published->sole()->version);

        // And a fresh 2568 calculation returns precisely what it returned before.
        $this->assertSame($before, $this->baselineResult());
    }

    public function test_a_simulation_saved_before_the_change_is_untouched_by_it(): void
    {
        $member = User::factory()->create();
        Sanctum::actingAs($member);
        $id = $this->postJson('/api/v1/tax-returns',
            ['tax_year' => 2568, 'form_code' => 'PND91', 'name' => 'ก่อนเปลี่ยนอัตรา'])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/tax-returns/$id/incomes",
            ['income_type' => 'SECTION_40_1', 'gross_amount' => '720000.00', 'exempt_amount' => '0.00'])->assertCreated();
        $this->postJson("/api/v1/tax-returns/$id/withholdings", ['type' => 'withholding', 'amount' => '25000.00'])->assertCreated();
        $saved = $this->postJson("/api/v1/tax-returns/$id/calculate")->assertOk()->json('data.calculation');
        // The row as it was stored, to compare against after the later year is published.
        $original = TaxCalculation::where('tax_return_id', $id)->sole();
        $originalSnapshot = $original->result_snapshot;

        // A later year opens and publishes different rates.
        $this->admin();
        $this->postJson('/api/v1/admin/tax-years', ['year' => 2569, 'copy_forms_from_year' => 2568])->assertCreated();
        $source = TaxRuleVersion::where('version', '2568.3')->sole();
        $draftId = $this->postJson("/api/v1/admin/tax-rule-versions/{$source->id}/clone",
            ['version' => '2569.1', 'tax_year' => 2569])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/admin/tax-rule-versions/{$draftId}/publish", [])->assertOk();

        Sanctum::actingAs($member);
        $return = TaxReturn::find($id);

        // The saved return still points at the version it was created under.
        $this->assertSame($source->id, $return->rule_version_id);
        // Recalculating it uses that version, not the newest published one anywhere.
        $this->assertSame('2568.3', $this->postJson("/api/v1/tax-returns/$id/calculate")
            ->assertOk()->json('data.calculation.rule_version'));
        // And the snapshot stored before the change was never rewritten. Byte-for-byte against
        // itself; by content against the API response, because MySQL's native JSON type
        // normalises key order while SQLite keeps the order it was given.
        $original->refresh();
        $this->assertSame($originalSnapshot, $original->result_snapshot);
        $this->assertEquals($saved['result'], $original->result_snapshot['result']);
        $this->assertSame($source->id, $original->rule_version_id);
    }

    public function test_a_year_with_published_rules_cannot_be_retired(): void
    {
        $this->admin();
        $year = TaxYear::where('year', 2568)->sole();

        // 2568 carries the published baseline; retiring it would make the product inconsistent
        // about which years it still stands behind.
        $this->deleteJson("/api/v1/admin/tax-years/{$year->id}")
            ->assertUnprocessable()->assertJsonValidationErrors('id');
        $this->assertTrue(TaxYear::find($year->id)->active);
    }

    public function test_retiring_a_year_hides_it_without_changing_anything(): void
    {
        $this->admin();
        $created = $this->postJson('/api/v1/admin/tax-years',
            ['year' => 2570, 'copy_forms_from_year' => 2568])->assertCreated()->json('data');

        $this->deleteJson("/api/v1/admin/tax-years/{$created['id']}")->assertOk();

        $this->assertFalse(TaxYear::find($created['id'])->active);
        // It is still there, with its forms; it is simply no longer offered.
        $this->assertDatabaseHas('tax_years', ['id' => $created['id']]);
        $years = collect($this->getJson('/api/v1/tax-years')->assertOk()->json('data'))->pluck('year');
        $this->assertNotContains(2570, $years);
        $this->assertContains(2568, $years);
    }

    public function test_the_year_itself_can_never_be_edited(): void
    {
        $this->admin();
        $created = $this->postJson('/api/v1/admin/tax-years', ['year' => 2569])->assertCreated()->json('data');

        // Rule versions, forms and saved returns all point at the row; renaming the year would
        // relabel history rather than correct it.
        $this->patchJson("/api/v1/admin/tax-years/{$created['id']}", ['year' => 2571])
            ->assertUnprocessable();
        $this->assertSame(2569, (int) TaxYear::find($created['id'])->year);
    }

    public function test_only_an_administrator_may_manage_tax_years(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/admin/tax-years')->assertForbidden();
        $this->postJson('/api/v1/admin/tax-years', ['year' => 2569])->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use App\Models\TaxRuleVersion;
use App\Models\TaxYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

/**
 * Opening next year's tax year must not break this year's calculator.
 *
 * That was the entire premise of the M9.1 tax-year workflow, and it did not hold. Opening a year
 * creates it active with its forms copied; the rule version is cloned and published **months**
 * later. In between, `TaxMetadataService::years()` listed the new year anyway, the wizard took the
 * newest year in the list, and every metadata request for it answered 409 through
 * `PublishedTaxRuleResolver`.
 *
 * Found in the running development environment: tax year 2569 had been opened, the public
 * simulator rendered an empty first step, and `GET /tax-years/2569/forms/PND91` returned 409 on
 * every attempt. Nothing was wrong with 2568 — it simply was no longer the year being asked for.
 */
class OpenedTaxYearDoesNotBreakTheSimulatorTest extends TestCase
{
    use ActsAsAdmin, RefreshDatabase;

    protected $seed = true;

    /** Exactly what `AdminTaxYearController::store` produces: active, forms copied, no rules. */
    private function openNextYear(): TaxYear
    {
        $this->actingAsAdmin();
        $this->postJson('/api/v1/admin/tax-years',
            ['year' => 2569, 'copy_forms_from_year' => 2568])->assertCreated();
        $this->forgetAuthenticatedUser();

        return TaxYear::where('year', 2569)->sole();
    }

    public function test_the_public_year_list_offers_only_years_that_can_be_calculated(): void
    {
        $this->openNextYear();

        $years = array_column($this->getJson('/api/v1/tax-years')->assertOk()->json('data'), 'year');

        $this->assertContains(2568, $years);
        $this->assertNotContains(2569, $years,
            'a year with no published rule version cannot be simulated and must not be offered');
    }

    public function test_the_simulator_still_works_after_next_year_is_opened(): void
    {
        // The failure the user actually saw: an empty first step, because the wizard took the
        // newest year in the list and every request for it 409'd.
        $this->openNextYear();

        $year = $this->getJson('/api/v1/tax-years')->assertOk()->json('data.0.year');
        $this->getJson("/api/v1/tax-years/$year/forms/PND90")->assertOk();
        $this->getJson("/api/v1/tax-years/$year/forms/PND91")->assertOk();
        $this->getJson("/api/v1/tax-years/$year/allowances")->assertOk();
        $this->getJson("/api/v1/tax-years/$year/tax-brackets")->assertOk();
    }

    public function test_a_calculation_still_runs_after_next_year_is_opened(): void
    {
        $this->openNextYear();

        $this->postJson('/api/v1/tax/calculate', ['tax_year' => 2568, 'form_code' => 'PND91',
            'profile' => ['birth_date' => '1990-01-15', 'marital_status' => 'single'],
            'incomes' => [['income_type' => 'SECTION_40_1', 'gross_amount' => '720000.00', 'exempt_amount' => '0.00']]])
            ->assertOk()->assertJsonPath('data.progressive_tax.total', '36500.00');
    }

    public function test_naming_the_unpublished_year_directly_still_answers_truthfully(): void
    {
        // Hidden from the list is not pretended out of existence: a caller who names it is told
        // why it cannot be used, rather than getting a 404 that suggests it was never opened.
        $this->openNextYear();

        $this->getJson('/api/v1/tax-years/2569/forms/PND91')->assertStatus(409);
        $this->postJson('/api/v1/tax/calculate', ['tax_year' => 2569, 'form_code' => 'PND91',
            'incomes' => [['income_type' => 'SECTION_40_1', 'gross_amount' => '1000.00', 'exempt_amount' => '0.00']]])
            ->assertStatus(409);
    }

    public function test_the_administrator_still_sees_the_year_they_are_preparing(): void
    {
        // Hiding it publicly must not hide it from the person whose job is to publish it.
        $this->openNextYear();
        $this->actingAsAdmin();

        $years = array_column($this->getJson('/api/v1/admin/tax-years')->assertOk()->json('data'), 'year');

        $this->assertContains(2569, $years);
    }

    public function test_the_year_appears_publicly_once_its_rules_are_published(): void
    {
        /*
         * The other half of the promise: the workflow has to finish. Once a rule version is
         * published into the new year, the public list offers it — nothing else has to be done,
         * and no flag has to be remembered.
         */
        $next = $this->openNextYear();
        $this->actingAsAdmin();

        $source = TaxRuleVersion::where('status', 'published')->sole();
        $draft = $this->postJson("/api/v1/admin/tax-rule-versions/{$source->id}/clone",
            ['version' => '2569.1', 'tax_year' => 2569])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/admin/tax-rule-versions/$draft/publish")->assertOk();
        $this->forgetAuthenticatedUser();

        $years = array_column($this->getJson('/api/v1/tax-years')->assertOk()->json('data'), 'year');

        $this->assertContains(2569, $years);
        $this->assertSame(2569, $years[0], 'the newest calculable year leads the list');
        $this->getJson('/api/v1/tax-years/2569/forms/PND91')->assertOk();
        $this->assertSame($next->id, TaxYear::where('year', 2569)->value('id'));
    }
}

<?php

namespace Tests\Feature;

use App\Services\Tax\AllowanceCoverageCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Milestone 09.1 — a client can tell the blocked allowance codes apart, and say why.
 *
 * The catalogue has always carried a specific source-cited reason per code, but it only reached a
 * caller who submitted a positive amount and was refused. A client deciding what to render had
 * only the absence of a rule to go on, so every blocked code looked identical and the wizard
 * explained them all with one generic sentence — under a heading claiming they appear on the form,
 * which for the umbrella categories is not true.
 */
class AllowanceCoverageMetadataTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    /** @return array<string, array<string, mixed>> code => payload */
    private function allowances(): array
    {
        return collect($this->getJson('/api/v1/tax-years/2568/allowances')->assertOk()->json('data'))
            ->keyBy('code')->all();
    }

    public function test_every_allowance_carries_a_coverage_status(): void
    {
        foreach ($this->allowances() as $code => $item) {
            $this->assertArrayHasKey('coverage', $item, "$code has no coverage");
            $this->assertContains($item['coverage']['status'],
                ['SUPPORTED', AllowanceCoverageCatalogue::PARTIAL_BLOCKED, AllowanceCoverageCatalogue::UNSUPPORTED]);
        }
    }

    public function test_a_blocked_code_reports_the_reason_its_own_source_gives(): void
    {
        $allowances = $this->allowances();

        foreach (AllowanceCoverageCatalogue::codes() as $code) {
            $coverage = $allowances[$code]['coverage'];
            $this->assertSame(AllowanceCoverageCatalogue::status($code), $coverage['status']);
            $this->assertSame(AllowanceCoverageCatalogue::errorCode($code), $coverage['reason_code']);
            $this->assertSame(AllowanceCoverageCatalogue::message($code), $coverage['reason']);
        }
    }

    public function test_the_two_blocked_kinds_are_distinguishable(): void
    {
        $allowances = $this->allowances();

        // ใบแนบ prints these lines; this baseline cannot yet calculate them.
        $this->assertSame('PARTIAL_BLOCKED', $allowances['PENSION_INSURANCE']['coverage']['status']);
        $this->assertSame('PARTIAL_BLOCKED', $allowances['SOCIAL_SECURITY']['coverage']['status']);
        // ใบแนบ prints no line for these at all — they are master categories.
        foreach (['INSURANCE', 'OTHER', 'ANNUAL_TAX_MEASURES'] as $code) {
            $this->assertSame('UNSUPPORTED', $allowances[$code]['coverage']['status']);
        }
    }

    /**
     * `status` answers "can the engine calculate this?"; `printed_on_form` answers "does ใบแนบ
     * print this line at all?". Those are different questions, and conflating them hid four
     * printed deduction lines — ข้อ 13, 16, 20 and 22 — from the reader entirely, while showing
     * three umbrella categories the attachment never prints.
     */
    public function test_printed_on_form_is_independent_of_calculability(): void
    {
        $allowances = $this->allowances();

        foreach (['CCTV_SYSTEM', 'SOCIAL_ENTERPRISE_INVESTMENT', 'NEW_HOME_CONSTRUCTION',
            'DOMESTIC_TRAVEL'] as $code) {
            $this->assertArrayHasKey($code, $allowances, "$code is a line ใบแนบ prints and must exist");
            $this->assertSame('UNSUPPORTED', $allowances[$code]['coverage']['status']);
            $this->assertTrue($allowances[$code]['coverage']['printed_on_form'],
                "$code is printed on ใบแนบ, so the reader must be told it exists");
            $this->assertNotNull($allowances[$code]['coverage']['reason']);
        }

        foreach (['INSURANCE', 'OTHER', 'ANNUAL_TAX_MEASURES'] as $code) {
            $this->assertFalse($allowances[$code]['coverage']['printed_on_form'],
                "$code is an umbrella category ใบแนบ never prints as a line");
        }
    }

    /** Every printed line the engine cannot calculate cites its own source, not a shared sentence. */
    public function test_each_printed_blocked_line_states_its_own_source(): void
    {
        $reasons = collect($this->allowances())
            ->filter(fn (array $item): bool => $item['coverage']['status'] !== 'SUPPORTED'
                && $item['coverage']['printed_on_form'])
            ->pluck('coverage.reason');

        $this->assertGreaterThanOrEqual(6, $reasons->count());
        $this->assertCount($reasons->count(), $reasons->unique(),
            'Two printed lines blocked for different documented reasons must not share one sentence.');
    }

    public function test_the_reasons_differ_from_each_other(): void
    {
        $allowances = $this->allowances();

        $this->assertNotSame($allowances['PENSION_INSURANCE']['coverage']['reason'],
            $allowances['SOCIAL_SECURITY']['coverage']['reason'],
            'Two codes blocked for different documented reasons must not share one sentence.');
    }

    public function test_a_calculable_code_is_supported_and_carries_no_reason(): void
    {
        $coverage = $this->allowances()['PROVIDENT_FUND']['coverage'];

        $this->assertSame(['status' => 'SUPPORTED', 'reason_code' => null, 'reason' => null,
            'printed_on_form' => true], $coverage);
    }

    public function test_a_family_derived_code_is_not_reported_as_blocked(): void
    {
        // These carry no allowance rule row, so a client that judged coverage by the rule's
        // absence would have called them unsupported. They are the opposite: fully derived.
        foreach (['PERSONAL', 'SPOUSE', 'CHILD', 'PARENT', 'DISABLED_PERSON'] as $code) {
            $this->assertSame('SUPPORTED', $this->allowances()[$code]['coverage']['status']);
        }
    }

    public function test_the_single_allowance_endpoint_reports_coverage_too(): void
    {
        $this->getJson('/api/v1/tax-years/2568/allowances/SOCIAL_SECURITY')->assertOk()
            ->assertJsonPath('data.coverage.reason_code', 'SOCIAL_SECURITY_RULE_UNSUPPORTED')
            ->assertJsonPath('data.coverage.status', 'PARTIAL_BLOCKED');
    }

    public function test_the_wizard_lists_only_printed_lines_as_unsupported(): void
    {
        $wizard = file_get_contents(resource_path('js/simulator-wizard.js'));

        // The heading promises the reader these appear on the form, so the list must be built from
        // `printed_on_form` — never from "has no rule", which also catches the umbrella categories
        // ใบแนบ never prints, and never from PARTIAL_BLOCKED alone, which hid the four printed
        // lines (ข้อ 13, 16, 20, 22) the engine cannot calculate at all.
        $this->assertStringContainsString('item.coverage?.printed_on_form', $wizard);
        $this->assertStringContainsString("item.coverage?.status !== 'SUPPORTED'", $wizard);
        $this->assertStringNotContainsString('!item.rule && !derivedAllowanceCodes.has(item.code)', $wizard);
        // Each card shows its own reason rather than one sentence standing in for every cause.
        $this->assertStringContainsString('data-coverage-reason', $wizard);
        // The suffix below is what made the old sentence an *allowance* card; the same generic
        // wording without it still belongs to the unrelated expense-rule notice.
        $this->assertStringNotContainsString('จึงไม่มีช่องกรอกจำนวนเงิน', $wizard);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Milestone 09.1 — the ใบแนบ family lines are claimed from the facts the filer declared.
 *
 * Before this, a family line was applied only when the caller named its code, and nothing in the
 * product ever named one: the allowance step tells the reader these lines are derived for them
 * and offers no control to ask with. A married filer with a child entered their family and
 * received nothing for it — 150,000 of deductions silently missing, and no warning to say so.
 *
 * What changed is only *whether* a line is claimed. Every amount is still derived by the strategy
 * that owns it from the printed instructions, a caller-supplied number is still discarded, and a
 * filer who does not qualify still receives that strategy's zero.
 */
class FamilyAllowanceClaimingTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    /** @param array<string, mixed> $overrides */
    private function calculate(array $overrides = []): TestResponse
    {
        return $this->postJson('/api/v1/tax/calculate', ['tax_year' => 2568, 'form_code' => 'PND91',
            'incomes' => [['income_type' => 'SECTION_40_1', 'gross_amount' => '720000.00', 'exempt_amount' => '0.00']],
            ...$overrides]);
    }

    /** @return array<string, string> code => eligible amount */
    private function lines(TestResponse $response): array
    {
        return collect($response->json('data.allowances.items'))
            ->mapWithKeys(fn (array $item): array => [$item['code'] => $item['eligible_amount']])->all();
    }

    public function test_declaring_a_profile_claims_the_personal_line(): void
    {
        $response = $this->calculate(['profile' => ['birth_date' => '1990-01-15', 'marital_status' => 'single']])->assertOk();

        // ใบแนบ item 1 — ผู้มีเงินได้ 60,000 บาท, with no condition beyond being a filer.
        $this->assertSame(['PERSONAL' => '60000.00'], $this->lines($response));
        $this->assertSame('60000.00', $response->json('data.allowances.total_eligible'));
        $this->assertSame('560000.00', $response->json('data.net_income'));
    }

    public function test_the_facts_a_filer_declares_decide_which_lines_are_claimed(): void
    {
        $response = $this->calculate([
            'profile' => ['birth_date' => '1985-09-02', 'marital_status' => 'married'],
            'spouse' => ['has_income' => false],
            'dependents' => [
                ['relation_type' => 'child', 'child_type' => 'legitimate', 'birth_order' => 1,
                    'birth_date' => '2015-06-10', 'eligible' => true],
                ['relation_type' => 'mother', 'eligible' => true],
            ],
        ])->assertOk();

        $lines = $this->lines($response);
        $this->assertSame('60000.00', $lines['PERSONAL']);
        $this->assertSame('60000.00', $lines['SPOUSE']);
        $this->assertSame('30000.00', $lines['CHILD']);
        $this->assertSame('30000.00', $lines['PARENT']);
        // No disabled person was declared, so that line is not claimed at all.
        $this->assertArrayNotHasKey('DISABLED_PERSON', $lines);

        $this->assertSame('180000.00', $response->json('data.allowances.total_eligible'));
    }

    public function test_a_payload_with_no_family_facts_claims_nothing(): void
    {
        // The regression baseline carried since M4: 720,000 less the 100,000 expense ceiling.
        $response = $this->calculate()->assertOk();

        $this->assertSame([], $this->lines($response));
        $this->assertSame('620000.00', $response->json('data.net_income'));
    }

    public function test_a_filer_who_does_not_qualify_receives_the_strategy_zero(): void
    {
        // A spouse with income of their own: the line is claimed because a spouse was declared,
        // and the strategy answers zero because ใบแนบ item 2.2 does not allow it.
        $response = $this->calculate([
            'profile' => ['birth_date' => '1985-09-02', 'marital_status' => 'married'],
            'spouse' => ['has_income' => true],
        ])->assertOk();

        $lines = $this->lines($response);
        $this->assertSame('0.00', $lines['SPOUSE']);
        $this->assertSame('60000.00', $lines['PERSONAL']);
    }

    public function test_an_automatic_claim_does_not_warn_about_a_discarded_amount(): void
    {
        // FAMILY_ALLOWANCE_DERIVED tells a caller their number was ignored. A line claimed on the
        // filer's behalf carries no number to ignore, so the warning would be about nothing.
        $warnings = array_column($this->calculate([
            'profile' => ['birth_date' => '1990-01-15', 'marital_status' => 'single'],
        ])->assertOk()->json('data.warnings'), 'code');

        $this->assertNotContains('FAMILY_ALLOWANCE_DERIVED', $warnings);
    }

    public function test_a_declared_amount_is_still_discarded_and_still_warns(): void
    {
        $response = $this->calculate([
            'profile' => ['birth_date' => '1990-01-15', 'marital_status' => 'single'],
            'allowances' => [['code' => 'PERSONAL', 'amount' => '999999.00']],
        ])->assertOk();

        $this->assertSame('60000.00', $this->lines($response)['PERSONAL']);
        $this->assertContains('FAMILY_ALLOWANCE_DERIVED', array_column($response->json('data.warnings'), 'code'));
    }

    public function test_a_declared_line_is_not_claimed_twice(): void
    {
        $response = $this->calculate([
            'profile' => ['birth_date' => '1990-01-15', 'marital_status' => 'single'],
            'allowances' => [['code' => 'PERSONAL', 'amount' => '0.00']],
        ])->assertOk();

        $this->assertCount(1, $response->json('data.allowances.items'));
        $this->assertSame('60000.00', $response->json('data.allowances.total_eligible'));
    }

    public function test_a_member_simulation_claims_the_same_lines_as_a_guest(): void
    {
        // Member and Guest build their payloads separately, so the claim has to live where both
        // reach it. This is the case that would have caught a browser-only fix.
        $member = User::factory()->create();
        Sanctum::actingAs($member);

        $id = $this->postJson('/api/v1/tax-returns',
            ['tax_year' => 2568, 'form_code' => 'PND91', 'name' => 'ครอบครัว'])->assertCreated()->json('data.id');
        $this->putJson("/api/v1/tax-returns/$id/profile",
            ['birth_date' => '1985-09-02', 'marital_status' => 'married'])->assertOk();
        $this->putJson("/api/v1/tax-returns/$id/spouse", ['has_income' => false, 'birth_date' => '1987-01-20'])->assertOk();
        $this->postJson("/api/v1/tax-returns/$id/incomes",
            ['income_type' => 'SECTION_40_1', 'gross_amount' => '720000.00', 'exempt_amount' => '0.00'])->assertCreated();

        $calculation = $this->postJson("/api/v1/tax-returns/$id/calculate")->assertOk()->json('data.calculation');

        $this->assertSame('120000.00', $calculation['allowances']['total_eligible']);
        $this->assertSame('500000.00', $calculation['net_income']);
    }
}

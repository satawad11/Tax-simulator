<?php

namespace Tests\Feature;

use App\Models\AllowanceCapGroup;
use App\Models\TaxRuleVersion;
use App\Services\Tax\CombinedAllowanceCapResolver;
use App\ValueObjects\Money;
use Database\Seeders\AllowanceCapGroupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Milestone 07.4 — the percentage-base, combined-cap and tiered models, end to end.
 *
 * Sources: docs/tax-source/PND90-2568-filing-instructions.pdf, ใบแนบ items 7.4, 9, 10.4, 17,
 * 18.1, 19.1 and 19.2 for the allowance side; page 3 and page 17 ตารางที่ 2 for the expense side.
 */
class AllowanceCapEngineTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seeded as part of the refresh rather than in setUp(): this class sorts first in the
     * suite, so it is the one that performs the migration, and a seed applied inside the
     * per-test transaction would roll back and leave later classes without a baseline.
     */
    protected $seed = true;

    /**
     * Salary of 1,000,000 deducts 100,000, so every percentage base is a round number:
     * gross 1,000,000, after exemption 1,000,000, after expense 900,000.
     */
    private function calculate(array $allowances, string $gross = '1000000.00'): TestResponse
    {
        return $this->postJson('/api/v1/tax/calculate', ['tax_year' => 2568, 'form_code' => 'PND91',
            'incomes' => [['income_type' => 'SECTION_40_1', 'gross_amount' => $gross]],
            'allowances' => $allowances, 'donations' => [], 'withholdings' => []]);
    }

    /**
     * Rewrites a seeded cap group of the published 2568.1 version, to exercise a resolver
     * branch no 2568 group actually takes.
     *
     * The query builder is used deliberately: M8 gave AllowanceCapGroup the same
     * ProtectsPublishedRules guard the other rule models have, so the model now refuses this
     * write — which is the point of the guard, and is asserted by the M8 suite. A fixture that
     * has to reach past it says so out loud rather than quietly weakening the guard.
     *
     * @param  array<string, mixed>  $values
     */
    private function rewriteGroup(string $code, array $values): void
    {
        DB::table('allowance_cap_groups')->where('code', $code)->update([...$values, 'updated_at' => now()]);
    }

    // ------------------------------------------------- percentage base

    public function test_a_percentage_allowance_reports_its_base_and_every_step_of_its_arithmetic(): void
    {
        // ใบแนบ item 10.4 — ร้อยละ 30 ของเงินได้พึงประเมินที่ได้รับซึ่งต้องเสียภาษีเงินได้,
        // เฉพาะส่วนที่ไม่เกิน 500,000. 30% of 1,000,000 is 300,000, and 250,000 was paid.
        $this->calculate([['code' => 'RMF', 'amount' => '250000.00']])->assertOk()
            ->assertJsonPath('data.allowances.items.0.rule_status', 'VERIFIED')
            ->assertJsonPath('data.allowances.items.0.method', 'percentage_limit')
            ->assertJsonPath('data.allowances.items.0.percentage', '30.0000')
            ->assertJsonPath('data.allowances.items.0.percentage_base', 'GROSS_AFTER_EXEMPTION')
            ->assertJsonPath('data.allowances.items.0.base_amount', '1000000.00')
            ->assertJsonPath('data.allowances.items.0.calculated_before_cap', '250000.00')
            ->assertJsonPath('data.allowances.items.0.maximum_amount', '500000.00')
            ->assertJsonPath('data.allowances.items.0.eligible_amount', '250000.00');
    }

    /** @return array<string, array{string, string, string, string}> */
    public static function percentageBoundaries(): array
    {
        // Gross 1,000,000; the 30% rate ceiling is 300,000 and the printed ceiling 500,000.
        return [
            'below the rate ceiling' => ['299999.99', '299999.99', '299999.99', '1000000.00'],
            'exactly at the rate ceiling' => ['300000.00', '300000.00', '300000.00', '1000000.00'],
            'above the rate ceiling' => ['400000.00', '300000.00', '300000.00', '1000000.00'],
            'nothing paid' => ['0.00', '0.00', '0.00', '1000000.00'],
        ];
    }

    #[DataProvider('percentageBoundaries')]
    public function test_the_rate_ceiling_binds_before_the_printed_ceiling(string $paid, string $beforeCap,
        string $eligible, string $base): void
    {
        $this->calculate([['code' => 'RMF', 'amount' => $paid]])->assertOk()
            ->assertJsonPath('data.allowances.items.0.base_amount', $base)
            ->assertJsonPath('data.allowances.items.0.calculated_before_cap', $beforeCap)
            ->assertJsonPath('data.allowances.items.0.eligible_amount', $eligible);
    }

    public function test_the_printed_ceiling_binds_when_the_rate_ceiling_is_higher(): void
    {
        // Gross 5,000,000: 30% is 1,500,000, so the printed 500,000 is what applies.
        $this->calculate([['code' => 'RMF', 'amount' => '900000.00']], '5000000.00')->assertOk()
            ->assertJsonPath('data.allowances.items.0.base_amount', '5000000.00')
            ->assertJsonPath('data.allowances.items.0.calculated_before_cap', '900000.00')
            ->assertJsonPath('data.allowances.items.0.eligible_amount', '500000.00');
    }

    public function test_a_percentage_rule_whose_base_is_unknown_is_reported_not_guessed(): void
    {
        DB::table('allowance_rules')->where('code', 'PND90_2568_RMF')
            ->update(['percentage_base' => 'NET_INCOME_BEFORE_DONATION']);

        $response = $this->calculate([['code' => 'RMF', 'amount' => '100000.00']])->assertOk()
            ->assertJsonPath('data.allowances.items.0.rule_status', 'UNVERIFIED')
            ->assertJsonPath('data.allowances.total_eligible', '0.00');
        $this->assertContains('UNVERIFIED_ALLOWANCE_RULE', array_column($response->json('data.warnings'), 'code'));
    }

    // ------------------------------------------------- combined caps

    public function test_the_life_and_health_group_caps_its_members_together(): void
    {
        // ใบแนบ item 7.4 — 100,000 shared. Individually 100,000 + 25,000 would be 125,000.
        $response = $this->calculate([
            ['code' => 'LIFE_INSURANCE', 'amount' => '100000.00'],
            ['code' => 'HEALTH_INSURANCE', 'amount' => '40000.00'],
        ])->assertOk()
            ->assertJsonPath('data.allowances.items.0.eligible_amount', '100000.00')
            ->assertJsonPath('data.allowances.items.1.eligible_amount', '25000.00')
            ->assertJsonPath('data.allowances.total_eligible', '100000.00');

        $group = collect($response->json('data.allowances.combined_cap_groups'))
            ->firstWhere('code', 'LIFE_AND_HEALTH_INSURANCE_2568');
        $this->assertSame(['LIFE_INSURANCE', 'HEALTH_INSURANCE'], $group['member_codes']);
        $this->assertSame('125000.00', $group['pre_cap_total']);
        $this->assertSame('100000.00', $group['maximum_amount']);
        $this->assertSame('100000.00', $group['eligible_total']);
        $this->assertContains('COMBINED_ALLOWANCE_CAP_APPLIED', array_column($response->json('data.warnings'), 'code'));
    }

    public function test_a_group_under_its_ceiling_changes_nothing_and_warns_about_nothing(): void
    {
        $response = $this->calculate([
            ['code' => 'LIFE_INSURANCE', 'amount' => '60000.00'],
            ['code' => 'HEALTH_INSURANCE', 'amount' => '20000.00'],
        ])->assertOk()->assertJsonPath('data.allowances.total_eligible', '80000.00');

        $group = collect($response->json('data.allowances.combined_cap_groups'))
            ->firstWhere('code', 'LIFE_AND_HEALTH_INSURANCE_2568');
        $this->assertSame('80000.00', $group['pre_cap_total']);
        $this->assertSame('80000.00', $group['eligible_total']);
        $this->assertNotContains('COMBINED_ALLOWANCE_CAP_APPLIED', array_column($response->json('data.warnings'), 'code'));
    }

    public function test_the_retirement_group_caps_across_three_codes(): void
    {
        // ใบแนบ items 9 and 10.4 — one 500,000 basket. Individually the three lines would be
        // 10,000 + 500,000 + 300,000 = 810,000.
        $response = $this->calculate([
            ['code' => 'PROVIDENT_FUND', 'amount' => '90000.00'],
            ['code' => 'NSF', 'amount' => '600000.00'],
            ['code' => 'RMF', 'amount' => '300000.00'],
        ])->assertOk()
            ->assertJsonPath('data.allowances.items.0.eligible_amount', '10000.00')
            ->assertJsonPath('data.allowances.items.1.eligible_amount', '500000.00')
            ->assertJsonPath('data.allowances.items.2.eligible_amount', '300000.00')
            ->assertJsonPath('data.allowances.total_eligible', '500000.00');

        $group = collect($response->json('data.allowances.combined_cap_groups'))
            ->firstWhere('code', 'RETIREMENT_SAVINGS_2568');
        $this->assertSame('810000.00', $group['pre_cap_total']);
        $this->assertSame('500000.00', $group['eligible_total']);
        // The presentation split always adds back to exactly the group total.
        $allocated = array_column($response->json('data.allowances.items'), 'allocated_amount');
        $this->assertSame('500000.00', (string) array_reduce($allocated,
            fn (Money $carry, string $share): Money => $carry->add(new Money($share)), new Money));
    }

    public function test_the_easy_e_receipt_bands_are_capped_individually_then_together(): void
    {
        // ใบแนบ item 17 — 30,000 + 20,000, together not exceeding 50,000.
        $this->calculate([
            ['code' => 'EASY_E_RECEIPT', 'amount' => '45000.00'],
            ['code' => 'EASY_E_RECEIPT_OTOP', 'amount' => '45000.00'],
        ])->assertOk()
            ->assertJsonPath('data.allowances.items.0.eligible_amount', '30000.00')
            ->assertJsonPath('data.allowances.items.1.eligible_amount', '20000.00')
            ->assertJsonPath('data.allowances.total_eligible', '50000.00');
    }

    public function test_a_group_total_does_not_depend_on_the_order_its_members_arrive_in(): void
    {
        $forward = $this->calculate([
            ['code' => 'LIFE_INSURANCE', 'amount' => '90000.00'],
            ['code' => 'HEALTH_INSURANCE', 'amount' => '25000.00'],
        ])->assertOk()->json('data.allowances.total_eligible');
        $reverse = $this->calculate([
            ['code' => 'HEALTH_INSURANCE', 'amount' => '25000.00'],
            ['code' => 'LIFE_INSURANCE', 'amount' => '90000.00'],
        ])->assertOk()->json('data.allowances.total_eligible');

        $this->assertSame('100000.00', $forward);
        $this->assertSame($forward, $reverse);
    }

    public function test_one_member_alone_is_still_capped_by_its_own_rule_only(): void
    {
        $this->calculate([['code' => 'HEALTH_INSURANCE', 'amount' => '99999.00']])->assertOk()
            ->assertJsonPath('data.allowances.items.0.eligible_amount', '25000.00')
            ->assertJsonPath('data.allowances.total_eligible', '25000.00');
    }

    public function test_a_group_without_a_ceiling_reports_its_total_and_caps_nothing(): void
    {
        $this->rewriteGroup('LIFE_AND_HEALTH_INSURANCE_2568', ['maximum_amount' => null]);

        $response = $this->calculate([
            ['code' => 'LIFE_INSURANCE', 'amount' => '100000.00'],
            ['code' => 'HEALTH_INSURANCE', 'amount' => '25000.00'],
        ])->assertOk()->assertJsonPath('data.allowances.total_eligible', '125000.00');
        $this->assertNotContains('COMBINED_ALLOWANCE_CAP_APPLIED', array_column($response->json('data.warnings'), 'code'));
    }

    public function test_an_inactive_group_is_ignored(): void
    {
        $this->rewriteGroup('LIFE_AND_HEALTH_INSURANCE_2568', ['active' => false]);

        $this->calculate([
            ['code' => 'LIFE_INSURANCE', 'amount' => '100000.00'],
            ['code' => 'HEALTH_INSURANCE', 'amount' => '25000.00'],
        ])->assertOk()->assertJsonPath('data.allowances.total_eligible', '125000.00')
            ->assertJsonCount(0, 'data.allowances.combined_cap_groups');
    }

    public function test_the_resolver_leaves_an_unrelated_item_untouched(): void
    {
        $version = TaxRuleVersion::where('version', '2568.3')->firstOrFail();
        $items = [
            ['code' => 'HOME_LOAN_INTEREST', 'eligible_amount' => new Money('100000')],
            ['code' => 'LIFE_INSURANCE', 'eligible_amount' => new Money('100000')],
            ['code' => 'HEALTH_INSURANCE', 'eligible_amount' => new Money('25000')],
        ];
        $result = (new CombinedAllowanceCapResolver)->apply($items, $version, new Money('225000'));

        $this->assertSame('200000.00', (string) $result['total_eligible']);
        $this->assertNull($result['items'][0]['combined_cap_group'] ?? null);
        $this->assertSame('LIFE_AND_HEALTH_INSURANCE_2568', $result['items'][1]['combined_cap_group']);
        $this->assertCount(1, $result['groups']);
    }

    // ------------------------------------------------- seeded data

    public function test_the_seeded_groups_cite_the_filing_instructions_and_hold_their_members(): void
    {
        $groups = AllowanceCapGroup::with('allowanceTypes')->orderBy('code')->get()->keyBy('code');

        $this->assertSame(['EASY_E_RECEIPT_2568', 'LIFE_AND_HEALTH_INSURANCE_2568', 'RETIREMENT_SAVINGS_2568'],
            $groups->keys()->all());
        $this->assertEquals('100000.00', $groups['LIFE_AND_HEALTH_INSURANCE_2568']->maximum_amount);
        $this->assertEquals('500000.00', $groups['RETIREMENT_SAVINGS_2568']->maximum_amount);
        $this->assertEquals('50000.00', $groups['EASY_E_RECEIPT_2568']->maximum_amount);
        $this->assertSame(['PROVIDENT_FUND', 'NSF', 'RMF'],
            $groups['RETIREMENT_SAVINGS_2568']->allowanceTypes->pluck('code')->all());
        foreach ($groups as $group) {
            $this->assertStringContainsString('PND90-2568-filing-instructions.pdf', (string) $group->source_reference);
        }
    }

    public function test_cap_group_seeding_is_repeatable_and_refuses_a_differing_ceiling(): void
    {
        $before = DB::table('allowance_cap_groups')->orderBy('id')->get()->toJson();
        $members = DB::table('allowance_cap_group_members')->orderBy('id')->get()->toJson();
        $this->seed(AllowanceCapGroupSeeder::class);
        $this->assertSame($before, DB::table('allowance_cap_groups')->orderBy('id')->get()->toJson());
        $this->assertSame($members, DB::table('allowance_cap_group_members')->orderBy('id')->get()->toJson());

        DB::table('allowance_cap_groups')->where('code', 'RETIREMENT_SAVINGS_2568')->update(['maximum_amount' => '9.00']);
        // Caught rather than expected, so the test method completes and this case's transaction
        // unwinds cleanly for whatever runs next.
        try {
            $this->seed(AllowanceCapGroupSeeder::class);
            $this->fail('Seeding must refuse to overwrite a differing ceiling.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('refusing to overwrite', $exception->getMessage());
        }
    }

    // ------------------------------------------------- planning and parity

    public function test_planning_prices_a_newly_verified_percentage_allowance_through_the_shared_engine(): void
    {
        $base = ['incomes' => [['income_type' => 'SECTION_40_1', 'gross_amount' => '1000000.00']],
            'allowances' => [], 'donations' => [], 'withholdings' => []];

        // Net falls from 900,000 to 600,000, i.e. out of the 20% band into the 15% band.
        $this->postJson('/api/v1/tax/plan', ['tax_year' => 2568, 'form_code' => 'PND91', 'base' => $base,
            'scenario' => ['allowances' => ['upsert' => [['code' => 'RMF', 'input_amount' => '300000.00']]]]])
            ->assertOk()->assertJsonPath('data.before.calculated_tax', '95000.00')
            ->assertJsonPath('data.after.calculated_tax', '42500.00')
            ->assertJsonPath('data.estimated_tax_saving', '52500.00');

        $this->assertDatabaseCount('tax_calculations', 0);
    }
}

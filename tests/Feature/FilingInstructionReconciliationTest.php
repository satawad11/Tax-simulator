<?php

namespace Tests\Feature;

use App\Models\TaxCalculation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Milestone 07.3 — the rules read from the 2568 filing-instruction booklets.
 *
 * Sources: docs/tax-source/PND90-2568-filing-instructions.pdf (18 pages) and
 *          docs/tax-source/PND91-2568-filing-instructions.pdf (15 pages).
 *
 * Until M7.3 these rules were blocked: the repository held the blank forms only, whose ใบแนบ
 * prints an amount on four of twenty-three lines and explains none of them. The booklets
 * explain every line, which settles the ผู้มีเงินได้ 60,000/120,000 question, the คู่สมรส
 * condition, the บุตร ordering, the บิดามารดา conditions and the base of the 0.5% minimum tax.
 */
class FilingInstructionReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /** @return array<string, mixed> */
    private function payload(string $form = 'PND91'): array
    {
        return ['tax_year' => 2568, 'form_code' => $form,
            'incomes' => [['income_type' => 'SECTION_40_1', 'gross_amount' => '720000.00']],
            'allowances' => [], 'donations' => [], 'withholdings' => []];
    }

    private function calculate(array $overrides, string $form = 'PND91'): TestResponse
    {
        return $this->postJson('/api/v1/tax/calculate', [...$this->payload($form), ...$overrides]);
    }

    // ------------------------------------------------- ใบแนบ item 1 — ผู้มีเงินได้

    public function test_the_personal_allowance_is_sixty_thousand_whatever_the_client_sends(): void
    {
        // page 7, item 1.1 — 120,000 belongs to a ห้างหุ้นส่วนสามัญ/คณะบุคคลที่มิใช่นิติบุคคล with
        // two or more members resident in Thailand, never to an individual filer.
        $response = $this->calculate(['allowances' => [['code' => 'PERSONAL', 'amount' => '999999.00']]])->assertOk()
            ->assertJsonPath('data.allowances.items.0.rule_status', 'DERIVED')
            ->assertJsonPath('data.allowances.items.0.input_amount', '999999.00')
            ->assertJsonPath('data.allowances.items.0.eligible_amount', '60000.00')
            ->assertJsonPath('data.allowances.total_eligible', '60000.00')
            ->assertJsonPath('data.net_income', '560000.00')
            ->assertJsonPath('data.progressive_tax.total', '36500.00');

        $this->assertContains('FAMILY_ALLOWANCE_DERIVED', array_column($response->json('data.warnings'), 'code'));
        $this->assertNotContains('UNVERIFIED_ALLOWANCE_RULE', array_column($response->json('data.warnings'), 'code'));
    }

    // ------------------------------------------------- ใบแนบ item 2 — คู่สมรส

    /** @return array<string, array{array<string, mixed>|null, string|null, string}> */
    public static function spouseCases(): array
    {
        return [
            // page 7, item 2.1 — คู่สมรสไม่มีเงินได้ … หักลดหย่อนคู่สมรส 60,000 บาท
            'married and spouse has no income' => [['has_income' => false], 'married', '60000.00'],
            // item 2.2 — คู่สมรสมีเงินได้ทั้ง 2 ฝ่าย … จึงไม่มีสิทธิหักลดหย่อนคู่สมรส
            'married and spouse has income' => [['has_income' => true], 'married', '0.00'],
            'married without a spouse block' => [null, 'married', '0.00'],
        ];
    }

    /**
     * Declaring a spouse while stating any other marital status is now an input error.
     *
     * The printed condition is unchanged — there is no spouse allowance unless the taxpayer is
     * married — but M9.2 stopped computing a zero for a contradictory pair of facts and reports
     * the contradiction instead, so the reader corrects the status rather than believing a
     * result derived from it.
     */
    public function test_a_spouse_block_requires_a_married_status(): void
    {
        $this->calculate([
            'profile' => ['marital_status' => 'single'],
            'spouse' => ['has_income' => false],
            'allowances' => [['code' => 'SPOUSE', 'amount' => '60000.00']],
        ])->assertUnprocessable()->assertJsonValidationErrors('profile.marital_status');
    }

    #[DataProvider('spouseCases')]
    public function test_the_spouse_allowance_follows_the_printed_condition(?array $spouse, ?string $status, string $expected): void
    {
        $this->calculate([
            'profile' => ['marital_status' => $status],
            ...($spouse === null ? [] : ['spouse' => $spouse]),
            'allowances' => [['code' => 'SPOUSE', 'amount' => '60000.00']],
        ])->assertOk()->assertJsonPath('data.allowances.items.0.eligible_amount', $expected)
            ->assertJsonPath('data.allowances.items.0.rule_status', 'DERIVED');
    }

    // ------------------------------------------------- ใบแนบ item 3 — บุตร

    public function test_a_second_legitimate_child_born_from_2561_adds_a_second_thirty_thousand(): void
    {
        // page 7, item 3.1 — คนละ 30,000 บาท และสำหรับบุตรชอบด้วยกฎหมายตั้งแต่คนที่สองเป็นต้นไป
        // ที่เกิดในหรือหลังปี พ.ศ. 2561 ให้หักลดหย่อนได้เพิ่มอีกคนละ 30,000 บาท
        $this->calculate([
            'dependents' => [
                ['relation_type' => 'child', 'child_type' => 'legitimate', 'birth_order' => 1, 'birth_date' => '2015-03-01', 'eligible' => true],
                ['relation_type' => 'child', 'child_type' => 'legitimate', 'birth_order' => 2, 'birth_date' => '2019-07-09', 'eligible' => true],
            ],
            'allowances' => [['code' => 'CHILD', 'amount' => '0.00']],
        ])->assertOk()->assertJsonPath('data.allowances.total_eligible', '90000.00');
    }

    public function test_a_second_child_born_before_2561_gets_the_base_amount_only(): void
    {
        // 2017 CE is พ.ศ. 2560, one year short of the printed cut-off.
        $this->calculate([
            'dependents' => [
                ['relation_type' => 'child', 'child_type' => 'legitimate', 'birth_order' => 2, 'birth_date' => '2017-12-31', 'eligible' => true],
            ],
            'allowances' => [['code' => 'CHILD', 'amount' => '0.00']],
        ])->assertOk()->assertJsonPath('data.allowances.total_eligible', '30000.00');
    }

    public function test_an_ineligible_child_is_not_counted(): void
    {
        $this->calculate([
            'dependents' => [
                ['relation_type' => 'child', 'child_type' => 'legitimate', 'birth_order' => 1, 'birth_date' => '2015-03-01', 'eligible' => false],
            ],
            'allowances' => [['code' => 'CHILD', 'amount' => '0.00']],
        ])->assertOk()->assertJsonPath('data.allowances.total_eligible', '0.00');
    }

    public function test_adopted_children_fill_only_the_remainder_of_three(): void
    {
        // page 7, item 3.3 — ให้นำบุตรตาม 3.1 ทั้งหมดมาหักก่อน … เมื่อรวมกับบุตรตาม 3.1
        // แล้วต้องไม่เกินสามคน
        $dependents = [];
        foreach ([1, 2, 3] as $order) {
            $dependents[] = ['relation_type' => 'child', 'child_type' => 'legitimate',
                'birth_order' => $order, 'birth_date' => '2010-01-0'.$order, 'eligible' => true];
        }
        $dependents[] = ['relation_type' => 'child', 'child_type' => 'adopted', 'eligible' => true];

        $response = $this->calculate(['dependents' => $dependents,
            'allowances' => [['code' => 'CHILD', 'amount' => '0.00']]])->assertOk()
            ->assertJsonPath('data.allowances.total_eligible', '90000.00');

        $this->assertContains('ADOPTED_CHILD_LIMIT_APPLIED', array_column($response->json('data.warnings'), 'code'));
    }

    /**
     * An under-specified child is refused rather than partly counted.
     *
     * Milestone 07.3 computed what it could and attached a warning (CHILD_TYPE_NOT_DECLARED,
     * CHILD_BIRTH_ORDER_NOT_DECLARED). M9.2 moved that judgement earlier: ใบแนบ item 3 needs the
     * child's type, and for a legitimate child its order and birth date, before any amount can
     * be claimed, so the missing fact is reported as an input error and nothing is calculated
     * from it. The allowance amounts themselves did not change — see the cases above, which
     * still assert 30,000 and the second-child addition from complete data.
     */
    public function test_a_child_without_a_declared_type_is_refused(): void
    {
        $this->calculate([
            'dependents' => [['relation_type' => 'child', 'eligible' => true]],
            'allowances' => [['code' => 'CHILD', 'amount' => '0.00']],
        ])->assertUnprocessable()->assertJsonValidationErrors('dependents.0.child_type');
    }

    public function test_a_legitimate_child_needs_its_order_and_birth_date(): void
    {
        $this->calculate([
            'dependents' => [['relation_type' => 'child', 'child_type' => 'legitimate', 'eligible' => true]],
            'allowances' => [['code' => 'CHILD', 'amount' => '0.00']],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['dependents.0.birth_order', 'dependents.0.birth_date']);
    }

    // ------------------------------------------------- ใบแนบ item 4 — บิดามารดา

    public function test_each_parent_is_thirty_thousand_and_spouse_parents_need_a_spouse_without_income(): void
    {
        // page 8, item 4.3 — หักลดหย่อนบิดามารดาของผู้มีเงินได้คนละ 30,000 บาท และหักลดหย่อน
        // ได้สำหรับบิดามารดาของคู่สมรสที่ไม่มีเงินได้อีกคนละ 30,000 บาท
        $dependents = [
            ['relation_type' => 'father', 'eligible' => true],
            ['relation_type' => 'mother', 'eligible' => true],
            ['relation_type' => 'spouse_mother', 'eligible' => true],
        ];
        // The ใบแนบ item 4 line itself, not the grand total: M9.1 also claims the personal and
        // spouse lines these same facts entitle, and this case is about the parent amount.
        $parentLine = fn ($response): string => collect($response->json('data.allowances.items'))
            ->firstWhere('code', 'PARENT')['eligible_amount'];

        $this->assertSame('90000.00', $parentLine($this->calculate([
            'profile' => ['marital_status' => 'married'], 'spouse' => ['has_income' => false],
            'dependents' => $dependents, 'allowances' => [['code' => 'PARENT', 'amount' => '0.00']]])->assertOk()));

        $response = $this->calculate(['profile' => ['marital_status' => 'married'], 'spouse' => ['has_income' => true],
            'dependents' => $dependents, 'allowances' => [['code' => 'PARENT', 'amount' => '0.00']]])->assertOk();
        // The spouse's mother drops out when the spouse has income of their own.
        $this->assertSame('60000.00', $parentLine($response));

        $this->assertContains('SPOUSE_PARENT_NOT_ELIGIBLE', array_column($response->json('data.warnings'), 'code'));
    }

    // ------------------------------------------------- ใบแนบ item 5 — คนพิการหรือคนทุพพลภาพ

    public function test_each_declared_disabled_person_is_sixty_thousand_and_the_other_person_limit_is_reported(): void
    {
        $response = $this->calculate([
            'dependents' => [['relation_type' => 'disabled_person', 'eligible' => true],
                ['relation_type' => 'disabled_person', 'eligible' => true]],
            'allowances' => [['code' => 'DISABLED_PERSON', 'amount' => '0.00']],
        ])->assertOk()->assertJsonPath('data.allowances.total_eligible', '120000.00');

        $this->assertContains('DISABLED_PERSON_OTHER_LIMIT_UNMODELLED', array_column($response->json('data.warnings'), 'code'));
    }

    // ------------------------------------------------- the 0.5% minimum tax

    public function test_the_minimum_tax_is_paid_when_it_exceeds_the_progressive_tax(): void
    {
        // page 6 — "คำนวณภาษีจาก 2 วิธี (แล้วให้ชำระภาษีจากยอดที่มากกว่า)". Base 2,000,000 of
        // มาตรา 40(7) income × 0.005 = 10,000, against a progressive tax of nothing.
        $response = $this->calculate(['incomes' => [['income_type' => 'SECTION_40_7', 'gross_amount' => '2000000.00',
            'expense_method_selection' => 'actual', 'actual_expense' => '1990000.00']]], 'PND90')->assertOk()
            ->assertJsonPath('data.progressive_tax.total', '0.00')
            ->assertJsonPath('data.minimum_tax.applicable', true)
            ->assertJsonPath('data.minimum_tax.base', '2000000.00')
            ->assertJsonPath('data.minimum_tax.tax', '10000.00')
            ->assertJsonPath('data.minimum_tax.method', 'MINIMUM_TAX')
            ->assertJsonPath('data.tax_components.tax_before_credits', '10000.00')
            ->assertJsonPath('data.result.amount', '10000.00');

        $this->assertContains('PND90_MINIMUM_TAX_APPLIED', array_column($response->json('data.warnings'), 'code'));
        $this->assertSame(['MINIMUM_TAX_BASE', 'MINIMUM_TAX', 'TAX_PAYABLE'],
            array_slice(array_column($response->json('data.trace'), 'code'), 12, 3));
    }

    public function test_a_minimum_tax_of_five_thousand_or_less_is_disregarded(): void
    {
        // "เว้นแต่คำนวณแล้วไม่เกิน 5,000 บาท ให้ชำระภาษีจากวิธีที่ 1." — 1,000,000 × 0.005 = 5,000.
        $this->calculate(['incomes' => [['income_type' => 'SECTION_40_7', 'gross_amount' => '1000000.00',
            'expense_method_selection' => 'actual', 'actual_expense' => '990000.00']]], 'PND90')->assertOk()
            ->assertJsonPath('data.minimum_tax.applicable', false)
            ->assertJsonPath('data.minimum_tax.tax', '5000.00')
            ->assertJsonPath('data.minimum_tax.method', 'PROGRESSIVE')
            ->assertJsonPath('data.result.amount', '0.00');
    }

    public function test_a_base_below_one_hundred_twenty_thousand_never_reaches_the_second_method(): void
    {
        $this->calculate(['incomes' => [['income_type' => 'SECTION_40_7', 'gross_amount' => '119999.99',
            'expense_method_selection' => 'actual', 'actual_expense' => '0.00']]], 'PND90')->assertOk()
            ->assertJsonPath('data.minimum_tax.applicable', false)
            ->assertJsonPath('data.minimum_tax.tax', '0.00')
            ->assertJsonPath('data.minimum_tax.base', '119999.99');
    }

    public function test_section_40_1_income_is_excluded_from_the_base_and_pnd91_never_uses_it(): void
    {
        // ไม่รวมเงินได้พึงประเมินมาตรา 40 (1): a PND90 return of salary alone has a base of nothing.
        $this->calculate([], 'PND90')->assertOk()
            ->assertJsonPath('data.minimum_tax.base', '0.00')
            ->assertJsonPath('data.minimum_tax.applicable', false);

        // ภ.ง.ด.91 carries มาตรา 40 (1) only and prints no second method at all.
        $this->calculate([])->assertOk()
            ->assertJsonPath('data.minimum_tax.base', '0.00')
            ->assertJsonPath('data.minimum_tax.applicable', false)
            ->assertJsonPath('data.progressive_tax.total', '45500.00')
            ->assertJsonPath('data.result.amount', '45500.00');
    }

    // ------------------------------------------------- ceilings read from the booklet

    /** @return array<string, array{string, string, string, string}> */
    public static function ceilings(): array
    {
        return [
            'parent health insurance (item 6.3)' => ['PARENT_HEALTH_INSURANCE', '15000.00', '20000.00', '15000.00'],
            'home loan interest (item 11)' => ['HOME_LOAN_INTEREST', '100000.00', '150000.00', '100000.00'],
            'maternity (item 14)' => ['MATERNITY', '60000.00', '60000.01', '60000.00'],
            'political party support (item 15)' => ['POLITICAL_PARTY_SUPPORT', '10000.00', '9999.99', '9999.99'],
            'art purchase (item 21)' => ['ART_PURCHASE', '100000.00', '40000.00', '40000.00'],
        ];
    }

    #[DataProvider('ceilings')]
    public function test_a_booklet_ceiling_caps_the_amount_paid(string $code, string $maximum, string $paid, string $eligible): void
    {
        $this->calculate(['allowances' => [['code' => $code, 'amount' => $paid]]])->assertOk()
            ->assertJsonPath('data.allowances.items.0.rule_status', 'VERIFIED')
            ->assertJsonPath('data.allowances.items.0.maximum_amount', $maximum)
            ->assertJsonPath('data.allowances.items.0.eligible_amount', $eligible);
    }

    public function test_a_ceiling_the_booklet_does_not_settle_stays_unseeded(): void
    {
        // M7.4 expressed the percentage and shared-cap shapes, so most of the codes this case
        // once listed are now VERIFIED. These two are not, and for reasons M7.4 did not remove:
        // SOCIAL_SECURITY's numeric limit is in another act, and PENSION_INSURANCE's ใบแนบ item
        // 7.6 states a 90,000 amount and a further 15%/200,000 entitlement without saying
        // whether they nest. M7.5 turned both into a refusal rather than a silent zero.
        foreach (['SOCIAL_SECURITY', 'PENSION_INSURANCE'] as $code) {
            $this->calculate(['allowances' => [['code' => $code, 'amount' => '100000.00']]])
                ->assertUnprocessable()->assertJsonValidationErrors('allowances.0.code');

            $response = $this->calculate(['allowances' => [['code' => $code, 'amount' => '0.00']]])->assertOk()
                ->assertJsonPath('data.allowances.items.0.rule_status', 'UNVERIFIED')
                ->assertJsonPath('data.allowances.total_eligible', '0.00');
            $this->assertContains('UNVERIFIED_ALLOWANCE_RULE', array_column($response->json('data.warnings'), 'code'), $code);
        }
    }

    public function test_the_seeded_ceilings_cite_the_filing_instructions(): void
    {
        $rules = DB::table('allowance_rules')->where('rule_version_id', $this->publishedVersionId())->get()->keyBy('code');

        // M7.4 added nine more ceilings to the six M7.3 seeded; these are the M7.3 five.
        $this->assertCount(17, $rules);
        foreach (['PND90_2568_PARENT_HEALTH_INSURANCE' => '15000.00', 'PND90_2568_HOME_LOAN_INTEREST' => '100000.00',
            'PND90_2568_MATERNITY' => '60000.00', 'PND90_2568_POLITICAL_PARTY_SUPPORT' => '10000.00',
            'PND90_2568_ART_PURCHASE' => '100000.00'] as $code => $maximum) {
            $this->assertSame('actual', $rules[$code]->method);
            $this->assertEquals($maximum, $rules[$code]->maximum_amount);
            $this->assertNull($rules[$code]->percentage);
            $this->assertStringContainsString('PND90-2568-filing-instructions.pdf', (string) $rules[$code]->source_reference);
        }
    }

    // ------------------------------------------------- entry-point parity and history

    public function test_saved_family_facts_derive_the_same_allowances_as_a_guest_and_do_not_rewrite_history(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $id = $this->postJson('/api/v1/tax-returns', ['tax_year' => 2568, 'form_code' => 'PND91',
            'name' => 'M7.3 family'])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/tax-returns/$id/incomes", $this->payload()['incomes'][0])->assertCreated();
        $this->putJson("/api/v1/tax-returns/$id/profile", ['marital_status' => 'married'])->assertOk();
        $this->putJson("/api/v1/tax-returns/$id/spouse", ['has_income' => false])->assertOk();
        $this->postJson("/api/v1/tax-returns/$id/dependents", ['relation_type' => 'child', 'child_type' => 'legitimate',
            'birth_order' => 2, 'birth_date' => '2020-05-12', 'eligible' => true])->assertCreated();

        $guest = [...$this->payload(), 'profile' => ['marital_status' => 'married'], 'spouse' => ['has_income' => false],
            'dependents' => [['relation_type' => 'child', 'child_type' => 'legitimate', 'birth_order' => 2,
                'birth_date' => '2020-05-12', 'eligible' => true]]];
        foreach (['PERSONAL', 'SPOUSE', 'CHILD'] as $code) {
            $this->postJson("/api/v1/tax-returns/$id/allowances", ['code' => $code, 'input_amount' => '999999.00'])
                ->assertCreated();
            $guest['allowances'][] = ['code' => $code, 'amount' => '999999.00'];
        }
        // An amount is still never accepted on a dependent row — only facts are.
        $this->postJson("/api/v1/tax-returns/$id/dependents", ['relation_type' => 'child', 'eligible' => true,
            'allowance_amount' => '999999.00'])->assertUnprocessable()->assertJsonValidationErrors(['allowance_amount']);

        $member = $this->postJson("/api/v1/tax-returns/$id/complete")->assertOk()->json('data.calculation');
        $public = $this->postJson('/api/v1/tax/calculate', $guest)->assertOk()->json('data');

        $this->assertSame($public['allowances'], $member['allowances']);
        // 60,000 ผู้มีเงินได้ + 60,000 คู่สมรส + 30,000 บุตร + 30,000 (คนที่สอง, เกิดหลัง พ.ศ. 2561)
        $this->assertSame('180000.00', $member['allowances']['total_eligible']);
        $this->assertSame($public['result'], $member['result']);

        $stored = TaxCalculation::where('tax_return_id', $id)->sole();
        $snapshot = $stored->getRawOriginal('result_snapshot');
        $this->getJson("/api/v1/tax-returns/$id/calculations/{$stored->id}")->assertOk();
        $this->putJson("/api/v1/tax-returns/$id/spouse", ['has_income' => true])->assertConflict();
        $this->assertSame($snapshot, $stored->fresh()->getRawOriginal('result_snapshot'));
        $this->assertDatabaseCount('tax_calculations', 1);
        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/tax-returns/$id/calculations/{$stored->id}")->assertNotFound();
    }

    public function test_planning_derives_family_allowances_through_the_shared_engine(): void
    {
        $base = ['profile' => ['marital_status' => 'married'], 'spouse' => ['has_income' => false],
            'incomes' => $this->payload()['incomes'], 'allowances' => [], 'donations' => [], 'withholdings' => []];

        /*
         * 720,000 less the 100,000 expense ceiling is 620,000; the declared facts entitle the
         * filer to ใบแนบ item 1 (60,000) and item 2 (60,000), so the base is already 500,000 net
         * and 27,500 tax before any scenario is applied.
         *
         * The scenario then asks for the spouse line at 999,999. It changes nothing at all — the
         * amount is derived from the facts, not taken from the request — which is the sharpest
         * form of the guarantee this case exists for: a client cannot buy a larger family
         * allowance by sending a larger number.
         */
        $this->postJson('/api/v1/tax/plan', ['tax_year' => 2568, 'form_code' => 'PND91', 'base' => $base,
            'scenario' => ['allowances' => ['upsert' => [['code' => 'SPOUSE', 'input_amount' => '999999.00']]]]])
            ->assertOk()->assertJsonPath('data.before.calculated_tax', '27500.00')
            ->assertJsonPath('data.after.calculated_tax', '27500.00')
            ->assertJsonPath('data.estimated_tax_saving', '0.00');

        $this->assertDatabaseCount('tax_calculations', 0);
    }

    public function test_a_client_calculated_family_field_is_still_rejected_outright(): void
    {
        $payload = $this->payload();
        $payload['allowances'] = [['code' => 'PERSONAL', 'amount' => '1.00', 'eligible_amount' => '999999.00']];

        $this->postJson('/api/v1/tax/calculate', $payload)->assertUnprocessable()
            ->assertJsonValidationErrors('allowances.0');
        $this->assertDatabaseCount('tax_returns', 0);
    }
}

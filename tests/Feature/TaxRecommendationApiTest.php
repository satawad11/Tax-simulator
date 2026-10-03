<?php

namespace Tests\Feature;

use App\Models\TaxReturn;
use App\Models\User;
use App\Services\Tax\TaxRecommendationService;
use Database\Seeders\RecommendationRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaxRecommendationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function payload(string $withholding = '25000.00', array $allowances = []): array
    {
        return ['tax_year' => 2568, 'form_code' => 'PND91',
            'incomes' => [['income_type' => 'SECTION_40_1', 'gross_amount' => '720000.00', 'exempt_amount' => '0.00']],
            'allowances' => $allowances, 'donations' => [],
            'withholdings' => [['type' => 'withholding', 'amount' => $withholding]]];
    }

    /** @return list<string> */
    private function codes(array $payload): array
    {
        return array_column($this->postJson('/api/v1/tax/calculate', $payload)->assertOk()->json('data.recommendations'), 'code');
    }

    public function test_seeded_rules_are_idempotent_and_bound_to_the_published_version(): void
    {
        $rules = DB::table('recommendation_rules')->where('rule_version_id', $this->publishedVersionId())->orderBy('code')->get();
        $this->seed(RecommendationRuleSeeder::class);

        $this->assertSame($rules->toJson(), DB::table('recommendation_rules')->where('rule_version_id', $this->publishedVersionId())->orderBy('code')->get()->toJson());
        $this->assertSame(['CHECK_SOCIAL_SECURITY', 'PAYABLE_DUE_TO_LOW_WITHHOLDING',
            'REFUND_DUE_TO_EXCESS_WITHHOLDING', 'VERIFY_UNVERIFIED_ALLOWANCE_RULE'], $rules->pluck('code')->all());
        $version = DB::table('tax_rule_versions')->where('version', '2568.3')->first();
        $this->assertSame([$version->id], $rules->pluck('rule_version_id')->unique()->values()->all());
    }

    public function test_section_40_1_without_social_security_input_is_reminded_to_check_it(): void
    {
        $recommendation = collect($this->postJson('/api/v1/tax/calculate', $this->payload())->assertOk()
            ->json('data.recommendations'))->firstWhere('code', 'CHECK_SOCIAL_SECURITY');

        $this->assertSame('POTENTIAL_ALLOWANCE', $recommendation['type']);
        $this->assertSame('medium', $recommendation['priority']);
        $this->assertSame(['type' => 'OPEN_ALLOWANCE', 'allowance_code' => 'SOCIAL_SECURITY'], $recommendation['action']);
        $this->assertStringContainsString('ควรตรวจสอบ', $recommendation['message']);
    }

    /**
     * M7.5 — the reminder's condition (`allowance_not_present`) is unchanged, but the only way
     * to make SOCIAL_SECURITY "present" is now a zero-amount line: a positive one is refused,
     * because ใบแนบ ข้อ 12 refers its ceiling to an act that is not a repository source.
     */
    public function test_social_security_reminder_disappears_once_the_input_is_present(): void
    {
        $this->assertNotContains('CHECK_SOCIAL_SECURITY',
            $this->codes($this->payload('25000.00', [['code' => 'SOCIAL_SECURITY', 'amount' => '0.00']])));

        $this->postJson('/api/v1/tax/calculate',
            $this->payload('25000.00', [['code' => 'SOCIAL_SECURITY', 'amount' => '9000.00']]))
            ->assertUnprocessable()->assertJsonValidationErrors('allowances.0.code');
    }

    public function test_the_social_security_reminder_does_not_promise_a_deduction_the_engine_refuses(): void
    {
        $recommendation = collect($this->postJson('/api/v1/tax/calculate', $this->payload())->assertOk()
            ->json('data.recommendations'))->firstWhere('code', 'CHECK_SOCIAL_SECURITY');

        $this->assertStringContainsString('ยังไม่รองรับการคำนวณรายการนี้', $recommendation['message']);
    }

    public function test_payable_returns_a_payment_recommendation_and_payment_guidance(): void
    {
        $response = $this->postJson('/api/v1/tax/calculate', $this->payload())->assertOk()
            ->assertJsonPath('data.result.status', 'PAYABLE')
            ->assertJsonPath('data.refund_guidance', null)
            ->assertJsonPath('data.payment_guidance.reason_code', 'WITHHOLDING_BELOW_CALCULATED_TAX')
            ->assertJsonPath('data.payment_guidance.amount', '20500.00');

        $payment = collect($response->json('data.recommendations'))->firstWhere('code', 'PAYABLE_DUE_TO_LOW_WITHHOLDING');
        $this->assertSame('PAYMENT', $payment['type']);
        $this->assertSame('high', $payment['priority']);
        $this->assertStringContainsString('45500.00', $payment['message']);
    }

    public function test_refund_returns_a_refund_recommendation_and_refund_guidance(): void
    {
        $response = $this->postJson('/api/v1/tax/calculate', $this->payload('50000.00'))->assertOk()
            ->assertJsonPath('data.result.status', 'REFUND')
            ->assertJsonPath('data.payment_guidance', null)
            ->assertJsonPath('data.refund_guidance.estimated_refund', '4500.00')
            ->assertJsonPath('data.refund_guidance.reason_code', 'CREDITS_EXCEED_CALCULATED_TAX')
            ->assertJsonCount(3, 'data.refund_guidance.checklist');

        $refund = collect($response->json('data.recommendations'))->firstWhere('code', 'REFUND_DUE_TO_EXCESS_WITHHOLDING');
        $this->assertSame('REFUND', $refund['type']);
        $this->assertSame('medium', $refund['priority']);
    }

    public function test_zero_returns_neither_guidance_nor_payment_or_refund_advice(): void
    {
        $this->postJson('/api/v1/tax/calculate', $this->payload('45500.00'))->assertOk()
            ->assertJsonPath('data.result.status', 'ZERO')
            ->assertJsonPath('data.refund_guidance', null)
            ->assertJsonPath('data.payment_guidance', null);

        $codes = $this->codes($this->payload('45500.00'));
        $this->assertNotContains('PAYABLE_DUE_TO_LOW_WITHHOLDING', $codes);
        $this->assertNotContains('REFUND_DUE_TO_EXCESS_WITHHOLDING', $codes);
    }

    /**
     * M7.5 blocks a *positive* amount on an allowance the baseline cannot calculate, so the
     * warning and its recommendation now belong to the one case that still reaches the engine:
     * a declared line of zero, which cannot mislead because it deducts nothing either way.
     */
    public function test_an_unverified_allowance_warning_produces_a_verification_recommendation(): void
    {
        $response = $this->postJson('/api/v1/tax/calculate',
            $this->payload('25000.00', [['code' => 'PENSION_INSURANCE', 'amount' => '0.00']]))->assertOk();

        $this->assertContains('UNVERIFIED_ALLOWANCE_RULE', array_column($response->json('data.warnings'), 'code'));
        $verification = collect($response->json('data.recommendations'))->firstWhere('code', 'VERIFY_UNVERIFIED_ALLOWANCE_RULE');
        $this->assertSame('MISSING_INFORMATION', $verification['type']);
        $this->assertSame('0.00', $response->json('data.allowances.total_eligible'));
    }

    public function test_recommendations_are_deduplicated_and_ordered_high_then_medium_then_code(): void
    {
        $codes = $this->codes($this->payload('25000.00', [['code' => 'PENSION_INSURANCE', 'amount' => '0.00']]));

        $this->assertSame(['PAYABLE_DUE_TO_LOW_WITHHOLDING', 'VERIFY_UNVERIFIED_ALLOWANCE_RULE', 'CHECK_SOCIAL_SECURITY'], $codes);
        $this->assertSame($codes, array_values(array_unique($codes)));
    }

    public function test_an_unsupported_condition_skips_its_rule_without_failing_the_request(): void
    {
        DB::table('recommendation_rules')->where('code', 'CHECK_SOCIAL_SECURITY')
            ->update(['conditions' => json_encode(['unsupported_condition' => 'SOCIAL_SECURITY'])]);

        $codes = $this->codes($this->payload());
        $this->assertNotContains('CHECK_SOCIAL_SECURITY', $codes);
        $this->assertContains('PAYABLE_DUE_TO_LOW_WITHHOLDING', $codes);
    }

    public function test_inactive_rules_are_never_returned(): void
    {
        DB::table('recommendation_rules')->update(['active' => false]);

        $this->assertSame([], $this->codes($this->payload()));
    }

    public function test_supported_condition_vocabulary_is_explicit(): void
    {
        $this->assertSame([
            'income_types_contains', 'income_types_only', 'allowance_present', 'allowance_not_present',
            'result_status', 'gross_income_greater_than', 'net_income_greater_than',
            'withholding_less_than_tax', 'withholding_greater_than_tax', 'warning_present',
        ], TaxRecommendationService::SUPPORTED_CONDITIONS);
    }

    public function test_member_recommendation_endpoint_is_read_only_and_creates_no_history(): void
    {
        $id = $this->savedReturn();
        $response = $this->getJson("/api/v1/tax-returns/$id/recommendations")->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.persisted', false)
            ->assertJsonPath('meta.rule_version', '2568.3')
            ->assertJsonPath('meta.result.status', 'PAYABLE')
            ->assertJsonPath('meta.payment_guidance.amount', '20500.00')
            ->assertJsonPath('meta.refund_guidance', null);

        $this->assertSame(['PAYABLE_DUE_TO_LOW_WITHHOLDING', 'CHECK_SOCIAL_SECURITY'], array_column($response->json('data'), 'code'));
        $this->assertDatabaseCount('tax_calculations', 0);
        $this->assertDatabaseCount('tax_calculation_brackets', 0);
        $this->assertDatabaseCount('tax_scenarios', 0);
    }

    public function test_member_recommendations_match_the_guest_recommendations_for_the_same_input(): void
    {
        $id = $this->savedReturn();
        $guest = $this->postJson('/api/v1/tax/calculate', $this->payload())->assertOk()->json('data.recommendations');

        $this->assertSame($guest, $this->getJson("/api/v1/tax-returns/$id/recommendations")->assertOk()->json('data'));
    }

    public function test_recommendations_remain_available_for_a_completed_simulation(): void
    {
        $id = $this->savedReturn();
        $this->postJson("/api/v1/tax-returns/$id/complete")->assertOk();

        $this->assertSame('completed', TaxReturn::findOrFail($id)->status);
        $this->getJson("/api/v1/tax-returns/$id/recommendations")->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_another_member_cannot_read_recommendations(): void
    {
        $id = $this->savedReturn();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/tax-returns/$id/recommendations")->assertNotFound()->assertJsonPath('success', false);
    }

    private function savedReturn(): int
    {
        Sanctum::actingAs(User::factory()->create());
        $id = $this->postJson('/api/v1/tax-returns', ['tax_year' => 2568, 'form_code' => 'PND91', 'name' => 'Synthetic simulation'])
            ->assertCreated()->json('data.id');
        $this->postJson("/api/v1/tax-returns/$id/incomes", ['income_type' => 'SECTION_40_1', 'gross_amount' => '720000.00', 'exempt_amount' => '0.00'])->assertCreated();
        $this->postJson("/api/v1/tax-returns/$id/withholdings", ['type' => 'withholding', 'amount' => '25000.00'])->assertCreated();

        return $id;
    }
}

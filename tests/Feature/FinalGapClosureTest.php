<?php

namespace Tests\Feature;

use App\Models\AllowanceRule;
use App\Models\AllowanceType;
use App\Models\TaxCalculation;
use App\Models\TaxReturn;
use App\Models\TaxRuleVersion;
use App\Models\User;
use App\Services\Admin\TaxRuleVersionCloneService;
use App\Services\Admin\TaxRuleVersionPublishingService;
use App\Services\Tax\Allowances\DisabledPersonAllowanceStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class FinalGapClosureTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_the_corrective_schema_is_additive_and_nullable(): void
    {
        $this->assertTrue(DB::getSchemaBuilder()->hasColumn('tax_return_dependents', 'disabled_person_relationship'));
        $return = TaxReturn::factory()->create();
        $dependent = $return->dependents()->create([
            'relationship' => 'disabled_person', 'relation_type' => 'disabled_person', 'eligible' => true,
        ]);

        $this->assertNull($dependent->disabled_person_relationship);
    }

    public function test_2568_1_keeps_the_original_guarded_disabled_person_result(): void
    {
        $response = $this->postJson('/api/v1/tax/calculate', $this->payload([
            ['relation_type' => 'disabled_person', 'disabled_person_relationship' => 'other_person', 'eligible' => true],
            ['relation_type' => 'disabled_person', 'disabled_person_relationship' => 'other_person', 'eligible' => true],
        ]))->assertOk()->assertJsonPath('data.rule_version', '2568.3')
            ->assertJsonPath('data.allowances.total_eligible', '120000.00');

        $this->assertContains('DISABLED_PERSON_OTHER_LIMIT_UNMODELLED', array_column($response->json('data.warnings'), 'code'));
    }

    public function test_the_discriminator_is_rejected_outside_a_disabled_person_row(): void
    {
        $this->postJson('/api/v1/tax/calculate', $this->payload([
            ['relation_type' => 'child', 'child_type' => 'legitimate', 'birth_order' => 1,
                'disabled_person_relationship' => 'family_member', 'eligible' => true],
        ]))->assertUnprocessable()->assertJsonValidationErrors('dependents.0.disabled_person_relationship');
    }

    public function test_draft_2568_2_applies_the_one_other_person_limit_after_publication(): void
    {
        $version = $this->publishDraft25682();
        $response = $this->postJson('/api/v1/tax/calculate', $this->payload([
            ['relation_type' => 'disabled_person', 'disabled_person_relationship' => 'family_member', 'eligible' => true],
            ['relation_type' => 'disabled_person', 'disabled_person_relationship' => 'other_person', 'eligible' => true],
            ['relation_type' => 'disabled_person', 'disabled_person_relationship' => 'other_person', 'eligible' => true],
        ]))->assertOk()->assertJsonPath('data.rule_version', '2568.2')
            ->assertJsonPath('data.allowances.total_eligible', '120000.00');

        $this->assertContains('DISABLED_PERSON_OTHER_LIMIT_APPLIED', array_column($response->json('data.warnings'), 'code'));
        $this->assertSame('published', $version->fresh()->status);
    }

    public function test_draft_rule_requires_the_relationship_discriminator(): void
    {
        $this->publishDraft25682();

        $this->postJson('/api/v1/tax/calculate', $this->payload([
            ['relation_type' => 'disabled_person', 'eligible' => true],
        ]))->assertUnprocessable()->assertJsonValidationErrors('dependents.0.disabled_person_relationship');
    }

    public function test_member_persistence_and_guest_member_results_match_on_2568_2(): void
    {
        $this->publishDraft25682();
        Sanctum::actingAs(User::factory()->create());
        $returnId = $this->postJson('/api/v1/tax-returns', [
            'tax_year' => 2568, 'form_code' => 'PND91', 'name' => '2568.2 disabled relationship',
        ])->assertCreated()->assertJsonPath('data.rule_version', '2568.2')->json('data.id');

        foreach (['family_member', 'other_person', 'other_person'] as $relationship) {
            $this->postJson("/api/v1/tax-returns/$returnId/dependents", [
                'relation_type' => 'disabled_person', 'disabled_person_relationship' => $relationship, 'eligible' => true,
            ])->assertCreated()->assertJsonPath('data.disabled_person_relationship', $relationship);
        }
        $this->postJson("/api/v1/tax-returns/$returnId/incomes", [
            'income_type' => 'SECTION_40_1', 'gross_amount' => '720000.00',
        ])->assertCreated();
        $this->postJson("/api/v1/tax-returns/$returnId/allowances", [
            'code' => 'DISABLED_PERSON', 'input_amount' => '0.00',
        ])->assertCreated();

        $memberResult = $this->postJson("/api/v1/tax-returns/$returnId/calculate")
            ->assertOk()->json('data.calculation');
        $guestResult = $this->postJson('/api/v1/tax/calculate', $this->payload([
            ['relation_type' => 'disabled_person', 'disabled_person_relationship' => 'family_member', 'eligible' => true],
            ['relation_type' => 'disabled_person', 'disabled_person_relationship' => 'other_person', 'eligible' => true],
            ['relation_type' => 'disabled_person', 'disabled_person_relationship' => 'other_person', 'eligible' => true],
        ]))->assertOk()->json('data');

        $this->assertJsonStringEqualsJsonString(json_encode($guestResult), json_encode($memberResult));
        $this->getJson("/api/v1/tax-returns/$returnId")->assertOk()
            ->assertJsonPath('data.dependents.0.disabled_person_relationship', 'family_member')
            ->assertJsonPath('data.dependents.1.disabled_person_relationship', 'other_person');
    }

    public function test_planning_uses_the_same_2568_2_relationship_limit(): void
    {
        $this->publishDraft25682();

        $response = $this->postJson('/api/v1/tax/plan', [
            'tax_year' => 2568,
            'form_code' => 'PND91',
            'base' => array_diff_key($this->payload([
                ['relation_type' => 'disabled_person', 'disabled_person_relationship' => 'other_person', 'eligible' => true],
                ['relation_type' => 'disabled_person', 'disabled_person_relationship' => 'other_person', 'eligible' => true],
            ]), ['tax_year' => true, 'form_code' => true]),
            'scenario' => [],
        ])->assertOk()->assertJsonPath('data.before.net_income', '560000.00')
            ->assertJsonPath('data.after.net_income', '560000.00');

        $this->assertContains('DISABLED_PERSON_OTHER_LIMIT_APPLIED', array_column($response->json('data.warnings'), 'code'));
    }

    public function test_publishing_the_draft_does_not_rewrite_a_2568_1_snapshot(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $returnId = $this->postJson('/api/v1/tax-returns', [
            'tax_year' => 2568, 'form_code' => 'PND91', 'name' => 'Frozen 2568.1 history',
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/tax-returns/$returnId/incomes", [
            'income_type' => 'SECTION_40_1', 'gross_amount' => '720000.00',
        ])->assertCreated();
        $this->postJson("/api/v1/tax-returns/$returnId/complete")->assertOk();

        $before = TaxCalculation::where('tax_return_id', $returnId)->firstOrFail();
        $snapshot = $before->getRawOriginal('result_snapshot');
        $input = $before->getRawOriginal('input_snapshot');
        $versionId = $before->rule_version_id;
        $this->publishDraft25682();

        $after = TaxCalculation::findOrFail($before->id);
        $this->assertSame($snapshot, $after->getRawOriginal('result_snapshot'));
        $this->assertSame($input, $after->getRawOriginal('input_snapshot'));
        $this->assertSame($versionId, $after->rule_version_id);
        $this->assertSame($versionId, TaxReturn::findOrFail($returnId)->rule_version_id);
    }

    public function test_published_2568_1_rule_rows_remain_immutable(): void
    {
        $baseline = TaxRuleVersion::where('version', '2568.3')->firstOrFail();

        $this->expectException(LogicException::class);
        $baseline->forceFill(['description' => 'must fail'])->save();
    }

    public function test_repository_production_readiness_command_passes_without_mutating_data(): void
    {
        $before = [
            'returns' => TaxReturn::withTrashed()->count(),
            'calculations' => TaxCalculation::count(),
            'baseline_updated_at' => TaxRuleVersion::where('version', '2568.3')->value('updated_at'),
        ];
        $exit = Artisan::call('ops:production-readiness', ['--repository-only' => true]);

        $this->assertSame(0, $exit, Artisan::output());
        $this->assertSame($before['returns'], TaxReturn::withTrashed()->count());
        $this->assertSame($before['calculations'], TaxCalculation::count());
        $this->assertEquals($before['baseline_updated_at'], TaxRuleVersion::where('version', '2568.3')->value('updated_at'));
    }

    /** @param list<array<string, mixed>> $dependents */
    private function payload(array $dependents): array
    {
        return [
            'tax_year' => 2568,
            'form_code' => 'PND91',
            'dependents' => $dependents,
            'incomes' => [['income_type' => 'SECTION_40_1', 'gross_amount' => '720000.00']],
            'allowances' => [['code' => 'DISABLED_PERSON', 'amount' => '0.00']],
            'donations' => [],
            'withholdings' => [],
        ];
    }

    private function publishDraft25682(): TaxRuleVersion
    {
        $actor = User::factory()->create()->forceFill(['role' => User::ROLE_ADMIN]);
        $actor->save();
        $baseline = TaxRuleVersion::where('version', '2568.3')->firstOrFail();
        $draft = app(TaxRuleVersionCloneService::class)->clone(
            $actor,
            $baseline,
            '2568.2',
            'Final gap draft: disabled-person relationship discriminator'
        );
        AllowanceRule::create([
            'tax_year_id' => $draft->tax_year_id,
            'rule_version_id' => $draft->id,
            'allowance_type_id' => AllowanceType::where('code', 'DISABLED_PERSON')->firstOrFail()->id,
            'code' => DisabledPersonAllowanceStrategy::RULE_CODE,
            'method' => 'custom',
            'conditions' => ['relationship_field' => 'disabled_person_relationship', 'other_person_limit' => 1],
            'source_reference' => 'docs/tax-source/PND90-2568-filing-instructions.pdf, pages 8-9, item 5',
            'active' => true,
        ]);

        return app(TaxRuleVersionPublishingService::class)->publish($actor, $draft)['version'];
    }
}

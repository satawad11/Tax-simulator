<?php

namespace Tests\Feature;

use App\Models\TaxReturn;
use App\Models\TaxRuleVersion;
use App\Models\TaxScenario;
use App\Models\User;
use App\Services\Tax\TaxCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TaxScenarioApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->owner = User::factory()->create();
    }

    private function savedReturn(?User $user = null): int
    {
        Sanctum::actingAs($user ?? $this->owner);
        $id = $this->postJson('/api/v1/tax-returns', ['tax_year' => 2568, 'form_code' => 'PND91', 'name' => 'Synthetic simulation'])
            ->assertCreated()->json('data.id');
        $this->postJson("/api/v1/tax-returns/$id/incomes", ['income_type' => 'SECTION_40_1', 'gross_amount' => '720000.00', 'exempt_amount' => '0.00'])->assertCreated();
        $this->postJson("/api/v1/tax-returns/$id/withholdings", ['type' => 'withholding', 'amount' => '25000.00'])->assertCreated();

        return $id;
    }

    private function payload(): array
    {
        return ['allowances' => ['upsert' => [['code' => 'SOCIAL_SECURITY', 'input_amount' => '9000.00']], 'remove' => []]];
    }

    private function scenario(int $return, ?array $payload = null): int
    {
        return $this->postJson("/api/v1/tax-returns/$return/scenarios",
            ['name' => 'ทดลองเพิ่มค่าลดหย่อน', 'payload' => $payload ?? $this->payload()])
            ->assertCreated()->json('data.id');
    }

    public function test_member_creates_reads_lists_updates_and_deletes_a_scenario(): void
    {
        $return = $this->savedReturn();
        $id = $this->scenario($return);

        $detail = $this->getJson("/api/v1/tax-returns/$return/scenarios/$id")->assertOk()
            ->assertJsonPath('data.name', 'ทดลองเพิ่มค่าลดหย่อน')
            ->assertJsonPath('data.calculation_result', null)
            ->assertJsonPath('data.rule_version', '2568.3')
            ->assertJsonPath('data.tax_year', 2568)
            ->assertJsonPath('data.source_tax_return_id', $return);

        $this->assertJsonStringEqualsJsonString(
            json_encode($this->payload(), JSON_THROW_ON_ERROR),
            json_encode($detail->json('data.payload'), JSON_THROW_ON_ERROR),
        );

        $this->getJson("/api/v1/tax-returns/$return/scenarios")->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.estimated_tax_saving', null)->assertJsonPath('data.0.result', null);

        $this->patchJson("/api/v1/tax-returns/$return/scenarios/$id", ['name' => 'ชื่อใหม่'])->assertOk()
            ->assertJsonPath('data.name', 'ชื่อใหม่');

        $this->deleteJson("/api/v1/tax-returns/$return/scenarios/$id")->assertNoContent();
        $this->assertDatabaseCount('tax_scenarios', 0);
        $this->assertDatabaseHas('tax_returns', ['id' => $return, 'status' => 'draft']);
    }

    public function test_scenarios_are_listed_newest_updated_first_without_recalculating(): void
    {
        $return = $this->savedReturn();
        $first = $this->scenario($return);
        $second = $this->scenario($return);
        $this->travel(1)->seconds();
        $this->postJson("/api/v1/tax-returns/$return/scenarios/$first/calculate")->assertOk();

        $this->mock(TaxCalculationService::class)->shouldNotReceive('calculate');
        $listed = $this->getJson("/api/v1/tax-returns/$return/scenarios")->assertOk()->json('data');

        $this->assertSame([$first, $second], array_column($listed, 'id'));
        $this->assertSame('0.00', $listed[0]['estimated_tax_saving']);
        $this->assertJsonStringEqualsJsonString(
            '{"status":"PAYABLE","amount":"20500.00"}',
            json_encode($listed[0]['result'], JSON_THROW_ON_ERROR),
        );
        $this->assertNull($listed[1]['estimated_tax_saving']);
    }

    public function test_calculating_a_scenario_persists_the_comparison_without_touching_calculation_history(): void
    {
        $return = $this->savedReturn();
        $id = $this->scenario($return, ['withholdings' => ['upsert' => [['type' => 'withholding', 'amount' => '50000.00']]]]);

        $this->postJson("/api/v1/tax-returns/$return/scenarios/$id/calculate")->assertOk()
            ->assertJsonPath('data.calculation_result.before.result.status', 'PAYABLE')
            ->assertJsonPath('data.calculation_result.after.result.status', 'REFUND')
            ->assertJsonPath('data.calculation_result.estimated_tax_saving', '0.00')
            ->assertJsonPath('data.calculation_result.rule_version', '2568.3');

        $scenario = TaxScenario::findOrFail($id);
        $this->assertSame('45500.00', $scenario->before_tax);
        $this->assertSame('45500.00', $scenario->after_tax);
        $this->assertSame('0.00', $scenario->estimated_tax_saving);
        $this->assertNotNull($scenario->calculated_at);
        $this->assertDatabaseCount('tax_calculations', 0);
        $this->assertDatabaseCount('tax_calculation_brackets', 0);
    }

    public function test_updating_the_payload_invalidates_the_stored_result_until_recalculated(): void
    {
        $return = $this->savedReturn();
        $id = $this->scenario($return);
        $this->postJson("/api/v1/tax-returns/$return/scenarios/$id/calculate")->assertOk();
        $this->assertNotNull(TaxScenario::findOrFail($id)->calculation_result);

        $this->patchJson("/api/v1/tax-returns/$return/scenarios/$id", ['payload' => ['allowances' => ['remove' => ['SOCIAL_SECURITY']]]])
            ->assertOk()->assertJsonPath('data.calculation_result', null)
            ->assertJsonPath('data.estimated_tax_saving', null);

        $scenario = TaxScenario::findOrFail($id);
        $this->assertNull($scenario->calculation_result);
        $this->assertNull($scenario->before_tax);
        $this->assertNull($scenario->calculated_at);

        $this->postJson("/api/v1/tax-returns/$return/scenarios/$id/calculate")->assertOk()
            ->assertJsonPath('data.calculation_result.after.result.status', 'PAYABLE');
    }

    public function test_renaming_a_scenario_keeps_its_calculated_result(): void
    {
        $return = $this->savedReturn();
        $id = $this->scenario($return);
        $this->postJson("/api/v1/tax-returns/$return/scenarios/$id/calculate")->assertOk();

        $this->patchJson("/api/v1/tax-returns/$return/scenarios/$id", ['name' => 'เปลี่ยนชื่อ'])->assertOk()
            ->assertJsonPath('data.estimated_tax_saving', '0.00');
    }

    public function test_a_scenario_never_changes_its_source_tax_return(): void
    {
        $return = $this->savedReturn();
        $before = $this->getJson("/api/v1/tax-returns/$return")->assertOk()->json('data');
        $id = $this->scenario($return, ['withholdings' => ['remove' => ['withholding']]]);
        $this->postJson("/api/v1/tax-returns/$return/scenarios/$id/calculate")->assertOk();

        $after = $this->getJson("/api/v1/tax-returns/$return")->assertOk()->json('data');
        unset($before['updated_at'], $after['updated_at']);
        $this->assertSame($before, $after);
    }

    public function test_a_scenario_keeps_the_source_rule_version_after_a_newer_version_is_published(): void
    {
        $return = $this->savedReturn();
        $id = $this->scenario($return);
        $source = TaxReturn::findOrFail($return);
        TaxRuleVersion::factory()->create(['tax_year_id' => $source->tax_year_id, 'version' => '2568.2'])
            ->update(['status' => 'published']);

        $this->postJson("/api/v1/tax-returns/$return/scenarios/$id/calculate")->assertOk()
            ->assertJsonPath('data.rule_version', '2568.3')
            ->assertJsonPath('data.calculation_result.rule_version', '2568.3');
        $this->assertSame($source->rule_version_id, TaxScenario::findOrFail($id)->rule_version_id);
    }

    public function test_a_completed_simulation_can_still_be_used_for_planning(): void
    {
        $return = $this->savedReturn();
        $this->postJson("/api/v1/tax-returns/$return/complete")->assertOk();
        $id = $this->scenario($return);

        $this->postJson("/api/v1/tax-returns/$return/scenarios/$id/calculate")->assertOk();
        $this->assertSame('completed', TaxReturn::findOrFail($return)->status);
        $this->assertSame(1, TaxReturn::findOrFail($return)->calculations()->count());
    }

    public function test_guest_and_member_scenarios_agree_for_equivalent_input(): void
    {
        $return = $this->savedReturn();
        $id = $this->scenario($return, ['withholdings' => ['upsert' => [['type' => 'withholding', 'amount' => '50000.00']]]]);
        $member = $this->postJson("/api/v1/tax-returns/$return/scenarios/$id/calculate")->assertOk()->json('data.calculation_result');

        $guest = $this->postJson('/api/v1/tax/plan', ['tax_year' => 2568, 'form_code' => 'PND91',
            'base' => ['incomes' => [['income_type' => 'SECTION_40_1', 'gross_amount' => '720000.00', 'exempt_amount' => '0.00']],
                'allowances' => [], 'donations' => [], 'withholdings' => [['type' => 'withholding', 'amount' => '25000.00']]],
            'scenario' => ['withholdings' => ['upsert' => [['type' => 'withholding', 'amount' => '50000.00']]]]])->assertOk()->json('data');

        $this->assertSame($guest, $member);
    }

    public static function scenarioRoutes(): array
    {
        return [['get', ''], ['get', '/{id}'], ['patch', '/{id}'], ['delete', '/{id}'], ['post', '/{id}/calculate']];
    }

    #[DataProvider('scenarioRoutes')]
    public function test_another_member_cannot_reach_a_scenario(string $method, string $suffix): void
    {
        $return = $this->savedReturn();
        $id = $this->scenario($return);
        Sanctum::actingAs(User::factory()->create());

        $path = "/api/v1/tax-returns/$return/scenarios".str_replace('{id}', (string) $id, $suffix);
        $this->json(strtoupper($method), $path, $method === 'patch' ? ['name' => 'x'] : [])
            ->assertNotFound()->assertJsonPath('success', false);
        $this->assertDatabaseHas('tax_scenarios', ['id' => $id, 'name' => 'ทดลองเพิ่มค่าลดหย่อน']);
    }

    public function test_another_member_cannot_create_a_scenario_on_a_foreign_return(): void
    {
        $return = $this->savedReturn();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/tax-returns/$return/scenarios", ['name' => 'x', 'payload' => []])->assertNotFound();
        $this->assertDatabaseCount('tax_scenarios', 0);
    }

    public function test_a_scenario_cannot_be_reached_through_another_tax_return_of_the_same_owner(): void
    {
        $return = $this->savedReturn();
        $id = $this->scenario($return);
        $other = $this->savedReturn();

        $this->getJson("/api/v1/tax-returns/$other/scenarios/$id")->assertNotFound();
        $this->postJson("/api/v1/tax-returns/$other/scenarios/$id/calculate")->assertNotFound();
    }

    public function test_scenarios_require_authentication(): void
    {
        $return = $this->savedReturn();
        $id = $this->scenario($return);
        app('auth')->forgetGuards();

        $this->getJson("/api/v1/tax-returns/$return/scenarios")->assertUnauthorized();
        $this->postJson("/api/v1/tax-returns/$return/scenarios/$id/calculate")->assertUnauthorized();
    }

    public static function invalidScenarioRequests(): array
    {
        return [
            'missing name' => [['payload' => []]],
            'missing payload' => [['name' => 'x']],
            'blank name' => [['name' => '', 'payload' => []]],
            'server calculated result' => [['name' => 'x', 'payload' => [], 'calculation_result' => ['a' => 1]]],
            'server calculated saving' => [['name' => 'x', 'payload' => [], 'estimated_tax_saving' => '1.00']],
            'client eligible amount' => [['name' => 'x', 'payload' => ['allowances' => ['upsert' => [['code' => 'RMF', 'amount' => '1', 'eligible_amount' => '1']]]]]],
            'client rule version' => [['name' => 'x', 'payload' => [], 'rule_version_id' => 1]],
            'unknown collection' => [['name' => 'x', 'payload' => ['incomes' => ['upsert' => []]]]],
            'unknown allowance' => [['name' => 'x', 'payload' => ['allowances' => ['upsert' => [['code' => 'UNKNOWN', 'amount' => '1']]]]]],
            'unsupported donation' => [['name' => 'x', 'payload' => ['donations' => ['upsert' => [['code' => 'UNKNOWN', 'amount' => '1']]]]]],
            'negative amount' => [['name' => 'x', 'payload' => ['withholdings' => ['upsert' => [['type' => 'withholding', 'amount' => '-1']]]]]],
            'remove and upsert one key' => [['name' => 'x', 'payload' => ['allowances' => ['upsert' => [['code' => 'RMF', 'amount' => '1']], 'remove' => ['RMF']]]]],
        ];
    }

    #[DataProvider('invalidScenarioRequests')]
    public function test_invalid_scenario_requests_return_sanitized_422(array $body): void
    {
        $return = $this->savedReturn();

        $this->postJson("/api/v1/tax-returns/$return/scenarios", $body)->assertUnprocessable()
            ->assertJsonPath('success', false)->assertJsonStructure(['errors']);
        $this->assertDatabaseCount('tax_scenarios', 0);
    }

    public function test_a_scenario_cannot_be_calculated_when_the_saved_return_is_incomplete(): void
    {
        $return = $this->savedReturn();
        $id = $this->scenario($return);
        TaxReturn::findOrFail($return)->incomes()->delete();

        $this->postJson("/api/v1/tax-returns/$return/scenarios/$id/calculate")->assertUnprocessable()
            ->assertJsonValidationErrors('incomes');
        $this->assertNull(TaxScenario::findOrFail($id)->calculation_result);
    }

    public function test_scenario_rows_are_owned_by_the_source_return_context(): void
    {
        $return = $this->savedReturn();
        $id = $this->scenario($return);
        $source = TaxReturn::findOrFail($return);

        $this->assertDatabaseHas('tax_scenarios', ['id' => $id, 'user_id' => $source->user_id,
            'tax_year_id' => $source->tax_year_id, 'rule_version_id' => $source->rule_version_id,
            'source_tax_return_id' => $return, 'tax_return_id' => $return]);
        $this->assertSame(0, DB::table('tax_scenarios')->whereNull('user_id')->count());
    }
}

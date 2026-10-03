<?php

namespace Tests\Feature;

use App\Models\TaxCalculation;
use App\Models\TaxCalculationBracket;
use App\Models\TaxReturn;
use App\Models\TaxRuleVersion;
use App\Models\User;
use App\Services\Tax\TaxCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberTaxCalculationTest extends TestCase
{
    use RefreshDatabase;

    private function saved(string $gross = '720000.00'): int
    {
        $this->seed();
        Sanctum::actingAs(User::factory()->create());
        $id = $this->postJson('/api/v1/tax-returns', ['tax_year' => 2568, 'form_code' => 'PND91', 'name' => 'Synthetic simulation'])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/tax-returns/$id/incomes", ['income_type' => 'SECTION_40_1', 'gross_amount' => $gross, 'exempt_amount' => '0.00'])->assertCreated();
        $this->postJson("/api/v1/tax-returns/$id/withholdings", ['type' => 'withholding', 'amount' => '25000.00'])->assertCreated();

        return $id;
    }

    public function test_member_and_guest_match_and_history_is_append_only(): void
    {
        $id = $this->saved();
        $guest = $this->postJson('/api/v1/tax/calculate', ['tax_year' => 2568, 'form_code' => 'PND91',
            'incomes' => [['income_type' => 'SECTION_40_1', 'gross_amount' => '720000.00', 'exempt_amount' => '0.00']],
            'withholdings' => [['type' => 'withholding', 'amount' => '25000.00']]])->assertOk()->json('data');
        $this->assertDatabaseCount('tax_calculations', 0);
        $first = $this->postJson("/api/v1/tax-returns/$id/calculate")->assertOk()->assertJsonPath('data.calculation.result.amount', '20500.00')->json('data');
        $this->assertJsonStringEqualsJsonString(json_encode($guest), json_encode($first['calculation']));
        $this->assertDatabaseCount('tax_calculations', 1);
        $this->assertDatabaseCount('tax_calculation_brackets', 8);
        $income = TaxReturn::findOrFail($id)->incomes()->firstOrFail();
        $this->patchJson("/api/v1/tax-returns/$id/incomes/$income->id", ['gross_amount' => '800000'])->assertOk();
        $second = $this->postJson("/api/v1/tax-returns/$id/calculate")->assertOk()->json('data');
        $this->assertNotSame($first['id'], $second['id']);
        $this->assertDatabaseCount('tax_calculations', 2);
        $this->assertDatabaseCount('tax_calculation_brackets', 16);
        $history = $this->getJson("/api/v1/tax-returns/$id/calculations/".$first['id'])->assertOk()->json('data.calculation');
        $this->assertJsonStringEqualsJsonString(json_encode($guest), json_encode($history));
        $this->getJson("/api/v1/tax-returns/$id/calculations?per_page=1")->assertOk()
            ->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.id', $second['id']);
        $this->getJson("/api/v1/tax-returns/$id")->assertOk()->assertJsonPath('data.latest_calculation.id', $second['id']);
        $this->assertDatabaseCount('tax_scenarios', 0);
    }

    public function test_fractional_satang_survives_storage_without_rounding(): void
    {
        $id = $this->saved('250000.01');
        $response = $this->postJson("/api/v1/tax-returns/$id/calculate")->assertOk()->assertJsonPath('data.calculation.progressive_tax.total', '0.0005');
        $calculation = TaxCalculation::findOrFail($response->json('data.id'));
        $this->assertNull($calculation->calculated_tax);
        $this->assertNull($calculation->brackets()->where('sort_order', 2)->firstOrFail()->tax_amount);
        $this->getJson("/api/v1/tax-returns/$id/calculations/$calculation->id")->assertOk()
            ->assertJsonPath('data.calculation.progressive_tax.total', '0.0005')->assertJsonPath('data.brackets.1.tax', '0.0005');
    }

    public function test_complete_freezes_inputs_and_duplicate_copies_every_input_but_not_history(): void
    {
        $id = $this->saved();
        $this->putJson("/api/v1/tax-returns/$id/profile", ['marital_status' => 'married'])->assertOk();
        $this->putJson("/api/v1/tax-returns/$id/spouse", ['has_income' => false])->assertOk();
        $this->postJson("/api/v1/tax-returns/$id/dependents", ['relation_type' => 'child', 'child_type' => 'legitimate', 'birth_order' => 1, 'birth_date' => '2015-03-01', 'eligible' => true])->assertCreated();
        $this->postJson("/api/v1/tax-returns/$id/allowances", ['code' => 'PERSONAL', 'input_amount' => '100'])->assertCreated();
        $version = TaxRuleVersion::where('version', '2568.3')->firstOrFail();
        DB::table('donation_rules')->insert(['tax_year_id' => $version->tax_year_id, 'rule_version_id' => $version->id,
            'code' => 'TEST_UNVERIFIED', 'donation_type' => 'general', 'active' => true]);
        $this->postJson("/api/v1/tax-returns/$id/donations", ['donation_code' => 'TEST_UNVERIFIED', 'input_amount' => '100'])->assertCreated();
        $this->postJson("/api/v1/tax-returns/$id/complete")->assertOk()->assertJsonPath('message', 'Simulation completed — เสร็จสิ้นการทดลอง');
        $source = TaxReturn::findOrFail($id);
        $this->assertSame('completed', $source->status);
        $this->assertNotNull($source->completed_at);
        $this->assertSame(1, $source->calculations()->count());
        $this->putJson("/api/v1/tax-returns/$id/profile", ['marital_status' => 'single'])->assertStatus(409);
        $this->putJson("/api/v1/tax-returns/$id/spouse", ['has_income' => true])->assertStatus(409);
        $this->deleteJson("/api/v1/tax-returns/$id/spouse")->assertStatus(409);
        foreach (['dependents', 'incomes', 'allowances', 'donations', 'withholdings'] as $relation) {
            $child = $source->$relation()->firstOrFail();
            $this->patchJson("/api/v1/tax-returns/$id/$relation/$child->id", [])->assertStatus(409);
            $this->deleteJson("/api/v1/tax-returns/$id/$relation/$child->id")->assertStatus(409);
        }
        $copy = $this->postJson("/api/v1/tax-returns/$id/duplicate", ['name' => 'Synthetic copy'])->assertCreated()
            ->assertJsonPath('data.status', 'draft')->assertJsonPath('data.completed_at', null)->assertJsonPath('data.latest_calculation', null)->json('data.id');
        $new = TaxReturn::findOrFail($copy);
        foreach (['profile', 'spouse', 'dependents', 'incomes', 'allowances', 'donations', 'withholdings'] as $relation) {
            $this->assertSame($source->$relation()->count(), $new->$relation()->count());
        }
        $this->assertSame($source->rule_version_id, $new->rule_version_id);
        $this->assertSame(0, $new->calculations()->count());
        $this->postJson("/api/v1/tax-returns/$id/complete")->assertStatus(409);
    }

    public function test_failed_completion_rolls_back_history_and_status(): void
    {
        $id = $this->saved();
        $this->mock(TaxCalculationService::class)->shouldReceive('calculate')->once()->andThrow(new \RuntimeException('Synthetic engine failure'));
        $this->postJson("/api/v1/tax-returns/$id/complete")->assertStatus(500);
        $this->assertDatabaseCount('tax_calculations', 0);
        $this->assertDatabaseHas('tax_returns', ['id' => $id, 'status' => 'draft', 'completed_at' => null]);
    }

    public function test_missing_income_cannot_complete(): void
    {
        $id = $this->saved();
        TaxReturn::findOrFail($id)->incomes()->delete();
        $this->postJson("/api/v1/tax-returns/$id/complete")->assertUnprocessable()->assertJsonValidationErrors('incomes');
        $this->assertDatabaseCount('tax_calculations', 0);
    }

    public function test_saved_version_does_not_switch_when_another_version_is_published(): void
    {
        $id = $this->saved();
        $return = TaxReturn::findOrFail($id);
        $new = TaxRuleVersion::factory()->create(['tax_year_id' => $return->tax_year_id, 'version' => '2568.2']);
        $new->update(['status' => 'published']);
        $this->postJson("/api/v1/tax-returns/$id/calculate")->assertOk()->assertJsonPath('data.calculation.rule_version', '2568.3');
        $this->assertSame($return->rule_version_id, TaxCalculation::firstOrFail()->rule_version_id);
    }

    public function test_bracket_write_failure_rolls_back_snapshot_and_completion(): void
    {
        $id = $this->saved();
        TaxCalculationBracket::creating(function ($bracket): void {
            if ($bracket->sort_order === 2) {
                throw new \RuntimeException('Synthetic storage failure');
            }
        });
        try {
            $this->postJson("/api/v1/tax-returns/$id/complete")->assertStatus(500);
            $this->assertDatabaseCount('tax_calculations', 0);
            $this->assertDatabaseCount('tax_calculation_brackets', 0);
            $this->assertDatabaseHas('tax_returns', ['id' => $id, 'status' => 'draft', 'completed_at' => null]);
        } finally {
            Event::forget('eloquent.creating: '.TaxCalculationBracket::class);
        }
    }

    public function test_history_read_never_calls_engine_and_is_scoped_to_parent(): void
    {
        $id = $this->saved();
        $calculation = $this->postJson("/api/v1/tax-returns/$id/calculate")->assertOk()->json('data.id');
        $other = $this->postJson('/api/v1/tax-returns', ['tax_year' => 2568, 'form_code' => 'PND91', 'name' => 'Another'])->assertCreated()->json('data.id');
        $this->mock(TaxCalculationService::class)->shouldNotReceive('calculate');
        $this->getJson("/api/v1/tax-returns/$id/calculations/$calculation")->assertOk();
        $this->getJson("/api/v1/tax-returns/$other/calculations/$calculation")->assertNotFound();
    }
}

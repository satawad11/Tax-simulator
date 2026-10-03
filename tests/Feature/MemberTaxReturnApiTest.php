<?php

namespace Tests\Feature;

use App\Models\TaxReturn;
use App\Models\TaxRuleVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MemberTaxReturnApiTest extends TestCase
{
    use RefreshDatabase;

    private function member(): User
    {
        $this->seed();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    private function draft(string $name = 'Synthetic draft'): int
    {
        return $this->postJson('/api/v1/tax-returns', ['tax_year' => 2568, 'form_code' => 'PND91', 'name' => $name])->assertCreated()->json('data.id');
    }

    public function test_member_routes_require_authentication_even_without_accept_header(): void
    {
        $this->get('/api/v1/tax-returns')->assertUnauthorized();
        $this->postJson('/api/v1/tax-returns', [])->assertUnauthorized();
        $this->putJson('/api/v1/tax-returns/1/profile', [])->assertUnauthorized();
    }

    public function test_create_resume_filters_and_soft_delete(): void
    {
        $user = $this->member();
        $id = $this->draft();
        $other = User::factory()->create();
        TaxReturn::factory()->create(['user_id' => $other->id]);
        $this->getJson('/api/v1/tax-returns')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.per_page', 20);
        $this->patchJson("/api/v1/tax-returns/$id", ['name' => 'Renamed draft', 'current_step' => 4])->assertOk()
            ->assertJsonPath('data.current_step', 4)->assertJsonPath('data.status', 'draft');
        $this->getJson("/api/v1/tax-returns/$id")->assertOk()->assertJsonStructure(['data' => [
            'profile', 'spouse', 'dependents', 'incomes', 'allowances', 'donations', 'withholdings', 'latest_calculation']]);
        $this->getJson('/api/v1/tax-returns?status=draft&tax_year=2568&form=PND91&q=Renamed&per_page=1')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $id);
        $this->getJson('/api/v1/tax-returns?status=completed')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/tax-returns?per_page=101')->assertUnprocessable();
        $this->deleteJson("/api/v1/tax-returns/$id")->assertNoContent();
        $this->assertSoftDeleted('tax_returns', ['id' => $id]);
        $this->getJson("/api/v1/tax-returns/$id")->assertNotFound();
    }

    public function test_ownership_policy_and_all_member_routes_hide_other_users_data(): void
    {
        $owner = $this->member();
        $id = $this->draft();
        $return = TaxReturn::findOrFail($id);
        $other = User::factory()->create();
        foreach (['view', 'update', 'delete', 'calculate'] as $ability) {
            $this->assertTrue(Gate::forUser($owner)->allows($ability, $return));
            $this->assertSame(404, Gate::forUser($other)->inspect($ability, $return)->status());
        }
        Sanctum::actingAs($other);
        foreach ([['get', ''], ['patch', ''], ['delete', ''], ['post', '/calculate'], ['get', '/calculations'],
            ['get', '/calculations/1'], ['post', '/complete'], ['post', '/duplicate'],
            ['put', '/profile'], ['put', '/spouse'], ['delete', '/spouse']] as [$method,$suffix]) {
            $this->{$method.'Json'}("/api/v1/tax-returns/$id$suffix", [])->assertNotFound();
        }
        foreach (['dependents', 'incomes', 'allowances', 'donations', 'withholdings'] as $relation) {
            $this->postJson("/api/v1/tax-returns/$id/$relation", [])->assertNotFound();
            $this->patchJson("/api/v1/tax-returns/$id/$relation/1", [])->assertNotFound();
            $this->deleteJson("/api/v1/tax-returns/$id/$relation/1")->assertNotFound();
        }
        $this->getJson('/api/v1/tax-returns')->assertOk()->assertJsonCount(0, 'data');
        $this->assertDatabaseHas('tax_returns', ['id' => $id, 'name' => 'Synthetic draft', 'deleted_at' => null]);
    }

    public static function inputs(): array
    {
        return [
            'dependent' => ['dependents', ['relation_type' => 'child', 'child_type' => 'legitimate', 'birth_order' => 1, 'birth_date' => '2020-05-12', 'eligible' => true], ['relation_type' => 'father', 'eligible' => true], 'relation_type', 'father'],
            'income' => ['incomes', ['income_type' => 'SECTION_40_1', 'gross_amount' => '720000', 'exempt_amount' => '0'], ['gross_amount' => '800000'], 'gross_amount', '800000.00'],
            'allowance' => ['allowances', ['code' => 'LIFE_INSURANCE', 'input_amount' => '75000'], ['input_amount' => '50000'], 'input_amount', '50000.00'],
            'donation' => ['donations', ['donation_code' => 'TEST_UNVERIFIED', 'input_amount' => '10000'], ['input_amount' => '9000'], 'input_amount', '9000.00'],
            'withholding' => ['withholdings', ['type' => 'withholding', 'payer_name' => 'Synthetic payer', 'payer_tax_id' => null, 'amount' => '25000'], ['amount' => '30000'], 'amount', '30000.00'],
        ];
    }

    private function donationFixture(): void
    {
        $version = TaxRuleVersion::where('version', '2568.3')->firstOrFail();
        DB::table('donation_rules')->insert(['tax_year_id' => $version->tax_year_id, 'rule_version_id' => $version->id,
            'code' => 'TEST_UNVERIFIED', 'donation_type' => 'general', 'active' => true]);
    }

    #[DataProvider('inputs')]
    public function test_nested_crud_and_parent_scoping(string $relation, array $input, array $patch, string $field, mixed $expected): void
    {
        $this->member();
        $id = $this->draft();
        $second = $this->draft('Other draft');
        $this->donationFixture();
        $child = $this->postJson("/api/v1/tax-returns/$id/$relation", $input)->assertCreated()->json('data.id');
        $this->patchJson("/api/v1/tax-returns/$id/$relation/$child", $patch)->assertOk()->assertJsonPath("data.$field", $expected);
        $this->patchJson("/api/v1/tax-returns/$second/$relation/$child", $patch)->assertNotFound();
        $this->deleteJson("/api/v1/tax-returns/$second/$relation/$child")->assertNotFound();
        $this->assertDatabaseHas('tax_return_'.$relation, ['id' => $child, 'tax_return_id' => $id]);
        $this->deleteJson("/api/v1/tax-returns/$id/$relation/$child")->assertNoContent();
        $this->assertDatabaseMissing('tax_return_'.$relation, ['id' => $child]);
    }

    public function test_profile_and_spouse_are_replaced_and_spouse_can_be_deleted(): void
    {
        $this->member();
        $id = $this->draft();
        $this->putJson("/api/v1/tax-returns/$id/profile", ['birth_date' => '1990-05-20', 'marital_status' => 'single', 'filing_status' => null])->assertOk();
        $this->putJson("/api/v1/tax-returns/$id/profile", ['marital_status' => 'married'])->assertOk()->assertJsonPath('data.birth_date', null);
        $this->assertDatabaseCount('tax_return_profiles', 1);
        $this->putJson("/api/v1/tax-returns/$id/spouse", ['birth_date' => '1992-01-10', 'has_income' => false, 'filing_status' => 'combined'])->assertOk();
        $this->getJson("/api/v1/tax-returns/$id")->assertOk()->assertJsonPath('data.spouse.filing_status', 'combined');
        $this->deleteJson("/api/v1/tax-returns/$id/spouse")->assertNoContent();
        $this->assertDatabaseCount('tax_return_spouses', 0);
    }

    public function test_unsafe_fields_unsupported_codes_and_partial_income_invariants_are_rejected(): void
    {
        $this->member();
        $id = $this->draft();
        foreach (['user_id', 'tax_year_id', 'tax_form_id', 'rule_version_id', 'status'] as $field) {
            $this->patchJson("/api/v1/tax-returns/$id", [$field => 1])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->postJson('/api/v1/tax-returns', ['tax_year' => 2568, 'form_code' => 'PND92', 'name' => 'Unsupported'])->assertUnprocessable();
        $this->postJson("/api/v1/tax-returns/$id/incomes", ['income_type' => 'SECTION_40_8', 'gross_amount' => '100'])->assertUnprocessable();
        $income = $this->postJson("/api/v1/tax-returns/$id/incomes", ['income_type' => 'SECTION_40_1', 'gross_amount' => '100', 'exempt_amount' => '50'])->assertCreated()->json('data.id');
        $this->patchJson("/api/v1/tax-returns/$id/incomes/$income", ['gross_amount' => '40'])->assertUnprocessable()->assertJsonValidationErrors('exempt_amount');
        $this->postJson("/api/v1/tax-returns/$id/allowances", ['code' => 'PERSONAL', 'input_amount' => '100', 'eligible_amount' => '999'])->assertUnprocessable();
        $this->postJson("/api/v1/tax-returns/$id/donations", ['donation_code' => 'NOT_A_DONATION_LINE', 'input_amount' => '100'])->assertUnprocessable();
        $this->postJson("/api/v1/tax-returns/$id/withholdings", ['type' => 'withholding', 'amount' => '-1'])->assertUnprocessable();
        $this->assertDatabaseHas('tax_return_incomes', ['id' => $income, 'gross_amount' => '100.00']);
    }
}

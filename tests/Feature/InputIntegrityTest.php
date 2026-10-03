<?php

namespace Tests\Feature;

use App\Models\TaxRuleVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InputIntegrityTest extends TestCase
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
        return [
            'tax_year' => 2568,
            'form_code' => $form,
            'profile' => ['marital_status' => 'single'],
            'incomes' => [['income_type' => 'SECTION_40_1', 'gross_amount' => '720000.00', 'exempt_amount' => '0.00']],
            'allowances' => [],
            'donations' => [],
            'withholdings' => [],
        ];
    }

    public function test_guest_rejects_duplicate_annual_items_with_stable_thai_errors(): void
    {
        $cases = [
            ['allowances', [['code' => 'HOME_LOAN_INTEREST', 'amount' => '1'], ['code' => 'HOME_LOAN_INTEREST', 'amount' => '2']], 'allowances.1.code', 'DUPLICATE_ALLOWANCE_CODE'],
            ['donations', [['code' => 'GENERAL_DONATION', 'amount' => '1'], ['code' => 'GENERAL_DONATION', 'amount' => '2']], 'donations.1.code', 'DUPLICATE_DONATION_CODE'],
            ['withholdings', [['type' => 'pnd93', 'amount' => '1'], ['type' => 'pnd93', 'amount' => '2']], 'withholdings.1.type', 'DUPLICATE_PREPAYMENT_TYPE'],
        ];

        foreach ($cases as [$collection, $items, $attribute, $code]) {
            $payload = $this->payload();
            $payload[$collection] = $items;
            $response = $this->postJson('/api/v1/tax/calculate', $payload)
                ->assertUnprocessable()->assertJsonValidationErrors($attribute);
            $message = $response->json('errors')[$attribute][0];
            $this->assertStringStartsWith($code.':', $message);
            $this->assertMatchesRegularExpression('/[ก-๙]/u', $message);
        }
    }

    public function test_legitimate_repeatable_income_and_withholding_rows_remain_allowed(): void
    {
        $payload = $this->payload();
        $payload['incomes'] = [
            ['income_type' => 'SECTION_40_1', 'description' => 'Employer A', 'gross_amount' => '400000', 'exempt_amount' => '0'],
            ['income_type' => 'SECTION_40_1', 'description' => 'Employer B', 'gross_amount' => '400000', 'exempt_amount' => '0'],
        ];
        $payload['withholdings'] = [
            ['type' => 'withholding', 'amount' => '10000'],
            ['type' => 'withholding', 'amount' => '15000'],
        ];
        $this->postJson('/api/v1/tax/calculate', $payload)->assertOk()
            ->assertJsonPath('data.income.gross_income', '800000.00')->assertJsonPath('data.credits.total', '25000.00');

        $payload = $this->payload('PND90');
        $payload['incomes'] = [
            ['income_type' => 'SECTION_40_5', 'income_subtype' => 'RENT_BUILDING_OR_RAFT', 'description' => 'Property A', 'gross_amount' => '100000', 'exempt_amount' => '0', 'expense_method_selection' => 'percentage'],
            ['income_type' => 'SECTION_40_5', 'income_subtype' => 'RENT_BUILDING_OR_RAFT', 'description' => 'Property B', 'gross_amount' => '200000', 'exempt_amount' => '0', 'expense_method_selection' => 'percentage'],
            ['income_type' => 'SECTION_40_8', 'income_subtype' => 'BUSINESS_COMMERCE_OTHER', 'expense_activity' => 'TABLE2_02_LAND_INSTALMENT_SALE', 'description' => 'Activity A', 'gross_amount' => '100000', 'exempt_amount' => '0', 'expense_method_selection' => 'percentage'],
            ['income_type' => 'SECTION_40_8', 'income_subtype' => 'BUSINESS_COMMERCE_OTHER', 'expense_activity' => 'TABLE2_03_GAMBLING_TABLE_FEES', 'description' => 'Activity B', 'gross_amount' => '100000', 'exempt_amount' => '0', 'expense_method_selection' => 'percentage'],
        ];
        $this->postJson('/api/v1/tax/calculate', $payload)->assertOk()
            ->assertJsonPath('data.income.gross_income', '500000.00');
    }

    public function test_required_family_and_tax_treatment_facts_are_exactly_conditional(): void
    {
        $this->postJson('/api/v1/tax/calculate', $this->payload())->assertOk();

        $payload = $this->payload();
        $payload['dependents'] = [['relation_type' => 'child', 'eligible' => true]];
        $this->postJson('/api/v1/tax/calculate', $payload)->assertUnprocessable()
            ->assertJsonValidationErrors('dependents.0.child_type');

        $payload['dependents'][0] = ['relation_type' => 'child', 'child_type' => 'legitimate', 'eligible' => true];
        $this->postJson('/api/v1/tax/calculate', $payload)->assertUnprocessable()
            ->assertJsonValidationErrors(['dependents.0.birth_order', 'dependents.0.birth_date']);

        $payload = $this->payload('PND90');
        $payload['incomes'] = [['income_type' => 'SECTION_40_8', 'income_subtype' => 'GIFT_OR_SUPPORT_RECEIVED',
            'gross_amount' => '100000', 'exempt_amount' => '0']];
        $this->postJson('/api/v1/tax/calculate', $payload)->assertUnprocessable()
            ->assertJsonValidationErrors('incomes.0.tax_treatment');
    }

    public function test_duplicate_dependents_are_rejected_only_for_safe_keys(): void
    {
        $payload = $this->payload();
        $payload['dependents'] = [
            ['relation_type' => 'father', 'eligible' => true],
            ['relation_type' => 'father', 'eligible' => true],
        ];
        $response = $this->postJson('/api/v1/tax/calculate', $payload)->assertUnprocessable()
            ->assertJsonValidationErrors('dependents.1.relation_type');
        $message = $response->json('errors')['dependents.1.relation_type'][0];
        $this->assertStringStartsWith('DUPLICATE_DEPENDENT:', $message);

        $payload['dependents'] = [
            ['relation_type' => 'child', 'child_type' => 'adopted', 'birth_date' => '2020-01-01', 'eligible' => true],
            ['relation_type' => 'child', 'child_type' => 'adopted', 'birth_date' => '2020-01-01', 'eligible' => true],
        ];
        $this->postJson('/api/v1/tax/calculate', $payload)->assertOk();
    }

    public function test_member_create_update_resume_and_duplicate_workflow_preserve_integrity(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $id = $this->postJson('/api/v1/tax-returns', [
            'tax_year' => 2568, 'form_code' => 'PND91', 'name' => 'Integrity draft',
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/tax-returns/$id/allowances", ['code' => 'HOME_LOAN_INTEREST', 'input_amount' => '1000'])->assertCreated();
        $this->postJson("/api/v1/tax-returns/$id/allowances", ['code' => 'HOME_LOAN_INTEREST', 'input_amount' => '2000'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson("/api/v1/tax-returns/$id/donations", ['donation_code' => 'GENERAL_DONATION', 'input_amount' => '1000'])->assertCreated();
        $this->postJson("/api/v1/tax-returns/$id/donations", ['donation_code' => 'GENERAL_DONATION', 'input_amount' => '2000'])
            ->assertUnprocessable()->assertJsonValidationErrors('donation_code');
        $this->postJson("/api/v1/tax-returns/$id/withholdings", ['type' => 'pnd94', 'amount' => '1000'])->assertCreated();
        $this->postJson("/api/v1/tax-returns/$id/withholdings", ['type' => 'pnd94', 'amount' => '2000'])
            ->assertUnprocessable()->assertJsonValidationErrors('type');
        $this->postJson("/api/v1/tax-returns/$id/dependents", ['relation_type' => 'father', 'eligible' => true])->assertCreated();
        $this->postJson("/api/v1/tax-returns/$id/dependents", ['relation_type' => 'father', 'eligible' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('relation_type');

        $this->getJson("/api/v1/tax-returns/$id")->assertOk()
            ->assertJsonCount(1, 'data.allowances')->assertJsonCount(1, 'data.donations')
            ->assertJsonCount(1, 'data.withholdings')->assertJsonCount(1, 'data.dependents');
        $copy = $this->postJson("/api/v1/tax-returns/$id/duplicate", ['name' => 'Integrity copy'])
            ->assertCreated()->assertJsonCount(1, 'data.allowances')->assertJsonCount(1, 'data.donations')
            ->assertJsonCount(1, 'data.withholdings')->assertJsonCount(1, 'data.dependents')->json('data.id');
        $this->postJson("/api/v1/tax-returns/$copy/allowances", ['code' => 'HOME_LOAN_INTEREST', 'input_amount' => '3000'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_ui_contract_exposes_fixed_annual_fields_and_duplicate_review(): void
    {
        $blade = file_get_contents(resource_path('views/simulator/index.blade.php'));
        $javascript = file_get_contents(resource_path('js/simulator-wizard.js'));
        $api = file_get_contents(resource_path('js/api.js'));

        $this->assertStringNotContainsString('data-add-donation', $blade);
        $this->assertStringContainsString('donationTypes.forEach', $javascript);
        $this->assertStringContainsString('refreshUniqueWithholdingOptions', $javascript);
        $this->assertStringContainsString('option.disabled', $javascript);
        $this->assertStringContainsString('พบรายการซ้ำ', $javascript);
        $this->assertStringContainsString('DUPLICATE_PREPAYMENT_TYPE', $api);
        $this->assertStringNotContainsString('name="birth_date" type="date" required', $blade);
    }

    public function test_form_matrices_classify_every_user_input_and_document_duplicate_contract(): void
    {
        $cardinalities = ['SINGLE', 'OPTIONAL_SINGLE', 'REPEATABLE', 'CONDITIONAL_REPEATABLE', 'DERIVED_ONLY'];
        foreach (['PND90_FORM_COVERAGE_MATRIX_2568.md', 'PND91_FORM_COVERAGE_MATRIX_2568.md'] as $file) {
            $lines = file(resource_path("../docs/ui/$file"), FILE_IGNORE_NEW_LINES);
            $this->assertNotFalse($lines);
            $header = collect($lines)->first(fn (string $line): bool => str_starts_with($line, '| Source/page'));
            $this->assertIsString($header);
            $this->assertStringContainsString('INPUT_CARDINALITY', $header);
            $this->assertStringContainsString('REQUIRED_INPUT_CLASS', $header);
            foreach ($lines as $line) {
                if (! str_starts_with($line, '|') || ! preg_match('/\| (USER_INPUT|CONDITIONAL_INPUT) \|/', $line)) {
                    continue;
                }
                $columns = array_map('trim', explode('|', trim($line, '|')));
                $this->assertContains($columns[10], $cardinalities, "$file has an unclassified input row: $line");
                $this->assertNotSame('', $columns[11]);
                $this->assertContains($columns[12], ['REJECT', 'MERGE', 'ALLOW', 'NOT_APPLICABLE']);
                $this->assertNotSame('', $columns[14]);
            }
        }

        $this->assertFileExists(base_path('docs/ui/INPUT_CARDINALITY_AND_DUPLICATE_RULES.md'));
        $this->assertFileExists(base_path('docs/api/DUPLICATE_VALIDATION.md'));
    }

    public function test_published_baseline_and_draft_status_are_unchanged(): void
    {
        $published = TaxRuleVersion::where('version', '2568.3')->firstOrFail();
        $this->assertSame('published', $published->status);
        $this->assertCount(8, $published->brackets);
        $draft = TaxRuleVersion::where('version', '2568.2')->first();
        $this->assertTrue($draft === null || $draft->status === 'draft');
    }
}

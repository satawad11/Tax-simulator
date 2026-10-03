<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Milestone092FormFidelityTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected $seed = true;

    public function test_pnd91_ui_explains_the_source_form_flow(): void
    {
        $this->get('/tax-simulator/pnd91')->assertOk()
            ->assertSee('ข้อ ก สำหรับเงินได้ตามมาตรา 40(1)', false)
            ->assertSee('ผู้จ่ายเงินได้', false)
            ->assertSee('ภาษีที่ชำระไว้', false)
            ->assertSee('ตรวจสอบและผล', false);
    }

    public function test_pnd90_metadata_exposes_subtypes_activities_and_holding_periods(): void
    {
        $response = $this->getJson('/api/v1/tax-years/2568/income-types/SECTION_40_8')->assertOk()
            ->assertJsonPath('data.form_sections.PND90', 'ข้อ 7 รายการเงินได้ตามมาตรา 40(8)')
            ->assertJsonCount(5, 'data.expense_rules');

        $rules = collect($response->json('data.expense_rules'))->keyBy('income_subtype');
        $this->assertSame('expense_activity', $rules['BUSINESS_COMMERCE_OTHER']['keyed_by']);
        $this->assertCount(44, $rules['BUSINESS_COMMERCE_OTHER']['keys']);
        $this->assertNotEmpty($rules['BUSINESS_COMMERCE_OTHER']['keys'][0]['label']);
        $this->assertSame('holding_years', $rules['IMMOVABLE_PROPERTY_NON_TRADE']['keyed_by']);
    }

    public function test_guided_ui_contains_supported_detail_fields_and_disables_unsupported_credits(): void
    {
        $javascript = file_get_contents(resource_path('js/simulator-wizard.js'));
        $pnd90 = $this->get('/tax-simulator/pnd90')->assertOk();

        foreach (['income_subtype', 'expense_activity', 'holding_years', 'expense_method_selection',
            'actual_expense', 'tax_treatment'] as $field) {
            $this->assertStringContainsString("name=\"$field\"", $javascript);
        }
        $this->assertStringContainsString('value="foreign_tax_credit" disabled', $javascript);
        $this->assertStringContainsString('รายการนี้มีในแบบ แต่ระบบจำลองรุ่นปัจจุบันยังไม่รองรับ', $javascript);
        $this->assertStringContainsString('form_sections', $javascript);
        $this->assertStringContainsString('data-jump-step', $javascript);
        $this->assertStringContainsString("withholding: 'ภาษีหัก ณ ที่จ่าย'", $javascript);
        $pnd90->assertSee('เครดิตภาษีเงินปันผลในข้อ 3', false)
            ->assertSee('เงินได้จากการขายอสังหาริมทรัพย์ที่เลือกเสียภาษีแยกในข้อ 8', false)
            ->assertSee('เงินได้ที่เลือกไม่นำมารวมคำนวณในข้อ 10', false);
    }

    public function test_validation_paths_are_presented_with_thai_field_names(): void
    {
        $javascript = file_get_contents(resource_path('js/api.js'));

        $this->assertStringContainsString('วิธีหักค่าใช้จ่ายของเงินได้รายการที่', $javascript);
        $this->assertStringContainsString('กิจกรรมตามตารางที่ 2 ของเงินได้รายการที่', $javascript);
        $this->assertStringContainsString('จำนวนปีถือครองของเงินได้รายการที่', $javascript);

        // M10 re-run: a message that falls through the translation table still reaches the reader,
        // but without the internal constant the backend prefixed it with.
        $this->assertStringContainsString("replace(/^[A-Z][A-Z0-9_]{3,}:\\s*/, '')", $javascript);
    }

    public function test_pnd90_only_minimum_tax_label_is_guarded_by_form_code(): void
    {
        $javascript = file_get_contents(resource_path('js/tax-result.js'));

        $this->assertStringContainsString("result.form_code === 'PND90' && result.minimum_tax", $javascript);
    }

    public function test_member_draft_round_trips_every_supported_detailed_income_field(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $returnId = $this->postJson('/api/v1/tax-returns', [
            'tax_year' => 2568,
            'form_code' => 'PND90',
            'name' => 'M9.2 resume fidelity',
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/tax-returns/$returnId/incomes", [
            'income_type' => 'SECTION_40_8',
            'income_subtype' => 'BUSINESS_COMMERCE_OTHER',
            'expense_activity' => 'TABLE2_09_HOTEL_OR_RESTAURANT',
            'expense_method_selection' => 'actual',
            'actual_expense' => '125000.00',
            'gross_amount' => '500000.00',
            'exempt_amount' => '0.00',
            'description' => 'กิจการตัวอย่าง',
        ])->assertCreated();

        $this->getJson("/api/v1/tax-returns/$returnId")->assertOk()
            ->assertJsonPath('data.incomes.0.income_subtype', 'BUSINESS_COMMERCE_OTHER')
            ->assertJsonPath('data.incomes.0.expense_activity', 'TABLE2_09_HOTEL_OR_RESTAURANT')
            ->assertJsonPath('data.incomes.0.expense_method_selection', 'actual')
            ->assertJsonPath('data.incomes.0.actual_expense', '125000.00');
    }

    public function test_guest_metadata_and_ui_requests_do_not_persist_a_return(): void
    {
        $this->get('/tax-simulator/pnd90')->assertOk();
        $this->getJson('/api/v1/tax-years/2568/income-types/SECTION_40_8')->assertOk();

        $this->assertDatabaseCount('tax_returns', 0);
        $this->assertDatabaseCount('tax_calculations', 0);
    }

    public function test_optional_income_source_description_may_be_left_blank(): void
    {
        $this->postJson('/api/v1/tax/calculate', [
            'tax_year' => 2568,
            'form_code' => 'PND90',
            'profile' => ['birth_date' => '1985-02-20', 'marital_status' => 'single'],
            'incomes' => [[
                'income_type' => 'SECTION_40_1',
                'description' => null,
                'gross_amount' => '500000.00',
                'exempt_amount' => '0.00',
            ]],
        ])->assertOk();
    }
}

<?php

namespace Tests\Feature;

use App\Models\TaxReturn;
use App\Models\User;
use App\Services\Tax\ExpenseActivityCatalogue;
use App\ValueObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Milestone 07.4 — the three ภ.ง.ด.90 subcategories the form prints with a blank percentage.
 *
 * Source: docs/tax-source/PND90-2568-filing-instructions.pdf — page 3 for the ข้อ 4 rent
 * classes and the ข้อ 7 item 3 (2) holding-period bands, page 17 ตารางที่ 2 for the ข้อ 7 item 1
 * activities.
 */
class Pnd90ExpenseSubtypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /** @param list<array<string, mixed>> $incomes */
    private function calculate(array $incomes): TestResponse
    {
        return $this->postJson('/api/v1/tax/calculate', ['tax_year' => 2568, 'form_code' => 'PND90',
            'incomes' => $incomes, 'allowances' => [], 'donations' => [], 'withholdings' => []]);
    }

    // ------------------------------------------------- ข้อ 4 — the five rent classes

    /** @return array<string, array{string, string}> */
    public static function rentClasses(): array
    {
        // Every class is หักค่าใช้จ่ายเป็นการเหมา on 1,000,000 of rent.
        return [
            'บ้าน โรงเรือน สิ่งปลูกสร้าง หรือแพ (ก) 30%' => ['RENT_BUILDING_OR_RAFT', '300000.00'],
            'ที่ดินที่ใช้ในการเกษตรกรรม (ข) 20%' => ['RENT_LAND_AGRICULTURAL', '200000.00'],
            'ที่ดินที่มิได้ใช้ในการเกษตรกรรม (ค) 15%' => ['RENT_LAND_NON_AGRICULTURAL', '150000.00'],
            'ยานพาหนะ (ง) 30%' => ['RENT_VEHICLE', '300000.00'],
            'ทรัพย์สินอย่างอื่น (จ) 10%' => ['RENT_OTHER_PROPERTY', '100000.00'],
        ];
    }

    #[DataProvider('rentClasses')]
    public function test_each_rent_class_deducts_the_rate_the_booklet_prints(string $subtype, string $expected): void
    {
        $this->calculate([['income_type' => 'SECTION_40_5', 'gross_amount' => '1000000.00',
            'income_subtype' => $subtype, 'expense_method_selection' => 'percentage']])->assertOk()
            ->assertJsonPath('data.expenses.total', $expected)
            ->assertJsonPath('data.expenses.items.0.rule_status', 'VERIFIED');
    }

    public function test_a_rent_class_still_offers_the_actual_expense_checkbox(): void
    {
        // The form prints `หักค่าใช้จ่าย ☐ ร้อยละ ............ ☐ จริง` on ข้อ 4 items (2)–(4).
        $this->calculate([['income_type' => 'SECTION_40_5', 'gross_amount' => '1000000.00',
            'income_subtype' => 'RENT_VEHICLE', 'expense_method_selection' => 'actual',
            'actual_expense' => '750000.00']])->assertOk()
            ->assertJsonPath('data.expenses.total', '750000.00');

        $this->calculate([['income_type' => 'SECTION_40_5', 'gross_amount' => '1000000.00',
            'income_subtype' => 'RENT_VEHICLE']])->assertUnprocessable()
            ->assertJsonValidationErrors('incomes.0.expense_method_selection');
    }

    public function test_the_retired_rent_other_subtype_is_no_longer_accepted(): void
    {
        $this->calculate([['income_type' => 'SECTION_40_5', 'gross_amount' => '1000000.00',
            'income_subtype' => 'RENT_OTHER', 'expense_method_selection' => 'percentage']])
            ->assertUnprocessable()->assertJsonValidationErrors('incomes.0.income_subtype');
    }

    // ------------------------------------------------- ข้อ 7 item 1 — ตารางที่ 2

    public function test_a_table_two_activity_deducts_sixty_percent(): void
    {
        $this->calculate([['income_type' => 'SECTION_40_8', 'gross_amount' => '1000000.00',
            'income_subtype' => 'BUSINESS_COMMERCE_OTHER', 'expense_activity' => 'TABLE2_09_HOTEL_OR_RESTAURANT',
            'expense_method_selection' => 'percentage']])->assertOk()
            ->assertJsonPath('data.expenses.total', '600000.00')
            ->assertJsonPath('data.expenses.items.0.expense_activity', 'TABLE2_09_HOTEL_OR_RESTAURANT')
            ->assertJsonPath('data.expenses.items.0.percentage', '60.0000')
            ->assertJsonPath('data.expenses.items.0.rule_status', 'VERIFIED');
    }

    /** @return array<string, array{string, string, string}> */
    public static function performerBands(): array
    {
        // (ก) 60% of the first 300,000, (ข) 40% above it, together capped at 600,000.
        return [
            'inside the first band' => ['200000.00', '120000.00', '120000.00'],
            'exactly at the band boundary' => ['300000.00', '180000.00', '180000.00'],
            'across both bands' => ['500000.00', '260000.00', '260000.00'],
            // 180,000 + 40% of 1,500,000 = 780,000, above the printed 600,000 ceiling.
            'above the combined ceiling' => ['1800000.00', '780000.00', '600000.00'],
        ];
    }

    #[DataProvider('performerBands')]
    public function test_the_performer_rate_keeps_its_bands_and_its_combined_ceiling(string $gross,
        string $beforeCeiling, string $expected): void
    {
        $response = $this->calculate([['income_type' => 'SECTION_40_8', 'gross_amount' => $gross,
            'income_subtype' => 'BUSINESS_COMMERCE_OTHER', 'expense_activity' => ExpenseActivityCatalogue::PERFORMER,
            'expense_method_selection' => 'percentage']])->assertOk()
            ->assertJsonPath('data.expenses.total', $expected)
            ->assertJsonPath('data.expenses.items.0.method', 'tiered_or_actual')
            ->assertJsonPath('data.expenses.items.0.maximum_amount', '600000.00');

        // The bands are reported as they are printed, never flattened into one blended rate,
        // and their sum is what the combined ceiling is then applied to.
        $tiers = $response->json('data.expenses.items.0.tiers');
        $this->assertSame(['FIRST_300000', 'ABOVE_300000'], array_column($tiers, 'tier_code'));
        $this->assertSame(['60.0000', '40.0000'], array_column($tiers, 'percentage'));
        $this->assertSame(['300000.00', null], array_column($tiers, 'threshold_amount'));
        $summed = array_reduce(array_column($tiers, 'eligible_amount'),
            fn (Money $carry, string $band): Money => $carry->add(new Money($band)), new Money);
        $this->assertSame($beforeCeiling, (string) $summed);
    }

    public function test_the_unlisted_activity_offers_only_the_actual_expense(): void
    {
        // ตารางที่ 2 row (44) — "ให้หักค่าใช้จ่ายจริงตามความจำเป็นและสมควร".
        $this->calculate([['income_type' => 'SECTION_40_8', 'gross_amount' => '1000000.00',
            'income_subtype' => 'BUSINESS_COMMERCE_OTHER', 'expense_activity' => ExpenseActivityCatalogue::UNLISTED,
            'actual_expense' => '420000.00']])->assertOk()
            ->assertJsonPath('data.expenses.total', '420000.00')
            ->assertJsonPath('data.expenses.items.0.method', 'actual');

        $this->calculate([['income_type' => 'SECTION_40_8', 'gross_amount' => '1000000.00',
            'income_subtype' => 'BUSINESS_COMMERCE_OTHER', 'expense_activity' => ExpenseActivityCatalogue::UNLISTED,
            'expense_method_selection' => 'percentage']])->assertUnprocessable()
            ->assertJsonValidationErrors('incomes.0.expense_method_selection');
    }

    public function test_an_absent_or_unknown_activity_is_a_422_not_a_default_rate(): void
    {
        $this->calculate([['income_type' => 'SECTION_40_8', 'gross_amount' => '1000000.00',
            'income_subtype' => 'BUSINESS_COMMERCE_OTHER', 'expense_method_selection' => 'percentage']])
            ->assertUnprocessable()->assertJsonValidationErrors('incomes.0.expense_activity');

        $this->calculate([['income_type' => 'SECTION_40_8', 'gross_amount' => '1000000.00',
            'income_subtype' => 'BUSINESS_COMMERCE_OTHER', 'expense_activity' => 'TABLE2_99_INVENTED',
            'expense_method_selection' => 'percentage']])
            ->assertUnprocessable()->assertJsonValidationErrors('incomes.0.expense_activity');
    }

    public function test_an_activity_on_a_category_that_takes_none_is_rejected(): void
    {
        $this->calculate([['income_type' => 'SECTION_40_1', 'gross_amount' => '500000.00',
            'expense_activity' => 'TABLE2_09_HOTEL_OR_RESTAURANT']])
            ->assertUnprocessable()->assertJsonValidationErrors('incomes.0.expense_activity');
    }

    public function test_two_activities_are_two_groups_each_with_its_own_rate(): void
    {
        $response = $this->calculate([
            ['income_type' => 'SECTION_40_8', 'gross_amount' => '1000000.00', 'income_subtype' => 'BUSINESS_COMMERCE_OTHER',
                'expense_activity' => 'TABLE2_09_HOTEL_OR_RESTAURANT', 'expense_method_selection' => 'percentage'],
            ['income_type' => 'SECTION_40_8', 'gross_amount' => '300000.00', 'income_subtype' => 'BUSINESS_COMMERCE_OTHER',
                'expense_activity' => ExpenseActivityCatalogue::PERFORMER, 'expense_method_selection' => 'percentage'],
        ])->assertOk()
            // 60% of 1,000,000 plus 60% of 300,000.
            ->assertJsonPath('data.expenses.total', '780000.00')
            ->assertJsonCount(2, 'data.expenses.items');

        $this->assertSame(['TABLE2_09_HOTEL_OR_RESTAURANT', ExpenseActivityCatalogue::PERFORMER],
            array_column($response->json('data.expenses.items'), 'expense_activity'));
    }

    // ------------------------------------------------- ข้อ 7 item 3 (2) — holding period

    /** @return array<string, array{int, string}> */
    public static function holdingBands(): array
    {
        // 1,000,000 of income at 92/84/77/71/65/60/55/50 percent.
        return [
            '1 year' => [1, '920000.00'],
            '2 years' => [2, '840000.00'],
            '3 years' => [3, '770000.00'],
            '4 years' => [4, '710000.00'],
            '5 years' => [5, '650000.00'],
            '6 years' => [6, '600000.00'],
            '7 years' => [7, '550000.00'],
            '8 years' => [8, '500000.00'],
            '9 years is still the open band' => [9, '500000.00'],
            'beyond the ten-year ceiling' => [30, '500000.00'],
        ];
    }

    #[DataProvider('holdingBands')]
    public function test_each_holding_period_band_deducts_its_printed_rate(int $years, string $expected): void
    {
        $this->calculate([['income_type' => 'SECTION_40_8', 'gross_amount' => '1000000.00',
            'income_subtype' => 'IMMOVABLE_PROPERTY_NON_TRADE', 'holding_years' => $years,
            'expense_method_selection' => 'percentage']])->assertOk()
            ->assertJsonPath('data.expenses.total', $expected)
            ->assertJsonPath('data.expenses.items.0.holding_years', $years)
            ->assertJsonPath('data.expenses.items.0.rule_status', 'VERIFIED');
    }

    public function test_an_absent_or_invalid_holding_period_is_a_422(): void
    {
        $this->calculate([['income_type' => 'SECTION_40_8', 'gross_amount' => '1000000.00',
            'income_subtype' => 'IMMOVABLE_PROPERTY_NON_TRADE', 'expense_method_selection' => 'percentage']])
            ->assertUnprocessable()->assertJsonValidationErrors('incomes.0.holding_years');

        $this->calculate([['income_type' => 'SECTION_40_8', 'gross_amount' => '1000000.00',
            'income_subtype' => 'IMMOVABLE_PROPERTY_NON_TRADE', 'holding_years' => 0,
            'expense_method_selection' => 'percentage']])
            ->assertUnprocessable()->assertJsonValidationErrors('incomes.0.holding_years');
    }

    public function test_a_holding_period_on_a_category_that_takes_none_is_rejected(): void
    {
        $this->calculate([['income_type' => 'SECTION_40_1', 'gross_amount' => '500000.00', 'holding_years' => 3]])
            ->assertUnprocessable()->assertJsonValidationErrors('incomes.0.holding_years');
    }

    // ------------------------------------------------- mixed, and member parity

    public function test_a_mixed_pnd90_return_prices_every_new_subcategory_in_one_calculation(): void
    {
        $response = $this->calculate([
            ['income_type' => 'SECTION_40_1', 'gross_amount' => '600000.00'],
            ['income_type' => 'SECTION_40_5', 'gross_amount' => '200000.00',
                'income_subtype' => 'RENT_LAND_AGRICULTURAL', 'expense_method_selection' => 'percentage'],
            ['income_type' => 'SECTION_40_8', 'gross_amount' => '500000.00', 'income_subtype' => 'BUSINESS_COMMERCE_OTHER',
                'expense_activity' => 'TABLE2_17_TRANSPORT_BY_VEHICLE', 'expense_method_selection' => 'percentage'],
            ['income_type' => 'SECTION_40_8', 'gross_amount' => '400000.00',
                'income_subtype' => 'IMMOVABLE_PROPERTY_NON_TRADE', 'holding_years' => 5,
                'expense_method_selection' => 'percentage'],
        ])->assertOk()
            ->assertJsonPath('data.income.gross_income', '1700000.00')
            // 100,000 (capped) + 40,000 (20%) + 300,000 (60%) + 260,000 (65%).
            ->assertJsonPath('data.expenses.total', '700000.00')
            ->assertJsonPath('data.income_after_expense', '1000000.00');

        $this->assertSame(['VERIFIED', 'VERIFIED', 'VERIFIED', 'VERIFIED'],
            array_column($response->json('data.expenses.items'), 'rule_status'));
    }

    public function test_a_member_return_saves_the_new_facts_and_matches_the_guest_result(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $id = $this->postJson('/api/v1/tax-returns', ['tax_year' => 2568, 'form_code' => 'PND90',
            'name' => 'M7.4 subtypes'])->assertCreated()->json('data.id');

        $lines = [
            ['income_type' => 'SECTION_40_8', 'gross_amount' => '500000.00', 'income_subtype' => 'BUSINESS_COMMERCE_OTHER',
                'expense_activity' => 'TABLE2_17_TRANSPORT_BY_VEHICLE', 'expense_method_selection' => 'percentage'],
            ['income_type' => 'SECTION_40_8', 'gross_amount' => '400000.00',
                'income_subtype' => 'IMMOVABLE_PROPERTY_NON_TRADE', 'holding_years' => 5,
                'expense_method_selection' => 'percentage'],
        ];
        foreach ($lines as $line) {
            $this->postJson("/api/v1/tax-returns/$id/incomes", $line)->assertCreated();
        }
        $this->getJson("/api/v1/tax-returns/$id")->assertOk()
            ->assertJsonPath('data.incomes.0.expense_activity', 'TABLE2_17_TRANSPORT_BY_VEHICLE')
            ->assertJsonPath('data.incomes.1.holding_years', 5);

        $member = $this->postJson("/api/v1/tax-returns/$id/calculate")->assertOk()->json('data.calculation');
        $guest = $this->calculate($lines)->assertOk()->json('data');

        $this->assertSame('560000.00', $member['expenses']['total']);
        $this->assertSame($guest['expenses'], $member['expenses']);
        $this->assertSame($guest['result'], $member['result']);
        $this->assertSame('draft', TaxReturn::findOrFail($id)->status);
    }

    public function test_a_saved_line_rejects_a_fact_its_category_does_not_print(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $id = $this->postJson('/api/v1/tax-returns', ['tax_year' => 2568, 'form_code' => 'PND90',
            'name' => 'M7.4 guard'])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/tax-returns/$id/incomes", ['income_type' => 'SECTION_40_8',
            'gross_amount' => '100.00', 'income_subtype' => 'BUSINESS_COMMERCE_OTHER'])
            ->assertUnprocessable()->assertJsonValidationErrors('expense_activity');
        $this->postJson("/api/v1/tax-returns/$id/incomes", ['income_type' => 'SECTION_40_1',
            'gross_amount' => '100.00', 'holding_years' => 4])
            ->assertUnprocessable()->assertJsonValidationErrors('holding_years');
        $this->assertDatabaseCount('tax_return_incomes', 0);
    }
}

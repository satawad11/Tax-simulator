<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Entering income sources: choosing the category, and how many sources one return may carry.
 *
 * Two questions sit behind this. Repeating a category is *correct* — two employers both pay
 * 40(1), and the return has to be able to say so — and the engine already aggregates repeated
 * rows into one group before a rule is applied, so the printed ceiling is never multiplied.
 * What was wrong was quieter: a new row arrived with 40(1) already chosen, so a reader adding a
 * row for rent or a fee and going straight to the amount had it taxed under a rule they never
 * picked, and the interface let them add a 101st row the API would refuse.
 */
class IncomeSourceEntryTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function wizard(): string
    {
        return file_get_contents(resource_path('js/simulator-wizard.js'));
    }

    /** @param list<array<string, string>> $incomes */
    private function payload(array $incomes): array
    {
        return ['tax_year' => 2568, 'form_code' => 'PND90', 'incomes' => $incomes,
            'allowances' => [], 'donations' => [], 'withholdings' => []];
    }

    /** @return list<array<string, string>> */
    private function repeated(int $count, string $gross = '100000'): array
    {
        return array_fill(0, $count, ['income_type' => 'SECTION_40_1', 'gross_amount' => $gross]);
    }

    public function test_one_category_may_be_chosen_by_any_number_of_sources(): void
    {
        $this->postJson('/api/v1/tax/calculate', $this->payload($this->repeated(5)))->assertOk()
            // Five sources, one category, one expense group — the lines are kept, not collapsed.
            ->assertJsonCount(1, 'data.income.items')
            ->assertJsonCount(5, 'data.income.items.0.lines')
            ->assertJsonPath('data.income.gross_income', '500000.00')
            ->assertJsonCount(1, 'data.expenses.items');
    }

    public function test_the_printed_ceiling_does_not_grow_with_the_number_of_sources(): void
    {
        // ใบแนบ ข้อ 1 prints one line for 40(1) and 40(2) together: 50% capped at 100,000. The cap
        // belongs to the line, so a hundred sources deduct exactly what one source of the same
        // total deducts. Splitting income across rows must never buy a larger deduction.
        $split = $this->postJson('/api/v1/tax/calculate', $this->payload($this->repeated(100)))->assertOk();
        $whole = $this->postJson('/api/v1/tax/calculate',
            $this->payload([['income_type' => 'SECTION_40_1', 'gross_amount' => '10000000']]))->assertOk();

        $split->assertJsonPath('data.expenses.total', '100000.00')
            ->assertJsonPath('data.income.gross_income', '10000000.00');
        $this->assertSame($whole->json('data.expenses.total'), $split->json('data.expenses.total'));
        $this->assertSame($whole->json('data.net_income'), $split->json('data.net_income'));
        $this->assertSame($whole->json('data.result.amount'), $split->json('data.result.amount'));
    }

    public function test_a_hundred_sources_are_accepted_and_the_hundred_and_first_is_refused(): void
    {
        $this->postJson('/api/v1/tax/calculate', $this->payload($this->repeated(100)))->assertOk();
        $this->postJson('/api/v1/tax/calculate', $this->payload($this->repeated(101)))
            ->assertStatus(422)->assertJsonValidationErrors('incomes');
    }

    public function test_the_wizard_states_the_same_limit_the_api_enforces(): void
    {
        $rules = file_get_contents(app_path('Http/Requests/Api/V1/CalculateTaxRequest.php'));
        $this->assertMatchesRegularExpression(
            "/'incomes' => \[[^\]]*'max:100'/", $rules,
            'The API limit moved; the wizard constant below has to move with it.');
        $this->assertStringContainsString('const MAX_INCOME_ROWS = 100;', $this->wizard());
    }

    public function test_the_add_button_stops_at_the_limit_and_says_why(): void
    {
        $wizard = $this->wizard();

        $this->assertStringContainsString('const full = count >= MAX_INCOME_ROWS;', $wizard);
        $this->assertStringContainsString('button.disabled = full', $wizard);
        // A disabled control with no stated reason is a dead end; the note carries the reason and
        // the way forward, and it is announced rather than shown by colour alone.
        $this->assertStringContainsString('data-income-capacity', $wizard);
        $this->assertStringContainsString('data-income-capacity', file_get_contents(
            resource_path('views/simulator/index.blade.php')));
        $this->assertStringContainsString('aria-live="polite"', file_get_contents(
            resource_path('views/simulator/index.blade.php')));
    }

    public function test_a_new_income_row_chooses_no_category_for_the_reader(): void
    {
        $wizard = $this->wizard();

        // The old default. 40(1) deducts 50% capped at 100,000 while 40(5) rent deducts 30%
        // uncapped, so a row left on the default was taxed under a rule nobody chose.
        $this->assertStringNotContainsString('addIncome({ income_type: metadata.incomes[0]?.code })', $wizard);
        $this->assertStringContainsString('— เลือกประเภทเงินได้ —', $wizard);
        $this->assertStringContainsString('<select name="income_type" required>${placeholder}', $wizard);
    }

    public function test_an_unchosen_category_is_caught_on_its_own_step(): void
    {
        $wizard = $this->wizard();

        $this->assertStringContainsString('เลือกประเภทเงินได้ให้ครบทุกแหล่งก่อนไปขั้นตอนถัดไป', $wizard);
        $this->assertStringContainsString("(select) => select.value === ''", $wizard);
    }

    public function test_an_unchosen_row_is_not_described_as_unsupported(): void
    {
        // Falling through to the rule lookup found nothing and reported the category as one the
        // engine cannot calculate — naming a limitation that does not exist, and hiding the one
        // action the reader actually has to take.
        $this->assertStringContainsString(
            'เลือกประเภทเงินได้ก่อน ระบบจึงจะแสดงประเภทย่อย กิจกรรม และวิธีหักค่าใช้จ่ายที่ตรงกับบรรทัดในแบบ',
            $this->wizard());
    }

    public function test_the_api_still_refuses_a_row_with_no_category(): void
    {
        // The browser gate is a courtesy. The rule that decides is on the server.
        $this->postJson('/api/v1/tax/calculate',
            $this->payload([['income_type' => '', 'gross_amount' => '100000']]))
            ->assertStatus(422)->assertJsonValidationErrors('incomes.0.income_type');
    }
}

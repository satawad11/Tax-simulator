<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * ภ.ง.ด.94 is a ภ.ง.ด.90 line and only a ภ.ง.ด.90 line.
 *
 * Found by reading the forms themselves rather than the notes about them. ภ.ง.ด.90 item 15 prints
 * two prepaid lines — ภ.ง.ด.93 and ภ.ง.ด.94 — while ภ.ง.ด.91 line 15 prints only ภ.ง.ด.93. The
 * string "94" does not occur anywhere in the whole ภ.ง.ด.91 form, which follows from what the form
 * is for: ภ.ง.ด.94 is the half-year return for มาตรา 40 (5)–(8), and ภ.ง.ด.91 carries มาตรา 40 (1)
 * alone, so a filer on this form cannot have filed one.
 *
 * The engine accepted it anyway and subtracted it in full, silently: 720,000 of salary came back
 * as 31,500 payable instead of 36,500. The rule had been carried across from ภ.ง.ด.90 — the
 * baseline document justified it by citing "ข้อ 11 item 15", and **ข้อ 11 is a ภ.ง.ด.90 section**.
 * ภ.ง.ด.91 has no ข้อ 11 at all; its calculation lines are numbered 1–22 directly.
 *
 * A credit the form does not print, applied without a warning, is the worst shape of error this
 * product can make: not a refusal, a confident wrong answer in the filer's favour.
 */
class PrepaidCreditBelongsToItsFormTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    /** @param list<array<string, string>> $withholdings */
    private function calculate(string $form, array $withholdings, array $income = []): TestResponse
    {
        return $this->postJson('/api/v1/tax/calculate', [
            'tax_year' => 2568, 'form_code' => $form,
            'profile' => ['birth_date' => '1990-01-15', 'marital_status' => 'single'],
            'incomes' => [$income ?: ['income_type' => 'SECTION_40_1',
                'gross_amount' => '720000.00', 'exempt_amount' => '0.00']],
            'withholdings' => $withholdings,
        ]);
    }

    public function test_pnd94_is_refused_on_pnd91(): void
    {
        $this->calculate('PND91', [['type' => 'pnd94', 'amount' => '5000.00']])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('withholdings.0.type');
    }

    public function test_the_refusal_says_which_form_the_line_belongs_to(): void
    {
        // A filer told only "invalid" would try again. The message names the reason: this line is
        // not on this form, and why it could not be.
        $response = $this->calculate('PND91', [['type' => 'pnd94', 'amount' => '1.00']])->assertStatus(422);

        $this->assertStringContainsString('PND94_NOT_ON_THIS_FORM',
            json_encode($response->json('errors'), JSON_UNESCAPED_UNICODE));
    }

    public function test_the_credit_no_longer_reduces_the_tax_on_pnd91(): void
    {
        /*
         * The figure this defect produced, stated so the regression is unmistakable. 720,000 of
         * salary with the personal allowance is 36,500 of tax; the phantom credit made it 31,500.
         */
        $this->calculate('PND91', [])->assertOk()
            ->assertJsonPath('data.progressive_tax.total', '36500.00')
            ->assertJsonPath('data.result.amount', '36500.00');

        $this->calculate('PND91', [['type' => 'pnd94', 'amount' => '5000.00']])->assertStatus(422);
    }

    public function test_pnd93_is_still_accepted_on_pnd91(): void
    {
        // ภ.ง.ด.91 line 15 does print this one, so it must keep working.
        $this->calculate('PND91', [['type' => 'pnd93', 'amount' => '5000.00']])->assertOk()
            ->assertJsonPath('data.credits.pnd93', '5000.00')
            ->assertJsonPath('data.result.amount', '31500.00');
    }

    public function test_pnd94_is_still_accepted_on_pnd90(): void
    {
        // ภ.ง.ด.90 item 15 prints both lines; narrowing ภ.ง.ด.91 must not narrow ภ.ง.ด.90.
        $this->calculate('PND90', [['type' => 'pnd94', 'amount' => '5000.00']],
            ['income_type' => 'SECTION_40_5', 'income_subtype' => 'RENT_BUILDING_OR_RAFT',
                'gross_amount' => '720000.00', 'exempt_amount' => '0.00',
                'expense_method_selection' => 'percentage'])
            ->assertOk()->assertJsonPath('data.credits.pnd94', '5000.00');
    }

    public function test_a_zero_amount_is_not_worth_refusing(): void
    {
        // Consistent with every other blocked figure in this engine: zero cannot change the tax,
        // so refusing it would be noise.
        $this->calculate('PND91', [['type' => 'pnd94', 'amount' => '0.00']])->assertOk();
    }

    public function test_withholding_is_unaffected_on_both_forms(): void
    {
        // Both forms print ภาษีเงินได้หัก ณ ที่จ่าย; nothing about it changed.
        $this->calculate('PND91', [['type' => 'withholding', 'amount' => '5000.00']])->assertOk()
            ->assertJsonPath('data.credits.withholding', '5000.00');
    }
}

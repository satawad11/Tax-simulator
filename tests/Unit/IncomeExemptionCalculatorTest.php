<?php

namespace Tests\Unit;

use App\Exceptions\TaxMetadataConflictException;
use App\Models\IncomeExemptionRule;
use App\Models\TaxRuleVersion;
use App\Models\TaxYear;
use App\Services\Tax\IncomeExemptionCalculator;
use App\ValueObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The arithmetic of เงินได้ที่ได้รับยกเว้น taken after expenses.
 *
 * The rules exercised here are structure-only fixtures on a draft version — these tests are about
 * the mechanics of each method, not about Thai tax law. The production numbers, and the source
 * lines they come from, are asserted in the seeder's own test.
 */
class IncomeExemptionCalculatorTest extends TestCase
{
    use RefreshDatabase;

    /*
     * The in-memory database is migrated and seeded once, on whichever test boots it first. A
     * class that sorts early and does not ask for the seed leaves every later class facing an
     * empty database, so this is not optional even though the fixtures below are self-contained.
     */
    protected $seed = true;

    private TaxRuleVersion $version;

    protected function setUp(): void
    {
        parent::setUp();
        $year = TaxYear::where('year', 2568)->firstOrFail();
        // A draft: rules belonging to a published version are immutable, so a fixture is built
        // here exactly as an administrator would build one — in a draft.
        $this->version = TaxRuleVersion::create(['tax_year_id' => $year->id, 'version' => 'TEST.1',
            'status' => 'draft', 'description' => 'Structure-only fixture']);
    }

    private function rule(string $code, string $method, array $values = []): IncomeExemptionRule
    {
        return IncomeExemptionRule::create(['tax_year_id' => $this->version->tax_year_id,
            'rule_version_id' => $this->version->id, 'code' => $code, 'name' => $code,
            'method' => $method, 'active' => true, ...$values]);
    }

    /** @param list<array{code: string, amount: string}> $claims */
    private function calculate(array $claims, string $ceiling = '100000000'): array
    {
        return (new IncomeExemptionCalculator)->calculate($claims, $this->version, new Money($ceiling));
    }

    public function test_a_stepped_grant_counts_only_completed_steps(): void
    {
        $this->rule('STEP', IncomeExemptionRule::STEPPED_GRANT,
            ['step_amount' => '1000000', 'grant_per_step' => '10000', 'maximum_amount' => '100000']);

        // "ต่อทุกจำ�นวน 1,000,000 บาท" counts whole units; the form prints no proportion for a
        // part-finished one, so 2,900,000 completes two units and not 2.9.
        foreach ([['0', '0.00'], ['999999', '0.00'], ['1000000', '10000.00'],
            ['2900000', '20000.00'], ['3000000', '30000.00']] as [$paid, $expected]) {
            $result = $this->calculate([['code' => 'STEP', 'amount' => $paid]]);
            $this->assertSame($expected, (string) $result['total'], "paid $paid");
        }
    }

    public function test_a_stepped_grant_stops_at_its_ceiling(): void
    {
        $this->rule('STEP', IncomeExemptionRule::STEPPED_GRANT,
            ['step_amount' => '1000000', 'grant_per_step' => '10000', 'maximum_amount' => '100000']);

        // Ten completed steps reach the ceiling; nothing beyond it is granted however much is paid.
        $this->assertSame('100000.00', (string) $this->calculate([['code' => 'STEP', 'amount' => '10000000']])['total']);
        $this->assertSame('100000.00', (string) $this->calculate([['code' => 'STEP', 'amount' => '99000000']])['total']);
    }

    public function test_a_percentage_rule_without_a_ceiling_grants_the_whole_share(): void
    {
        $this->rule('FULL', IncomeExemptionRule::PERCENTAGE, ['percentage' => '100.0000']);

        $this->assertSame('450000.00', (string) $this->calculate([['code' => 'FULL', 'amount' => '450000']])['total']);
    }

    public function test_the_declared_amount_is_kept_beside_what_the_rule_allowed(): void
    {
        $this->rule('STEP', IncomeExemptionRule::STEPPED_GRANT,
            ['step_amount' => '1000000', 'grant_per_step' => '10000', 'maximum_amount' => '100000']);

        // A filer who paid 2,400,000 is granted 20,000. Reporting only the smaller number would
        // leave them unable to see why, so both travel.
        $item = $this->calculate([['code' => 'STEP', 'amount' => '2400000']])['items'][0];
        $this->assertSame('2400000.00', (string) $item['input_amount']);
        $this->assertSame('20000.00', (string) $item['eligible_amount']);
        $this->assertSame('VERIFIED', $item['rule_status']);
    }

    public function test_exemptions_never_exceed_the_income_left_after_expenses(): void
    {
        $this->rule('FULL', IncomeExemptionRule::PERCENTAGE, ['percentage' => '100.0000']);

        // An exemption removes assessable income; it cannot remove more than there is, or the
        // base would go negative and produce a smaller tax than the form allows.
        $result = $this->calculate([['code' => 'FULL', 'amount' => '500000']], '180000');
        $this->assertSame('180000.00', (string) $result['total']);
        $this->assertSame('EXEMPTION_EXCEEDS_INCOME', $result['warnings'][0]['code']);
    }

    public function test_a_positive_amount_on_a_code_with_no_rule_is_refused(): void
    {
        $this->expectException(TaxMetadataConflictException::class);
        $this->calculate([['code' => 'NOT_SEEDED', 'amount' => '1000']]);
    }

    public function test_a_zero_on_a_code_with_no_rule_deducts_nothing_and_warns(): void
    {
        // A zero cannot change the tax, so refusing it would be noise — but it is still reported
        // as unverified rather than silently accepted as a supported line.
        $result = $this->calculate([['code' => 'NOT_SEEDED', 'amount' => '0']]);

        $this->assertSame('0.00', (string) $result['total']);
        $this->assertSame('UNVERIFIED', $result['items'][0]['rule_status']);
        $this->assertSame('UNVERIFIED_EXEMPTION_RULE', $result['warnings'][0]['code']);
    }

    public function test_an_inactive_rule_is_treated_as_absent(): void
    {
        $this->rule('OFF', IncomeExemptionRule::PERCENTAGE, ['percentage' => '100.0000', 'active' => false]);

        $this->expectException(TaxMetadataConflictException::class);
        $this->calculate([['code' => 'OFF', 'amount' => '1000']]);
    }

    public function test_a_negative_declaration_is_refused(): void
    {
        $this->rule('FULL', IncomeExemptionRule::PERCENTAGE, ['percentage' => '100.0000']);

        $this->expectException(\InvalidArgumentException::class);
        $this->calculate([['code' => 'FULL', 'amount' => '-1']]);
    }

    public function test_an_unknown_method_is_refused_rather_than_guessed(): void
    {
        $this->rule('ODD', 'method_that_does_not_exist', ['percentage' => '100.0000']);

        $this->expectException(TaxMetadataConflictException::class);
        $this->calculate([['code' => 'ODD', 'amount' => '1000']]);
    }

    public function test_whole_multiples_truncate_and_reject_a_meaningless_unit(): void
    {
        $this->assertSame(2, (new Money('2900000'))->wholeMultiplesOf(new Money('1000000')));
        $this->assertSame(0, (new Money('999999.99'))->wholeMultiplesOf(new Money('1000000')));

        $this->expectException(\InvalidArgumentException::class);
        (new Money('100'))->wholeMultiplesOf(new Money('0'));
    }
}

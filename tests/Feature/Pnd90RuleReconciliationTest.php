<?php

namespace Tests\Feature;

use App\Models\TaxRuleVersion;
use App\Services\Tax\ExpenseActivityCatalogue;
use App\Services\Tax\IncomeSubtypeCatalogue;
use App\Services\Tax\PropertyHoldingPeriodCatalogue;
use Brick\Math\BigDecimal;
use Database\Seeders\Pnd90ExpenseRuleSeeder;
use Database\Seeders\TaxBaselineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Locks the reconciled state of production expense-rule data against ภ.ง.ด.90.
 *
 * Every expectation here mirrors a row of docs/tax/PND90_RULE_MATRIX.md. Seeding, removing or
 * editing a rule fails this class first, which forces the matrix to be updated in the same
 * change rather than drifting away from the data.
 */
class Pnd90RuleReconciliationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * income type => subtype => [method, percentage, cap, fixed]. Null values mean the field
     * is absent. A subtype mapped to null has no verified rule on purpose.
     */
    public const MATRIX = [
        'SECTION_40_1' => [null => ['percentage_limit', '50.0000', '100000.00', null]],
        'SECTION_40_2' => [null => ['percentage_limit', '50.0000', '100000.00', null]],
        'SECTION_40_3' => [
            'ANNUITY_FROM_WILL_OR_JUDGMENT' => ['fixed', null, null, '0.00'],
            'COPYRIGHT_GOODWILL_OTHER_RIGHTS' => ['percentage_or_actual', '50.0000', '100000.00', null],
        ],
        'SECTION_40_4' => [null => ['fixed', null, null, '0.00']],
        // M7.4 replaced the single blank-percentage `RENT_OTHER` with the four asset classes
        // the filing instructions name on page 3, each with its own printed rate.
        'SECTION_40_5' => [
            'RENT_BUILDING_OR_RAFT' => ['percentage_or_actual', '30.0000', null, null],
            'RENT_LAND_AGRICULTURAL' => ['percentage_or_actual', '20.0000', null, null],
            'RENT_LAND_NON_AGRICULTURAL' => ['percentage_or_actual', '15.0000', null, null],
            'RENT_VEHICLE' => ['percentage_or_actual', '30.0000', null, null],
            'RENT_OTHER_PROPERTY' => ['percentage_or_actual', '10.0000', null, null],
            'HIRE_PURCHASE_BREACH' => ['percentage', '20.0000', null, null],
        ],
        'SECTION_40_6' => [
            'MEDICAL_PRACTICE' => ['percentage_or_actual', '60.0000', null, null],
            'FINE_ARTS' => ['percentage_or_actual', '60.0000', null, null],
            'OTHER_LIBERAL_PROFESSION' => ['percentage_or_actual', '30.0000', null, null],
        ],
        'SECTION_40_7' => [null => ['percentage_or_actual', '60.0000', null, null]],
        // M7.4 resolved these two from a further fact the form prints beside the category, so
        // each now has a *set* of rules rather than one. self::KEYED marks that, and
        // test_a_keyed_subtype_has_one_rule_per_printed_key covers them in full.
        'SECTION_40_8' => [
            'BUSINESS_COMMERCE_OTHER' => self::KEYED,
            'MUTUAL_FUND_PROFIT_SHARE' => ['fixed', null, null, '0.00'],
            'IMMOVABLE_PROPERTY_INHERITED_OR_GIFTED' => ['percentage', '50.0000', null, null],
            'IMMOVABLE_PROPERTY_NON_TRADE' => self::KEYED,
            'GIFT_OR_SUPPORT_RECEIVED' => ['fixed', null, null, '0.00'],
        ],
    ];

    /** A subtype whose rate is keyed by a further printed fact, so it holds several rules. */
    public const KEYED = ['__keyed__'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public static function matrixRows(): array
    {
        $rows = [];
        foreach (self::MATRIX as $incomeType => $subtypes) {
            foreach ($subtypes as $subtype => $expected) {
                $rows[$incomeType.($subtype === null ? '' : " / $subtype")] = [$incomeType, $subtype ?: null, $expected];
            }
        }

        return $rows;
    }

    #[DataProvider('matrixRows')]
    public function test_each_documented_category_matches_its_seeded_rule(string $incomeType, ?string $subtype, ?array $expected): void
    {
        $rules = DB::table('expense_rules')->where('rule_version_id', $this->publishedVersionId())
            ->whereIn('income_type_id', DB::table('income_types')->where('code', $incomeType)->pluck('id'))
            ->when($subtype === null,
                fn ($query) => $query->whereNull('income_subtype'),
                fn ($query) => $query->where('income_subtype', $subtype))->get();

        if ($expected === null) {
            $this->assertCount(0, $rules, "$incomeType/$subtype gained a rule without a matrix update.");

            return;
        }
        if ($expected === self::KEYED) {
            $this->assertGreaterThan(1, $rules->count(), "$incomeType/$subtype should hold one rule per printed key.");

            return;
        }

        $this->assertCount(1, $rules, "$incomeType/$subtype should have exactly one verified rule.");
        $rule = $rules->sole();
        [$method, $percentage, $cap, $fixed] = $expected;
        $this->assertSame($method, $rule->method);
        foreach ([['percentage', $percentage], ['maximum_amount', $cap], ['fixed_amount', $fixed]] as [$field, $value]) {
            if ($value === null) {
                $this->assertNull($rule->$field, "$incomeType/$subtype should not define $field.");

                continue;
            }
            $this->assertSame(0, BigDecimal::of((string) $rule->$field)->compareTo($value),
                "$incomeType/$subtype has an unexpected $field.");
        }
        $this->assertSame(1, (int) $rule->active);
        // M7.1 rules cite the form; M7.4 rules cite the filing instructions, which is where
        // their rate is printed. Either is a repository source document.
        $this->assertMatchesRegularExpression('/ภงด\.90\.pdf|PND90-2568-filing-instructions\.pdf/',
            (string) $rule->source_reference, "$incomeType/$subtype must cite the source document.");
        // Mechanics the engine refuses to interpret must stay absent.
        $this->assertNull($rule->conditions);
        $this->assertNull($rule->minimum_amount);
    }

    /**
     * The two ข้อ 7 subcategories whose rate is keyed by a further printed fact hold exactly
     * one rule per key — 44 ตารางที่ 2 activities and 8 holding-period bands — with no gaps
     * and no invented keys.
     */
    public function test_a_keyed_subtype_has_one_rule_per_printed_key(): void
    {
        $activities = DB::table('expense_rules')->where('rule_version_id', $this->publishedVersionId())->whereNotNull('expense_activity')
            ->orderBy('expense_activity')->pluck('expense_activity')->all();
        $expected = ExpenseActivityCatalogue::codes();
        sort($expected);
        $this->assertSame($expected, $activities);
        $this->assertCount(44, $activities);

        $bands = DB::table('expense_rules')->where('rule_version_id', $this->publishedVersionId())->whereNotNull('holding_years_min')
            ->orderBy('holding_years_min')->get(['holding_years_min', 'holding_years_max', 'percentage']);
        $this->assertCount(8, $bands);
        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8], $bands->pluck('holding_years_min')->map(fn ($y) => (int) $y)->all());
        $this->assertNull($bands->last()->holding_years_max);
        foreach (PropertyHoldingPeriodCatalogue::BANDS as $index => [$min, $max, $percentage]) {
            $this->assertSame(0, BigDecimal::of((string) $bands[$index]->percentage)->compareTo($percentage));
        }

        // ตารางที่ 2 row (1) is the one banded rate, and its bands are stored, not flattened.
        $performer = DB::table('expense_rules')->where('rule_version_id', $this->publishedVersionId())->where('expense_activity', ExpenseActivityCatalogue::PERFORMER)->sole();
        $this->assertSame('tiered_or_actual', $performer->method);
        $this->assertNull($performer->percentage);
        $this->assertSame(0, BigDecimal::of((string) $performer->maximum_amount)->compareTo('600000.00'));
        $tiers = DB::table('expense_rule_tiers')->where('expense_rule_id', $performer->id)->orderBy('sort_order')->get();
        $this->assertSame(['FIRST_300000', 'ABOVE_300000'], $tiers->pluck('tier_code')->all());
        $this->assertSame(0, BigDecimal::of((string) $tiers[0]->threshold_amount)->compareTo('300000.00'));
        $this->assertSame(0, BigDecimal::of((string) $tiers[0]->percentage)->compareTo('60.0000'));
        $this->assertNull($tiers[1]->threshold_amount);
        $this->assertSame(0, BigDecimal::of((string) $tiers[1]->percentage)->compareTo('40.0000'));
        // Row (44) prints no rate at all, only "หักค่าใช้จ่ายจริง".
        $this->assertSame('actual', DB::table('expense_rules')->where('rule_version_id', $this->publishedVersionId())
            ->where('expense_activity', ExpenseActivityCatalogue::UNLISTED)->sole()->method);
    }

    public function test_every_income_type_is_reviewed_and_every_subtype_is_documented(): void
    {
        $reviewed = array_keys(self::MATRIX);
        sort($reviewed);
        $this->assertSame(DB::table('income_types')->orderBy('code')->pluck('code')->all(), $reviewed,
            'Every Section 40 income type must have a documented reconciliation status.');

        foreach (self::MATRIX as $incomeType => $subtypes) {
            $documented = array_values(array_filter(array_keys($subtypes), fn ($key) => $key !== '' && $key !== null));
            $this->assertSame(IncomeSubtypeCatalogue::codes($incomeType), $documented,
                "$incomeType subtypes disagree between the catalogue and the matrix.");
        }
    }

    public function test_the_employment_group_is_shared_by_section_40_1_and_40_2(): void
    {
        $grouped = DB::table('expense_rules')->where('rule_version_id', $this->publishedVersionId())->where('expense_group', 'SECTION_40_1_2')
            ->join('income_types', 'income_types.id', '=', 'expense_rules.income_type_id')
            ->orderBy('income_types.code')->pluck('income_types.code')->all();

        $this->assertSame(['SECTION_40_1', 'SECTION_40_2'], $grouped);
        $this->assertSame(1, DB::table('expense_rules')->where('rule_version_id', $this->publishedVersionId())->whereNotNull('expense_group')
            ->distinct()->count('expense_group'), 'Only the ข้อ 1 group is shared.');
    }

    public function test_seeding_is_repeatable_and_refuses_to_overwrite_a_differing_rule(): void
    {
        $before = DB::table('expense_rules')->orderBy('id')->get()->toJson();
        $this->seed(Pnd90ExpenseRuleSeeder::class);
        $this->assertSame($before, DB::table('expense_rules')->orderBy('id')->get()->toJson());

        /*
         * The drift is introduced in the version this seeder writes — the original baseline,
         * which a later version carried forward and retired. Editing the live version instead
         * would prove nothing about this seeder, because it never looks there.
         */
        DB::table('expense_rules')
            ->where('rule_version_id', TaxRuleVersion::where('version', TaxBaselineSeeder::PREDECESSOR_VERSION)->sole()->id)
            ->where('code', 'PND90_SECTION_40_7_EXPENSE')->update(['percentage' => '55.0000']);
        $this->expectException(\RuntimeException::class);
        $this->seed(Pnd90ExpenseRuleSeeder::class);
    }

    public function test_the_approved_section_40_1_mechanics_are_unchanged(): void
    {
        $rule = DB::table('expense_rules')->where('rule_version_id', $this->publishedVersionId())->where('code', 'PND91_SECTION_40_1_EXPENSE')->sole();

        $this->assertSame('percentage_limit', $rule->method);
        $this->assertSame(0, BigDecimal::of((string) $rule->percentage)->compareTo('50.0000'));
        $this->assertSame(0, BigDecimal::of((string) $rule->maximum_amount)->compareTo('100000.00'));
        $this->assertStringContainsString('MILESTONE_04_PROMPT.md', (string) $rule->source_reference);
    }

    public function test_only_the_source_printed_allowance_and_donation_rules_exist(): void
    {
        // ใบแนบ itself prints an amount for four lines, of which item 8 was the only one
        // resolvable from declared input; M7.3 read five further ceilings out of the filing
        // instructions and M7.4 nine more, once the schema could carry a percentage's base and
        // a ceiling shared across codes. ใบแนบ items 1–5 are still not rule rows at all — they
        // are derived per person.
        $this->assertSame(['PND90_2568_ART_PURCHASE', 'PND90_2568_EASY_E_RECEIPT', 'PND90_2568_EASY_E_RECEIPT_OTOP',
            'PND90_2568_HEALTH_INSURANCE', 'PND90_2568_HOME_LOAN_INTEREST', 'PND90_2568_LIFE_INSURANCE',
            'PND90_2568_MATERNITY', 'PND90_2568_NSF', 'PND90_2568_PARENT_HEALTH_INSURANCE',
            'PND90_2568_POLITICAL_PARTY_SUPPORT', 'PND90_2568_PROVIDENT_FUND', 'PND90_2568_RMF',
            'PND90_2568_THAI_ESG', 'PND90_2568_THAI_ESGX', 'PND90_2568_THAI_ESGX_SWITCH',
            // ใบแนบ ข้อ 22.1 เมืองหลัก, added by the version that carried this baseline forward.
            // The booklet grants it as two entitlements, so it is two rules.
            'PND90_DOMESTIC_TRAVEL_MAIN_CITY', 'PND90_DOMESTIC_TRAVEL_MAIN_CITY_ETAX'],
            DB::table('allowance_rules')->where('rule_version_id', $this->publishedVersionId())
                ->orderBy('code')->pluck('code')->all());
        // ข้อ 11 prints exactly two donation lines.
        $this->assertSame(['GENERAL_DONATION', 'SPECIAL_DONATION'],
            DB::table('donation_rules')->where('rule_version_id', $this->publishedVersionId())
                ->orderBy('code')->pluck('code')->all());
    }
}

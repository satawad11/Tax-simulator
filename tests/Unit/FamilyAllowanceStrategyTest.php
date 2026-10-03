<?php

namespace Tests\Unit;

use App\DTO\Tax\FamilyFacts;
use App\Services\Tax\Allowances\ChildAllowanceStrategy;
use App\Services\Tax\Allowances\DisabledPersonAllowanceStrategy;
use App\Services\Tax\Allowances\ParentAllowanceStrategy;
use App\Services\Tax\Allowances\PersonalAllowanceStrategy;
use App\Services\Tax\Allowances\SpouseAllowanceStrategy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * ใบแนบ items 1–5 in isolation, without the database or HTTP layer.
 *
 * Source: docs/tax-source/PND90-2568-filing-instructions.pdf, pages 7–9.
 */
class FamilyAllowanceStrategyTest extends TestCase
{
    private function child(?string $type, ?int $order, ?string $birthDate, bool $eligible = true): array
    {
        return array_filter(['relation_type' => 'child', 'child_type' => $type, 'birth_order' => $order,
            'birth_date' => $birthDate, 'eligible' => $eligible], fn ($value): bool => $value !== null);
    }

    public function test_the_personal_amount_never_varies(): void
    {
        $strategy = new PersonalAllowanceStrategy;

        foreach ([new FamilyFacts, new FamilyFacts(['marital_status' => 'married'], ['has_income' => true])] as $facts) {
            $this->assertSame('60000.00', (string) $strategy->derive($facts)['amount']);
        }
    }

    /** @return array<string, array{?string, ?bool, string, bool}> */
    public static function spouseFacts(): array
    {
        return [
            'married, spouse without income' => ['married', false, '60000.00', false],
            'married, spouse with income' => ['married', true, '0.00', false],
            'single' => ['single', false, '0.00', true],
            'married, no spouse declared' => ['married', null, '0.00', true],
            'nothing declared' => [null, null, '0.00', true],
        ];
    }

    #[DataProvider('spouseFacts')]
    public function test_the_spouse_amount(?string $status, ?bool $spouseHasIncome, string $expected, bool $warns): void
    {
        $facts = new FamilyFacts($status === null ? null : ['marital_status' => $status],
            $spouseHasIncome === null ? null : ['has_income' => $spouseHasIncome]);
        $result = (new SpouseAllowanceStrategy)->derive($facts);

        $this->assertSame($expected, (string) $result['amount']);
        $this->assertSame($warns, array_column($result['warnings'], 'code') === ['SPOUSE_ALLOWANCE_FACTS_MISSING']);
    }

    /** @return array<string, array{list<array<string, mixed>>, string}> */
    public static function childFacts(): array
    {
        $legit = fn (int $order, string $date) => ['relation_type' => 'child', 'child_type' => 'legitimate',
            'birth_order' => $order, 'birth_date' => $date, 'eligible' => true];
        $adopted = ['relation_type' => 'child', 'child_type' => 'adopted', 'eligible' => true];

        return [
            'no children' => [[], '0.00'],
            'first child' => [[$legit(1, '2019-01-01')], '30000.00'],
            // 2018 CE is พ.ศ. 2561 exactly — the first year the extra 30,000 applies.
            'second child born in 2561' => [[$legit(2, '2018-01-01')], '60000.00'],
            'second child born in 2560' => [[$legit(2, '2017-12-31')], '30000.00'],
            'third child born after 2561' => [[$legit(3, '2022-06-30')], '60000.00'],
            'two adopted only' => [[$adopted, $adopted], '60000.00'],
            'four adopted only' => [[$adopted, $adopted, $adopted, $adopted], '90000.00'],
            'three legitimate then adopted' => [
                [$legit(1, '2010-01-01'), $legit(2, '2011-01-01'), $legit(3, '2012-01-01'), $adopted], '90000.00'],
            'two legitimate then two adopted' => [
                [$legit(1, '2010-01-01'), $legit(2, '2011-01-01'), $adopted, $adopted], '90000.00'],
        ];
    }

    #[DataProvider('childFacts')]
    public function test_the_child_amount(array $dependents, string $expected): void
    {
        $result = (new ChildAllowanceStrategy)->derive(new FamilyFacts(dependents: $dependents));

        $this->assertSame($expected, (string) $result['amount']);
    }

    public function test_a_child_missing_the_facts_item_3_needs_is_reported(): void
    {
        $strategy = new ChildAllowanceStrategy;

        $untyped = $strategy->derive(new FamilyFacts(dependents: [$this->child(null, 1, '2019-01-01')]));
        $this->assertSame('0.00', (string) $untyped['amount']);
        $this->assertSame(['CHILD_TYPE_NOT_DECLARED'], array_column($untyped['warnings'], 'code'));

        $unordered = $strategy->derive(new FamilyFacts(dependents: [$this->child('legitimate', null, '2019-01-01')]));
        $this->assertSame('30000.00', (string) $unordered['amount']);
        $this->assertSame(['CHILD_BIRTH_ORDER_NOT_DECLARED'], array_column($unordered['warnings'], 'code'));

        $undated = $strategy->derive(new FamilyFacts(dependents: [$this->child('legitimate', 2, null)]));
        $this->assertSame('30000.00', (string) $undated['amount']);
        $this->assertSame(['CHILD_BIRTH_ORDER_NOT_DECLARED'], array_column($undated['warnings'], 'code'));
    }

    public function test_an_ineligible_child_is_never_counted_and_never_consumes_the_adopted_limit(): void
    {
        $result = (new ChildAllowanceStrategy)->derive(new FamilyFacts(dependents: [
            $this->child('legitimate', 1, '2010-01-01', false),
            $this->child('adopted', null, null),
        ]));

        $this->assertSame('30000.00', (string) $result['amount']);
        $this->assertSame([], $result['warnings']);
    }

    public function test_spouse_parents_count_only_while_the_spouse_has_no_income(): void
    {
        $dependents = [
            ['relation_type' => 'father', 'eligible' => true],
            ['relation_type' => 'spouse_father', 'eligible' => true],
            ['relation_type' => 'spouse_mother', 'eligible' => false],
        ];
        $strategy = new ParentAllowanceStrategy;

        $without = $strategy->derive(new FamilyFacts(['marital_status' => 'married'], ['has_income' => false], $dependents));
        $this->assertSame('60000.00', (string) $without['amount']);
        $this->assertSame([], $without['warnings']);

        $with = $strategy->derive(new FamilyFacts(['marital_status' => 'married'], ['has_income' => true], $dependents));
        $this->assertSame('30000.00', (string) $with['amount']);
        $this->assertSame(['SPOUSE_PARENT_NOT_ELIGIBLE'], array_column($with['warnings'], 'code'));
    }

    public function test_the_disabled_person_amount_is_sixty_thousand_each(): void
    {
        $strategy = new DisabledPersonAllowanceStrategy;

        $one = $strategy->derive(new FamilyFacts(dependents: [['relation_type' => 'disabled_person', 'eligible' => true]]));
        $this->assertSame('60000.00', (string) $one['amount']);
        $this->assertSame([], $one['warnings']);

        $two = $strategy->derive(new FamilyFacts(dependents: array_fill(0, 2,
            ['relation_type' => 'disabled_person', 'eligible' => true])));
        $this->assertSame('120000.00', (string) $two['amount']);
        $this->assertSame(['DISABLED_PERSON_OTHER_LIMIT_UNMODELLED'], array_column($two['warnings'], 'code'));
    }

    public function test_a_dependent_of_another_relation_never_leaks_into_a_family_line(): void
    {
        $facts = new FamilyFacts(dependents: [['relation_type' => 'disabled_person', 'eligible' => true]]);

        $this->assertSame('0.00', (string) (new ChildAllowanceStrategy)->derive($facts)['amount']);
        $this->assertSame('0.00', (string) (new ParentAllowanceStrategy)->derive($facts)['amount']);
    }
}

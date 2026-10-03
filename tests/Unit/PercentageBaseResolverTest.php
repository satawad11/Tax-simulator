<?php

namespace Tests\Unit;

use App\Exceptions\TaxMetadataConflictException;
use App\Services\Tax\PercentageBaseResolver;
use App\ValueObjects\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Milestone 07.4 — a percentage rule may only name a base the engine can compute exactly.
 */
class PercentageBaseResolverTest extends TestCase
{
    /** @return array<string, Money> */
    private function amounts(): array
    {
        return [
            PercentageBaseResolver::GROSS_INCOME => new Money('1000000'),
            PercentageBaseResolver::GROSS_AFTER_EXEMPTION => new Money('900000'),
            PercentageBaseResolver::INCOME_AFTER_EXPENSE => new Money('800000'),
        ];
    }

    /** @return array<string, array{string, string}> */
    public static function bases(): array
    {
        return [
            'gross' => [PercentageBaseResolver::GROSS_INCOME, '1000000.00'],
            'after exemption' => [PercentageBaseResolver::GROSS_AFTER_EXEMPTION, '900000.00'],
            'after expense' => [PercentageBaseResolver::INCOME_AFTER_EXPENSE, '800000.00'],
        ];
    }

    #[DataProvider('bases')]
    public function test_each_supported_base_resolves_to_its_own_amount(string $base, string $expected): void
    {
        $this->assertSame($expected, (string) (new PercentageBaseResolver)->resolve($base, $this->amounts()));
    }

    /** @return array<string, array{?string}> */
    public static function unsupported(): array
    {
        return [
            'null' => [null],
            'empty' => [''],
            'not in the vocabulary' => ['NET_INCOME_BEFORE_DONATION'],
            'lower case' => ['gross_income'],
        ];
    }

    #[DataProvider('unsupported')]
    public function test_a_base_outside_the_vocabulary_is_a_rule_data_conflict(?string $base): void
    {
        $this->assertFalse(PercentageBaseResolver::knows($base));
        $this->expectException(TaxMetadataConflictException::class);
        (new PercentageBaseResolver)->resolve($base, $this->amounts());
    }

    public function test_a_supported_base_the_stage_cannot_supply_is_a_conflict_not_a_zero(): void
    {
        $this->expectException(TaxMetadataConflictException::class);
        (new PercentageBaseResolver)->resolve(PercentageBaseResolver::INCOME_AFTER_EXPENSE, [
            PercentageBaseResolver::GROSS_INCOME => new Money('1000000'),
        ]);
    }

    public function test_the_vocabulary_is_exactly_what_the_engine_can_compute(): void
    {
        $this->assertSame(['GROSS_INCOME', 'GROSS_AFTER_EXEMPTION', 'INCOME_AFTER_EXPENSE'],
            PercentageBaseResolver::SUPPORTED);
    }
}

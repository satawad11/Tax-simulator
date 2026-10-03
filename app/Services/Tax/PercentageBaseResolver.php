<?php

namespace App\Services\Tax;

use App\Exceptions\TaxMetadataConflictException;
use App\ValueObjects\Money;

/**
 * Names the amount a percentage rule is a percentage *of*.
 *
 * The 2568 filing instructions always say this in words, and the words differ between rules,
 * so the base is stored on the rule rather than assumed by the calculator. The vocabulary is
 * deliberately small: a base is listed here only when the engine can compute it exactly at the
 * point the rule is applied, and a rule naming anything else is a 409 rather than a guess.
 */
class PercentageBaseResolver
{
    /**
     * เงินได้พึงประเมิน before any deduction — the gross of every progressive income line.
     */
    public const GROSS_INCOME = 'GROSS_INCOME';

    /**
     * "เงินได้พึงประเมินที่ได้รับซึ่งต้องเสียภาษีเงินได้" — assessable income that is actually
     * subject to tax, i.e. gross less the income the form records as exempt. This is the base
     * ใบแนบ items 10.4, 18.1 and 19.1 name, and the only one any seeded 2568 rule uses.
     */
    public const GROSS_AFTER_EXEMPTION = 'GROSS_AFTER_EXEMPTION';

    /**
     * เงินได้หลังหักค่าใช้จ่าย — the allowance stage's own opening balance.
     */
    public const INCOME_AFTER_EXPENSE = 'INCOME_AFTER_EXPENSE';

    public const SUPPORTED = [self::GROSS_INCOME, self::GROSS_AFTER_EXEMPTION, self::INCOME_AFTER_EXPENSE];

    /**
     * @param  array<string, Money>  $amounts  base code => amount, supplied by the engine
     */
    public function resolve(?string $base, array $amounts): Money
    {
        if ($base === null || ! in_array($base, self::SUPPORTED, true)) {
            throw new TaxMetadataConflictException('Percentage base "'.($base ?? 'null').'" has no approved definition.');
        }
        if (! isset($amounts[$base])) {
            throw new TaxMetadataConflictException('Percentage base "'.$base.'" is not available at this calculation stage.');
        }

        return $amounts[$base];
    }

    public static function knows(?string $base): bool
    {
        return $base !== null && in_array($base, self::SUPPORTED, true);
    }
}

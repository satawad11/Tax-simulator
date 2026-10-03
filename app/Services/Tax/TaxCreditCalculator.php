<?php

namespace App\Services\Tax;

use App\ValueObjects\Money;

/**
 * Aggregates the prepayments and credits ภ.ง.ด.90 ข้อ 11 item 15 deducts.
 *
 * That line carries three checkboxes — ภาษีเงินได้หัก ณ ที่จ่ายและเครดิตภาษี, ภาษีเงินได้ชำระไว้
 * ตามแบบ ภ.ง.ด.93 and ภาษีเงินได้ชำระไว้ตามแบบ ภ.ง.ด.94 — all feeding item 16 with no stated
 * limit, so all three reduce the balance in full.
 *
 * Foreign tax credit is a different line (item 13) whose limit wording is not specific enough
 * to apply, and `other_credit` has no counterpart on the form at all; both stay at zero with a
 * warning rather than being granted.
 */
class TaxCreditCalculator
{
    public const TYPES = ['withholding', 'foreign_tax_credit', 'pnd93', 'pnd94', 'other_credit'];

    /** Credits ข้อ 11 item 15 deducts in full. */
    public const PREPAID_TYPES = ['withholding', 'pnd93', 'pnd94'];

    /**
     * @param  list<array{type: string, amount: string|int}>  $entries
     * @return array{totals: array<string, Money>, warnings: list<array{code: string, message: string, path: string}>}
     */
    public function calculate(array $entries): array
    {
        $totals = array_fill_keys([...self::TYPES, 'total'], new Money);
        $warnings = [];

        foreach ($entries as $index => $entry) {
            if (! in_array($entry['type'], self::TYPES, true) ||
                (new Money($entry['amount']))->compare(new Money) < 0) {
                throw new \InvalidArgumentException('Credits require a supported type and nonnegative amount.');
            }
            if (in_array($entry['type'], self::PREPAID_TYPES, true)) {
                $totals[$entry['type']] = $totals[$entry['type']]->add(new Money($entry['amount']));

                continue;
            }
            // TODO: approve credit eligibility and limits before granting these credits.
            $warnings[] = ['code' => $entry['type'] === 'foreign_tax_credit' ? 'UNVERIFIED_FOREIGN_TAX_CREDIT_LIMIT' : 'UNVERIFIED_TAX_CREDIT_RULE',
                'message' => 'This credit has no verified eligibility and limit mechanics; eligible amount is zero.', 'path' => "withholdings.$index"];
        }

        foreach (self::PREPAID_TYPES as $type) {
            $totals['total'] = $totals['total']->add($totals[$type]);
        }

        return ['totals' => $totals, 'warnings' => $warnings];
    }
}

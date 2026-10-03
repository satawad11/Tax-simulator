<?php

namespace App\Services\Tax;

use App\Exceptions\TaxMetadataConflictException;
use App\Models\IncomeExemptionRule;
use App\Models\TaxRuleVersion;
use App\ValueObjects\Money;

/**
 * Applies the verified rule of each เงินได้ที่ได้รับยกเว้น line the filer declared.
 *
 * This runs **after** expenses and before allowances, because ใบแนบ ข้อ 13 (13.4) and ข้อ 20 (20.4)
 * both say the amount comes off เงินได้พึงประเมิน once มาตรา 42 ทวิ ถึง มาตรา 46 have been applied.
 *
 * Like `ExpenseCalculator`, branching is by rule **method** and never by code: a further verified
 * line is a seeding change, not a code change. A code with no active rule never receives an
 * invented deduction — the request layer refuses a positive amount for one, so only a zero can
 * reach here, and a zero deducts nothing.
 */
class IncomeExemptionCalculator
{
    /**
     * @param  list<array{code: string, amount: string|int, declarations_confirmed?: bool}>  $claims
     * @return array{items: list<array<string, mixed>>, total: Money, warnings: list<array<string, string>>}
     */
    public function calculate(array $claims, TaxRuleVersion $version, Money $ceiling): array
    {
        $rules = IncomeExemptionRule::where('rule_version_id', $version->id)
            ->where('active', true)->get()->keyBy('code');
        $items = $warnings = [];
        $total = new Money;

        foreach ($claims as $index => $claim) {
            $declared = new Money($claim['amount']);
            if ($declared->compare(new Money) < 0) {
                throw new \InvalidArgumentException('A declared exemption cannot be negative.');
            }
            $rule = $rules->get($claim['code']);

            if ($rule === null) {
                if ($declared->compare(new Money) > 0) {
                    throw new TaxMetadataConflictException(
                        'No verified exemption rule exists for '.$claim['code'].'.');
                }
                $items[] = $this->item($claim['code'], null, $declared, new Money, 'UNVERIFIED');
                $warnings[] = ['code' => 'UNVERIFIED_EXEMPTION_RULE',
                    'message' => 'No verified exemption rule exists for this line; nothing was deducted.',
                    'path' => 'income_exemptions.'.$index];

                continue;
            }

            $eligible = $this->eligible($rule, $declared);
            $total = $total->add($eligible);
            $items[] = $this->item($claim['code'], $rule, $declared, $eligible, 'VERIFIED');
        }

        /*
         * An exemption removes assessable income, so it can never remove more than there is. The
         * ceiling is the income still standing after expenses; without it a large declared amount
         * would drive the base negative and, through the minimum-tax base, produce a smaller tax
         * than the form allows.
         */
        if ($total->compare($ceiling) > 0) {
            $warnings[] = ['code' => 'EXEMPTION_EXCEEDS_INCOME',
                'message' => 'The exemptions claimed exceed the income remaining after expenses, so only that much was applied.',
                'path' => 'income_exemptions'];
            $total = $ceiling;
        }

        return ['items' => $items, 'total' => $total, 'warnings' => $warnings];
    }

    private function eligible(IncomeExemptionRule $rule, Money $declared): Money
    {
        $eligible = match ($rule->method) {
            IncomeExemptionRule::PERCENTAGE => $declared->percentage((string) $rule->percentage),
            // "10,000 บาท ต่อทุกจำนวน 1,000,000 บาท" — completed units only; see Money::wholeMultiplesOf.
            IncomeExemptionRule::STEPPED_GRANT => (new Money((string) $rule->grant_per_step))
                ->multiply($declared->wholeMultiplesOf(new Money((string) $rule->step_amount))),
            default => throw new TaxMetadataConflictException(
                'Exemption rule method "'.$rule->method.'" is not applicable.'),
        };

        return $rule->maximum_amount === null
            ? $eligible : $eligible->min(new Money((string) $rule->maximum_amount));
    }

    /** @return array<string, mixed> */
    private function item(string $code, ?IncomeExemptionRule $rule, Money $declared, Money $eligible, string $status): array
    {
        return [
            'code' => $code,
            'name' => $rule?->name,
            'method' => $rule?->method,
            'percentage' => $rule?->percentage,
            'step_amount' => $rule?->step_amount === null ? null : new Money((string) $rule->step_amount),
            'grant_per_step' => $rule?->grant_per_step === null ? null : new Money((string) $rule->grant_per_step),
            'maximum_amount' => $rule?->maximum_amount === null ? null : new Money((string) $rule->maximum_amount),
            'input_amount' => $declared,
            'eligible_amount' => $eligible,
            'rule_status' => $status,
            'source_reference' => $rule?->source_reference,
        ];
    }
}

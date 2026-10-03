<?php

namespace App\Services\Tax;

use App\DTO\Tax\FamilyFacts;
use App\Models\AllowanceRule;
use App\Models\AllowanceType;
use App\Models\TaxRuleVersion;
use App\ValueObjects\Money;

/**
 * Applies the verified allowance rule of each declared allowance.
 *
 * Three kinds of line exist, and they are settled differently:
 *
 *   ใบแนบ items 1–5 (ผู้มีเงินได้, คู่สมรส, บุตร, บิดามารดา, คนพิการฯ) print a per-person amount,
 *     so the amount is derived by FamilyAllowanceResolver from declared family facts and the
 *     client-submitted amount is ignored entirely (Milestone 07.3);
 *   a percentage line states a rate and, in words, the income it is a rate *of*; that base is
 *     stored on the rule and resolved by PercentageBaseResolver (Milestone 07.4);
 *   every other line is an amount the taxpayer actually paid, capped where the filing
 *     instructions print a ceiling.
 *
 * Individual ceilings are applied here. A ceiling the source states *across* codes belongs to
 * CombinedAllowanceCapResolver, which runs afterwards on the items this class produces.
 *
 * A code without a stored rule is still never given a default: it returns zero with a warning.
 */
class AllowanceCalculator
{
    /** Allowance methods whose arithmetic is unambiguous. */
    public const SUPPORTED_METHODS = ['fixed', 'actual', 'percentage_limit'];

    public function __construct(
        private FamilyAllowanceResolver $family,
        private PercentageBaseResolver $bases = new PercentageBaseResolver,
    ) {}

    /**
     * @param  list<array{code: string, amount: string|int}>  $entries
     * @param  array<string, Money>  $baseAmounts  percentage-base code => amount
     */
    public function calculate(array $entries, TaxRuleVersion $version, FamilyFacts $facts = new FamilyFacts, array $baseAmounts = []): array
    {
        $items = $warnings = [];
        $input = $eligibleTotal = new Money;
        $entries = $this->withFamilyLinesTheFactsEntitle($entries, $facts);
        $types = AllowanceType::whereIn('code', array_column($entries, 'code'))->where('is_active', true)
            ->with(['allowanceRules' => fn ($query) => $query->where('rule_version_id', $version->id)
                ->where('active', true)->whereNotNull('source_reference')->where('source_reference', '!=', '')])->get()->keyBy('code');

        foreach ($entries as $index => $entry) {
            $type = $types->get($entry['code']);
            if (! $type) {
                throw new \InvalidArgumentException('Unknown allowance code.');
            }
            $amount = new Money($entry['amount']);
            $input = $input->add($amount);

            if ($this->family->owns($type->code)) {
                $derived = $this->family->derive($type->code, $facts, $version);
                $eligibleTotal = $eligibleTotal->add($derived['amount']);
                $items[] = [...$this->blank($type->code, $amount), 'method' => 'derived',
                    'eligible_amount' => $derived['amount'], 'rule_status' => 'DERIVED', 'basis' => $derived['basis']];
                // The warning tells a caller their number was discarded. A line this service
                // claimed on the filer's behalf carries no number to discard, so saying so would
                // be noise about something the caller never did.
                if (empty($entry['claimed_automatically']) && $amount->compare($derived['amount']) !== 0) {
                    $warnings[] = ['code' => 'FAMILY_ALLOWANCE_DERIVED',
                        'message' => 'ค่าลดหย่อนตามใบแนบ ข้อ 1–5 คำนวณจากข้อมูลครอบครัวที่แจ้งไว้ จำนวนเงินที่ส่งมาจะไม่ถูกนำมาใช้',
                        'path' => "allowances.$index"];
                }
                foreach ($derived['warnings'] as $warning) {
                    $warnings[] = [...$warning, 'path' => "allowances.$index"];
                }

                continue;
            }
            $rule = $type->allowanceRules->first();
            $applied = $this->eligible($rule, $amount, $baseAmounts);

            if ($applied === null) {
                $items[] = [...$this->blank($type->code, $amount), 'eligible_amount' => new Money, 'rule_status' => 'UNVERIFIED'];
                $warnings[] = ['code' => 'UNVERIFIED_ALLOWANCE_RULE',
                    'message' => $rule ? 'Allowance eligibility mechanics are not yet verified.' : 'No verified allowance rule is available.',
                    'path' => "allowances.$index"];

                continue;
            }
            $eligibleTotal = $eligibleTotal->add($applied['eligible_amount']);
            $items[] = [...$this->blank($type->code, $amount), ...$applied, 'rule_status' => 'VERIFIED'];
        }

        return ['items' => $items, 'total_input' => $input, 'total_eligible' => $eligibleTotal, 'warnings' => $warnings];
    }

    /**
     * Adds the ใบแนบ family lines the declared facts entitle the filer to, if they are not
     * already declared.
     *
     * Milestone 09.1. These lines are derived, not entered: the product tells the reader so and
     * offers no field to claim them with, which meant a family the filer had already described
     * produced no deduction at all. Claiming them here — rather than in the browser — keeps
     * Guest, Member and any future client on one behaviour, because each builds its own payload.
     *
     * An explicitly declared entry is left exactly as it is, so a caller that names a code still
     * behaves as before, and the order the caller chose is preserved.
     *
     * @param  list<array<string, mixed>>  $entries
     * @return list<array<string, mixed>>
     */
    private function withFamilyLinesTheFactsEntitle(array $entries, FamilyFacts $facts): array
    {
        $declared = array_column($entries, 'code');

        foreach ($this->family->claimableCodes($facts) as $code) {
            if (in_array($code, $declared, true)) {
                continue;
            }
            // The amount is a placeholder: the strategy that owns the line replaces it.
            $entries[] = ['code' => $code, 'amount' => '0.00', 'claimed_automatically' => true];
        }

        return $entries;
    }

    /** Every item carries the same keys, so a consumer never has to test for their presence. */
    private function blank(string $code, Money $amount): array
    {
        return ['code' => $code, 'input_amount' => $amount, 'method' => null, 'percentage' => null,
            'percentage_base' => null, 'base_amount' => null, 'calculated_before_cap' => null,
            'maximum_amount' => null, 'fixed_amount' => null, 'combined_cap_group' => null,
            'allocated_amount' => null, 'eligible_amount' => new Money, 'rule_status' => 'UNVERIFIED'];
    }

    /**
     * Null means the rule is absent or its mechanics are not approved; the caller warns.
     *
     * @param  array<string, Money>  $baseAmounts
     * @return array<string, mixed>|null
     */
    private function eligible(?AllowanceRule $rule, Money $input, array $baseAmounts): ?array
    {
        if ($rule === null || ! in_array($rule->method, self::SUPPORTED_METHODS, true)
            || $rule->conditions !== null || $rule->minimum_amount !== null) {
            return null;
        }
        $maximum = $rule->maximum_amount === null ? null : new Money($rule->maximum_amount);
        $shared = ['method' => $rule->method, 'maximum_amount' => $maximum,
            'fixed_amount' => $rule->fixed_amount === null ? null : new Money($rule->fixed_amount)];

        if ($rule->method === 'percentage_limit') {
            if ($rule->percentage === null || $maximum === null || ! PercentageBaseResolver::knows($rule->percentage_base)) {
                return null;
            }
            $base = $this->bases->resolve($rule->percentage_base, $baseAmounts);
            // "เท่าที่ได้จ่าย … ในอัตราไม่เกินร้อยละ N ของ <base> … เฉพาะส่วนที่ไม่เกิน <cap>":
            // the amount paid, then the rate ceiling, then the printed ceiling.
            $beforeCap = $input->min($base->percentage($rule->percentage));

            return [...$shared, 'percentage' => $rule->percentage, 'percentage_base' => $rule->percentage_base,
                'base_amount' => $base, 'calculated_before_cap' => $beforeCap,
                'eligible_amount' => $beforeCap->min($maximum)];
        }
        if ($rule->percentage !== null) {
            return null;
        }

        return match ($rule->method) {
            // A stated entitlement, independent of what the taxpayer declares.
            'fixed' => $rule->fixed_amount === null ? null
                : [...$shared, 'eligible_amount' => new Money($rule->fixed_amount)],
            // The amount actually paid, capped where the attachment prints a ceiling.
            'actual' => [...$shared, 'calculated_before_cap' => $input,
                'eligible_amount' => $maximum === null ? $input : $input->min($maximum)],
            default => null,
        };
    }
}

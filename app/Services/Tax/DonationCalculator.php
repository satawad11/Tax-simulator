<?php

namespace App\Services\Tax;

use App\Models\DonationRule;
use App\Models\TaxRuleVersion;
use App\ValueObjects\Money;
use Brick\Math\BigDecimal;

/**
 * Applies the two donation deductions ภ.ง.ด.90 ข้อ 11 prints, in the order it prints them.
 *
 *   item 3  คงเหลือ — income after expenses and allowances (this method's $remaining)
 *   item 4  หัก เงินบริจาค (2 เท่าของจำนวนที่ได้จ่ายไปจริง แต่ไม่เกินร้อยละ 10 ของ 3.)
 *   item 5  คงเหลือ (3. - 4.)
 *   item 6  หัก เงินบริจาค (ไม่เกินร้อยละ 10 ของ 5.)
 *   item 7  เงินได้สุทธิ (5. - 6.)
 *
 * The two caps therefore stand on different bases, and the order matters: the general cap is
 * computed after the special deduction has already reduced the base. The stages are never
 * merged into one combined cap.
 */
class DonationCalculator
{
    /** @param list<array{code: string, amount: string|int}> $entries */
    public function calculate(array $entries, TaxRuleVersion $version, Money $remaining): array
    {
        $rules = DonationRule::where('rule_version_id', $version->id)->where('active', true)
            ->whereIn('code', array_column($entries, 'code'))->get()->keyBy('code');
        $staged = ['special' => [], 'general' => []];
        $input = new Money;

        foreach ($entries as $index => $entry) {
            $rule = $rules->get($entry['code']);
            if (! $rule) {
                throw new \InvalidArgumentException('Unknown donation code.');
            }
            if (! array_key_exists($rule->donation_type, $staged)) {
                throw new \InvalidArgumentException('Unsupported donation stage.');
            }
            $amount = new Money($entry['amount']);
            $input = $input->add($amount);
            $staged[$rule->donation_type][] = ['index' => $index, 'rule' => $rule, 'amount' => $amount];
        }

        $items = $warnings = [];
        [$special, $afterSpecial] = $this->stage($staged['special'], $remaining, $items, $warnings);
        [$general, $net] = $this->stage($staged['general'], $afterSpecial, $items, $warnings);
        usort($items, fn (array $a, array $b): int => $a['index'] <=> $b['index']);

        return ['items' => array_map(fn (array $item): array => array_diff_key($item, ['index' => null]), $items),
            'total_input' => $input, 'total_eligible' => $special->add($general),
            'special' => $special, 'after_special' => $afterSpecial, 'general' => $general,
            'net_income' => $net, 'warnings' => $warnings];
    }

    /**
     * One printed donation line: multiply each entry, then cap the stage against its own base.
     *
     * @param  list<array{index: int, rule: DonationRule, amount: Money}>  $entries
     * @param  list<array<string, mixed>>  $items
     * @param  list<array<string, string>>  $warnings
     * @return array{0: Money, 1: Money}
     */
    private function stage(array $entries, Money $base, array &$items, array &$warnings): array
    {
        if ($entries === []) {
            return [new Money, $base];
        }
        $rule = $entries[0]['rule'];
        if ($rule->multiplier === null || $rule->max_percentage === null || $rule->conditions !== null
            || trim((string) $rule->source_reference) === '') {
            foreach ($entries as $entry) {
                $items[] = ['index' => $entry['index'], 'code' => $entry['rule']->code, 'stage' => $entry['rule']->donation_type,
                    'input_amount' => $entry['amount'], 'multiplier' => null, 'cap_base' => null,
                    'cap_percentage' => null, 'multiplied_amount' => null, 'eligible_amount' => new Money];
                $warnings[] = ['code' => 'UNVERIFIED_DONATION_RULE',
                    'message' => 'Donation mechanics are not yet verified.', 'path' => 'donations.'.$entry['index']];
            }

            return [new Money, $base];
        }

        $cap = $base->percentage($rule->max_percentage)->max(new Money);
        $allocated = new Money;
        foreach ($entries as $entry) {
            // value x multiplier, expressed through the shared percentage helper so the
            // decimal arithmetic stays identical to the rest of the engine.
            $multiplied = $entry['amount']->percentage((string) BigDecimal::of($entry['rule']->multiplier)->multipliedBy(100));
            // Allocation across several entries of one printed line is presentational and
            // taken in submission order; the stage total is what reaches the tax base.
            $eligible = $cap->subtract($allocated)->max(new Money)->min($multiplied);
            $allocated = $allocated->add($eligible);
            $items[] = ['index' => $entry['index'], 'code' => $entry['rule']->code, 'stage' => $entry['rule']->donation_type,
                'input_amount' => $entry['amount'], 'multiplier' => $entry['rule']->multiplier,
                'cap_base' => $base, 'cap_percentage' => $entry['rule']->max_percentage,
                'multiplied_amount' => $multiplied, 'eligible_amount' => $eligible];
        }

        return [$allocated, $base->subtract($allocated)->max(new Money)];
    }
}

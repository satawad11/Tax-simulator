<?php

namespace App\Services\Tax;

use App\Models\AllowanceCapGroup;
use App\Models\TaxRuleVersion;
use App\ValueObjects\Money;

/**
 * Applies the ceilings the 2568 filing instructions state across several allowance codes.
 *
 * Each member line is capped individually first, by its own rule. This resolver then looks at
 * every group whose members appear in the calculation and, where their combined total exceeds
 * the group ceiling, reduces the total to that ceiling.
 *
 * **The excess is taken from the group, not from a member.** The booklet states the shared
 * ceilings (ใบแนบ items 7.4, 9, 10.4, 17) without saying which line gives way when two members
 * are claimed together, so inventing a priority would be inventing law. The group therefore
 * reports one capped total and a per-member `allocated_amount` produced by proportional
 * reduction, which is presentation only: the taxable base depends on the group total alone,
 * and the same total results whatever order the members arrive in.
 */
class CombinedAllowanceCapResolver
{
    /**
     * @param  list<array<string, mixed>>  $items  allowance items already capped individually
     * @return array{items: list<array<string, mixed>>, groups: list<array<string, mixed>>, total_eligible: Money, warnings: list<array<string, string>>}
     */
    public function apply(array $items, TaxRuleVersion $version, Money $individualTotal): array
    {
        $codes = array_values(array_filter(array_column($items, 'code')));
        $groups = $codes === [] ? collect() : AllowanceCapGroup::where('rule_version_id', $version->id)
            ->where('active', true)
            ->whereHas('allowanceTypes', fn ($query) => $query->whereIn('code', $codes))
            ->with('allowanceTypes')->orderBy('code')->get();

        $breakdown = $warnings = [];
        $total = $individualTotal;

        foreach ($groups as $group) {
            $memberCodes = $group->allowanceTypes->pluck('code')->all();
            $indexes = array_keys(array_filter($items,
                fn (array $item): bool => in_array($item['code'] ?? null, $memberCodes, true)));
            $preCap = array_reduce($indexes,
                fn (Money $carry, int $index): Money => $carry->add($items[$index]['eligible_amount']), new Money);
            $ceiling = $group->maximum_amount === null ? null : new Money($group->maximum_amount);
            $capped = $ceiling === null ? $preCap : $preCap->min($ceiling);

            foreach ($indexes as $index) {
                $items[$index]['combined_cap_group'] = $group->code;
            }
            if ($ceiling !== null && $preCap->compare($ceiling) > 0) {
                $total = $total->subtract($preCap)->add($capped);
                $this->allocate($items, $indexes, $preCap, $capped);
                $warnings[] = ['code' => 'COMBINED_ALLOWANCE_CAP_APPLIED',
                    'message' => 'ค่าลดหย่อนกลุ่ม "'.$group->name.'" รวมกันต้องไม่เกิน '.$group->maximum_amount.' บาท จึงปรับยอดรวมลงตามเพดานของกลุ่ม',
                    'path' => 'allowances.combined_cap_groups'];
            }
            $breakdown[] = ['code' => $group->code, 'name' => $group->name,
                'member_codes' => array_values(array_intersect($memberCodes, $codes)),
                'pre_cap_total' => $preCap, 'maximum_amount' => $ceiling,
                'eligible_total' => $capped, 'source_reference' => $group->source_reference];
        }

        return ['items' => array_values($items), 'groups' => $breakdown, 'total_eligible' => $total, 'warnings' => $warnings];
    }

    /**
     * Presentation-only proportional reduction. The last member absorbs the rounding remainder
     * so the allocated parts always add back to exactly the group total.
     *
     * @param  list<array<string, mixed>>  $items
     * @param  list<int>  $indexes
     */
    private function allocate(array &$items, array $indexes, Money $preCap, Money $capped): void
    {
        $assigned = new Money;
        $last = array_key_last($indexes);
        foreach ($indexes as $position => $index) {
            $share = $position === $last
                ? $capped->subtract($assigned)
                : $items[$index]['eligible_amount']->proportion($capped, $preCap);
            $assigned = $assigned->add($share);
            $items[$index]['allocated_amount'] = $share;
        }
    }
}

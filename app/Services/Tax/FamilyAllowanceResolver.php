<?php

namespace App\Services\Tax;

use App\DTO\Tax\FamilyFacts;
use App\Models\TaxRuleVersion;
use App\Services\Tax\Allowances\ChildAllowanceStrategy;
use App\Services\Tax\Allowances\DisabledPersonAllowanceStrategy;
use App\Services\Tax\Allowances\FamilyAllowanceStrategy;
use App\Services\Tax\Allowances\ParentAllowanceStrategy;
use App\Services\Tax\Allowances\PersonalAllowanceStrategy;
use App\Services\Tax\Allowances\SpouseAllowanceStrategy;

/**
 * Owns the five ใบแนบ family allowance lines whose amounts are printed in the filing
 * instructions rather than declared by the taxpayer.
 *
 * These five are server-derived on purpose. The printed amounts are fixed per person, so a
 * client-supplied number can only be wrong, and Milestone 07.3 requires that such a number
 * never reaches the tax base. The declared entry still decides *whether* the line is claimed
 * — the taxpayer must ask for it — but its amount is discarded and replaced by the amount the
 * strategy derives from profile / spouse / dependents.
 *
 * Source: docs/tax-source/PND90-2568-filing-instructions.pdf, pages 7–9, ใบแนบ items 1–5.
 */
class FamilyAllowanceResolver
{
    /** @var array<string, FamilyAllowanceStrategy> */
    private array $strategies = [];

    public function __construct(
        PersonalAllowanceStrategy $personal,
        SpouseAllowanceStrategy $spouse,
        ChildAllowanceStrategy $child,
        ParentAllowanceStrategy $parent,
        DisabledPersonAllowanceStrategy $disabled,
    ) {
        foreach ([$personal, $spouse, $child, $parent, $disabled] as $strategy) {
            $this->strategies[$strategy->code()] = $strategy;
        }
    }

    /** @return list<string> the allowance codes this resolver owns */
    public function codes(): array
    {
        return array_keys($this->strategies);
    }

    public function owns(string $code): bool
    {
        return array_key_exists($code, $this->strategies);
    }

    /**
     * The ใบแนบ family lines the declared facts entitle this filer to claim.
     *
     * Milestone 09.1. Until now a family line was only applied when the caller named the code,
     * and nothing in the product ever named it: the simulator tells the reader these lines are
     * derived for them and gives them no control to ask with, so a married taxpayer with a child
     * entered their family and received nothing for it. Claiming follows the facts instead.
     *
     * This decides only *whether* a line is claimed. Every amount is still produced by the
     * strategy that owns it, from the printed instructions, and a caller-supplied number is still
     * discarded — a filer who does not qualify gets the strategy's zero, not a line removed here.
     *
     * Each line follows the fact that entitles it, and a payload that declares no family facts at
     * all claims nothing. ใบแนบ item 1 carries no condition beyond being a filer, so the personal
     * line follows the profile — which is what the simulator requires before it will calculate
     * anything real, and what its longest-standing regression baseline deliberately omits.
     *
     * @return list<string> in ใบแนบ order
     */
    public function claimableCodes(FamilyFacts $facts): array
    {
        $codes = [];

        if ($facts->profile !== null) {
            $codes[] = 'PERSONAL';
        }
        if ($facts->spouse !== null) {
            $codes[] = 'SPOUSE';
        }
        if ($facts->dependentsOf('child') !== []) {
            $codes[] = 'CHILD';
        }
        if ($facts->dependentsOf('father', 'mother', 'spouse_father', 'spouse_mother') !== []) {
            $codes[] = 'PARENT';
        }
        if ($facts->dependentsOf('disabled_person') !== []) {
            $codes[] = 'DISABLED_PERSON';
        }

        return $codes;
    }

    /**
     * @return array{amount: Money, basis: string, warnings: list<array{code: string, message: string}>}
     */
    public function derive(string $code, FamilyFacts $facts, TaxRuleVersion $version): array
    {
        return $this->strategies[$code]->derive($facts, $version);
    }
}

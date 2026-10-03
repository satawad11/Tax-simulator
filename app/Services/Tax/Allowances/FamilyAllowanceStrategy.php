<?php

namespace App\Services\Tax\Allowances;

use App\DTO\Tax\FamilyFacts;
use App\Models\TaxRuleVersion;

/**
 * One printed ใบแนบ family allowance line, derived from declared facts alone.
 *
 * Source: วิธีการกรอกแบบ ภ.ง.ด.90 — docs/tax-source/PND90-2568-filing-instructions.pdf,
 * pages 7–9, ใบแนบแสดงรายละเอียดรายการลดหย่อนและยกเว้นหลังจากหักค่าใช้จ่าย items 1–5.
 */
interface FamilyAllowanceStrategy
{
    /** The allowance_types code this strategy owns. */
    public function code(): string;

    /**
     * The derived entitlement.
     *
     * @return array{amount: Money, basis: string, warnings: list<array{code: string, message: string}>}
     */
    public function derive(FamilyFacts $facts, ?TaxRuleVersion $version = null): array;
}

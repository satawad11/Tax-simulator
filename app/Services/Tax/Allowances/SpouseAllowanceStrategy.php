<?php

namespace App\Services\Tax\Allowances;

use App\DTO\Tax\FamilyFacts;
use App\Models\TaxRuleVersion;
use App\ValueObjects\Money;

/**
 * ใบแนบ item 2 — คู่สมรส 60,000 บาท.
 *
 * PND90-2568-filing-instructions.pdf, page 7:
 *
 *   2. คู่สมรส 60,000 บาท
 *   2.1 กรณีคู่สมรสไม่มีเงินได้ … ผู้มีเงินได้หักลดหย่อนคู่สมรส 60,000 บาท
 *   2.2 กรณีคู่สมรสมีเงินได้ทั้ง 2 ฝ่าย คู่สมรสต่างฝ่ายต่างหักลดหย่อนสำหรับผู้มีเงินได้
 *       60,000 บาทแล้ว จึงไม่มีสิทธิหักลดหย่อนคู่สมรส
 *
 * So the line is 60,000 exactly when the taxpayer is married and the spouse has no income,
 * and nothing at all when both have income — each then takes their own item 1 instead. That
 * is the whole rule; no combined-filing concept is needed to decide it.
 */
class SpouseAllowanceStrategy implements FamilyAllowanceStrategy
{
    public const AMOUNT = '60000.00';

    public function code(): string
    {
        return 'SPOUSE';
    }

    public function derive(FamilyFacts $facts, ?TaxRuleVersion $version = null): array
    {
        $hasIncome = $facts->spouseHasIncome();
        if (! $facts->isMarried() || $hasIncome === null) {
            return ['amount' => new Money, 'basis' => 'ใบแนบ item 2 — ไม่เข้าเงื่อนไข: ยังไม่ได้ระบุสถานภาพสมรสและข้อมูลคู่สมรส',
                'warnings' => [['code' => 'SPOUSE_ALLOWANCE_FACTS_MISSING',
                    'message' => 'ใบแนบ ข้อ 2 หักลดหย่อนคู่สมรสได้เมื่อระบุสถานภาพ "สมรส" และข้อมูลคู่สมรสว่าไม่มีเงินได้']]];
        }
        if ($hasIncome) {
            return ['amount' => new Money, 'basis' => 'ใบแนบ item 2.2 — คู่สมรสมีเงินได้ทั้ง 2 ฝ่าย จึงไม่มีสิทธิหักลดหย่อนคู่สมรส', 'warnings' => []];
        }

        return ['amount' => new Money(self::AMOUNT), 'basis' => 'ใบแนบ item 2.1 — คู่สมรสไม่มีเงินได้ 60,000 บาท', 'warnings' => []];
    }
}

<?php

namespace App\Services\Tax\Allowances;

use App\DTO\Tax\FamilyFacts;
use App\Models\TaxRuleVersion;
use App\ValueObjects\Money;

/**
 * ใบแนบ item 4 — อุปการะเลี้ยงดูบิดามารดา.
 *
 * PND90-2568-filing-instructions.pdf, page 8, verbatim:
 *
 *   4.1 บิดามารดาต้องมีอายุตั้งแต่ 60 ปีขึ้นไป และอยู่ในความอุปการะเลี้ยงดูของผู้มีเงินได้
 *       แต่ต้องไม่มีเงินได้พึงประเมินในปีภาษีที่ขอหักลดหย่อนเกิน 30,000 บาทขึ้นไป
 *   4.2 ผู้มีเงินได้หรือคู่สมรสของผู้มีเงินได้ต้องเป็นบุตรชอบด้วยกฎหมาย
 *       (บุตรบุญธรรมไม่มีสิทธิหักลดหย่อน) และการหักลดหย่อนหักได้ตลอดปีภาษี
 *   4.3 หักลดหย่อนบิดามารดาของผู้มีเงินได้คนละ 30,000 บาท และหักลดหย่อนได้สำหรับ
 *       บิดามารดาของคู่สมรสที่ไม่มีเงินได้อีกคนละ 30,000 บาท
 *
 * So 30,000 per qualifying parent, and a spouse's parents count only while the spouse has no
 * income. The age and income conditions in 4.1 turn on the parent's own assessable income,
 * which this schema does not hold, so they remain the taxpayer's declaration (`eligible`).
 */
class ParentAllowanceStrategy implements FamilyAllowanceStrategy
{
    public const AMOUNT = '30000.00';

    public const OWN = ['father', 'mother'];

    public const SPOUSE = ['spouse_father', 'spouse_mother'];

    public function code(): string
    {
        return 'PARENT';
    }

    public function derive(FamilyFacts $facts, ?TaxRuleVersion $version = null): array
    {
        $warnings = [];
        $own = $this->eligible($facts->dependentsOf(...self::OWN));
        $spouseParents = $this->eligible($facts->dependentsOf(...self::SPOUSE));
        $counted = $own;

        if ($spouseParents > 0) {
            $spouseHasIncome = $facts->spouseHasIncome();
            if ($facts->isMarried() && $spouseHasIncome === false) {
                $counted += $spouseParents;
            } else {
                $warnings[] = ['code' => 'SPOUSE_PARENT_NOT_ELIGIBLE',
                    'message' => 'ใบแนบ ข้อ 4.3 หักลดหย่อนบิดามารดาของคู่สมรสได้เฉพาะกรณีคู่สมรสไม่มีเงินได้'];
            }
        }

        return ['amount' => (new Money(self::AMOUNT))->multiply((string) $counted),
            'basis' => 'ใบแนบ item 4 — บิดามารดาที่หักลดหย่อนได้ '.$counted.' คน คนละ 30,000 บาท',
            'warnings' => $warnings];
    }

    /** @param list<array<string, mixed>> $rows */
    private function eligible(array $rows): int
    {
        return count(array_filter($rows, fn (array $row): bool => ($row['eligible'] ?? false) === true));
    }
}

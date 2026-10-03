<?php

namespace App\Services\Tax\Allowances;

use App\DTO\Tax\FamilyFacts;
use App\Models\TaxRuleVersion;
use App\ValueObjects\Money;

/**
 * ใบแนบ item 5 — อุปการะเลี้ยงดูคนพิการหรือคนทุพพลภาพ.
 *
 * PND90-2568-filing-instructions.pdf, page 8, verbatim:
 *
 *   5.1 การหักลดหย่อนค่าอุปการะเลี้ยงดูบิดามารดา คู่สมรส บุตรชอบด้วยกฎหมายหรือบุตรบุญธรรม
 *       ของผู้มีเงินได้ บิดามารดาหรือบุตรชอบด้วยกฎหมายของคู่สมรสของผู้มีเงินได้ หรือบุคคลอื่น
 *       ที่ผู้มีเงินได้เป็นผู้ดูแลตามกฎหมายว่าด้วยการส่งเสริมและพัฒนาคุณภาพชีวิตคนพิการ
 *       คนละ 60,000 บาท
 *
 * The amount is therefore settled: 60,000 per declared person. Page 9 adds a further limit —
 * a บุคคลอื่น (someone outside the listed family relationships) may certify the taxpayer
 * "ได้ไม่เกิน 1 คน". This schema stores one undifferentiated `disabled_person` relation and
 * cannot tell a family member from a บุคคลอื่น, so that sub-limit is reported rather than
 * applied; it is never silently assumed either way.
 */
class DisabledPersonAllowanceStrategy implements FamilyAllowanceStrategy
{
    public const AMOUNT = '60000.00';

    public const RELATIONSHIP_FAMILY = 'family_member';

    public const RELATIONSHIP_OTHER = 'other_person';

    public const RELATIONSHIPS = [self::RELATIONSHIP_FAMILY, self::RELATIONSHIP_OTHER];

    public const RULE_CODE = 'PND90_2568_DISABLED_PERSON_OTHER_LIMIT';

    public function code(): string
    {
        return 'DISABLED_PERSON';
    }

    public function derive(FamilyFacts $facts, ?TaxRuleVersion $version = null): array
    {
        $eligible = array_values(array_filter($facts->dependentsOf('disabled_person'),
            fn (array $row): bool => ($row['eligible'] ?? false) === true));
        $rule = $version?->allowanceRules()->where('code', self::RULE_CODE)->where('active', true)->first();

        if ($rule === null || ($rule->conditions['other_person_limit'] ?? null) !== 1) {
            $counted = count($eligible);
            $warnings = $counted > 1 ? [['code' => 'DISABLED_PERSON_OTHER_LIMIT_UNMODELLED',
                'message' => 'ใบแนบ ข้อ 5 จำกัดการรับรองโดย "บุคคลอื่น" ไว้ไม่เกิน 1 คน ซึ่ง rule version นี้ยังแยกบุคคลอื่นออกจากบุคคลในครอบครัวไม่ได้ '
                    .'จำนวนที่แสดงจึงอาจสูงกว่าสิทธิจริง']] : [];

            return ['amount' => (new Money(self::AMOUNT))->multiply((string) $counted),
                'basis' => 'ใบแนบ item 5 — คนพิการหรือคนทุพพลภาพในความอุปการะ '.$counted.' คน คนละ 60,000 บาท',
                'warnings' => $warnings];
        }

        $familyCount = count(array_filter($eligible,
            fn (array $row): bool => ($row['disabled_person_relationship'] ?? null) === self::RELATIONSHIP_FAMILY));
        $otherCount = count(array_filter($eligible,
            fn (array $row): bool => ($row['disabled_person_relationship'] ?? null) === self::RELATIONSHIP_OTHER));
        $undeclaredCount = count($eligible) - $familyCount - $otherCount;
        $countedOther = min($otherCount, (int) $rule->conditions['other_person_limit']);
        $counted = $familyCount + $countedOther + $undeclaredCount;
        $warnings = [];

        if ($otherCount > $countedOther) {
            $warnings[] = ['code' => 'DISABLED_PERSON_OTHER_LIMIT_APPLIED',
                'message' => 'ใบแนบ ข้อ 5 จำกัดบุคคลอื่นที่อยู่ในความดูแลไว้ไม่เกิน 1 คน ระบบจึงนับบุคคลอื่น 1 คน'];
        }
        if ($undeclaredCount > 0) {
            $warnings[] = ['code' => 'DISABLED_PERSON_RELATIONSHIP_MISSING',
                'message' => 'โปรดระบุว่าคนพิการหรือคนทุพพลภาพเป็นบุคคลในครอบครัวหรือบุคคลอื่น เพื่อให้ระบบใช้ข้อจำกัดได้ครบถ้วน'];
        }

        return ['amount' => (new Money(self::AMOUNT))->multiply((string) $counted),
            'basis' => 'ใบแนบ item 5 — บุคคลในครอบครัว '.$familyCount.' คน บุคคลอื่น '.$countedOther.' คน'
                .($undeclaredCount === 0 ? '' : ' และยังไม่ระบุความสัมพันธ์ '.$undeclaredCount.' คน').' คนละ 60,000 บาท',
            'warnings' => $warnings];
    }
}

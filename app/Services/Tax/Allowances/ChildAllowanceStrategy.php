<?php

namespace App\Services\Tax\Allowances;

use App\DTO\Tax\FamilyFacts;
use App\Models\TaxRuleVersion;
use App\ValueObjects\Money;

/**
 * ใบแนบ item 3 — บุตร.
 *
 * PND90-2568-filing-instructions.pdf, page 7, verbatim:
 *
 *   3.1 บุตรชอบด้วยกฎหมายของผู้มีเงินได้ หรือบุตรชอบด้วยกฎหมายของคู่สมรสของผู้มีเงินได้
 *       คนละ 30,000 บาท และสำหรับบุตรชอบด้วยกฎหมายตั้งแต่คนที่สองเป็นต้นไปที่เกิดในหรือ
 *       หลังปี พ.ศ. 2561 ให้หักลดหย่อนได้เพิ่มอีกคนละ 30,000 บาท โดยในการนับลำดับบุตร
 *       ให้นับลำดับของบุตรทุกคนไม่ว่าจะมีชีวิตอยู่หรือไม่ก็ตาม
 *   3.2 บุตรบุญธรรมของผู้มีเงินได้ คนละ 30,000 บาท แต่รวมกันต้องไม่เกินสามคน
 *   3.3 ในกรณีผู้มีเงินได้มีบุตรทั้ง 3.1 และ 3.2 … ให้นำบุตรตาม 3.1 ทั้งหมดมาหักก่อน แล้วจึง
 *       นำบุตรตาม 3.2 มาหัก เว้นแต่ในกรณีผู้มีเงินได้มีบุตรตาม 3.1 ที่มีชีวิตอยู่รวมเป็นจำนวน
 *       ตั้งแต่สามคนขึ้นไป จะนำบุตรตาม 3.2 มาหักไม่ได้ แต่ถ้าบุตรตาม 3.1 มีจำนวนไม่ถึงสามคน
 *       ให้นำบุตรตาม 3.2 มาหักได้ โดยเมื่อรวมกับบุตรตาม 3.1 แล้วต้องไม่เกินสามคน
 *
 * The printed eligibility test — อายุไม่เกิน 25 ปีและยังศึกษาอยู่ในมหาวิทยาลัยหรือชั้นอุดมศึกษา
 * หรือซึ่งเป็นผู้เยาว์ หรือศาลสั่งให้เป็นคนไร้ความสามารถหรือเสมือนไร้ความสามารถ, and มิให้หัก
 * ลดหย่อนสำหรับบุตรที่มีเงินได้พึงประเมิน 30,000 บาทขึ้นไป — depends on the child's study
 * status and the child's own income, neither of which this schema records. It is therefore
 * the taxpayer's declaration (`eligible`), not something this engine decides.
 */
class ChildAllowanceStrategy implements FamilyAllowanceStrategy
{
    public const BASE_AMOUNT = '30000.00';

    /** ตั้งแต่คนที่สองเป็นต้นไปที่เกิดในหรือหลังปี พ.ศ. 2561 — an extra 30,000. */
    public const ADDITIONAL_AMOUNT = '30000.00';

    public const ADDITIONAL_FROM_BIRTH_ORDER = 2;

    public const ADDITIONAL_FROM_BUDDHIST_YEAR = 2561;

    /** บุตรบุญธรรม … แต่รวมกันต้องไม่เกินสามคน (item 3.2, and the combined limit in 3.3). */
    public const ADOPTED_LIMIT = 3;

    public const LEGITIMATE = 'legitimate';

    public const ADOPTED = 'adopted';

    public function code(): string
    {
        return 'CHILD';
    }

    public function derive(FamilyFacts $facts, ?TaxRuleVersion $version = null): array
    {
        $children = $facts->dependentsOf('child');
        $warnings = $counted = [];
        $legitimate = $adopted = [];

        foreach ($children as $child) {
            if (($child['eligible'] ?? false) !== true) {
                continue;
            }
            $type = $child['child_type'] ?? null;
            if (! in_array($type, [self::LEGITIMATE, self::ADOPTED], true)) {
                $warnings[] = ['code' => 'CHILD_TYPE_NOT_DECLARED',
                    'message' => 'ใบแนบ ข้อ 3 แยกบุตรชอบด้วยกฎหมาย (3.1) ออกจากบุตรบุญธรรม (3.2) จึงต้องระบุประเภทของบุตรก่อนจึงจะคำนวณได้'];

                continue;
            }
            $type === self::LEGITIMATE ? $legitimate[] = $child : $adopted[] = $child;
        }

        $total = new Money;
        foreach ($legitimate as $child) {
            $total = $total->add(new Money(self::BASE_AMOUNT));
            $counted[] = self::LEGITIMATE;
            $extra = $this->additionalWarrants($child);
            if ($extra === null) {
                $warnings[] = ['code' => 'CHILD_BIRTH_ORDER_NOT_DECLARED',
                    'message' => 'ใบแนบ ข้อ 3.1 ให้หักเพิ่มอีก 30,000 บาทสำหรับบุตรชอบด้วยกฎหมายตั้งแต่คนที่สองเป็นต้นไปที่เกิดในหรือหลังปี พ.ศ. 2561 '
                        .'จึงต้องระบุลำดับบุตรและปีเกิด มิฉะนั้นจะหักได้เพียง 30,000 บาท'];

                continue;
            }
            if ($extra) {
                $total = $total->add(new Money(self::ADDITIONAL_AMOUNT));
            }
        }
        // 3.3 — บุตรตาม 3.1 มาหักก่อน; adopted children only fill the remainder up to three,
        // and not at all once three legitimate children already count.
        $remaining = max(0, self::ADOPTED_LIMIT - count($legitimate));
        $allowed = min($remaining, count($adopted));
        for ($i = 0; $i < $allowed; $i++) {
            $total = $total->add(new Money(self::BASE_AMOUNT));
            $counted[] = self::ADOPTED;
        }
        if ($allowed < count($adopted)) {
            $warnings[] = ['code' => 'ADOPTED_CHILD_LIMIT_APPLIED',
                'message' => 'ใบแนบ ข้อ 3.2/3.3 หักลดหย่อนบุตรบุญธรรมรวมกันได้ไม่เกินสามคน และหักบุตรชอบด้วยกฎหมายก่อน'];
        }

        return ['amount' => $total,
            'basis' => 'ใบแนบ item 3 — บุตรชอบด้วยกฎหมาย '.count($legitimate).' คน, บุตรบุญธรรมที่หักได้ '.$allowed.' คน',
            'warnings' => $this->unique($warnings)];
    }

    /** Null when the facts needed to decide the extra 30,000 were not declared. */
    private function additionalWarrants(array $child): ?bool
    {
        $order = $child['birth_order'] ?? null;
        $birthDate = $child['birth_date'] ?? null;
        if (! is_int($order) || $order < 1 || ! is_string($birthDate) || $birthDate === '') {
            return null;
        }
        if ($order < self::ADDITIONAL_FROM_BIRTH_ORDER) {
            return false;
        }
        $gregorianYear = (int) substr($birthDate, 0, 4);

        return $gregorianYear + 543 >= self::ADDITIONAL_FROM_BUDDHIST_YEAR;
    }

    /** @param list<array{code: string, message: string}> $warnings */
    private function unique(array $warnings): array
    {
        $seen = [];
        foreach ($warnings as $warning) {
            $seen[$warning['code']] = $warning;
        }

        return array_values($seen);
    }
}

<?php

namespace App\Services\Tax;

/**
 * Input-shape rules only. This service never calculates or changes a tax amount.
 */
final class InputIntegrityValidator
{
    public const UNIQUE_PREPAYMENT_TYPES = ['pnd93', 'pnd94'];

    public const UNIQUE_RELATIONSHIP_ROLES = ['father', 'mother', 'spouse_father', 'spouse_mother'];

    public const DUPLICATE_ALLOWANCE_CODE = 'DUPLICATE_ALLOWANCE_CODE';

    public const DUPLICATE_DONATION_CODE = 'DUPLICATE_DONATION_CODE';

    public const DUPLICATE_PREPAYMENT_TYPE = 'DUPLICATE_PREPAYMENT_TYPE';

    public const DUPLICATE_DEPENDENT = 'DUPLICATE_DEPENDENT';

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    public function errors(array $payload, string $prefix = ''): array
    {
        return [
            ...$this->duplicateErrors($payload, $prefix),
            ...$this->requiredFactErrors($payload, $prefix),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function duplicateErrors(array $payload, string $prefix): array
    {
        $errors = [];
        $errors += $this->duplicateByKey(
            $payload['allowances'] ?? [],
            fn (array $item): ?string => $this->stringKey($item['code'] ?? null),
            fn (int $index): string => $prefix."allowances.$index.code",
            self::DUPLICATE_ALLOWANCE_CODE.': พบรายการค่าลดหย่อนรหัสเดียวกันซ้ำ กรุณากรอกยอดรวมรายปีเพียงรายการเดียว',
        );
        $errors += $this->duplicateByKey(
            $payload['donations'] ?? [],
            fn (array $item): ?string => $this->stringKey($item['code'] ?? null),
            fn (int $index): string => $prefix."donations.$index.code",
            self::DUPLICATE_DONATION_CODE.': พบประเภทเงินบริจาคซ้ำ กรุณากรอกยอดรวมรายปีเพียงรายการเดียวต่อประเภท',
        );
        $errors += $this->duplicateByKey(
            $payload['withholdings'] ?? [],
            fn (array $item): ?string => in_array($item['type'] ?? null, self::UNIQUE_PREPAYMENT_TYPES, true)
                ? (string) $item['type'] : null,
            fn (int $index): string => $prefix."withholdings.$index.type",
            self::DUPLICATE_PREPAYMENT_TYPE.': พบยอดภาษีชำระล่วงหน้าประเภทเดียวกันซ้ำ กรุณากรอกยอดรวมรายปีเพียงรายการเดียว',
        );
        $errors += $this->duplicateByKey(
            $payload['dependents'] ?? [],
            fn (array $item): ?string => $this->dependentKey($item),
            fn (int $index): string => $prefix."dependents.$index.relation_type",
            self::DUPLICATE_DEPENDENT.': พบผู้พึ่งพาที่มีบทบาทหรือลำดับเดียวกันซ้ำ กรุณาตรวจสอบรายการเดิม',
        );

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function requiredFactErrors(array $payload, string $prefix): array
    {
        $errors = [];
        if (is_array($payload['spouse'] ?? null)
            && (($payload['profile']['marital_status'] ?? null) !== 'married')) {
            $errors[$prefix.'profile.marital_status'] =
                'REQUIRED_WHEN_SPOUSE_DECLARED: เมื่อกรอกข้อมูลคู่สมรส ต้องระบุสถานภาพเป็นสมรส';
        }

        foreach (is_array($payload['dependents'] ?? null) ? $payload['dependents'] : [] as $index => $dependent) {
            if (! is_array($dependent)) {
                continue;
            }
            if (! array_key_exists('eligible', $dependent)) {
                $errors[$prefix."dependents.$index.eligible"] =
                    'REQUIRED_DEPENDENT_DECLARATION: ต้องยืนยันว่าเข้าเงื่อนไขหรือไม่สำหรับผู้พึ่งพาทุกราย';
            }
            if (($dependent['relation_type'] ?? null) !== 'child') {
                continue;
            }
            $childType = $dependent['child_type'] ?? null;
            if ($childType === null || $childType === '') {
                $errors[$prefix."dependents.$index.child_type"] =
                    'REQUIRED_CHILD_TYPE: เมื่อขอสิทธิบุตร ต้องระบุประเภทบุตรตามใบแนบ';
            }
            if ($childType === 'legitimate') {
                if (($dependent['birth_order'] ?? null) === null) {
                    $errors[$prefix."dependents.$index.birth_order"] =
                        'REQUIRED_CHILD_BIRTH_ORDER: บุตรชอบด้วยกฎหมายต้องระบุลำดับบุตร';
                }
                if (($dependent['birth_date'] ?? null) === null) {
                    $errors[$prefix."dependents.$index.birth_date"] =
                        'REQUIRED_CHILD_BIRTH_DATE: บุตรชอบด้วยกฎหมายต้องระบุวันเกิดเพื่อพิจารณารายการตามใบแนบ';
                }
            }
        }

        foreach (is_array($payload['incomes'] ?? null) ? $payload['incomes'] : [] as $index => $income) {
            if (! is_array($income)
                || ! SeparateTaxCalculator::eligible($income['income_type'] ?? null, $income['income_subtype'] ?? null)
                || isset($income['tax_treatment'])) {
                continue;
            }
            $errors[$prefix."incomes.$index.tax_treatment"] =
                'REQUIRED_TAX_TREATMENT: เงินได้จากการให้หรือการรับตามข้อ 9 ต้องเลือกว่าจะรวมคำนวณหรือเสียภาษีแยก';
        }

        return $errors;
    }

    /**
     * @param  callable(array<string, mixed>): ?string  $keyResolver
     * @param  callable(int): string  $attributeResolver
     * @return array<string, string>
     */
    private function duplicateByKey(
        mixed $items,
        callable $keyResolver,
        callable $attributeResolver,
        string $message,
    ): array {
        if (! is_array($items)) {
            return [];
        }
        $seen = [];
        $errors = [];
        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }
            $key = $keyResolver($item);
            if ($key === null) {
                continue;
            }
            if (isset($seen[$key])) {
                $errors[$attributeResolver((int) $index)] = $message;
            }
            $seen[$key] = true;
        }

        return $errors;
    }

    /** @param array<string, mixed> $dependent */
    public function dependentKey(array $dependent): ?string
    {
        $relationType = $this->stringKey($dependent['relation_type'] ?? null);
        if (in_array($relationType, self::UNIQUE_RELATIONSHIP_ROLES, true)) {
            return 'role:'.$relationType;
        }
        if ($relationType === 'child' && ($dependent['child_type'] ?? null) !== null
            && ($dependent['birth_order'] ?? null) !== null) {
            return 'child:'.$dependent['child_type'].':'.$dependent['birth_order'];
        }

        return null;
    }

    private function stringKey(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}

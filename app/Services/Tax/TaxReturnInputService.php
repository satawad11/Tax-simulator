<?php

namespace App\Services\Tax;

use App\Models\AllowanceType;
use App\Models\DonationRule;
use App\Models\IncomeExemptionRule;
use App\Models\TaxReturn;
use App\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaxReturnInputService
{
    public function __construct(
        private TaxReturnService $returns,
        private InputIntegrityValidator $inputIntegrity,
    ) {}

    public function save(TaxReturn $return, string $relation, array $data, ?string $id = null): Model
    {
        return DB::transaction(function () use ($return, $relation, $data, $id): Model {
            $return = $this->returns->locked($return);
            $this->returns->assertDraft($return);
            $query = $return->$relation();
            $record = $id !== null ? $query->whereKey($id)->firstOrFail() :
                (in_array($relation, ['profile', 'spouse'], true) ? $query->first() : null);
            if ($record === null && ! in_array($relation, ['profile', 'spouse'], true) && $query->count() >= 100) {
                throw ValidationException::withMessages([$relation => 'At most 100 entries are supported.']);
            }
            if ($relation === 'incomes') {
                // Saving follows the form's income-type mapping. Whether a saved type can be
                // calculated is a separate, later gate, so a draft may hold an income type
                // whose expense rule is not verified yet.
                $code = $data['income_type'] ?? $record?->incomeType->code;
                $type = $return->taxForm->incomeTypes()->where('code', $code)->first();
                if (! $type) {
                    throw ValidationException::withMessages(['income_type' => 'This income type is not supported by the selected tax form.']);
                }
                $subtype = array_key_exists('income_subtype', $data) ? ($data['income_subtype'] ?: null) : $record?->income_subtype;
                if (IncomeSubtypeCatalogue::requiresSubtype($code)) {
                    if ($subtype === null || ! IncomeSubtypeCatalogue::knows($code, $subtype)) {
                        throw ValidationException::withMessages(['income_subtype' => 'This income type is printed as several subcategories; state which one applies ('
                            .implode(', ', IncomeSubtypeCatalogue::codes($code)).').']);
                    }
                } elseif ($subtype !== null) {
                    throw ValidationException::withMessages(['income_subtype' => 'This income type is printed as a single category and takes no subtype.']);
                }
                $data['income_subtype'] = $subtype;
                // M7.4 — ข้อ 7 prints two further facts beside a blank percentage. They are
                // required exactly where the form prints them and rejected everywhere else.
                $activity = array_key_exists('expense_activity', $data) ? ($data['expense_activity'] ?: null) : $record?->expense_activity;
                if (ExpenseActivityCatalogue::requiresActivity($code, $subtype)) {
                    if ($activity === null || ! ExpenseActivityCatalogue::knows($activity)) {
                        throw ValidationException::withMessages(['expense_activity' => 'ภ.ง.ด.90 ข้อ 7 ข้อย่อย 1 takes its expense rate from '
                            .'ตารางที่ 2 (คำแนะนำ หน้า 17); state which of its 44 activities applies.']);
                    }
                } elseif ($activity !== null) {
                    throw ValidationException::withMessages(['expense_activity' => 'This income category takes no expense activity.']);
                }
                $data['expense_activity'] = $activity;
                $years = array_key_exists('holding_years', $data) ? ($data['holding_years'] === null ? null : (int) $data['holding_years']) : $record?->holding_years;
                if (PropertyHoldingPeriodCatalogue::requiresHoldingYears($code, $subtype)) {
                    if (! PropertyHoldingPeriodCatalogue::knows($years === null ? null : (int) $years)) {
                        throw ValidationException::withMessages(['holding_years' => 'ภ.ง.ด.90 ข้อ 7 ข้อย่อย 3 (2) takes its expense rate from '
                            .'จำนวนปีที่ถือครอง; state the number of years.']);
                    }
                } elseif ($years !== null) {
                    throw ValidationException::withMessages(['holding_years' => 'This income category takes no holding period.']);
                }
                $data['holding_years'] = $years === null ? null : (int) $years;
                $treatment = array_key_exists('tax_treatment', $data) ? ($data['tax_treatment'] ?: null) : $record?->tax_treatment;
                if ($treatment === SeparateTaxCalculator::TREATMENT && ! SeparateTaxCalculator::eligible($code, $subtype)) {
                    throw ValidationException::withMessages(['tax_treatment' => 'Only gift income under มาตรา 42 (26) (27) (28) may elect the separate rate printed in ข้อ 9.']);
                }
                if (SeparateTaxCalculator::eligible($code, $subtype) && $treatment === null) {
                    throw ValidationException::withMessages(['tax_treatment' => 'REQUIRED_TAX_TREATMENT: เงินได้จากการให้หรือการรับตามข้อ 9 ต้องเลือกว่าจะรวมคำนวณหรือเสียภาษีแยก']);
                }
                $data['tax_treatment'] = $treatment;
                $data['income_type_id'] = $type->id;
                unset($data['income_type']);
                $gross = new Money($data['gross_amount'] ?? $record?->gross_amount ?? '0');
                $exempt = new Money($data['exempt_amount'] ?? $record?->exempt_amount ?? '0');
                if ($exempt->compare($gross) > 0) {
                    throw ValidationException::withMessages(['exempt_amount' => 'Exempt amount must not exceed gross amount.']);
                }
                $data['exempt_amount'] = (string) $exempt;
                $actual = $data['actual_expense'] ?? $record?->actual_expense;
                if ($actual !== null && (new Money($actual))->compare($gross->subtract($exempt)) > 0) {
                    throw ValidationException::withMessages(['actual_expense' => 'Declared actual expense must not exceed income after exemption.']);
                }
            }
            if ($relation === 'allowances') {
                $code = $data['code'] ?? $record?->allowanceType->code;
                $type = AllowanceType::where('code', $code)->where('is_active', true)->firstOrFail();
                $duplicate = $return->allowances()->where('allowance_type_id', $type->id);
                if ($record) {
                    $duplicate->whereKeyNot($record->id);
                }
                if ($duplicate->exists()) {
                    throw ValidationException::withMessages(['code' => InputIntegrityValidator::DUPLICATE_ALLOWANCE_CODE
                        .': พบรายการค่าลดหย่อนรหัสเดียวกันซ้ำ กรุณาแก้ไขรายการเดิม']);
                }
                $data['allowance_type_id'] = $type->id;
                unset($data['code']);
            }
            if ($relation === 'incomeExemptions') {
                $code = $data['code'] ?? $record?->code;
                /*
                 * Checked against the return's *saved* version, not a table-wide list. A return
                 * calculated under an earlier version must not be able to save a line only a
                 * later version carries, or it would be unable to recalculate itself.
                 */
                $rule = IncomeExemptionRule::where('rule_version_id', $return->rule_version_id)
                    ->where('code', $code)->where('active', true)->first();
                if (! $rule) {
                    throw ValidationException::withMessages(['code' => 'INCOME_EXEMPTION_UNSUPPORTED: '
                        .'รายการเงินได้ที่ได้รับยกเว้นนี้ไม่มีกฎที่ตรวจสอบแล้วในกฎภาษีฉบับที่แบบนี้ใช้อยู่']);
                }
                $duplicate = $return->incomeExemptions()->where('code', $code);
                if ($record) {
                    $duplicate->whereKeyNot($record->id);
                }
                if ($duplicate->exists()) {
                    throw ValidationException::withMessages(['code' => 'DUPLICATE_INCOME_EXEMPTION_CODE: '
                        .'พบรายการเงินได้ที่ได้รับยกเว้นรหัสเดียวกันซ้ำ กรุณาแก้ไขยอดรวมรายปีในรายการเดิม']);
                }
            }
            if ($relation === 'donations') {
                $code = $data['donation_code'] ?? $record?->donation_code;
                $rule = DonationRule::where('rule_version_id', $return->rule_version_id)->where('code', $code)->where('active', true)->first();
                if (! $rule) {
                    throw ValidationException::withMessages(['donation_code' => 'Unknown or unsupported donation code for the saved rule version.']);
                }
                $duplicate = $return->donations()->where('donation_code', $code);
                if ($record) {
                    $duplicate->whereKeyNot($record->id);
                }
                if ($duplicate->exists()) {
                    throw ValidationException::withMessages(['donation_code' => InputIntegrityValidator::DUPLICATE_DONATION_CODE
                        .': พบประเภทเงินบริจาคซ้ำ กรุณาแก้ไขยอดรวมรายปีในรายการเดิม']);
                }
                $data['donation_type'] = $rule->donation_type;
            }
            if ($relation === 'withholdings') {
                $type = $data['type'] ?? $record?->type;
                if (in_array($type, InputIntegrityValidator::UNIQUE_PREPAYMENT_TYPES, true)) {
                    $duplicate = $return->withholdings()->where('type', $type);
                    if ($record) {
                        $duplicate->whereKeyNot($record->id);
                    }
                    if ($duplicate->exists()) {
                        throw ValidationException::withMessages(['type' => InputIntegrityValidator::DUPLICATE_PREPAYMENT_TYPE
                            .': พบยอดภาษีชำระล่วงหน้าประเภทเดียวกันซ้ำ กรุณาแก้ไขยอดรวมรายปีในรายการเดิม']);
                    }
                }
            }
            if ($relation === 'dependents') {
                $dependent = array_replace($record?->only([
                    'relation_type', 'disabled_person_relationship', 'child_type', 'birth_order', 'birth_date', 'eligible',
                ]) ?? [], $data);
                $dependentErrors = $this->inputIntegrity->errors(['dependents' => [$dependent]]);
                if ($dependentErrors !== []) {
                    throw ValidationException::withMessages(collect($dependentErrors)->mapWithKeys(
                        fn (string $message, string $attribute): array => [str_replace('dependents.0.', '', $attribute) => $message]
                    )->all());
                }
                $dependentKey = $this->inputIntegrity->dependentKey($dependent);
                if ($dependentKey !== null) {
                    $duplicate = $return->dependents();
                    if (str_starts_with($dependentKey, 'role:')) {
                        $duplicate->where('relation_type', $dependent['relation_type']);
                    } else {
                        $duplicate->where('relation_type', 'child')
                            ->where('child_type', $dependent['child_type'])
                            ->where('birth_order', $dependent['birth_order']);
                    }
                    if ($record) {
                        $duplicate->whereKeyNot($record->id);
                    }
                    if ($duplicate->exists()) {
                        throw ValidationException::withMessages(['relation_type' => InputIntegrityValidator::DUPLICATE_DEPENDENT
                            .': พบผู้พึ่งพาที่มีบทบาทหรือลำดับเดียวกันซ้ำ กรุณาตรวจสอบรายการเดิม']);
                    }
                }
                $data['relationship'] = match ($dependent['relation_type']) {
                    'child' => 'child','disabled_person' => 'disabled_person',default => 'parent'
                };
                if ($dependent['relation_type'] !== 'disabled_person') {
                    $data['disabled_person_relationship'] = null;
                }
            }
            if (in_array($relation, ['profile', 'spouse'], true)) {
                $data = array_replace($relation === 'profile' ? ['birth_date' => null, 'marital_status' => null, 'filing_status' => null] :
                    ['birth_date' => null, 'has_income' => false, 'filing_status' => null], $data);
            }
            if ($record) {
                $record->fill($data)->save();
            } else {
                $record = $query->create($data);
            }
            $return->touch();

            return $record->refresh()->load(match ($relation) {
                'incomes' => ['incomeType'],'allowances' => ['allowanceType'],default => []
            });
        });
    }

    public function delete(TaxReturn $return, string $relation, ?string $id = null): void
    {
        DB::transaction(function () use ($return, $relation, $id): void {
            $return = $this->returns->locked($return);
            $this->returns->assertDraft($return);
            $query = $return->$relation();
            if ($id !== null) {
                $query->whereKey($id)->firstOrFail()->delete();
            } else {
                $query->delete();
            }
            $return->touch();
        });
    }
}

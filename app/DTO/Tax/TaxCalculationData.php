<?php

namespace App\DTO\Tax;

final readonly class TaxCalculationData
{
    /**
     * @param  list<array{income_type: string, gross_amount: string|int, exempt_amount?: string|int, actual_expense?: string|int|null, description?: string}>  $incomes
     * @param  list<array{code: string, amount: string|int}>  $allowances
     * @param  list<array{code: string, amount: string|int}>  $donations
     * @param  list<array{type: string, amount: string|int}>  $withholdings
     * @param  list<array{code: string, amount: string|int, declarations_confirmed?: bool}>  $incomeExemptions
     */
    public function __construct(
        public int $taxYear,
        public string $formCode,
        public array $incomes,
        public array $allowances = [],
        public array $donations = [],
        public array $withholdings = [],
        /*
         * เงินได้ที่ได้รับยกเว้น taken after expenses — ใบแนบ ข้อ 13 (13.4) and ข้อ 20 (20.4). Kept
         * apart from `allowances` because the two land at different points in the calculation and
         * are not interchangeable; see IncomeExemptionCalculator.
         */
        public array $incomeExemptions = [],
        // Milestone 07.3 — the facts the printed ใบแนบ family allowances are derived from.
        // Facts only: no family allowance amount ever travels on this DTO.
        public FamilyFacts $family = new FamilyFacts,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self((int) $data['tax_year'], $data['form_code'], $data['incomes'],
            $data['allowances'] ?? [], $data['donations'] ?? [], $data['withholdings'] ?? [],
            $data['income_exemptions'] ?? [], FamilyFacts::fromArray($data));
    }
}

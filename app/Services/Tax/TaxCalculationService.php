<?php

namespace App\Services\Tax;

use App\DTO\Tax\TaxCalculationData;
use App\Exceptions\TaxMetadataConflictException;
use App\Models\TaxRuleVersion;
use App\ValueObjects\Money;

class TaxCalculationService
{
    /** Forms this engine can calculate. Which income types each accepts comes from the database. */
    public const FORMS = ['PND90', 'PND91'];

    public function __construct(
        private TaxMetadataService $metadata,
        private IncomeCalculator $incomeCalculator,
        private ExpenseCalculator $expenseCalculator,
        private IncomeExemptionCalculator $incomeExemptionCalculator,
        private AllowanceCalculator $allowanceCalculator,
        private CombinedAllowanceCapResolver $combinedCapResolver,
        private DonationCalculator $donationCalculator,
        private ProgressiveTaxCalculator $progressiveCalculator,
        private TaxCreditCalculator $creditCalculator,
        private SeparateTaxCalculator $separateTaxCalculator,
        private MinimumTaxCalculator $minimumTaxCalculator,
        private TaxRefundService $refundService,
        private TaxAnalysisService $analysisService,
    ) {}

    public function calculate(TaxCalculationData $data, ?TaxRuleVersion $savedVersion = null): array
    {
        if ($data->incomes === [] || ! in_array($data->formCode, self::FORMS, true)) {
            throw new \InvalidArgumentException('A supported tax form and at least one income line are required.');
        }
        if ($savedVersion === null) {
            $year = $this->metadata->context($data->taxYear);
            $form = $this->metadata->form($year, $data->formCode);
            $version = $year->publishedRuleVersion;
        } else {
            $version = $savedVersion->fresh();
            $year = $version->taxYear;
            if ($year->year !== $data->taxYear || ! in_array($version->status, ['published', 'retired'], true)) {
                throw new TaxMetadataConflictException('The saved rule version is not a published historical context.');
            }
            $form = $year->forms()->where('code', $data->formCode)->firstOrFail();
        }
        // Income types come from the stored form mapping, so PND91 still accepts SECTION_40_1
        // only while PND90 accepts every category mapped to it.
        $mapped = $form->incomeTypes()->pluck('code')->all();
        foreach ($data->incomes as $line) {
            if (! in_array($line['income_type'] ?? null, $mapped, true)) {
                throw new \InvalidArgumentException('Income type is not mapped to the selected tax form.');
            }
        }
        // ข้อ 9 income is taxed at its own printed rate and never joins ข้อ 1–ข้อ 7, so it is
        // split out before the progressive flow begins.
        $elected = array_values(array_filter($data->incomes, SeparateTaxCalculator::elected(...)));
        $progressiveLines = array_values(array_filter($data->incomes, fn (array $line): bool => ! SeparateTaxCalculator::elected($line)));
        if ($progressiveLines === []) {
            throw new \InvalidArgumentException('At least one income line must use the progressive tax base.');
        }
        $separate = $this->separateTaxCalculator->calculate($elected);
        $income = $this->incomeCalculator->calculate($progressiveLines);
        $expenses = $this->expenseCalculator->calculate($income['items'], $version);
        $afterExpense = $income['gross_after_exemption']->subtract($expenses['total'])->max(new Money);
        /*
         * เงินได้ที่ได้รับยกเว้น that ใบแนบ takes *after* expenses — ข้อ 13 (13.4) and ข้อ 20 (20.4)
         * both say "หักออกจากเงินได้พึงประเมิน … เมื่อได้หักตามมาตรา 42 ทวิ ถึงมาตรา 46 แล้ว". It is a
         * separate stage from the allowance block below, and from the per-line exempt amount
         * above, because all three sit at different points and none substitutes for another.
         *
         * It does **not** reduce the ภ.ง.ด.90 minimum-tax base: that base is defined by reference
         * to the form's own ข้อ 1–ข้อ 7 boxes, which are gross assessable figures upstream of this
         * stage — the same reason the per-line exempt amount does not reduce it either.
         */
        $exemptions = $this->incomeExemptionCalculator->calculate($data->incomeExemptions, $version, $afterExpense);
        $afterIncomeExemption = $afterExpense->subtract($exemptions['total'])->max(new Money);
        // M7.4 — a percentage allowance names the income it is a percentage of, in words, and
        // those words are stored on the rule. Every base the vocabulary allows is known here.
        $bases = [
            PercentageBaseResolver::GROSS_INCOME => $income['gross_income'],
            PercentageBaseResolver::GROSS_AFTER_EXEMPTION => $income['gross_after_exemption'],
            PercentageBaseResolver::INCOME_AFTER_EXPENSE => $afterExpense,
        ];
        $allowances = $this->allowanceCalculator->calculate($data->allowances, $version, $data->family, $bases);
        // Individual ceilings are applied above; a ceiling the source states across codes is
        // applied here, on the already-capped items.
        $combined = $this->combinedCapResolver->apply($allowances['items'], $version, $allowances['total_eligible']);
        $allowances = [...$allowances, 'items' => $combined['items'], 'combined_cap_groups' => $combined['groups'],
            'total_eligible' => $combined['total_eligible'],
            'warnings' => [...$allowances['warnings'], ...$combined['warnings']]];
        $afterAllowance = $afterIncomeExemption->subtract($allowances['total_eligible'])->max(new Money);
        $donations = $this->donationCalculator->calculate($data->donations, $version, $afterAllowance);
        $net = $donations['net_income'];
        $progressive = $this->progressiveCalculator->calculate($net, $version->brackets()->orderBy('sort_order')->get());
        // ภ.ง.ด.90 page 6 — "คำนวณภาษีจาก 2 วิธี (แล้วให้ชำระภาษีจากยอดที่มากกว่า)". Method 1 stays
        // reported as progressive_tax; what is actually payable is the greater of the two.
        $minimum = $this->minimumTaxCalculator->calculate($form->code, $income['items'], $progressive['total']);
        $creditResult = $this->creditCalculator->calculate($data->withholdings);
        $credits = $creditResult['totals'];
        $result = $this->refundService->calculate($minimum['payable'], $credits, $separate['tax']);
        $analysis = $this->analysisService->analyze($income['gross_income'], $net, $minimum['payable'],
            $progressive['marginal_rate'], $credits['withholding'], $result);
        $stages = [
            ['GROSS_INCOME', 'เงินได้รวม', $income['gross_income']],
            ['EXEMPT_INCOME', 'หักเงินได้ที่ได้รับยกเว้น', $income['exempt_income']],
            ['INCOME_AFTER_EXEMPTION', 'คงเหลือหลังหักเงินได้ยกเว้น', $income['gross_after_exemption']],
            ['EXPENSE', 'หักค่าใช้จ่าย', $expenses['total']],
            ['INCOME_AFTER_EXPENSE', 'เงินได้หลังหักค่าใช้จ่าย', $afterExpense],
            // Printed only when a line was claimed, so a return without one keeps the trace it
            // has always had rather than gaining two steps that would always read zero.
            ...($exemptions['items'] === [] ? [] : [
                ['INCOME_EXEMPTIONS', 'หักเงินได้ที่ได้รับยกเว้นหลังหักค่าใช้จ่าย', $exemptions['total']],
                ['INCOME_AFTER_INCOME_EXEMPTIONS', 'คงเหลือหลังหักเงินได้ที่ได้รับยกเว้น', $afterIncomeExemption],
            ]),
            ['ALLOWANCES', 'หักค่าลดหย่อน', $allowances['total_eligible']],
            ['INCOME_AFTER_ALLOWANCES', 'คงเหลือหลังหักค่าลดหย่อน', $afterAllowance],
            ['SPECIAL_DONATION', 'หักเงินบริจาคพิเศษ', $donations['special']],
            ['INCOME_AFTER_SPECIAL_DONATION', 'คงเหลือหลังหักเงินบริจาคพิเศษ', $donations['after_special']],
            ['GENERAL_DONATION', 'หักเงินบริจาคทั่วไป', $donations['general']],
            ['NET_INCOME', 'เงินได้สุทธิ', $net],
            ['PROGRESSIVE_TAX', 'ภาษีตามขั้นเงินได้', $progressive['total']],
            // ภ.ง.ด.90 วิธีที่ 2 is printed only when its base reaches 120,000 and its result
            // exceeds 5,000, so the standard trace keeps its shape for every other return.
            ...($minimum['applicable'] === false ? [] : [
                ['MINIMUM_TAX_BASE', 'เงินได้พึงประเมินตาม ข้อ 1 ถึง ข้อ 7 (ไม่รวมมาตรา 40(1))', $minimum['base']],
                ['MINIMUM_TAX', 'ภาษีตามวิธีที่ 2 (ร้อยละ 0.5)', $minimum['tax']],
                ['TAX_PAYABLE', 'ภาษีที่ต้องชำระ (ยอดที่มากกว่า)', $minimum['payable']],
            ]),
            ['FOREIGN_TAX_CREDIT', 'เครดิตภาษีต่างประเทศ', $credits['foreign_tax_credit']],
            ['TAX_AFTER_FOREIGN_CREDIT', 'ภาษีหลังเครดิตต่างประเทศ', $result['components']['tax_after_foreign_credit']],
            ['WITHHOLDING_AND_PREPAID', 'ภาษีหัก ณ ที่จ่ายและชำระล่วงหน้า', $result['components']['withholding_and_prepaid']],
            // ข้อ 11 item 19 adds the ข้อ 9 tax only when income was routed there, so these two
            // steps appear only for an election and the standard trace keeps its 16 steps.
            ...($elected === [] ? [] : [
                ['SEPARATE_TAX_BASE', 'เงินได้ที่เลือกเสียภาษีแยกต่างหาก', $separate['base']],
                ['SEPARATE_TAX', 'ภาษีจากเงินได้ที่เลือกเสียภาษีแยกต่างหาก', $separate['tax']],
            ]),
            ['RESULT', 'ผลการจำลอง', $result['amount']],
        ];
        $trace = [];
        foreach ($stages as $index => [$code, $label, $amount]) {
            $trace[] = ['step' => $index + 1, 'code' => $code, 'label' => $label, 'amount' => $amount];
        }
        $trace[array_key_last($trace)]['status'] = $result['status'];
        // TODO: obtain approved legal final rounding; do not discard fractional satang.
        $warnings = [...$expenses['warnings'], ...$exemptions['warnings'], ...$allowances['warnings'], ...$donations['warnings'], ...$creditResult['warnings'],
            ...($minimum['method'] === 'MINIMUM_TAX' ? [['code' => 'PND90_MINIMUM_TAX_APPLIED',
                'message' => 'ภ.ง.ด.90 คำนวณภาษีจาก 2 วิธี และวิธีที่ 2 (ร้อยละ 0.5 ของเงินได้พึงประเมินตาม ข้อ 1 ถึง ข้อ 7 ไม่รวมมาตรา 40(1)) '
                    .'ให้ยอดที่มากกว่า จึงใช้ยอดนั้นเป็นภาษีที่ต้องชำระ',
                'path' => 'minimum_tax.tax']] : []),
            ['code' => 'ROUNDING_RULE_PENDING', 'message' => 'Exact decimal amounts are retained; legal final rounding is not yet verified.', 'path' => 'result.amount']];
        unset($expenses['warnings'], $exemptions['warnings'], $allowances['warnings'], $donations['warnings'], $donations['special'], $donations['general'],
            $donations['after_special'], $donations['net_income'], $progressive['marginal_rate']);

        return ['tax_year' => $year->year, 'form_code' => $form->code, 'rule_version' => $version->version,
            'income' => $income, 'expenses' => $expenses, 'income_after_expense' => $afterExpense,
            'income_exemptions' => $exemptions, 'income_after_income_exemptions' => $afterIncomeExemption,
            'allowances' => $allowances, 'donations' => $donations, 'net_income' => $net,
            'progressive_tax' => $progressive, 'separate_tax' => $separate, 'minimum_tax' => $minimum,
            'tax_components' => ['progressive_tax' => $progressive['total'], 'minimum_tax' => $minimum['tax'],
                'tax_before_credits' => $minimum['payable'], 'separate_tax' => $separate['tax'],
                'total' => $minimum['payable']->add($separate['tax'])],
            'credits' => $credits, 'result' => $result,
            'analysis' => $analysis, 'trace' => $trace, 'warnings' => $warnings,
            'disclaimer' => 'ผลลัพธ์เป็นการประมาณการจากข้อมูลที่กรอกในระบบจำลอง ไม่ใช่การยื่นแบบภาษีจริงหรือการรับรองผลโดยหน่วยงานราชการ'];
    }
}

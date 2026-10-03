<?php

namespace App\Services\Tax\Allowances;

use App\DTO\Tax\FamilyFacts;
use App\Models\TaxRuleVersion;
use App\ValueObjects\Money;

/**
 * ใบแนบ item 1 — ผู้มีเงินได้ 60,000 บาท.
 *
 * PND90-2568-filing-instructions.pdf, page 7:
 *
 *   1. ผู้มีเงินได้ 60,000 บาท
 *   1.1 กรณีผู้มีเงินได้เป็นห้างหุ้นส่วนสามัญหรือคณะบุคคลที่มิใช่นิติบุคคล หากอยู่ในประเทศไทย
 *       เพียงคนเดียวให้หักลดหย่อนได้ 60,000 บาท หากอยู่ในประเทศไทยตั้งแต่ 2 คนขึ้นไป
 *       ให้หักลดหย่อนได้ 120,000 บาท
 *   1.2 กรณีคู่สมรสมีเงินได้ฝ่ายเดียว … ผู้มีเงินได้หักลดหย่อนสำหรับผู้มีเงินได้ 60,000 บาท
 *
 * This resolves the 60,000 / 120,000 question the earlier reconciliation left open: 120,000
 * belongs to a ห้างหุ้นส่วนสามัญ/คณะบุคคลที่มิใช่นิติบุคคล with two or more members resident
 * in Thailand, which is not a taxpayer this simulator models. An individual filer is always
 * 60,000, whatever the marital status.
 */
class PersonalAllowanceStrategy implements FamilyAllowanceStrategy
{
    public const AMOUNT = '60000.00';

    public function code(): string
    {
        return 'PERSONAL';
    }

    public function derive(FamilyFacts $facts, ?TaxRuleVersion $version = null): array
    {
        return ['amount' => new Money(self::AMOUNT), 'basis' => 'ใบแนบ item 1 — ผู้มีเงินได้ 60,000 บาท', 'warnings' => []];
    }
}

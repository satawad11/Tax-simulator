<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class TaxCalculationResource extends MetadataResource
{
    public function toArray(Request $request): array
    {
        return array_intersect_key($this->resource, array_flip([
            'tax_year', 'form_code', 'rule_version', 'income', 'expenses', 'income_after_expense',
            // เงินได้ที่ได้รับยกเว้น taken after expenses — ใบแนบ ข้อ 13 (13.4) and ข้อ 20 (20.4). It
            // is a stage of its own between expenses and allowances, so a client that shows the
            // reader how the tax was reached needs it by name and not folded into either.
            'income_exemptions', 'income_after_income_exemptions',
            'allowances', 'donations', 'net_income', 'progressive_tax',
            // M7.2 appends the ข้อ 9 separate-rate component alongside the progressive tax;
            // M7.3 appends ภ.ง.ด.90's second calculation method (ร้อยละ 0.5).
            'separate_tax', 'minimum_tax', 'tax_components', 'credits', 'result',
            'analysis', 'trace', 'warnings', 'disclaimer',
            // M6 appends read-only guidance; M4 field names and semantics are unchanged.
            'recommendations', 'refund_guidance', 'payment_guidance',
        ]));
    }
}

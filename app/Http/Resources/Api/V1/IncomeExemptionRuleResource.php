<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

/**
 * A ใบแนบ line the filer claims as เงินได้ที่ได้รับยกเว้น, deducted after expenses.
 *
 * `declarations` is the part a client must not skip. These lines turn on facts no declared figure
 * carries — a geographic zone, a contract date, a contractor's registration — so the reader is
 * shown each printed condition and affirms it. The engine applies the arithmetic to what they
 * state and asserts no entitlement of its own.
 */
class IncomeExemptionRuleResource extends MetadataResource
{
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'method' => $this->method,
            'percentage' => $this->percentage,
            'step_amount' => $this->step_amount,
            'grant_per_step' => $this->grant_per_step,
            'maximum_amount' => $this->maximum_amount,
            'declarations' => $this->resource->declarations(),
        ];
    }
}

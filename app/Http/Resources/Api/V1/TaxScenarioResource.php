<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class TaxScenarioResource extends TaxScenarioSummaryResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'source_tax_return_id' => $this->source_tax_return_id,
            'tax_year' => $this->taxYear?->year,
            'rule_version' => $this->ruleVersion?->version,
            'payload' => $this->payload,
            'calculation_result' => $this->calculation_result,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

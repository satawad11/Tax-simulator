<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class TaxReturnSummaryResource extends MetadataResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'status' => $this->status, 'current_step' => $this->current_step,
            'tax_year' => $this->taxYear->year, 'form_code' => $this->taxForm->code, 'rule_version' => $this->ruleVersion->version,
            'completed_at' => $this->completed_at?->toIso8601String(), 'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String()];
    }
}

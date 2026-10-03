<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class TaxCalculationSummaryResource extends MetadataResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'calculated_at' => $this->calculated_at?->toIso8601String(),
            'rule_version' => $this->result_snapshot['rule_version'] ?? null,
            'result' => $this->result_snapshot['result'] ?? ['status' => $this->result_status, 'amount' => $this->final_tax]];
    }
}

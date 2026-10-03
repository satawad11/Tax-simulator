<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class TaxScenarioSummaryResource extends MetadataResource
{
    public function toArray(Request $request): array
    {
        $result = $this->calculation_result;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'updated_at' => $this->updated_at?->toIso8601String(),
            // Read straight from the stored comparison; listing never recalculates.
            'estimated_tax_saving' => $result['estimated_tax_saving'] ?? null,
            'result' => $result['after']['result'] ?? null,
            'calculated_at' => $this->calculated_at?->toIso8601String(),
        ];
    }
}

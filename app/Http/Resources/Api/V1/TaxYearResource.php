<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class TaxYearResource extends MetadataResource
{
    public function toArray(Request $request): array
    {
        return [
            'year' => $this->year, 'name' => $this->name, 'active' => $this->active,
            $this->mergeWhen($this->resource->relationLoaded('publishedRuleVersion'), fn (): array => [
                'filing_start_date' => $this->filing_start_date?->format('Y-m-d'),
                'filing_end_date' => $this->filing_end_date?->format('Y-m-d'),
                'rule_version' => ['version' => $this->publishedRuleVersion->version, 'status' => $this->publishedRuleVersion->status],
            ]),
        ];
    }
}

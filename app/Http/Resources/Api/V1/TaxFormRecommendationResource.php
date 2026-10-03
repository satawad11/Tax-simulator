<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class TaxFormRecommendationResource extends MetadataResource
{
    public function toArray(Request $request): array
    {
        return [
            'recommended_form' => ['code' => $this->resource['recommended_form']->code, 'name' => $this->resource['recommended_form']->name],
            'reason_code' => $this->resource['reason_code'], 'reason' => $this->resource['reason'],
        ];
    }
}

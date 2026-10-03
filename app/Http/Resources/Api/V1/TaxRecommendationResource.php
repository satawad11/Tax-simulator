<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class TaxRecommendationResource extends MetadataResource
{
    public function toArray(Request $request): array
    {
        return array_intersect_key($this->resource, array_flip(['code', 'type', 'priority', 'title', 'message', 'action']));
    }
}

<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class TaxBracketResource extends MetadataResource
{
    public function toArray(Request $request): array
    {
        return ['from' => $this->min_amount, 'to' => $this->max_amount,
            'rate' => $this->rate, 'sort_order' => $this->sort_order];
    }
}

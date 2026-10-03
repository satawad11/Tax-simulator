<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class TaxReturnProfileResource extends MetadataResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'birth_date' => $this->birth_date?->format('Y-m-d'), 'marital_status' => $this->marital_status, 'filing_status' => $this->filing_status];
    }
}

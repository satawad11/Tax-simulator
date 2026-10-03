<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class TaxReturnSpouseResource extends MetadataResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'birth_date' => $this->birth_date?->format('Y-m-d'), 'has_income' => $this->has_income, 'filing_status' => $this->filing_status];
    }
}

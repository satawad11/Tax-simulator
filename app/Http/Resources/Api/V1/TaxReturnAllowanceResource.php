<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class TaxReturnAllowanceResource extends MetadataResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'code' => $this->allowanceType->code, 'input_amount' => $this->input_amount];
    }
}

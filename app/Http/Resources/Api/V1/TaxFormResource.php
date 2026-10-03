<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class TaxFormResource extends MetadataResource
{
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code, 'name' => $this->name, 'description' => $this->description, 'active' => $this->active,
            'supported_income_types' => IncomeTypeResource::collection($this->whenLoaded('incomeTypes')),
        ];
    }
}

<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class TaxReturnWithholdingResource extends MetadataResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'type' => $this->type, 'payer_name' => $this->payer_name, 'payer_tax_id' => $this->payer_tax_id, 'amount' => $this->amount];
    }
}

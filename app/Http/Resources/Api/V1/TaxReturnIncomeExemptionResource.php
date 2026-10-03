<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class TaxReturnIncomeExemptionResource extends MetadataResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'code' => $this->code, 'input_amount' => $this->input_amount,
            // Echoed back so a client reopening a draft can restore the affirmation it needs to
            // recalculate, rather than silently re-asking or assuming it was given.
            'declarations_confirmed' => $this->declarations_confirmed];
    }
}

<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class TaxReturnDonationResource extends MetadataResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'donation_code' => $this->donation_code, 'input_amount' => $this->input_amount];
    }
}

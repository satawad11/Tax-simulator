<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class AllowanceRuleResource extends MetadataResource
{
    public function toArray(Request $request): array
    {
        return ['method' => $this->method, 'fixed_amount' => $this->fixed_amount,
            'percentage' => $this->percentage, 'percentage_base' => $this->percentage_base,
            'maximum_amount' => $this->maximum_amount,
            'minimum_amount' => $this->minimum_amount, 'conditions' => $this->conditions];
    }
}

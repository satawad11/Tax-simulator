<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class ExpenseRuleResource extends MetadataResource
{
    public function toArray(Request $request): array
    {
        return ['method' => $this->method, 'fixed_amount' => $this->fixed_amount,
            'percentage' => $this->percentage, 'maximum_amount' => $this->maximum_amount,
            'minimum_amount' => $this->minimum_amount, 'conditions' => $this->conditions];
    }
}

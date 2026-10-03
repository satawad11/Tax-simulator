<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class TaxReturnResource extends TaxReturnSummaryResource
{
    public function toArray(Request $request): array
    {
        return [...parent::toArray($request), 'profile' => $this->profile ? new TaxReturnProfileResource($this->profile) : null,
            'spouse' => $this->spouse ? new TaxReturnSpouseResource($this->spouse) : null,
            'dependents' => TaxReturnDependentResource::collection($this->dependents),
            'incomes' => TaxReturnIncomeResource::collection($this->incomes),
            'allowances' => TaxReturnAllowanceResource::collection($this->allowances),
            'income_exemptions' => TaxReturnIncomeExemptionResource::collection($this->incomeExemptions),
            'donations' => TaxReturnDonationResource::collection($this->donations),
            'withholdings' => TaxReturnWithholdingResource::collection($this->withholdings),
            'latest_calculation' => $this->latestCalculation ? new TaxCalculationSummaryResource($this->latestCalculation) : null];
    }
}

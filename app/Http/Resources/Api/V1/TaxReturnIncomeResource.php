<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class TaxReturnIncomeResource extends MetadataResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'income_type' => $this->incomeType->code, 'description' => $this->description,
            'gross_amount' => $this->gross_amount, 'exempt_amount' => $this->exempt_amount,
            'income_subtype' => $this->income_subtype,
            'expense_activity' => $this->expense_activity, 'holding_years' => $this->holding_years,
            'expense_method_selection' => $this->expense_method_selection,
            'tax_treatment' => $this->tax_treatment,
            'actual_expense' => $this->actual_expense];
    }
}

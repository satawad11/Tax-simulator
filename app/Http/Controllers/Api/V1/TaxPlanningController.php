<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PlanTaxRequest;
use App\Http\Resources\Api\V1\TaxPlanningResource;
use App\Services\Tax\TaxPlanningService;

class TaxPlanningController extends Controller
{
    /** Guest planning: stateless, persists nothing, resolves the published rule version. */
    public function __invoke(PlanTaxRequest $request, TaxPlanningService $service): TaxPlanningResource
    {
        $validated = $request->validated();

        return new TaxPlanningResource($service->plan(
            [...$validated['base'], 'tax_year' => $validated['tax_year'], 'form_code' => $validated['form_code']],
            $validated['scenario'],
        ));
    }
}

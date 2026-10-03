<?php

namespace App\Http\Controllers\Api\V1;

use App\DTO\Tax\TaxCalculationData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CalculateTaxRequest;
use App\Http\Resources\Api\V1\TaxCalculationResource;
use App\Services\Tax\TaxSimulationService;

class TaxCalculationController extends Controller
{
    public function __invoke(CalculateTaxRequest $request, TaxSimulationService $service): TaxCalculationResource
    {
        return new TaxCalculationResource($service->simulate(TaxCalculationData::fromArray($request->validated())));
    }
}

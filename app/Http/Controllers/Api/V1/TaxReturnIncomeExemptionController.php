<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\EmptyMemberActionRequest;
use App\Http\Requests\Api\V1\WriteTaxReturnIncomeExemptionRequest;
use App\Http\Resources\Api\V1\TaxReturnIncomeExemptionResource;
use App\Models\TaxReturn;
use App\Services\Tax\TaxReturnInputService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class TaxReturnIncomeExemptionController extends Controller
{
    public function store(WriteTaxReturnIncomeExemptionRequest $request, TaxReturn $taxReturn, TaxReturnInputService $service): JsonResponse
    {
        return (new TaxReturnIncomeExemptionResource($service->save($taxReturn, 'incomeExemptions', $request->validated())))
            ->response()->setStatusCode(201);
    }

    public function update(WriteTaxReturnIncomeExemptionRequest $request, TaxReturn $taxReturn, TaxReturnInputService $service): TaxReturnIncomeExemptionResource
    {
        return new TaxReturnIncomeExemptionResource($service->save($taxReturn, 'incomeExemptions', $request->validated(), $request->route('child')));
    }

    public function destroy(EmptyMemberActionRequest $request, TaxReturn $taxReturn, TaxReturnInputService $service): Response
    {
        $service->delete($taxReturn, 'incomeExemptions', $request->route('child'));

        return response()->noContent();
    }
}

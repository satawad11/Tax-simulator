<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\EmptyMemberActionRequest;
use App\Http\Requests\Api\V1\WriteTaxReturnIncomeRequest;
use App\Http\Resources\Api\V1\TaxReturnIncomeResource;
use App\Models\TaxReturn;
use App\Services\Tax\TaxReturnInputService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class TaxReturnIncomeController extends Controller
{
    public function store(WriteTaxReturnIncomeRequest $request, TaxReturn $taxReturn, TaxReturnInputService $service): JsonResponse
    {
        return (new TaxReturnIncomeResource($service->save($taxReturn, 'incomes', $request->validated())))->response()->setStatusCode(201);
    }

    public function update(WriteTaxReturnIncomeRequest $request, TaxReturn $taxReturn, TaxReturnInputService $service): TaxReturnIncomeResource
    {
        return new TaxReturnIncomeResource($service->save($taxReturn, 'incomes', $request->validated(), $request->route('child')));
    }

    public function destroy(EmptyMemberActionRequest $request, TaxReturn $taxReturn, TaxReturnInputService $service): Response
    {
        $service->delete($taxReturn, 'incomes', $request->route('child'));

        return response()->noContent();
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\EmptyMemberActionRequest;
use App\Http\Requests\Api\V1\WriteTaxReturnWithholdingRequest;
use App\Http\Resources\Api\V1\TaxReturnWithholdingResource;
use App\Models\TaxReturn;
use App\Services\Tax\TaxReturnInputService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class TaxReturnWithholdingController extends Controller
{
    public function store(WriteTaxReturnWithholdingRequest $request, TaxReturn $taxReturn, TaxReturnInputService $service): JsonResponse
    {
        return (new TaxReturnWithholdingResource($service->save($taxReturn, 'withholdings', $request->validated())))->response()->setStatusCode(201);
    }

    public function update(WriteTaxReturnWithholdingRequest $request, TaxReturn $taxReturn, TaxReturnInputService $service): TaxReturnWithholdingResource
    {
        return new TaxReturnWithholdingResource($service->save($taxReturn, 'withholdings', $request->validated(), $request->route('child')));
    }

    public function destroy(EmptyMemberActionRequest $request, TaxReturn $taxReturn, TaxReturnInputService $service): Response
    {
        $service->delete($taxReturn, 'withholdings', $request->route('child'));

        return response()->noContent();
    }
}

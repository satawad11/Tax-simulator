<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\EmptyMemberActionRequest;
use App\Http\Requests\Api\V1\WriteTaxReturnDependentRequest;
use App\Http\Resources\Api\V1\TaxReturnDependentResource;
use App\Models\TaxReturn;
use App\Services\Tax\TaxReturnInputService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class TaxReturnDependentController extends Controller
{
    public function store(WriteTaxReturnDependentRequest $request, TaxReturn $taxReturn, TaxReturnInputService $service): JsonResponse
    {
        return (new TaxReturnDependentResource($service->save($taxReturn, 'dependents', $request->validated())))->response()->setStatusCode(201);
    }

    public function update(WriteTaxReturnDependentRequest $request, TaxReturn $taxReturn, TaxReturnInputService $service): TaxReturnDependentResource
    {
        return new TaxReturnDependentResource($service->save($taxReturn, 'dependents', $request->validated(), $request->route('child')));
    }

    public function destroy(EmptyMemberActionRequest $request, TaxReturn $taxReturn, TaxReturnInputService $service): Response
    {
        $service->delete($taxReturn, 'dependents', $request->route('child'));

        return response()->noContent();
    }
}

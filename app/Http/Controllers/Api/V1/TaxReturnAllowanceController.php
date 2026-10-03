<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\EmptyMemberActionRequest;
use App\Http\Requests\Api\V1\WriteTaxReturnAllowanceRequest;
use App\Http\Resources\Api\V1\TaxReturnAllowanceResource;
use App\Models\TaxReturn;
use App\Services\Tax\TaxReturnInputService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class TaxReturnAllowanceController extends Controller
{
    public function store(WriteTaxReturnAllowanceRequest $request, TaxReturn $taxReturn, TaxReturnInputService $service): JsonResponse
    {
        return (new TaxReturnAllowanceResource($service->save($taxReturn, 'allowances', $request->validated())))->response()->setStatusCode(201);
    }

    public function update(WriteTaxReturnAllowanceRequest $request, TaxReturn $taxReturn, TaxReturnInputService $service): TaxReturnAllowanceResource
    {
        return new TaxReturnAllowanceResource($service->save($taxReturn, 'allowances', $request->validated(), $request->route('child')));
    }

    public function destroy(EmptyMemberActionRequest $request, TaxReturn $taxReturn, TaxReturnInputService $service): Response
    {
        $service->delete($taxReturn, 'allowances', $request->route('child'));

        return response()->noContent();
    }
}

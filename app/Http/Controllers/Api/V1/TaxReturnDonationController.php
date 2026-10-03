<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\EmptyMemberActionRequest;
use App\Http\Requests\Api\V1\WriteTaxReturnDonationRequest;
use App\Http\Resources\Api\V1\TaxReturnDonationResource;
use App\Models\TaxReturn;
use App\Services\Tax\TaxReturnInputService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class TaxReturnDonationController extends Controller
{
    public function store(WriteTaxReturnDonationRequest $request, TaxReturn $taxReturn, TaxReturnInputService $service): JsonResponse
    {
        return (new TaxReturnDonationResource($service->save($taxReturn, 'donations', $request->validated())))->response()->setStatusCode(201);
    }

    public function update(WriteTaxReturnDonationRequest $request, TaxReturn $taxReturn, TaxReturnInputService $service): TaxReturnDonationResource
    {
        return new TaxReturnDonationResource($service->save($taxReturn, 'donations', $request->validated(), $request->route('child')));
    }

    public function destroy(EmptyMemberActionRequest $request, TaxReturn $taxReturn, TaxReturnInputService $service): Response
    {
        $service->delete($taxReturn, 'donations', $request->route('child'));

        return response()->noContent();
    }
}

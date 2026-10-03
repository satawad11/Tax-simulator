<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DuplicateTaxReturnRequest;
use App\Http\Requests\Api\V1\EmptyMemberActionRequest;
use App\Http\Requests\Api\V1\ListTaxReturnsRequest;
use App\Http\Requests\Api\V1\StoreTaxReturnRequest;
use App\Http\Requests\Api\V1\UpdateTaxReturnRequest;
use App\Http\Resources\Api\V1\TaxReturnResource;
use App\Http\Resources\Api\V1\TaxReturnSummaryResource;
use App\Models\TaxReturn;
use App\Services\Tax\TaxReturnDuplicationService;
use App\Services\Tax\TaxReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class TaxReturnController extends Controller
{
    public function index(ListTaxReturnsRequest $request, TaxReturnService $service): AnonymousResourceCollection
    {
        return TaxReturnSummaryResource::collection($service->listing($request->user(), $request->validated()))->additional(['success' => true, 'message' => null]);
    }

    public function store(StoreTaxReturnRequest $request, TaxReturnService $service): JsonResponse
    {
        return (new TaxReturnResource($service->create($request->user(), $request->validated())))->response()->setStatusCode(201);
    }

    public function show(TaxReturn $taxReturn, TaxReturnService $service): TaxReturnResource
    {
        return new TaxReturnResource($service->detail($taxReturn));
    }

    public function update(UpdateTaxReturnRequest $request, TaxReturn $taxReturn, TaxReturnService $service): TaxReturnResource
    {
        return new TaxReturnResource($service->update($taxReturn, $request->validated()));
    }

    public function destroy(EmptyMemberActionRequest $request, TaxReturn $taxReturn, TaxReturnService $service): Response
    {
        $service->delete($taxReturn);

        return response()->noContent();
    }

    public function duplicate(DuplicateTaxReturnRequest $request, TaxReturn $taxReturn, TaxReturnDuplicationService $service): JsonResponse
    {
        return (new TaxReturnResource($service->duplicate($taxReturn, $request->validated('name'))))->response()->setStatusCode(201);
    }
}

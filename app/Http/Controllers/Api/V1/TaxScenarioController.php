<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\EmptyMemberActionRequest;
use App\Http\Requests\Api\V1\ListTaxCalculationsRequest;
use App\Http\Requests\Api\V1\StoreTaxScenarioRequest;
use App\Http\Requests\Api\V1\UpdateTaxScenarioRequest;
use App\Http\Resources\Api\V1\TaxScenarioResource;
use App\Http\Resources\Api\V1\TaxScenarioSummaryResource;
use App\Models\TaxReturn;
use App\Services\Tax\TaxScenarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class TaxScenarioController extends Controller
{
    public function index(ListTaxCalculationsRequest $request, TaxReturn $taxReturn, TaxScenarioService $service): AnonymousResourceCollection
    {
        return TaxScenarioSummaryResource::collection($service->listing($taxReturn, (int) $request->validated('per_page', 20)))
            ->additional(['success' => true, 'message' => null]);
    }

    public function store(StoreTaxScenarioRequest $request, TaxReturn $taxReturn, TaxScenarioService $service): JsonResponse
    {
        return (new TaxScenarioResource($service->create($taxReturn, $request->validated())))->response()->setStatusCode(201);
    }

    public function show(TaxReturn $taxReturn, string $scenario, TaxScenarioService $service): TaxScenarioResource
    {
        return new TaxScenarioResource($service->find($taxReturn, $scenario));
    }

    public function update(UpdateTaxScenarioRequest $request, TaxReturn $taxReturn, string $scenario, TaxScenarioService $service): TaxScenarioResource
    {
        return new TaxScenarioResource($service->update($service->find($taxReturn, $scenario), $request->validated()));
    }

    public function destroy(EmptyMemberActionRequest $request, TaxReturn $taxReturn, string $scenario, TaxScenarioService $service): Response
    {
        $service->delete($service->find($taxReturn, $scenario));

        return response()->noContent();
    }
}

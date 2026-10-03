<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\EmptyMemberActionRequest;
use App\Http\Requests\Api\V1\ListTaxCalculationsRequest;
use App\Http\Resources\Api\V1\TaxCalculationDetailResource;
use App\Http\Resources\Api\V1\TaxCalculationSummaryResource;
use App\Models\TaxReturn;
use App\Services\Tax\MemberTaxCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MemberTaxCalculationController extends Controller
{
    public function calculate(EmptyMemberActionRequest $request, TaxReturn $taxReturn, MemberTaxCalculationService $service): JsonResponse
    {
        return (new TaxCalculationDetailResource($service->calculate($taxReturn)))->response()->setStatusCode(200);
    }

    public function complete(EmptyMemberActionRequest $request, TaxReturn $taxReturn, MemberTaxCalculationService $service): JsonResponse
    {
        $resource = new TaxCalculationDetailResource($service->calculate($taxReturn, true));
        $resource->completionMessage = 'Simulation completed — เสร็จสิ้นการทดลอง';

        return $resource->response()->setStatusCode(200);
    }

    public function index(ListTaxCalculationsRequest $request, TaxReturn $taxReturn): AnonymousResourceCollection
    {
        return TaxCalculationSummaryResource::collection($taxReturn->calculations()->orderByDesc('id')->paginate($request->validated('per_page', 20)))
            ->additional(['success' => true, 'message' => null]);
    }

    public function show(TaxReturn $taxReturn, string $calculationId): TaxCalculationDetailResource
    {
        return new TaxCalculationDetailResource($taxReturn->calculations()->whereKey($calculationId)->with('brackets')->firstOrFail());
    }
}

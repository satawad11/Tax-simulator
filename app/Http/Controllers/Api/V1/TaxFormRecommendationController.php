<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RecommendTaxFormRequest;
use App\Http\Resources\Api\V1\TaxFormRecommendationResource;
use App\Services\Tax\TaxFormRecommendationService;

class TaxFormRecommendationController extends Controller
{
    public function __invoke(RecommendTaxFormRequest $request, TaxFormRecommendationService $service): TaxFormRecommendationResource
    {
        return new TaxFormRecommendationResource($service->recommend((int) $request->validated('tax_year'), $request->validated('income_types')));
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\EmptyMemberActionRequest;
use App\Http\Resources\Api\V1\TaxScenarioResource;
use App\Models\TaxReturn;
use App\Services\Tax\TaxScenarioService;

class TaxScenarioCalculationController extends Controller
{
    public function __invoke(
        EmptyMemberActionRequest $request,
        TaxReturn $taxReturn,
        string $scenario,
        TaxScenarioService $service,
    ): TaxScenarioResource {
        return new TaxScenarioResource($service->calculate($taxReturn, $service->find($taxReturn, $scenario)));
    }
}

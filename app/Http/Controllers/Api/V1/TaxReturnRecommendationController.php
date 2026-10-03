<?php

namespace App\Http\Controllers\Api\V1;

use App\DTO\Tax\TaxCalculationData;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TaxRecommendationResource;
use App\Models\TaxReturn;
use App\Services\Tax\MemberTaxCalculationService;
use App\Services\Tax\TaxCalculationService;
use App\Services\Tax\TaxSimulationService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaxReturnRecommendationController extends Controller
{
    /**
     * Read-only: the saved return is recalculated in memory under its own rule version so
     * the advice matches the saved data, but no TaxCalculation history row is created.
     */
    public function __invoke(
        TaxReturn $taxReturn,
        MemberTaxCalculationService $member,
        TaxCalculationService $engine,
        TaxSimulationService $simulation,
    ): AnonymousResourceCollection {
        $payload = $member->payload($taxReturn);
        $result = $engine->calculate(TaxCalculationData::fromArray($payload), $taxReturn->ruleVersion);
        $decorated = $simulation->decorate($result, $payload, $taxReturn->ruleVersion);

        return TaxRecommendationResource::collection($decorated['recommendations'])->additional([
            'success' => true,
            'message' => null,
            'meta' => [
                'tax_return_id' => $taxReturn->id,
                'rule_version' => $decorated['rule_version'],
                'result' => $decorated['result'],
                'refund_guidance' => $decorated['refund_guidance'],
                'payment_guidance' => $decorated['payment_guidance'],
                'warnings' => $decorated['warnings'],
                'persisted' => false,
            ],
        ]);
    }
}

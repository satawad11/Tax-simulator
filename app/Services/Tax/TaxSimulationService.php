<?php

namespace App\Services\Tax;

use App\DTO\Tax\TaxCalculationData;
use App\Models\TaxRuleVersion;

/**
 * Read-only orchestration layer that decorates a TaxCalculationService result with
 * rule-based recommendations and refund/payment guidance.
 *
 * Nothing here recalculates or alters a tax amount, and nothing here is persisted:
 * the decoration is derived on every read from the result and its input payload.
 */
class TaxSimulationService
{
    public function __construct(
        private TaxCalculationService $engine,
        private TaxMetadataService $metadata,
        private TaxRecommendationService $recommendations,
        private TaxRefundGuidanceService $guidance,
    ) {}

    /** Guest simulation: resolves the currently published rule version for the tax year. */
    public function simulate(TaxCalculationData $data): array
    {
        $result = $this->engine->calculate($data);
        $version = $this->metadata->context($data->taxYear)->publishedRuleVersion;

        return $this->decorate($result, $this->payload($data), $version);
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $input
     */
    public function decorate(array $result, array $input, TaxRuleVersion $version): array
    {
        return [
            ...$result,
            'recommendations' => $this->recommendations->generate($result, $input, $version),
            ...$this->guidance->guide($result),
        ];
    }

    /** @return array<string, mixed> */
    public function payload(TaxCalculationData $data): array
    {
        return ['incomes' => $data->incomes, 'allowances' => $data->allowances,
            'donations' => $data->donations, 'withholdings' => $data->withholdings];
    }
}

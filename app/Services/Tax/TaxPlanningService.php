<?php

namespace App\Services\Tax;

use App\DTO\Tax\TaxCalculationData;
use App\Models\TaxRuleVersion;
use App\ValueObjects\Money;

/**
 * Hypothetical what-if comparison.
 *
 * Both sides are produced by the same TaxCalculationService under the same rule version;
 * this service only merges the scenario, subtracts and explains. It contains no tax
 * formula, grants no eligibility and recommends no financial product.
 */
class TaxPlanningService
{
    public function __construct(
        private TaxCalculationService $engine,
        private TaxMetadataService $metadata,
        private ScenarioMerger $merger,
        private TaxRecommendationService $recommendations,
    ) {}

    /**
     * @param  array<string, mixed>  $base  a full TaxCalculationData payload
     * @param  array<string, mixed>  $scenario
     * @param  TaxRuleVersion|null  $version  source rule version; null resolves the published one
     * @return array<string, mixed>
     */
    public function plan(array $base, array $scenario, ?TaxRuleVersion $version = null): array
    {
        $version ??= $this->metadata->context((int) $base['tax_year'])->publishedRuleVersion;
        $after = $this->merger->apply($base, $scenario);

        $beforeResult = $this->engine->calculate(TaxCalculationData::fromArray($base), $version);
        $afterResult = $this->engine->calculate(TaxCalculationData::fromArray($after), $version);

        $beforeTax = new Money((string) $beforeResult['progressive_tax']['total']);
        $afterTax = new Money((string) $afterResult['progressive_tax']['total']);

        return [
            'tax_year' => $beforeResult['tax_year'],
            'form_code' => $beforeResult['form_code'],
            'rule_version' => $beforeResult['rule_version'],
            'before' => $this->side($beforeResult),
            'after' => $this->side($afterResult),
            'difference' => [
                'net_income' => (new Money((string) $afterResult['net_income']))
                    ->subtract(new Money((string) $beforeResult['net_income'])),
                'calculated_tax' => $afterTax->subtract($beforeTax),
                'result_amount' => $this->signed($afterResult)->subtract($this->signed($beforeResult)),
            ],
            // Tax saving is a liability difference, never a payment-timing difference.
            'estimated_tax_saving' => $beforeTax->subtract($afterTax)->max(new Money),
            'warnings' => $this->warnings($beforeResult, $afterResult),
            'recommendations' => $this->recommendations->generate($afterResult, $after, $version),
            'disclaimer' => $beforeResult['disclaimer'],
        ];
    }

    /** @return array<string, mixed> */
    private function side(array $result): array
    {
        return [
            'net_income' => $result['net_income'],
            'calculated_tax' => $result['progressive_tax']['total'],
            'result' => ['status' => $result['result']['status'], 'amount' => $result['result']['amount']],
        ];
    }

    /** PAYABLE is positive, REFUND is negative, so a comparison keeps its direction. */
    private function signed(array $result): Money
    {
        $amount = new Money((string) $result['result']['amount']);

        return match ($result['result']['status']) {
            'PAYABLE' => $amount,
            'REFUND' => (new Money)->subtract($amount),
            default => new Money,
        };
    }

    /** After-scenario warnings first, then any before-scenario warning the scenario removed. */
    private function warnings(array $before, array $after): array
    {
        $warnings = $after['warnings'];
        $seen = array_map(fn (array $warning): string => json_encode($warning, JSON_THROW_ON_ERROR), $warnings);
        foreach ($before['warnings'] as $warning) {
            if (! in_array(json_encode($warning, JSON_THROW_ON_ERROR), $seen, true)) {
                $warnings[] = $warning;
            }
        }

        return $warnings;
    }
}

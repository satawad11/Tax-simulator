<?php

namespace App\Services\Tax;

use App\Exceptions\TaxMetadataConflictException;
use App\Models\TaxReturn;
use App\Models\TaxScenario;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Member planning scenarios.
 *
 * A scenario never mutates its source TaxReturn and never writes to tax_calculations:
 * its comparison lives only in tax_scenarios.calculation_result, so the return's
 * calculation history stays the history of the saved return itself.
 */
class TaxScenarioService
{
    public function __construct(
        private MemberTaxCalculationService $member,
        private TaxPlanningService $planning,
    ) {}

    public function listing(TaxReturn $return, int $perPage): LengthAwarePaginator
    {
        return $return->scenarios()->orderByDesc('updated_at')->orderByDesc('id')->paginate($perPage);
    }

    /** Scoped to the route's tax return, so a foreign scenario id is a 404, not a leak. */
    public function find(TaxReturn $return, string $scenarioId): TaxScenario
    {
        return $return->scenarios()->whereKey($scenarioId)->firstOrFail();
    }

    public function create(TaxReturn $return, array $data): TaxScenario
    {
        $this->assertPlannable($return);

        return DB::transaction(fn (): TaxScenario => $return->scenarios()->create([
            'name' => $data['name'],
            'payload' => $data['payload'],
            'calculation_result' => null,
        ]));
    }

    public function update(TaxScenario $scenario, array $data): TaxScenario
    {
        return DB::transaction(function () use ($scenario, $data): TaxScenario {
            $scenario = TaxScenario::whereKey($scenario->id)->lockForUpdate()->firstOrFail();
            $scenario->name = $data['name'] ?? $scenario->name;
            if (array_key_exists('payload', $data)) {
                // A changed scenario invalidates the stored comparison until it is recalculated.
                $scenario->payload = $data['payload'];
                $scenario->calculation_result = null;
                $scenario->before_tax = null;
                $scenario->after_tax = null;
                $scenario->estimated_tax_saving = null;
                $scenario->calculated_at = null;
            }
            $scenario->save();

            return $scenario;
        });
    }

    public function delete(TaxScenario $scenario): void
    {
        DB::transaction(fn () => TaxScenario::whereKey($scenario->id)->lockForUpdate()->firstOrFail()->delete());
    }

    public function calculate(TaxReturn $return, TaxScenario $scenario): TaxScenario
    {
        $this->assertPlannable($return);
        // Build and validate the base outside the write transaction; it touches no scenario row.
        $base = $this->member->payload($return);

        return DB::transaction(function () use ($scenario, $base): TaxScenario {
            $scenario = TaxScenario::whereKey($scenario->id)->lockForUpdate()->firstOrFail();
            $comparison = $this->planning->plan($base, $scenario->payload ?? [], $scenario->ruleVersion);
            $scenario->calculation_result = json_decode(json_encode($comparison, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
            $scenario->before_tax = $this->projection((string) $comparison['before']['calculated_tax']);
            $scenario->after_tax = $this->projection((string) $comparison['after']['calculated_tax']);
            $scenario->estimated_tax_saving = $this->projection((string) $comparison['estimated_tax_saving']);
            $scenario->calculated_at = now();
            $scenario->save();

            return $scenario;
        });
    }

    private function assertPlannable(TaxReturn $return): void
    {
        if (! in_array($return->status, ['draft', 'completed'], true)) {
            throw new TaxMetadataConflictException('Planning scenarios are available for draft and completed simulations only.');
        }
    }

    /** Exact DECIMAL(15,2) projection only; calculation_result JSON stays authoritative. */
    private function projection(string $value): ?string
    {
        $decimal = BigDecimal::of($value)->strippedOfTrailingZeros();
        if ($decimal->getScale() > 2 || $decimal->abs()->compareTo('9999999999999.99') > 0) {
            return null;
        }

        return (string) $decimal->toScale(2);
    }
}

<?php

namespace App\Http\Resources\Api\V1;

use App\Services\Tax\TaxSimulationService;
use Illuminate\Http\Request;

class TaxCalculationDetailResource extends MetadataResource
{
    public ?string $completionMessage = null;

    public function with(Request $request): array
    {
        return ['success' => true, 'message' => $this->completionMessage];
    }

    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'calculated_at' => $this->calculated_at?->toIso8601String(),
            // Guidance is derived on read from the stored snapshot; the snapshot itself is never rewritten.
            'calculation' => $this->guided(),
            'brackets' => $this->brackets->map(fn ($row) => $row->result_snapshot ?? [
                'sort_order' => $row->sort_order, 'min_amount' => $row->from_amount, 'max_amount' => $row->to_amount,
                'rate' => $row->rate, 'taxable_amount' => $row->taxable_amount, 'tax' => $row->tax_amount]),
            'trace' => $this->calculation_trace];
    }

    /** @return array<string, mixed>|null */
    private function guided(): ?array
    {
        $snapshot = $this->result_snapshot;
        if (! is_array($snapshot) || $this->ruleVersion === null) {
            return $snapshot;
        }

        return app(TaxSimulationService::class)->decorate($snapshot, $this->input_snapshot ?? [], $this->ruleVersion);
    }
}

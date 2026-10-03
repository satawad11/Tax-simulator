<?php

namespace App\Services\Tax;

/**
 * Deterministic base + scenario merge.
 *
 * Only allowances, donations and withholdings can be changed by a scenario. Each
 * collection is keyed (allowances/donations by `code`, withholdings by `type`) and the
 * scenario declares `remove` (list of keys) and `upsert` (list of items) explicitly.
 *
 * Order of operations, applied per collection:
 *   1. every base entry whose key appears in `remove` is dropped (all duplicates);
 *   2. the first base entry whose key appears in `upsert` is replaced by the scenario
 *      entry, in place; further base entries with that key are dropped;
 *   3. `upsert` keys absent from the base are appended in the order given.
 *
 * Nothing is inferred: a key that appears in both `remove` and `upsert` is rejected by
 * validation, never silently resolved.
 */
class ScenarioMerger
{
    /** collection name => key field */
    public const COLLECTIONS = ['allowances' => 'code', 'donations' => 'code', 'withholdings' => 'type'];

    /**
     * @param  array<string, mixed>  $base  a full TaxCalculationData payload
     * @param  array<string, mixed>  $scenario
     * @return array<string, mixed> a new payload; $base is never mutated
     */
    public function apply(array $base, array $scenario): array
    {
        foreach (self::COLLECTIONS as $collection => $key) {
            $base[$collection] = $this->merge($base[$collection] ?? [], $scenario[$collection] ?? [], $key);
        }

        return $base;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $spec
     * @return list<array<string, string>>
     */
    private function merge(array $items, array $spec, string $key): array
    {
        $remove = array_map('strval', $spec['remove'] ?? []);
        $upsert = [];
        foreach ($spec['upsert'] ?? [] as $entry) {
            $upsert[(string) $entry[$key]] = (string) ($entry['amount'] ?? $entry['input_amount']);
        }

        $merged = [];
        $applied = [];
        foreach ($items as $item) {
            $code = (string) $item[$key];
            if (in_array($code, $remove, true)) {
                continue;
            }
            if (! array_key_exists($code, $upsert)) {
                $merged[] = [$key => $code, 'amount' => (string) $item['amount']];

                continue;
            }
            if (isset($applied[$code])) {
                continue;
            }
            $applied[$code] = true;
            $merged[] = [$key => $code, 'amount' => $upsert[$code]];
        }
        foreach ($upsert as $code => $amount) {
            if (! isset($applied[$code])) {
                $merged[] = [$key => (string) $code, 'amount' => $amount];
            }
        }

        return $merged;
    }
}

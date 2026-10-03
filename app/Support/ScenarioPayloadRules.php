<?php

namespace App\Support;

use App\Models\DonationRule;
use App\Rules\MoneyInput;
use App\Services\Tax\ScenarioMerger;
use App\Services\Tax\TaxCreditCalculator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared validation for the scenario override structure used by both the Guest planning
 * request (`scenario`) and the Member scenario request (`payload`).
 */
final class ScenarioPayloadRules
{
    public const WITHHOLDING_TYPES = TaxCreditCalculator::TYPES;

    /** @return array<string, mixed> */
    public static function rules(string $prefix): array
    {
        // `present`, not `required`: an empty scenario is a legitimate "change nothing" comparison.
        $rules = [$prefix => ['present', 'array:'.implode(',', array_keys(ScenarioMerger::COLLECTIONS))]];

        foreach (ScenarioMerger::COLLECTIONS as $collection => $key) {
            $path = "$prefix.$collection";
            $rules[$path] = ['sometimes', 'array:upsert,remove'];
            $rules["$path.upsert"] = ['sometimes', 'array', 'list', 'max:100'];
            $rules["$path.upsert.*"] = ['required', $collection === 'withholdings' ? 'array:type,amount' : 'array:code,amount,input_amount'];
            $rules["$path.upsert.*.$key"] = [...self::key($collection), 'distinct'];
            $rules["$path.upsert.*.amount"] = $collection === 'withholdings'
                ? ['required', new MoneyInput]
                : ['sometimes', 'required', new MoneyInput];
            if ($collection !== 'withholdings') {
                $rules["$path.upsert.*.input_amount"] = ['sometimes', 'required', new MoneyInput];
            }
            $rules["$path.remove"] = ['sometimes', 'array', 'list', 'max:100'];
            $rules["$path.remove.*"] = [...self::key($collection), 'distinct'];
        }

        return $rules;
    }

    /** @return list<mixed> */
    private static function key(string $collection): array
    {
        return match ($collection) {
            'allowances' => ['required', 'string', Rule::exists('allowance_types', 'code')->where('is_active', true)],
            'donations' => ['required', 'string', 'max:100'],
            default => ['required', 'string', Rule::in(self::WITHHOLDING_TYPES)],
        };
    }

    /**
     * Deterministic-merge guards that cannot be expressed as field rules.
     *
     * @param  array<string, mixed>|null  $scenario
     */
    public static function validate(Validator $validator, string $prefix, ?array $scenario, ?int $ruleVersionId): void
    {
        if (! is_array($scenario)) {
            return;
        }
        $donationCodes = [];
        foreach (ScenarioMerger::COLLECTIONS as $collection => $key) {
            $spec = $scenario[$collection] ?? [];
            if (! is_array($spec)) {
                continue;
            }
            $upsert = is_array($spec['upsert'] ?? null) ? $spec['upsert'] : [];
            $remove = is_array($spec['remove'] ?? null) ? $spec['remove'] : [];
            foreach ($upsert as $index => $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                if ($collection !== 'withholdings'
                    && array_key_exists('amount', $entry) === array_key_exists('input_amount', $entry)) {
                    $validator->errors()->add("$prefix.$collection.upsert.$index.amount",
                        'Provide exactly one of amount or input_amount.');
                }
                if ($collection === 'donations' && is_string($entry[$key] ?? null)) {
                    $donationCodes[] = $entry[$key];
                }
                if (is_string($entry[$key] ?? null) && in_array($entry[$key], $remove, true)) {
                    $validator->errors()->add("$prefix.$collection.upsert.$index.$key",
                        'A scenario entry may not be removed and upserted at the same time.');
                }
            }
        }
        if ($donationCodes === [] || $ruleVersionId === null) {
            return;
        }
        $known = DonationRule::where('rule_version_id', $ruleVersionId)->where('active', true)
            ->whereIn('code', $donationCodes)->pluck('code')->all();
        foreach ($scenario['donations']['upsert'] ?? [] as $index => $entry) {
            if (is_string($entry['code'] ?? null) && ! in_array($entry['code'], $known, true)) {
                $validator->errors()->add("$prefix.donations.upsert.$index.code",
                    'Unknown or unsupported donation code for this rule version.');
            }
        }
    }
}

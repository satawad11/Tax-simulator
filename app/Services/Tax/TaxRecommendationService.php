<?php

namespace App\Services\Tax;

use App\Models\RecommendationRule;
use App\Models\TaxRuleVersion;
use App\ValueObjects\Money;
use Illuminate\Support\Facades\Log;

/**
 * Deterministic, rule-based recommendation engine.
 *
 * Recommendations derive only from seeded recommendation rules, user-entered simulation
 * input and calculation output. No generative model participates, and no recommendation
 * grants eligibility or computes a tax benefit.
 */
class TaxRecommendationService
{
    /** Conditions understood by this milestone; anything else disables its rule. */
    public const SUPPORTED_CONDITIONS = [
        'income_types_contains', 'income_types_only', 'allowance_present', 'allowance_not_present',
        'result_status', 'gross_income_greater_than', 'net_income_greater_than',
        'withholding_less_than_tax', 'withholding_greater_than_tax', 'warning_present',
    ];

    public const TYPES = ['MISSING_INFORMATION', 'POTENTIAL_ALLOWANCE', 'TAX_PLANNING', 'PAYMENT', 'REFUND'];

    private const PRIORITY_RANK = ['high' => 3, 'medium' => 2, 'low' => 1];

    /**
     * @param  array<string, mixed>  $result  calculation output (live engine array or stored snapshot)
     * @param  array<string, mixed>  $input  the calculation input payload that produced the result
     * @return list<array<string, mixed>>
     */
    public function generate(array $result, array $input, TaxRuleVersion $version): array
    {
        $context = $this->context($result, $input);
        $recommendations = [];
        $rules = RecommendationRule::where('rule_version_id', $version->id)
            ->where('active', true)->orderBy('code')->get();

        foreach ($rules as $rule) {
            if (isset($recommendations[$rule->code]) || ! $this->usable($rule)) {
                continue;
            }
            $conditions = is_array($rule->conditions) ? $rule->conditions : [];
            if (! $this->matches($rule->code, $conditions, $context)) {
                continue;
            }
            $action = $this->action($rule->code, (string) ($rule->action_type ?? ''), $conditions);
            if ($action === false) {
                continue;
            }
            $recommendations[$rule->code] = [
                'code' => $rule->code,
                'type' => $rule->type,
                'priority' => strtolower((string) $rule->priority),
                'title' => $rule->title,
                'message' => $this->message($rule->code, (string) $rule->message_template, $context),
                'action' => $action,
            ];
        }

        $recommendations = array_values($recommendations);
        usort($recommendations, fn (array $a, array $b): int => [self::PRIORITY_RANK[$b['priority']], $a['code']]
            <=> [self::PRIORITY_RANK[$a['priority']], $b['code']]);

        return $recommendations;
    }

    /** @return array<string, mixed> */
    private function context(array $result, array $input): array
    {
        $prepaid = $this->amount($result['result']['components']['withholding_and_prepaid'] ?? '0');
        $taxAfterForeign = $this->amount($result['result']['components']['tax_after_foreign_credit'] ?? '0');

        return [
            'income_types' => array_values(array_unique(array_column($input['incomes'] ?? [], 'income_type'))),
            'allowance_codes' => array_values(array_unique(array_column($input['allowances'] ?? [], 'code'))),
            'result_status' => (string) ($result['result']['status'] ?? ''),
            'gross_income' => $this->amount($result['income']['gross_income'] ?? '0'),
            'net_income' => $this->amount($result['net_income'] ?? '0'),
            'calculated_tax' => $this->amount($result['progressive_tax']['total'] ?? '0'),
            'withholding' => $this->amount($result['credits']['withholding'] ?? '0'),
            'result_amount' => $this->amount($result['result']['amount'] ?? '0'),
            'warnings' => array_column($result['warnings'] ?? [], 'code'),
            'prepaid_below_tax' => $prepaid->compare($taxAfterForeign) < 0,
            'prepaid_above_tax' => $prepaid->compare($taxAfterForeign) > 0,
        ];
    }

    private function usable(RecommendationRule $rule): bool
    {
        $problem = match (true) {
            ! in_array($rule->type, self::TYPES, true) => 'type',
            ! isset(self::PRIORITY_RANK[strtolower((string) $rule->priority)]) => 'priority',
            trim((string) $rule->title) === '' => 'title',
            trim((string) $rule->message_template) === '' => 'message_template',
            default => null,
        };
        if ($problem !== null) {
            $this->skip($rule->code, $problem, 'unsupported recommendation rule field');
        }

        return $problem === null;
    }

    /** @param array<string, mixed> $conditions */
    private function matches(string $code, array $conditions, array $context): bool
    {
        foreach ($conditions as $key => $value) {
            $outcome = match ($key) {
                'income_types_contains' => in_array((string) $value, $context['income_types'], true),
                'income_types_only' => $context['income_types'] === [(string) $value],
                'allowance_present' => in_array((string) $value, $context['allowance_codes'], true),
                'allowance_not_present' => ! in_array((string) $value, $context['allowance_codes'], true),
                'result_status' => $context['result_status'] === (string) $value,
                'gross_income_greater_than' => $this->exceeds($context['gross_income'], $value),
                'net_income_greater_than' => $this->exceeds($context['net_income'], $value),
                'withholding_less_than_tax' => $context['prepaid_below_tax'] === (bool) $value,
                'withholding_greater_than_tax' => $context['prepaid_above_tax'] === (bool) $value,
                'warning_present' => in_array((string) $value, $context['warnings'], true),
                default => null,
            };
            if ($outcome === null) {
                $this->skip($code, (string) $key, 'unsupported recommendation condition');

                return false;
            }
            if ($outcome === false) {
                return false;
            }
        }

        return true;
    }

    private function exceeds(Money $actual, mixed $threshold): ?bool
    {
        if ((! is_string($threshold) && ! is_int($threshold)) ||
            ! preg_match('/^(0|[1-9][0-9]{0,12})(\.[0-9]{1,2})?$/D', (string) $threshold)) {
            return null;
        }

        return $actual->compare(new Money((string) $threshold)) > 0;
    }

    /**
     * @param  array<string, mixed>  $conditions
     * @return array<string, string>|null|false false disables the rule
     */
    private function action(string $code, string $type, array $conditions): array|null|false
    {
        if ($type === '') {
            return null;
        }
        if ($type !== 'OPEN_ALLOWANCE') {
            return ['type' => $type];
        }
        $allowance = $conditions['allowance_not_present'] ?? $conditions['allowance_present'] ?? null;
        if (! is_string($allowance) || $allowance === '') {
            $this->skip($code, 'action_type', 'OPEN_ALLOWANCE requires an allowance condition');

            return false;
        }

        return ['type' => $type, 'allowance_code' => $allowance];
    }

    /** @param array<string, mixed> $context */
    private function message(string $code, string $template, array $context): string
    {
        return preg_replace_callback('/\{\{([a-z_]{1,40})\}\}/', function (array $match) use ($code, $context): string {
            $value = match ($match[1]) {
                'gross_income', 'net_income', 'calculated_tax', 'withholding', 'result_amount' => (string) $context[$match[1]],
                'result_status' => $context['result_status'],
                default => null,
            };
            if ($value === null) {
                $this->skip($code, $match[1], 'unsupported recommendation message placeholder');

                return $match[0];
            }

            return $value;
        }, $template) ?? $template;
    }

    /** Sanitized logging only: rule metadata, never user financial input. */
    private function skip(string $code, string $field, string $reason): void
    {
        Log::warning('Recommendation rule skipped.', ['rule_code' => $code, 'field' => $field, 'reason' => $reason]);
    }

    private function amount(mixed $value): Money
    {
        return $value instanceof Money ? $value : new Money((string) $value);
    }
}

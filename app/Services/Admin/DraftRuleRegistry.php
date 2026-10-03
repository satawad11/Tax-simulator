<?php

namespace App\Services\Admin;

use App\Models\AllowanceCapGroup;
use App\Models\AllowanceRule;
use App\Models\DonationRule;
use App\Models\ExpenseRule;
use App\Models\RecommendationRule;
use App\Models\TaxBracket;
use App\Services\Tax\AllowanceCalculator;
use App\Services\Tax\ExpenseRuleResolver;
use App\Services\Tax\PercentageBaseResolver;
use App\Services\Tax\TaxRecommendationService;
use Illuminate\Validation\Rule;

/**
 * The rule entities an admin may edit on a draft version, and exactly which fields.
 *
 * Milestone 08. One declaration per entity rather than six near-identical controllers: the
 * interesting part of each is its field list and its validation, and putting them side by side
 * is what makes a missing or over-permissive field visible.
 *
 * M8 invents no new rule class. Every entity here is one the engine already reads.
 */
final class DraftRuleRegistry
{
    /**
     * resource segment => [model class, audit label]
     *
     * @var array<string, array{class-string, string}>
     */
    public const ENTITIES = [
        'tax-brackets' => [TaxBracket::class, 'tax_bracket'],
        'expense-rules' => [ExpenseRule::class, 'expense_rule'],
        'allowance-rules' => [AllowanceRule::class, 'allowance_rule'],
        'allowance-cap-groups' => [AllowanceCapGroup::class, 'allowance_cap_group'],
        'donation-rules' => [DonationRule::class, 'donation_rule'],
        'recommendation-rules' => [RecommendationRule::class, 'recommendation_rule'],
    ];

    public static function knows(string $resource): bool
    {
        return array_key_exists($resource, self::ENTITIES);
    }

    /** @return class-string */
    public static function model(string $resource): string
    {
        return self::ENTITIES[$resource][0];
    }

    public static function entityType(string $resource): string
    {
        return self::ENTITIES[$resource][1];
    }

    /**
     * Validation for one entity. `$required` is `required` on create and `sometimes` on update,
     * so a PATCH need not resend every field.
     *
     * @return array<string, mixed>
     */
    public static function rules(string $resource, string $required): array
    {
        $money = ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'];
        $percentage = ['nullable', 'numeric', 'min:0', 'max:100'];
        $source = [$required, 'string', 'max:500'];

        return match ($resource) {
            'tax-brackets' => [
                'sort_order' => [$required, 'integer', 'min:1', 'max:100'],
                'min_amount' => [$required, 'numeric', 'min:0'],
                'max_amount' => ['nullable', 'numeric', 'min:0'],
                'rate' => [$required, 'numeric', 'min:0', 'max:100'],
                'source_reference' => ['sometimes', 'nullable', 'string', 'max:500'],
            ],
            'expense-rules' => [
                'code' => [$required, 'string', 'max:100'],
                'income_type_id' => [$required, 'integer', Rule::exists('income_types', 'id')],
                'income_subtype' => ['sometimes', 'nullable', 'string', 'max:50'],
                'expense_activity' => ['sometimes', 'nullable', 'string', 'max:50'],
                'holding_years_min' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:200'],
                'holding_years_max' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:200'],
                'expense_group' => ['sometimes', 'nullable', 'string', 'max:50'],
                'method' => [$required, Rule::in(ExpenseRuleResolver::SUPPORTED_METHODS)],
                'percentage' => $percentage,
                'maximum_amount' => $money,
                'fixed_amount' => $money,
                'minimum_amount' => $money,
                'active' => ['sometimes', 'boolean'],
                'source_reference' => $source,
            ],
            'allowance-rules' => [
                'code' => [$required, 'string', 'max:100'],
                'allowance_type_id' => [$required, 'integer', Rule::exists('allowance_types', 'id')],
                'method' => [$required, Rule::in(AllowanceCalculator::SUPPORTED_METHODS)],
                'percentage' => $percentage,
                'percentage_base' => ['sometimes', 'nullable', Rule::in(PercentageBaseResolver::SUPPORTED)],
                'maximum_amount' => $money,
                'fixed_amount' => $money,
                'minimum_amount' => $money,
                'active' => ['sometimes', 'boolean'],
                'source_reference' => $source,
            ],
            'allowance-cap-groups' => [
                'code' => [$required, 'string', 'max:100'],
                'name' => [$required, 'string', 'max:255'],
                'maximum_amount' => $money,
                'percentage' => $percentage,
                'percentage_base' => ['sometimes', 'nullable', Rule::in(PercentageBaseResolver::SUPPORTED)],
                'active' => ['sometimes', 'boolean'],
                'source_reference' => $source,
                'member_allowance_type_ids' => ['sometimes', 'array', 'max:20'],
                'member_allowance_type_ids.*' => ['integer', Rule::exists('allowance_types', 'id')],
            ],
            'donation-rules' => [
                'code' => [$required, 'string', 'max:100'],
                'name' => [$required, 'string', 'max:255'],
                'donation_type' => [$required, Rule::in(['special', 'general'])],
                'multiplier' => [$required, 'numeric', 'min:0', 'max:10'],
                'max_percentage' => [$required, 'numeric', 'min:0', 'max:100'],
                'active' => ['sometimes', 'boolean'],
                'source_reference' => $source,
            ],
            'recommendation-rules' => [
                'code' => [$required, 'string', 'max:100'],
                'type' => [$required, 'string', 'max:50'],
                'priority' => [$required, Rule::in(['high', 'medium', 'low'])],
                'title' => [$required, 'string', 'max:255'],
                'message_template' => [$required, 'string', 'max:2000'],
                'action_type' => ['sometimes', 'nullable', 'string', 'max:50'],
                'conditions' => ['sometimes', 'array'],
                'active' => ['sometimes', 'boolean'],
                'source_reference' => ['sometimes', 'nullable', 'string', 'max:500'],
            ],
            default => [],
        };
    }

    /**
     * Fields that are never taken from a request: the version and year a row belongs to are
     * decided by the route, not by the caller.
     *
     * @return list<string>
     */
    public static function serverControlled(): array
    {
        return ['id', 'rule_version_id', 'tax_year_id', 'created_at', 'updated_at'];
    }

    /** Keys the registry validates but that are not columns of the entity's own table. */
    public static function relationKeys(string $resource): array
    {
        return $resource === 'allowance-cap-groups' ? ['member_allowance_type_ids'] : [];
    }

    /** @return list<string> the recommendation condition vocabulary, for the UI and validation */
    public static function recommendationConditions(): array
    {
        return TaxRecommendationService::SUPPORTED_CONDITIONS;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One rule row's citation: which approved document, which page, which item.
 *
 * Milestone 08. `rule_entity_type` is restricted to the engine's own rule tables, so this is a
 * narrow mapping rather than an open polymorphic store — a row cannot point at anything that is
 * not a tax rule.
 */
class TaxRuleSource extends Model
{
    protected $table = 'tax_rule_sources';

    /** rule entity key => the model that owns it. */
    public const ENTITY_TYPES = [
        'tax_bracket' => TaxBracket::class,
        'expense_rule' => ExpenseRule::class,
        'allowance_rule' => AllowanceRule::class,
        'allowance_cap_group' => AllowanceCapGroup::class,
        'donation_rule' => DonationRule::class,
        'recommendation_rule' => RecommendationRule::class,
    ];

    protected $fillable = ['rule_version_id', 'tax_source_id', 'rule_entity_type',
        'rule_entity_id', 'page_reference', 'section_reference', 'notes'];

    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(TaxRuleVersion::class, 'rule_version_id');
    }

    public function taxSource(): BelongsTo
    {
        return $this->belongsTo(TaxSource::class, 'tax_source_id');
    }

    public static function knows(?string $entityType): bool
    {
        return $entityType !== null && array_key_exists($entityType, self::ENTITY_TYPES);
    }
}

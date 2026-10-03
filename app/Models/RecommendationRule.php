<?php

namespace App\Models;

use App\Models\Concerns\HasLegacySchemaAliases;
use App\Models\Concerns\ProtectsPublishedRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecommendationRule extends Model
{
    use HasLegacySchemaAliases;
    use ProtectsPublishedRules;

    protected $table = 'recommendation_rules';

    protected $fillable = ['tax_year_id', 'rule_version_id', 'code', 'category', 'message', 'conditions', 'priority', 'source_reference', 'type', 'title', 'message_template', 'action_type', 'active'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'conditions' => 'array',
            'priority' => 'string',
        ];
    }

    public function taxYear(): BelongsTo
    {
        return $this->belongsTo(TaxYear::class, 'tax_year_id');
    }

    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(TaxRuleVersion::class, 'rule_version_id');
    }

    /** @return array<string, string> */
    protected function schemaAliases(): array
    {
        return ['type' => 'category', 'message_template' => 'message'];
    }
}

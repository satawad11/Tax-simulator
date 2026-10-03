<?php

namespace App\Models;

use App\Models\Concerns\HasLegacySchemaAliases;
use App\Models\Concerns\ProtectsPublishedRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AllowanceRule extends Model
{
    use HasLegacySchemaAliases;
    use ProtectsPublishedRules;

    protected $table = 'allowance_rules';

    protected $fillable = ['tax_year_id', 'rule_version_id', 'allowance_type_id', 'code', 'limit_amount', 'percentage', 'conditions', 'source_reference', 'method', 'fixed_amount', 'maximum_amount', 'minimum_amount', 'active', 'percentage_base'];

    protected function casts(): array
    {
        return [
            'fixed_amount' => 'decimal:2',
            'maximum_amount' => 'decimal:2',
            'minimum_amount' => 'decimal:2',
            'active' => 'boolean',
            'limit_amount' => 'decimal:2',
            'percentage' => 'decimal:4',
            'conditions' => 'array',
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

    public function allowanceType(): BelongsTo
    {
        return $this->belongsTo(AllowanceType::class, 'allowance_type_id');
    }

    /** @return array<string, string> */
    protected function schemaAliases(): array
    {
        return ['maximum_amount' => 'limit_amount'];
    }
}

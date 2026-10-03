<?php

namespace App\Models;

use App\Models\Concerns\HasLegacySchemaAliases;
use App\Models\Concerns\ProtectsPublishedRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncomeRule extends Model
{
    use HasLegacySchemaAliases;
    use ProtectsPublishedRules;

    protected $table = 'income_rules';

    protected $fillable = ['tax_year_id', 'rule_version_id', 'income_type_id', 'code', 'name', 'exempt_amount', 'conditions', 'source_reference', 'active', 'metadata'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'metadata' => 'array',
            'exempt_amount' => 'decimal:2',
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

    public function incomeType(): BelongsTo
    {
        return $this->belongsTo(IncomeType::class, 'income_type_id');
    }

    /** @return array<string, string> */
    protected function schemaAliases(): array
    {
        return ['metadata' => 'conditions'];
    }
}

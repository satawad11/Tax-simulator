<?php

namespace App\Models;

use App\Models\Concerns\HasLegacySchemaAliases;
use App\Models\Concerns\ProtectsPublishedRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxBracket extends Model
{
    use HasLegacySchemaAliases;
    use ProtectsPublishedRules;

    protected $table = 'tax_brackets';

    protected $fillable = ['tax_year_id', 'rule_version_id', 'position', 'lower_bound', 'upper_bound', 'rate', 'source_reference', 'min_amount', 'max_amount', 'sort_order'];

    protected function casts(): array
    {
        return [
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'sort_order' => 'integer',
            'lower_bound' => 'decimal:2',
            'upper_bound' => 'decimal:2',
            'rate' => 'decimal:4',
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

    public function calculationBrackets(): HasMany
    {
        return $this->hasMany(TaxCalculationBracket::class, 'tax_bracket_id');
    }

    /** @return array<string, string> */
    protected function schemaAliases(): array
    {
        return ['min_amount' => 'lower_bound', 'max_amount' => 'upper_bound', 'sort_order' => 'position'];
    }
}

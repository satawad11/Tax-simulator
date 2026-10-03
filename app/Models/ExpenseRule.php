<?php

namespace App\Models;

use App\Models\Concerns\HasLegacySchemaAliases;
use App\Models\Concerns\ProtectsPublishedRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseRule extends Model
{
    use HasLegacySchemaAliases;
    use ProtectsPublishedRules;

    protected $table = 'expense_rules';

    protected $fillable = ['tax_year_id', 'rule_version_id', 'income_type_id', 'code', 'method', 'fixed_amount', 'limit_amount', 'percentage', 'conditions', 'source_reference', 'maximum_amount', 'minimum_amount', 'active', 'income_subtype', 'expense_group', 'expense_activity', 'holding_years_min', 'holding_years_max'];

    protected function casts(): array
    {
        return [
            'maximum_amount' => 'decimal:2',
            'minimum_amount' => 'decimal:2',
            'active' => 'boolean',
            'fixed_amount' => 'decimal:2',
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

    public function incomeType(): BelongsTo
    {
        return $this->belongsTo(IncomeType::class, 'income_type_id');
    }

    /** The printed bands of a tiered rate, in the order the source prints them. */
    public function tiers(): HasMany
    {
        return $this->hasMany(ExpenseRuleTier::class, 'expense_rule_id')->orderBy('sort_order');
    }

    /** @return array<string, string> */
    protected function schemaAliases(): array
    {
        return ['maximum_amount' => 'limit_amount'];
    }
}

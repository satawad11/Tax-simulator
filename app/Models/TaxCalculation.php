<?php

namespace App\Models;

use App\Models\Concerns\HasLegacySchemaAliases;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxCalculation extends Model
{
    use HasLegacySchemaAliases;

    protected $table = 'tax_calculations';

    protected $fillable = ['tax_return_id', 'rule_version_id', 'gross_income', 'exempt_income', 'total_expense', 'income_after_expense', 'total_allowance', 'total_donation', 'net_income', 'calculated_tax', 'credits', 'withholding', 'final_amount', 'result_status', 'result_snapshot', 'input_snapshot', 'trace', 'calculated_at', 'tax_credit', 'withholding_tax', 'prepaid_tax', 'final_tax', 'calculation_trace'];

    protected function casts(): array
    {
        return [
            'result_snapshot' => 'array',
            'tax_credit' => 'decimal:2',
            'withholding_tax' => 'decimal:2',
            'prepaid_tax' => 'decimal:2',
            'final_tax' => 'decimal:2',
            'calculation_trace' => 'array',
            'gross_income' => 'decimal:2',
            'exempt_income' => 'decimal:2',
            'total_expense' => 'decimal:2',
            'income_after_expense' => 'decimal:2',
            'total_allowance' => 'decimal:2',
            'total_donation' => 'decimal:2',
            'net_income' => 'decimal:2',
            'calculated_tax' => 'decimal:2',
            'credits' => 'decimal:2',
            'withholding' => 'decimal:2',
            'final_amount' => 'decimal:2',
            'input_snapshot' => 'array',
            'trace' => 'array',
            'calculated_at' => 'immutable_datetime',
        ];
    }

    public function taxReturn(): BelongsTo
    {
        return $this->belongsTo(TaxReturn::class, 'tax_return_id');
    }

    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(TaxRuleVersion::class, 'rule_version_id');
    }

    public function brackets(): HasMany
    {
        return $this->hasMany(TaxCalculationBracket::class, 'tax_calculation_id');
    }

    public function scenarios(): HasMany
    {
        return $this->hasMany(TaxScenario::class, 'base_calculation_id');
    }

    /** @return array<string, string> */
    protected function schemaAliases(): array
    {
        return ['tax_credit' => 'credits', 'withholding_tax' => 'withholding', 'final_tax' => 'final_amount', 'calculation_trace' => 'trace'];
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\HasLegacySchemaAliases;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxCalculationBracket extends Model
{
    use HasLegacySchemaAliases;

    protected $table = 'tax_calculation_brackets';

    protected $fillable = ['result_snapshot', 'tax_calculation_id', 'tax_bracket_id', 'position', 'lower_bound', 'upper_bound', 'rate', 'taxable_amount', 'tax_amount', 'from_amount', 'to_amount', 'sort_order'];

    protected function casts(): array
    {
        return [
            'result_snapshot' => 'array',
            'from_amount' => 'decimal:2',
            'to_amount' => 'decimal:2',
            'sort_order' => 'integer',
            'lower_bound' => 'decimal:2',
            'upper_bound' => 'decimal:2',
            'rate' => 'decimal:4',
            'taxable_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
        ];
    }

    public function taxCalculation(): BelongsTo
    {
        return $this->belongsTo(TaxCalculation::class, 'tax_calculation_id');
    }

    public function taxBracket(): BelongsTo
    {
        return $this->belongsTo(TaxBracket::class, 'tax_bracket_id');
    }

    /** @return array<string, string> */
    protected function schemaAliases(): array
    {
        return ['from_amount' => 'lower_bound', 'to_amount' => 'upper_bound', 'sort_order' => 'position'];
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\HasLegacySchemaAliases;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxReturnIncome extends Model
{
    use HasLegacySchemaAliases;

    protected $table = 'tax_return_incomes';

    protected $fillable = ['tax_return_id', 'income_type_id', 'amount', 'exempt_amount', 'actual_expense', 'details', 'description', 'gross_amount', 'expense_method', 'calculated_expense', 'net_amount', 'metadata', 'income_subtype', 'expense_activity', 'holding_years', 'expense_method_selection', 'tax_treatment'];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'calculated_expense' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'metadata' => 'array',
            'amount' => 'decimal:2',
            'exempt_amount' => 'decimal:2',
            'actual_expense' => 'decimal:2',
            'details' => 'array',
        ];
    }

    public function taxReturn(): BelongsTo
    {
        return $this->belongsTo(TaxReturn::class, 'tax_return_id');
    }

    public function incomeType(): BelongsTo
    {
        return $this->belongsTo(IncomeType::class, 'income_type_id');
    }

    /** @return array<string, string> */
    protected function schemaAliases(): array
    {
        return ['gross_amount' => 'amount', 'metadata' => 'details'];
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\HasLegacySchemaAliases;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxReturnAllowance extends Model
{
    use HasLegacySchemaAliases;

    protected $table = 'tax_return_allowances';

    protected $fillable = ['tax_return_id', 'allowance_type_id', 'amount', 'quantity', 'details', 'input_amount', 'eligible_amount', 'metadata'];

    protected function casts(): array
    {
        return [
            'input_amount' => 'decimal:2',
            'eligible_amount' => 'decimal:2',
            'metadata' => 'array',
            'amount' => 'decimal:2',
            'quantity' => 'integer',
            'details' => 'array',
        ];
    }

    public function taxReturn(): BelongsTo
    {
        return $this->belongsTo(TaxReturn::class, 'tax_return_id');
    }

    public function allowanceType(): BelongsTo
    {
        return $this->belongsTo(AllowanceType::class, 'allowance_type_id');
    }

    /** @return array<string, string> */
    protected function schemaAliases(): array
    {
        return ['input_amount' => 'amount', 'metadata' => 'details'];
    }
}

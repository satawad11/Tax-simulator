<?php

namespace App\Models;

use App\Models\Concerns\HasLegacySchemaAliases;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxReturnWithholding extends Model
{
    use HasLegacySchemaAliases;

    protected $table = 'tax_return_withholdings';

    protected $fillable = ['tax_return_id', 'credit_type', 'amount', 'description', 'type', 'payer_name', 'payer_tax_id', 'metadata'];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'amount' => 'decimal:2',
        ];
    }

    public function taxReturn(): BelongsTo
    {
        return $this->belongsTo(TaxReturn::class, 'tax_return_id');
    }

    /** @return array<string, string> */
    protected function schemaAliases(): array
    {
        return ['type' => 'credit_type'];
    }
}

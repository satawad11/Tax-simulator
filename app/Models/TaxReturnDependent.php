<?php

namespace App\Models;

use App\Models\Concerns\HasLegacySchemaAliases;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxReturnDependent extends Model
{
    use HasLegacySchemaAliases;

    protected $table = 'tax_return_dependents';

    protected $fillable = ['tax_return_id', 'relationship', 'label', 'birth_date', 'details', 'relation_type', 'disabled_person_relationship', 'child_type', 'birth_order', 'eligible', 'allowance_amount', 'metadata'];

    protected function casts(): array
    {
        return [
            'eligible' => 'boolean',
            'allowance_amount' => 'decimal:2',
            'metadata' => 'array',
            'birth_date' => 'immutable_date',
            'details' => 'array',
        ];
    }

    public function taxReturn(): BelongsTo
    {
        return $this->belongsTo(TaxReturn::class, 'tax_return_id');
    }

    /** @return array<string, string> */
    protected function schemaAliases(): array
    {
        return ['metadata' => 'details'];
    }
}

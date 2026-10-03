<?php

namespace App\Models;

use App\Models\Concerns\HasLegacySchemaAliases;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxReturnSpouse extends Model
{
    use HasLegacySchemaAliases;

    protected $table = 'tax_return_spouses';

    protected $fillable = ['tax_return_id', 'birth_date', 'has_income', 'details', 'filing_status', 'extra_data'];

    protected function casts(): array
    {
        return [
            'extra_data' => 'array',
            'birth_date' => 'immutable_date',
            'has_income' => 'boolean',
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
        return ['extra_data' => 'details'];
    }
}

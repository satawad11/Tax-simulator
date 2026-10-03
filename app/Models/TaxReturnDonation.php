<?php

namespace App\Models;

use App\Models\Concerns\HasLegacySchemaAliases;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxReturnDonation extends Model
{
    use HasLegacySchemaAliases;

    protected $table = 'tax_return_donations';

    protected $fillable = ['tax_return_id', 'donation_type', 'amount', 'details', 'donation_code', 'input_amount', 'eligible_amount', 'metadata'];

    protected function casts(): array
    {
        return [
            'input_amount' => 'decimal:2',
            'eligible_amount' => 'decimal:2',
            'metadata' => 'array',
            'amount' => 'decimal:2',
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
        return ['input_amount' => 'amount', 'metadata' => 'details'];
    }
}

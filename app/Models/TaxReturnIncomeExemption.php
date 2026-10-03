<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A เงินได้ที่ได้รับยกเว้น line a member saved on their return — ใบแนบ ข้อ 13 and ข้อ 20.
 *
 * Kept apart from `TaxReturnAllowance` because the two are deducted at different points in the
 * calculation, and `declarations_confirmed` has no counterpart there: these lines turn on facts
 * the engine cannot establish, so the filer's affirmation is part of the saved input rather than
 * something reconstructed when the return is recalculated.
 */
class TaxReturnIncomeExemption extends Model
{
    protected $table = 'tax_return_income_exemptions';

    protected $fillable = ['tax_return_id', 'code', 'input_amount', 'eligible_amount',
        'declarations_confirmed', 'metadata'];

    protected function casts(): array
    {
        return [
            'input_amount' => 'decimal:2',
            'eligible_amount' => 'decimal:2',
            'declarations_confirmed' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function taxReturn(): BelongsTo
    {
        return $this->belongsTo(TaxReturn::class, 'tax_return_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TaxFormIncomeType extends Pivot
{
    protected $table = 'tax_form_income_types';

    public $incrementing = true;

    protected $fillable = ['tax_form_id', 'income_type_id'];

    public function taxForm(): BelongsTo
    {
        return $this->belongsTo(TaxForm::class, 'tax_form_id');
    }

    public function incomeType(): BelongsTo
    {
        return $this->belongsTo(IncomeType::class, 'income_type_id');
    }
}

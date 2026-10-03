<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IncomeType extends Model
{
    protected $table = 'income_types';

    protected $fillable = ['code', 'name', 'description', 'section_code'];

    public function formMappings(): HasMany
    {
        return $this->hasMany(TaxFormIncomeType::class, 'income_type_id');
    }

    public function incomeRules(): HasMany
    {
        return $this->hasMany(IncomeRule::class, 'income_type_id');
    }

    public function expenseRules(): HasMany
    {
        return $this->hasMany(ExpenseRule::class, 'income_type_id');
    }

    public function returnIncomes(): HasMany
    {
        return $this->hasMany(TaxReturnIncome::class, 'income_type_id');
    }

    public function taxForms(): BelongsToMany
    {
        return $this->belongsToMany(TaxForm::class, 'tax_form_income_types', 'income_type_id', 'tax_form_id')->using(TaxFormIncomeType::class)->withPivot('id')->withTimestamps();
    }
}

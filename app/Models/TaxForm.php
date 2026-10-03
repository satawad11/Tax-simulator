<?php

namespace App\Models;

use App\Models\Concerns\HasLegacySchemaAliases;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxForm extends Model
{
    use HasFactory;
    use HasLegacySchemaAliases;

    protected $table = 'tax_forms';

    protected $fillable = ['tax_year_id', 'code', 'name', 'description', 'is_active', 'active'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function taxYear(): BelongsTo
    {
        return $this->belongsTo(TaxYear::class, 'tax_year_id');
    }

    public function incomeTypeMappings(): HasMany
    {
        return $this->hasMany(TaxFormIncomeType::class, 'tax_form_id');
    }

    public function taxReturns(): HasMany
    {
        return $this->hasMany(TaxReturn::class, 'tax_form_id');
    }

    public function incomeTypes(): BelongsToMany
    {
        return $this->belongsToMany(IncomeType::class, 'tax_form_income_types', 'tax_form_id', 'income_type_id')->using(TaxFormIncomeType::class)->withPivot('id')->withTimestamps();
    }

    /** @return array<string, string> */
    protected function schemaAliases(): array
    {
        return ['active' => 'is_active'];
    }
}

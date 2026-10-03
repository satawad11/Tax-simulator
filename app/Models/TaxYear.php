<?php

namespace App\Models;

use App\Models\Concerns\HasLegacySchemaAliases;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxYear extends Model
{
    use HasFactory;
    use HasLegacySchemaAliases;

    protected $table = 'tax_years';

    protected $attributes = ['active' => true, 'is_active' => true];

    protected $fillable = ['year', 'name', 'is_active', 'filing_start_date', 'filing_end_date', 'active'];

    protected function casts(): array
    {
        return [
            'filing_start_date' => 'immutable_date',
            'filing_end_date' => 'immutable_date',
            'active' => 'boolean',
            'year' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function forms(): HasMany
    {
        return $this->hasMany(TaxForm::class, 'tax_year_id');
    }

    public function ruleVersions(): HasMany
    {
        return $this->hasMany(TaxRuleVersion::class, 'tax_year_id');
    }

    public function brackets(): HasMany
    {
        return $this->hasMany(TaxBracket::class, 'tax_year_id');
    }

    public function incomeRules(): HasMany
    {
        return $this->hasMany(IncomeRule::class, 'tax_year_id');
    }

    public function expenseRules(): HasMany
    {
        return $this->hasMany(ExpenseRule::class, 'tax_year_id');
    }

    public function allowanceRules(): HasMany
    {
        return $this->hasMany(AllowanceRule::class, 'tax_year_id');
    }

    public function donationRules(): HasMany
    {
        return $this->hasMany(DonationRule::class, 'tax_year_id');
    }

    public function recommendationRules(): HasMany
    {
        return $this->hasMany(RecommendationRule::class, 'tax_year_id');
    }

    public function taxReturns(): HasMany
    {
        return $this->hasMany(TaxReturn::class, 'tax_year_id');
    }

    /** @return array<string, string> */
    protected function schemaAliases(): array
    {
        return ['active' => 'is_active'];
    }
}

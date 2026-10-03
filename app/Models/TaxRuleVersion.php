<?php

namespace App\Models;

use App\Models\Concerns\ProtectsPublishedRules;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxRuleVersion extends Model
{
    use HasFactory, ProtectsPublishedRules;

    protected $table = 'tax_rule_versions';

    protected $fillable = ['tax_year_id', 'version', 'status', 'published_at', 'source_reference', 'description', 'effective_from', 'effective_to'];

    protected function casts(): array
    {
        return [
            'effective_from' => 'immutable_datetime',
            'effective_to' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }

    public function taxYear(): BelongsTo
    {
        return $this->belongsTo(TaxYear::class, 'tax_year_id');
    }

    public function brackets(): HasMany
    {
        return $this->hasMany(TaxBracket::class, 'rule_version_id');
    }

    public function incomeRules(): HasMany
    {
        return $this->hasMany(IncomeRule::class, 'rule_version_id');
    }

    public function expenseRules(): HasMany
    {
        return $this->hasMany(ExpenseRule::class, 'rule_version_id');
    }

    public function allowanceRules(): HasMany
    {
        return $this->hasMany(AllowanceRule::class, 'rule_version_id');
    }

    public function donationRules(): HasMany
    {
        return $this->hasMany(DonationRule::class, 'rule_version_id');
    }

    public function recommendationRules(): HasMany
    {
        return $this->hasMany(RecommendationRule::class, 'rule_version_id');
    }

    public function taxReturns(): HasMany
    {
        return $this->hasMany(TaxReturn::class, 'rule_version_id');
    }

    public function calculations(): HasMany
    {
        return $this->hasMany(TaxCalculation::class, 'rule_version_id');
    }
}

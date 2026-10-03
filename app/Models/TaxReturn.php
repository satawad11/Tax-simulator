<?php

namespace App\Models;

use App\Models\Concerns\HasLegacySchemaAliases;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxReturn extends Model
{
    use HasFactory, SoftDeletes;
    use HasLegacySchemaAliases;

    protected $table = 'tax_returns';

    protected $fillable = ['user_id', 'tax_year_id', 'rule_version_id', 'tax_form_id', 'title', 'status', 'completed_at', 'name', 'current_step'];

    protected function casts(): array
    {
        return [
            'current_step' => 'integer',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function taxYear(): BelongsTo
    {
        return $this->belongsTo(TaxYear::class, 'tax_year_id');
    }

    public function taxForm(): BelongsTo
    {
        return $this->belongsTo(TaxForm::class, 'tax_form_id');
    }

    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(TaxRuleVersion::class, 'rule_version_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(TaxReturnProfile::class, 'tax_return_id');
    }

    public function spouse(): HasOne
    {
        return $this->hasOne(TaxReturnSpouse::class, 'tax_return_id');
    }

    public function dependents(): HasMany
    {
        return $this->hasMany(TaxReturnDependent::class, 'tax_return_id');
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(TaxReturnIncome::class, 'tax_return_id');
    }

    public function allowances(): HasMany
    {
        return $this->hasMany(TaxReturnAllowance::class, 'tax_return_id');
    }

    /** ใบแนบ ข้อ 13 และ ข้อ 20 — deducted after expenses, so not part of `allowances`. */
    public function incomeExemptions(): HasMany
    {
        return $this->hasMany(TaxReturnIncomeExemption::class, 'tax_return_id');
    }

    public function donations(): HasMany
    {
        return $this->hasMany(TaxReturnDonation::class, 'tax_return_id');
    }

    public function withholdings(): HasMany
    {
        return $this->hasMany(TaxReturnWithholding::class, 'tax_return_id');
    }

    public function calculations(): HasMany
    {
        return $this->hasMany(TaxCalculation::class, 'tax_return_id');
    }

    public function latestCalculation(): HasOne
    {
        return $this->hasOne(TaxCalculation::class, 'tax_return_id')->latestOfMany();
    }

    public function scenarios(): HasMany
    {
        return $this->hasMany(TaxScenario::class, 'tax_return_id');
    }

    /** @return array<string, string> */
    protected function schemaAliases(): array
    {
        return ['name' => 'title'];
    }
}

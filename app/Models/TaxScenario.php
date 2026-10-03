<?php

namespace App\Models;

use App\Models\Concerns\HasLegacySchemaAliases;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxScenario extends Model
{
    use HasLegacySchemaAliases;

    protected $table = 'tax_scenarios';

    protected $fillable = ['tax_return_id', 'base_calculation_id', 'name', 'input_overrides', 'before_tax', 'after_tax', 'estimated_tax_saving', 'trace', 'calculated_at', 'user_id', 'source_tax_return_id', 'tax_year_id', 'rule_version_id', 'payload', 'calculation_result'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'calculation_result' => 'array',
            'input_overrides' => 'array',
            'trace' => 'array',
            'before_tax' => 'decimal:2',
            'after_tax' => 'decimal:2',
            'estimated_tax_saving' => 'decimal:2',
            'calculated_at' => 'immutable_datetime',
        ];
    }

    public function taxReturn(): BelongsTo
    {
        return $this->belongsTo(TaxReturn::class, 'tax_return_id');
    }

    public function baseCalculation(): BelongsTo
    {
        return $this->belongsTo(TaxCalculation::class, 'base_calculation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sourceTaxReturn(): BelongsTo
    {
        return $this->belongsTo(TaxReturn::class, 'source_tax_return_id');
    }

    public function taxYear(): BelongsTo
    {
        return $this->belongsTo(TaxYear::class, 'tax_year_id');
    }

    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(TaxRuleVersion::class, 'rule_version_id');
    }

    protected static function booted(): void
    {
        static::saving(function (self $scenario): void {
            $source = TaxReturn::withTrashed()->findOrFail($scenario->source_tax_return_id ?? $scenario->tax_return_id);
            foreach (['user_id', 'tax_year_id', 'rule_version_id'] as $field) {
                if ($scenario->$field !== null && (int) $scenario->$field !== (int) $source->$field) {
                    throw new \LogicException('Scenario ownership and rule context must match its source return.');
                }
                $scenario->$field = $source->$field;
            }
        });
    }

    /** @return array<string, string> */
    protected function schemaAliases(): array
    {
        return ['source_tax_return_id' => 'tax_return_id', 'payload' => 'input_overrides'];
    }
}

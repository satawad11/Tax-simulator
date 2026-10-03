<?php

namespace App\Models;

use App\Models\Concerns\HasLegacySchemaAliases;
use App\Models\Concerns\ProtectsPublishedRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DonationRule extends Model
{
    use HasLegacySchemaAliases;
    use ProtectsPublishedRules;

    protected $table = 'donation_rules';

    protected $fillable = ['tax_year_id', 'rule_version_id', 'code', 'donation_type', 'multiplier', 'cap_percentage', 'limit_amount', 'conditions', 'source_reference', 'name', 'max_percentage', 'active'];

    protected function casts(): array
    {
        return [
            'max_percentage' => 'decimal:4',
            'active' => 'boolean',
            'multiplier' => 'decimal:4',
            'cap_percentage' => 'decimal:4',
            'limit_amount' => 'decimal:2',
            'conditions' => 'array',
        ];
    }

    public function taxYear(): BelongsTo
    {
        return $this->belongsTo(TaxYear::class, 'tax_year_id');
    }

    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(TaxRuleVersion::class, 'rule_version_id');
    }

    /** @return array<string, string> */
    protected function schemaAliases(): array
    {
        return ['max_percentage' => 'cap_percentage'];
    }
}

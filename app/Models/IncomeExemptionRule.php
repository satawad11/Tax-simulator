<?php

namespace App\Models;

use App\Models\Concerns\ProtectsPublishedRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line ใบแนบ prints as เงินได้ที่ได้รับยกเว้น, deducted after expenses rather than in the
 * allowance block — see the migration for why the two are not interchangeable.
 *
 * Like every other rule it belongs to a rule version and inherits `ProtectsPublishedRules`, so a
 * published version can never gain, lose or change one of these.
 */
class IncomeExemptionRule extends Model
{
    use ProtectsPublishedRules;

    /** A share of the declared amount — ใบแนบ ข้อ 13 prints ร้อยละหนึ่งร้อย. */
    public const PERCENTAGE = 'percentage';

    /**
     * A fixed grant for each whole step of declared spending, which is how ใบแนบ ข้อ 20 prints it:
     * 10,000 บาท ต่อทุกจำนวน 1,000,000 บาท. A part-finished step grants nothing, because the form
     * says "ต่อทุกจำนวน" and never prints a proportion.
     */
    public const STEPPED_GRANT = 'stepped_grant';

    protected $table = 'income_exemption_rules';

    protected $fillable = ['tax_year_id', 'rule_version_id', 'code', 'name', 'method', 'percentage',
        'step_amount', 'grant_per_step', 'maximum_amount', 'active', 'conditions', 'source_reference'];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:4',
            'step_amount' => 'decimal:2',
            'grant_per_step' => 'decimal:2',
            'maximum_amount' => 'decimal:2',
            'active' => 'boolean',
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

    /**
     * The facts a filer must affirm before this line may be claimed.
     *
     * Every one of these is printed in the filing instructions as a condition of the right, and
     * none of them can be derived from an amount. The engine does not verify them — it records
     * that the filer asserted them, exactly as it already does for exempt income and dependants.
     *
     * @return list<string>
     */
    public function declarations(): array
    {
        return array_values(array_filter((array) ($this->conditions['declarations'] ?? [])));
    }
}

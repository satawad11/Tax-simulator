<?php

namespace App\Models;

use App\Models\Concerns\ProtectsPublishedRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * One ceiling the 2568 filing instructions state across several allowance codes.
 *
 * Milestone 07.4. The group owns the ceiling; its members own their individual ceilings.
 *
 * Milestone 08 added ProtectsPublishedRules. M7.4 created this table when the only writer was a
 * seeder running before publication, so the omission never bit; once an admin API can write
 * rule data it would have been the one rule table a published version did not protect.
 */
class AllowanceCapGroup extends Model
{
    use ProtectsPublishedRules;

    protected $table = 'allowance_cap_groups';

    protected $fillable = ['tax_year_id', 'rule_version_id', 'code', 'name', 'maximum_amount',
        'percentage', 'percentage_base', 'conditions', 'source_reference', 'active'];

    protected function casts(): array
    {
        return ['conditions' => 'array', 'active' => 'boolean', 'maximum_amount' => 'decimal:2'];
    }

    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(TaxRuleVersion::class, 'rule_version_id');
    }

    public function allowanceTypes(): BelongsToMany
    {
        return $this->belongsToMany(AllowanceType::class, 'allowance_cap_group_members',
            'allowance_cap_group_id', 'allowance_type_id')->withPivot('sort_order')->orderByPivot('sort_order');
    }
}

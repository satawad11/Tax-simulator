<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One printed band of a tiered expense rate.
 *
 * Milestone 07.4. `threshold_amount` is the band's upper bound measured on the income the
 * rule applies to; the final band leaves it null because the source prints it open-ended.
 */
class ExpenseRuleTier extends Model
{
    protected $table = 'expense_rule_tiers';

    protected $fillable = ['expense_rule_id', 'tier_code', 'sort_order', 'threshold_amount', 'percentage', 'source_reference'];

    protected function casts(): array
    {
        // Cast both so SQLite and MySQL put the same decimal string on the wire.
        return ['threshold_amount' => 'decimal:2', 'percentage' => 'decimal:4'];
    }

    public function expenseRule(): BelongsTo
    {
        return $this->belongsTo(ExpenseRule::class, 'expense_rule_id');
    }
}

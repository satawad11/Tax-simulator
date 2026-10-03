<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A repository-approved document that a tax rule was read from.
 *
 * Milestone 08. The registry never points outside the repository: `file_path` is a path under
 * `docs/tax-source/`, and nothing in this project fetches an external URL to establish a rule.
 */
class TaxSource extends Model
{
    protected $table = 'tax_sources';

    /** The kinds of document Milestones 4–7.5 actually read. */
    public const TYPES = ['OFFICIAL_FORM', 'FILING_INSTRUCTIONS', 'ATTACHMENT', 'INTERNAL_APPROVED_REFERENCE'];

    protected $fillable = ['code', 'title', 'source_type', 'file_path', 'description',
        'tax_year_id', 'document_date', 'active', 'created_by'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'document_date' => 'immutable_date'];
    }

    public function taxYear(): BelongsTo
    {
        return $this->belongsTo(TaxYear::class, 'tax_year_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function ruleSources(): HasMany
    {
        return $this->hasMany(TaxRuleSource::class, 'tax_source_id');
    }

    /** True while any published rule version cites this source, which blocks deletion. */
    public function isCitedByPublishedRule(): bool
    {
        return $this->ruleSources()
            ->whereHas('ruleVersion', fn ($query) => $query->where('status', 'published'))
            ->exists();
    }
}

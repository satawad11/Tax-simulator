<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\Api\V1\MetadataResource;
use Illuminate\Http\Request;

/**
 * A tax year as an administrator sees it.
 *
 * Milestone 09.1. Beyond the year itself, the console needs the three counts that decide what may
 * be done with it: whether it has forms (without them a simulation cannot start), whether it has a
 * published rule version (which pins it open), and how many saved returns already depend on it.
 */
class AdminTaxYearResource extends MetadataResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'year' => (int) $this->year,
            'name' => $this->name,
            'active' => (bool) $this->active,
            'filing_start_date' => $this->filing_start_date?->format('Y-m-d'),
            'filing_end_date' => $this->filing_end_date?->format('Y-m-d'),
            'form_count' => $this->whenCounted('forms'),
            'rule_version_count' => $this->whenCounted('ruleVersions'),
            'tax_return_count' => $this->whenCounted('taxReturns'),
            'published_version' => $this->whenLoaded('ruleVersions',
                fn () => $this->ruleVersions->firstWhere('status', 'published')?->version),
        ];
    }
}

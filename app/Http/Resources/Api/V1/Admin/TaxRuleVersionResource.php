<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\Api\V1\MetadataResource;
use Illuminate\Http\Request;

/**
 * Milestone 08. The admin read model of a rule version: what it is, what it holds, how much of
 * it is evidenced, and — for a draft — whether it would pass validation right now.
 */
class TaxRuleVersionResource extends MetadataResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'version' => $this->version,
            'status' => $this->status,
            'tax_year' => $this->taxYear?->year,
            'description' => $this->description,
            'editable' => $this->status === 'draft',
            'created_at' => $this->created_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            ...($this->additional['summary'] ?? []),
        ];
    }
}

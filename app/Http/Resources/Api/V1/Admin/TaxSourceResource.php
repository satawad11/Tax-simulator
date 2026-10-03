<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\Api\V1\MetadataResource;
use Illuminate\Http\Request;

/** Milestone 08. */
class TaxSourceResource extends MetadataResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'title' => $this->title,
            'source_type' => $this->source_type,
            'file_path' => $this->file_path,
            'description' => $this->description,
            'tax_year' => $this->whenLoaded('taxYear', fn (): ?int => $this->taxYear?->year),
            'document_date' => $this->document_date?->format('Y-m-d'),
            'active' => (bool) $this->active,
            'citation_count' => $this->whenCounted('ruleSources'),
            'cited_by_published_rule' => array_key_exists('cited_by_published_rule', $this->resource->getAttributes())
                ? (bool) $this->cited_by_published_rule
                : $this->isCitedByPublishedRule(),
        ];
    }
}

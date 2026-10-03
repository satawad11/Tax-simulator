<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

/**
 * A published item in a public listing.
 *
 * Milestone 08. Nothing administrative crosses this boundary: no status, no draft metadata,
 * no author identity, no internal note.
 */
class ContentSummaryResource extends MetadataResource
{
    public function toArray(Request $request): array
    {
        return [
            'type' => strtoupper((string) $this->type),
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'featured' => (bool) $this->featured,
            'category' => $this->whenLoaded('category', fn (): ?array => $this->category === null ? null
                : ['name' => $this->category->name, 'slug' => $this->category->slug]),
            'tags' => $this->whenLoaded('tags', fn (): array => $this->tags
                ->map(fn ($tag): array => ['name' => $tag->name, 'slug' => $tag->slug])->values()->all()),
            'tax_year' => $this->whenLoaded('taxYear', fn (): ?int => $this->taxYear?->year),
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}

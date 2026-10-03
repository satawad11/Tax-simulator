<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

/** A single published item. Milestone 08. */
class ContentDetailResource extends MetadataResource
{
    public function toArray(Request $request): array
    {
        return [
            'type' => strtoupper((string) $this->type),
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            // Structured plain text, never markup; see App\Services\Content\ContentBodyFormat.
            'body' => $this->content,
            'body_format' => 'STRUCTURED_TEXT',
            'featured' => (bool) $this->featured,
            'category' => $this->category === null ? null
                : ['name' => $this->category->name, 'slug' => $this->category->slug],
            'tags' => $this->tags->map(fn ($tag): array => ['name' => $tag->name, 'slug' => $tag->slug])->values()->all(),
            'tax_year' => $this->taxYear?->year,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            // Phase 3 — attribution, so an API client can credit the announcement a news item
            // came from exactly as the article page does.
            'source_name' => $this->source_name,
            'source_url' => $this->source_url,
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}

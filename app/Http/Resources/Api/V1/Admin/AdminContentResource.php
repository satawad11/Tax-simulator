<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\Api\V1\MetadataResource;
use App\Models\ContentPost;
use Illuminate\Http\Request;

/** Milestone 08. The admin view carries the workflow fields the public view never sees. */
class AdminContentResource extends MetadataResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => strtoupper((string) $this->type),
            // The words the console prints, from the model that owns the vocabulary — so the
            // browser never needs a copy of it and cannot drift from the pages that render
            // server-side. See the Phase 3 note on `ContentPost::TYPE_LABELS`.
            'type_label' => ContentPost::typeLabel($this->type),
            'title' => $this->title,
            'slug' => $this->slug,
            'slug_locked' => $this->slugIsLocked(),
            'excerpt' => $this->excerpt,
            'body' => $this->content,
            'body_format' => 'STRUCTURED_TEXT',
            'status' => $this->status,
            'status_label' => ContentPost::statusLabel($this->status),
            'featured' => (bool) $this->featured,
            'sort_order' => (int) $this->sort_order,
            'category' => $this->category === null ? null
                : ['id' => $this->category->id, 'name' => $this->category->name, 'slug' => $this->category->slug],
            'tags' => $this->tags->map(fn ($tag): array => ['id' => $tag->id, 'name' => $tag->name, 'slug' => $tag->slug])->values()->all(),
            'tax_year' => $this->taxYear?->year,
            'tax_year_id' => $this->tax_year_id,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'source_name' => $this->source_name,
            'source_url' => $this->source_url,
            // The author's name only; an administrative list is no place for an email address.
            'author' => $this->author?->name,
            'published_at' => $this->published_at?->toIso8601String(),
            'first_published_at' => $this->first_published_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

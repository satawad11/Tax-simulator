<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

/** Milestone 08. */
class ContentCategoryResource extends MetadataResource
{
    public function toArray(Request $request): array
    {
        return ['name' => $this->name, 'slug' => $this->slug, 'description' => $this->description,
            'sort_order' => (int) $this->sort_order,
            ...($this->resource->relationLoaded('posts') ? ['published_count' => $this->posts->count()] : [])];
    }
}

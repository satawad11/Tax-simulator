<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\Api\V1\MetadataResource;
use Illuminate\Http\Request;

/**
 * A category or tag as an administrator sees it.
 *
 * Milestone 08. The public resources deliberately omit the primary key — a reader addresses
 * content by slug — but the CMS has to reference a row it is about to edit, so the admin view
 * carries the id and the active flag the public view does not.
 */
class AdminContentTaxonomyResource extends MetadataResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'active' => (bool) $this->active,
            // M9.1 — how many posts are filed here. This is what decides whether deleting the
            // label removes it or merely deactivates it, so the console shows the number rather
            // than letting the outcome surprise the administrator.
            'content_count' => $this->whenCounted('posts'),
            ...($this->resource->getTable() === 'content_categories'
                ? ['description' => $this->description, 'sort_order' => (int) $this->sort_order]
                : []),
        ];
    }
}

<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

/** Milestone 08. */
class ContentTagResource extends MetadataResource
{
    public function toArray(Request $request): array
    {
        return ['name' => $this->name, 'slug' => $this->slug];
    }
}

<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\Api\V1\MetadataResource;
use Illuminate\Http\Request;

/** Milestone 08. */
class AdminAuditLogResource extends MetadataResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'summary' => $this->summary,
            'actor' => $this->actor?->name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

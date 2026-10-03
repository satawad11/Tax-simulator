<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MetadataResource extends JsonResource
{
    public function with(Request $request): array
    {
        return ['success' => true, 'message' => null];
    }
}

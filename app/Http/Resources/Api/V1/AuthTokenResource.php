<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class AuthTokenResource extends MetadataResource
{
    public function toArray(Request $request): array
    {
        return ['user' => new UserResource($this->resource['user']), 'token' => $this->resource['token'], 'token_type' => 'Bearer'];
    }
}

<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HealthResource extends JsonResource
{
    /** @return array{status: string, service: string} */
    public function toArray(Request $request): array
    {
        return ['status' => 'ok', 'service' => 'tax-simulator-api'];
    }

    /** @return array{success: bool, message: null} */
    public function with(Request $request): array
    {
        return ['success' => true, 'message' => null];
    }
}

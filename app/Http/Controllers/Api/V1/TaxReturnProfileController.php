<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\WriteTaxReturnProfileRequest;
use App\Http\Resources\Api\V1\TaxReturnProfileResource;
use App\Models\TaxReturn;
use App\Services\Tax\TaxReturnInputService;
use Illuminate\Http\JsonResponse;

class TaxReturnProfileController extends Controller
{
    public function update(WriteTaxReturnProfileRequest $request, TaxReturn $taxReturn, TaxReturnInputService $service): JsonResponse
    {
        return (new TaxReturnProfileResource($service->save($taxReturn, 'profile', $request->validated())))->response()->setStatusCode(200);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AllowanceIndexRequest;
use App\Http\Resources\Api\V1\AllowanceTypeResource;
use App\Services\Tax\TaxMetadataService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AllowanceController extends Controller
{
    public function index(AllowanceIndexRequest $request, TaxMetadataService $metadata, int $year): AnonymousResourceCollection
    {
        return AllowanceTypeResource::collection($metadata->allowanceList($metadata->context($year), $request->validated('category')))
            ->additional(['success' => true, 'message' => null]);
    }

    public function show(TaxMetadataService $metadata, int $year, string $code): AllowanceTypeResource
    {
        return new AllowanceTypeResource($metadata->allowance($metadata->context($year), $code));
    }
}

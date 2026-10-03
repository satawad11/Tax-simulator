<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TaxYearResource;
use App\Services\Tax\TaxMetadataService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaxYearController extends Controller
{
    public function index(TaxMetadataService $metadata): AnonymousResourceCollection
    {
        return TaxYearResource::collection($metadata->years())->additional(['success' => true, 'message' => null]);
    }

    public function show(TaxMetadataService $metadata, int $year): TaxYearResource
    {
        return new TaxYearResource($metadata->context($year));
    }
}

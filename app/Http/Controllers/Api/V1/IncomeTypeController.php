<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IncomeTypeIndexRequest;
use App\Http\Resources\Api\V1\IncomeTypeResource;
use App\Services\Tax\TaxMetadataService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class IncomeTypeController extends Controller
{
    public function index(IncomeTypeIndexRequest $request, TaxMetadataService $metadata, int $year): AnonymousResourceCollection
    {
        return IncomeTypeResource::collection($metadata->incomeTypes($metadata->context($year), $request->validated('form'))->get())
            ->additional(['success' => true, 'message' => null]);
    }

    public function show(TaxMetadataService $metadata, int $year, string $code): IncomeTypeResource
    {
        return new IncomeTypeResource($metadata->incomeType($metadata->context($year), $code));
    }
}

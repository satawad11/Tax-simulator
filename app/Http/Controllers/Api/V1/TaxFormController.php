<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TaxFormResource;
use App\Services\Tax\TaxMetadataService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaxFormController extends Controller
{
    public function index(TaxMetadataService $metadata, int $year): AnonymousResourceCollection
    {
        return TaxFormResource::collection($metadata->forms($metadata->context($year)))->additional(['success' => true, 'message' => null]);
    }

    public function show(TaxMetadataService $metadata, int $year, string $form): TaxFormResource
    {
        $record = $metadata->form($metadata->context($year), $form);
        $record->load(['incomeTypes' => fn ($query) => $query->orderBy('code')]);

        return new TaxFormResource($record);
    }
}

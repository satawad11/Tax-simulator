<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TaxBracketCollectionResource;
use App\Services\Tax\TaxMetadataService;

class TaxBracketController extends Controller
{
    public function __invoke(TaxMetadataService $metadata, int $year): TaxBracketCollectionResource
    {
        $context = $metadata->context($year);
        $context->publishedRuleVersion->load(['brackets' => fn ($query) => $query->orderBy('sort_order')]);

        return new TaxBracketCollectionResource($context);
    }
}

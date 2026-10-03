<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\IncomeExemptionRuleResource;
use App\Models\IncomeExemptionRule;
use App\Services\Tax\TaxMetadataService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The เงินได้ที่ได้รับยกเว้น lines the resolved rule version can calculate.
 *
 * Scoped to the published version for the requested year, so a client is never offered a line
 * belonging to a retired version it cannot submit against.
 */
class IncomeExemptionController extends Controller
{
    public function index(TaxMetadataService $metadata, int $year): AnonymousResourceCollection
    {
        $version = $metadata->context($year)->publishedRuleVersion;

        return IncomeExemptionRuleResource::collection(
            IncomeExemptionRule::where('rule_version_id', $version->id)
                ->where('active', true)->orderBy('code')->get())
            ->additional(['success' => true, 'message' => null]);
    }
}

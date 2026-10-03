<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\WriteTaxYearRequest;
use App\Http\Resources\Api\V1\Admin\AdminTaxYearResource;
use App\Models\TaxRuleVersion;
use App\Models\TaxYear;
use App\Services\Admin\TaxYearService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Tax year administration.
 *
 * Milestone 09.1. This is what makes "next year's rates changed" an administrative task rather
 * than a code change: a year is opened here, its form structure copied from the year before, and
 * the rules themselves then carried over by cloning a rule version into it.
 *
 * Authorisation reuses the rule-version policy rather than introducing a second one — a year and
 * the rule versions inside it are the same responsibility, and splitting them would let the two
 * drift apart.
 */
class AdminTaxYearController extends Controller
{
    public function __construct(private TaxYearService $years) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', TaxRuleVersion::class);

        return AdminTaxYearResource::collection(
            TaxYear::withCount(['forms', 'ruleVersions', 'taxReturns'])
                ->with('ruleVersions:id,tax_year_id,version,status')
                ->orderByDesc('year')->get()
        )->additional(['success' => true, 'message' => null]);
    }

    public function store(WriteTaxYearRequest $request): JsonResponse
    {
        $this->authorize('create', TaxRuleVersion::class);
        $year = $this->years->create($request->user(), $request->validated());

        return (new AdminTaxYearResource($this->withCounts($year)))->response()->setStatusCode(201);
    }

    public function update(WriteTaxYearRequest $request, TaxYear $taxYear): AdminTaxYearResource
    {
        $this->authorize('create', TaxRuleVersion::class);

        return new AdminTaxYearResource($this->withCounts(
            $this->years->update($request->user(), $taxYear, $request->validated())
        ));
    }

    /** Retires a year. Nothing is deleted; see TaxYearService::deactivate(). */
    public function destroy(Request $request, TaxYear $taxYear): AdminTaxYearResource
    {
        $this->authorize('create', TaxRuleVersion::class);

        return new AdminTaxYearResource($this->withCounts(
            $this->years->deactivate($request->user(), $taxYear)
        ));
    }

    private function withCounts(TaxYear $year): TaxYear
    {
        return $year->loadCount(['forms', 'ruleVersions', 'taxReturns'])
            ->load('ruleVersions:id,tax_year_id,version,status');
    }
}

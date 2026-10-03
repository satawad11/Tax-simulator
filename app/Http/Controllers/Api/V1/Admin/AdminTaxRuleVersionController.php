<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\CloneTaxRuleVersionRequest;
use App\Http\Requests\Api\V1\Admin\WriteTaxRuleVersionRequest;
use App\Http\Resources\Api\V1\Admin\TaxRuleVersionResource;
use App\Models\TaxRuleVersion;
use App\Services\Admin\TaxRuleVersionCloneService;
use App\Services\Admin\TaxRuleVersionPublishingService;
use App\Services\Admin\TaxRuleVersionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Tax rule version administration.
 *
 * Milestone 08. The whole lifecycle is here: create a draft, clone an existing version into a
 * draft, edit it, validate it, publish it, archive it. A published version is never edited —
 * the models refuse it and `TaxRuleVersionPolicy::update()` refuses it before they are reached.
 */
class AdminTaxRuleVersionController extends Controller
{
    public function __construct(
        private TaxRuleVersionService $versions,
        private TaxRuleVersionCloneService $cloner,
        private TaxRuleVersionPublishingService $publishing,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', TaxRuleVersion::class);
        $query = TaxRuleVersion::with('taxYear');

        if (in_array($request->query('status'), ['draft', 'published', 'archived'], true)) {
            $query->where('status', $request->query('status'));
        }
        if ($year = $request->query('tax_year')) {
            $query->whereHas('taxYear', fn ($inner) => $inner->where('year', (int) $year));
        }

        return TaxRuleVersionResource::collection($query->orderByDesc('id')->get())
            ->additional(['success' => true, 'message' => null]);
    }

    public function store(WriteTaxRuleVersionRequest $request): JsonResponse
    {
        $this->authorize('create', TaxRuleVersion::class);
        $draft = $this->versions->create($request->user(), (int) $request->validated('tax_year'),
            $request->validated('version'), $request->validated('description'));

        return (new TaxRuleVersionResource($draft->load('taxYear')))->response()->setStatusCode(201);
    }

    public function show(TaxRuleVersion $taxRuleVersion): TaxRuleVersionResource
    {
        $this->authorize('view', $taxRuleVersion);

        return (new TaxRuleVersionResource($taxRuleVersion->load('taxYear')))
            ->additional(['summary' => $this->versions->summary($taxRuleVersion)]);
    }

    public function update(WriteTaxRuleVersionRequest $request, TaxRuleVersion $taxRuleVersion): TaxRuleVersionResource
    {
        $this->authorize('update', $taxRuleVersion);

        return new TaxRuleVersionResource(
            $this->versions->update($request->user(), $taxRuleVersion, $request->validated())->load('taxYear')
        );
    }

    public function clone(CloneTaxRuleVersionRequest $request, TaxRuleVersion $taxRuleVersion): JsonResponse
    {
        // Cloning *reads* a published version and writes a new draft, so it is authorized as a
        // create rather than as an edit of the source.
        $this->authorize('create', TaxRuleVersion::class);
        $draft = $this->cloner->clone($request->user(), $taxRuleVersion,
            $request->validated('version'), $request->validated('description'),
            $request->validated('tax_year'));

        return (new TaxRuleVersionResource($draft->load('taxYear')))
            ->additional(['summary' => $this->versions->summary($draft)])
            ->response()->setStatusCode(201);
    }

    public function validateVersion(Request $request, TaxRuleVersion $taxRuleVersion): JsonResponse
    {
        $this->authorize('view', $taxRuleVersion);

        return response()->json(['success' => true, 'message' => null,
            'data' => $this->publishing->validate($request->user(), $taxRuleVersion)]);
    }

    public function publish(Request $request, TaxRuleVersion $taxRuleVersion): TaxRuleVersionResource
    {
        $this->authorize('publish', $taxRuleVersion);
        $result = $this->publishing->publish($request->user(), $taxRuleVersion);

        return (new TaxRuleVersionResource($result['version']->load('taxYear')))
            ->additional(['summary' => $this->versions->summary($result['version'])]);
    }

    public function archive(Request $request, TaxRuleVersion $taxRuleVersion): TaxRuleVersionResource
    {
        $this->authorize('archive', $taxRuleVersion);

        return new TaxRuleVersionResource(
            $this->versions->archive($request->user(), $taxRuleVersion)->load('taxYear')
        );
    }
}

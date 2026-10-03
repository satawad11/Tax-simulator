<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\WriteTaxSourceRequest;
use App\Http\Resources\Api\V1\Admin\TaxSourceResource;
use App\Models\TaxSource;
use App\Services\Admin\TaxSourceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Administration of the approved-source registry.
 *
 * Milestone 08. A source cited by a **published** rule cannot be destroyed: evidence for a rule
 * that is in force must remain readable, or the rule stops being auditable.
 */
class AdminTaxSourceController extends Controller
{
    public function __construct(private TaxSourceService $sources) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', TaxSource::class);
        $query = TaxSource::with('taxYear')->withCount('ruleSources')
            ->withExists(['ruleSources as cited_by_published_rule' => fn ($ruleSources) => $ruleSources
                ->whereHas('ruleVersion', fn ($ruleVersion) => $ruleVersion->where('status', 'published'))]);

        if ($request->query('active') !== null) {
            $query->where('active', filter_var($request->query('active'), FILTER_VALIDATE_BOOLEAN));
        }
        if (in_array($request->query('source_type'), TaxSource::TYPES, true)) {
            $query->where('source_type', $request->query('source_type'));
        }

        return TaxSourceResource::collection($query->orderBy('code')->get())
            ->additional(['success' => true, 'message' => null]);
    }

    public function store(WriteTaxSourceRequest $request): JsonResponse
    {
        $this->authorize('create', TaxSource::class);

        return (new TaxSourceResource($this->sources->create($request->user(), $request->validated())))
            ->response()->setStatusCode(201);
    }

    public function show(TaxSource $taxSource): TaxSourceResource
    {
        $this->authorize('view', $taxSource);

        return new TaxSourceResource($taxSource->load('taxYear')->loadCount('ruleSources'));
    }

    public function update(WriteTaxSourceRequest $request, TaxSource $taxSource): TaxSourceResource
    {
        $this->authorize('update', $taxSource);

        return new TaxSourceResource($this->sources->update($request->user(), $taxSource, $request->validated()));
    }

    public function destroy(Request $request, TaxSource $taxSource): TaxSourceResource
    {
        $this->authorize('delete', $taxSource);

        return new TaxSourceResource($this->sources->delete($request->user(), $taxSource));
    }
}

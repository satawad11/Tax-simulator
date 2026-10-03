<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\WriteDraftRuleRequest;
use App\Models\AllowanceType;
use App\Models\IncomeType;
use App\Models\TaxRuleVersion;
use App\Services\Admin\DraftRuleRegistry;
use App\Services\Admin\DraftRuleService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Editing the rule entities of a draft version.
 *
 * Milestone 08. One controller across the six rule entities, because each differs only in its
 * field list — which lives in DraftRuleRegistry, where the six declarations sit side by side
 * and an over-permissive field is visible at a glance.
 *
 * Every write refuses a non-draft version twice over: `TaxRuleVersionPolicy::update()` answers
 * 403 before the service is reached, and the models throw if anything ever gets past that.
 */
class AdminDraftRuleController extends Controller
{
    public function __construct(private DraftRuleService $rules) {}

    public function index(TaxRuleVersion $taxRuleVersion, string $resource): JsonResponse
    {
        $this->authorize('view', $taxRuleVersion);
        $this->assertResource($resource);

        return response()->json(['success' => true, 'message' => null, 'data' => [
            'resource' => $resource,
            'version' => $taxRuleVersion->version,
            'editable' => $taxRuleVersion->status === 'draft',
            'items' => $this->rules->list($taxRuleVersion, $resource),
        ]]);
    }

    public function store(WriteDraftRuleRequest $request, TaxRuleVersion $taxRuleVersion, string $resource): JsonResponse
    {
        $this->authorize('update', $taxRuleVersion);
        $this->assertResource($resource);
        $record = $this->rules->create($request->user(), $taxRuleVersion, $resource, $request->validated());

        return response()->json(['success' => true, 'message' => null, 'data' => $record], 201);
    }

    public function update(WriteDraftRuleRequest $request, TaxRuleVersion $taxRuleVersion, string $resource, int $ruleId): JsonResponse
    {
        $this->authorize('update', $taxRuleVersion);
        $this->assertResource($resource);
        $record = $this->find($resource, $ruleId);
        $updated = $this->rules->update($request->user(), $taxRuleVersion, $resource, $record, $request->validated());

        return response()->json(['success' => true, 'message' => null, 'data' => $updated]);
    }

    public function destroy(Request $request, TaxRuleVersion $taxRuleVersion, string $resource, int $ruleId): Response
    {
        $this->authorize('update', $taxRuleVersion);
        $this->assertResource($resource);
        $this->rules->delete($request->user(), $taxRuleVersion, $resource, $this->find($resource, $ruleId));

        return response()->noContent();
    }

    /**
     * The rows a rule may point at, with their primary keys.
     *
     * Milestone 09.1 — two rule entities are keyed by an `income_type_id` / `allowance_type_id`,
     * and the public metadata deliberately omits primary keys (a reader addresses content by
     * code). The console therefore had no way to offer a picker and would have had to ask an
     * administrator to type a database id. This is a read-only list for that picker: it exposes
     * nothing a signed-in administrator cannot already read, and it carries no rule values.
     */
    public function references(): JsonResponse
    {
        $this->authorize('viewAny', TaxRuleVersion::class);

        return response()->json(['success' => true, 'message' => null, 'data' => [
            'income_types' => IncomeType::orderBy('code')->get(['id', 'code', 'name'])->all(),
            'allowance_types' => AllowanceType::orderBy('code')->get(['id', 'code', 'name'])->all(),
        ]]);
    }

    private function assertResource(string $resource): void
    {
        abort_unless(DraftRuleRegistry::knows($resource), 404);
    }

    private function find(string $resource, int $id): Model
    {
        $model = DraftRuleRegistry::model($resource);

        return $model::findOrFail($id);
    }
}

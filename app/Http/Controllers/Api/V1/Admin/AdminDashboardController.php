<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\AdminAuditLogResource;
use App\Models\AdminAuditLog;
use App\Models\ContentPost;
use App\Models\TaxRuleVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

/**
 * The admin overview and the audit trail.
 *
 * Milestone 08. Counts and recent activity, not an analytics platform.
 */
class AdminDashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $this->authorize('viewAny', ContentPost::class);

        return response()->json(['success' => true, 'message' => null, 'data' => [
            'content' => [
                'published' => ContentPost::where('status', 'published')->count(),
                'draft' => ContentPost::where('status', 'draft')->count(),
                'archived' => ContentPost::where('status', 'archived')->count(),
            ],
            'rule_versions' => [
                'published' => TaxRuleVersion::with('taxYear')->where('status', 'published')
                    ->get()->map(fn (TaxRuleVersion $version): array => [
                        'version' => $version->version, 'tax_year' => $version->taxYear?->year,
                        'published_at' => $version->published_at?->toIso8601String(),
                    ])->values()->all(),
                'draft_count' => TaxRuleVersion::where('status', 'draft')->count(),
                'archived_count' => TaxRuleVersion::where('status', 'archived')->count(),
            ],
            'recent_activity' => AdminAuditLogResource::collection(
                AdminAuditLog::with('actor')->orderByDesc('id')->limit(10)->get()
            )->resolve(),
        ]]);
    }

    public function auditLogs(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ContentPost::class);
        $query = AdminAuditLog::with('actor');

        if ($entity = $request->query('entity_type')) {
            $query->where('entity_type', $entity);
        }
        if ($action = $request->query('action')) {
            $query->where('action', $action);
        }

        /*
         * The two filters an audit trail actually needs.
         *
         * Action and entity answer "what kind of thing happened". The questions this record exists
         * for are "what did this administrator change?" and "what happened around the time that
         * rule was published?" — neither of which could be asked. Newest-first pagination made
         * *recent* activity findable and everything older effectively unreachable.
         */
        if ($actor = $request->integer('actor_user_id')) {
            $query->where('actor_user_id', $actor);
        }
        foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
            $value = trim((string) $request->query($key));
            if ($value === '') {
                continue;
            }
            try {
                // `to` covers the whole day the operator named, not the midnight that starts it.
                $boundary = $key === 'from'
                    ? Carbon::parse($value)->startOfDay()
                    : Carbon::parse($value)->endOfDay();
            } catch (\Throwable) {
                // An unparseable date narrows nothing rather than emptying the page without saying why.
                continue;
            }
            $query->where('created_at', $operator, $boundary);
        }

        return AdminAuditLogResource::collection(
            $query->orderByDesc('id')->paginate(min((int) $request->query('per_page', 25), 100))->withQueryString()
        )->additional(['success' => true, 'message' => null]);
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\WriteContentTaxonomyRequest;
use App\Http\Resources\Api\V1\Admin\AdminContentTaxonomyResource;
use App\Models\ContentCategory;
use App\Models\ContentPost;
use App\Models\ContentTag;
use App\Services\Admin\AdminAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Category and tag administration.
 *
 * Milestone 08. A taxonomy row still in use is deactivated rather than deleted, so published
 * content never loses the label it was filed under.
 */
class AdminContentTaxonomyController extends Controller
{
    public function __construct(private AdminAuditService $audit) {}

    public function categories(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ContentPost::class);

        return AdminContentTaxonomyResource::collection(
            ContentCategory::withCount('posts')->orderBy('sort_order')->orderBy('name')->get()
        )->additional(['success' => true, 'message' => null]);
    }

    public function storeCategory(WriteContentTaxonomyRequest $request): JsonResponse
    {
        $this->authorize('create', ContentPost::class);
        $category = ContentCategory::create($request->validated());
        $this->record($request, 'content_category', $category->id, 'Created category '.$category->slug);

        return (new AdminContentTaxonomyResource($category))->response()->setStatusCode(201);
    }

    public function updateCategory(WriteContentTaxonomyRequest $request, ContentCategory $category): AdminContentTaxonomyResource
    {
        $this->authorize('create', ContentPost::class);
        $category->fill($request->validated())->save();
        $this->record($request, 'content_category', $category->id, 'Updated category '.$category->slug);

        return new AdminContentTaxonomyResource($category->fresh());
    }

    public function destroyCategory(Request $request, ContentCategory $category): Response
    {
        $this->authorize('create', ContentPost::class);
        if ($category->posts()->exists()) {
            // Content already filed here keeps its category; the label is retired instead.
            $category->active = false;
            $category->save();
            $this->record($request, 'content_category', $category->id, 'Deactivated category '.$category->slug.' (still in use)');

            return response()->noContent();
        }
        $slug = $category->slug;
        $category->delete();
        $this->record($request, 'content_category', null, 'Deleted unused category '.$slug);

        return response()->noContent();
    }

    public function tags(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ContentPost::class);

        return AdminContentTaxonomyResource::collection(ContentTag::withCount('posts')->orderBy('name')->get())
            ->additional(['success' => true, 'message' => null]);
    }

    public function storeTag(WriteContentTaxonomyRequest $request): JsonResponse
    {
        $this->authorize('create', ContentPost::class);
        $tag = ContentTag::create($request->validated());
        $this->record($request, 'content_tag', $tag->id, 'Created tag '.$tag->slug);

        return (new AdminContentTaxonomyResource($tag))->response()->setStatusCode(201);
    }

    public function updateTag(WriteContentTaxonomyRequest $request, ContentTag $tag): AdminContentTaxonomyResource
    {
        $this->authorize('create', ContentPost::class);
        $tag->fill($request->validated())->save();
        $this->record($request, 'content_tag', $tag->id, 'Updated tag '.$tag->slug);

        return new AdminContentTaxonomyResource($tag->fresh());
    }

    public function destroyTag(Request $request, ContentTag $tag): Response
    {
        $this->authorize('create', ContentPost::class);
        if ($tag->posts()->exists()) {
            $tag->active = false;
            $tag->save();
            $this->record($request, 'content_tag', $tag->id, 'Deactivated tag '.$tag->slug.' (still in use)');

            return response()->noContent();
        }
        $slug = $tag->slug;
        $tag->delete();
        $this->record($request, 'content_tag', null, 'Deleted unused tag '.$slug);

        return response()->noContent();
    }

    private function record(Request $request, string $entityType, ?int $id, string $summary): void
    {
        $this->audit->record($request->user(), AdminAuditService::CONTENT_UPDATED, $entityType, $id, $summary);
    }
}

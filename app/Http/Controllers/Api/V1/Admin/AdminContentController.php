<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\PublishContentRequest;
use App\Http\Requests\Api\V1\Admin\WriteContentRequest;
use App\Http\Resources\Api\V1\Admin\AdminContentResource;
use App\Models\ContentPost;
use App\Services\Content\ContentPublishingService;
use App\Services\Content\ContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

/**
 * Admin CMS.
 *
 * Milestone 08. Every route is behind `auth:sanctum` + the admin middleware, and every action
 * is additionally authorized through ContentPolicy so a route added later without the
 * middleware still cannot be reached by a member.
 *
 * Status transitions are their own endpoints on purpose: a generic PATCH can never publish.
 */
class AdminContentController extends Controller
{
    public function __construct(
        private ContentService $content,
        private ContentPublishingService $publishing,
    ) {}

    /**
     * Mints a short-lived signed link to preview this post as a reader would see it.
     *
     * The console cannot simply link to a preview page: it authenticates with a bearer token from
     * session storage, and a plain `<a>` carries no headers. So the authorisation is moved into
     * the URL — minted here, where the admin middleware and `ContentPolicy` have both already
     * run, and verified there by Laravel's `signed` middleware.
     *
     * **Thirty minutes, and the response says so.** A signed URL is a bearer credential in a
     * string: anyone holding it sees the draft until it expires, which is useful — an editor
     * pastes it to a colleague for a second opinion — and is exactly why it must be short. Long
     * enough to review a draft, short enough that a link left in a chat log stops working.
     */
    public function previewUrl(ContentPost $content): JsonResponse
    {
        $this->authorize('view', $content);
        $expiresAt = now()->addMinutes(30);

        return response()->json(['success' => true, 'message' => null, 'data' => [
            'url' => URL::temporarySignedRoute('content.preview', $expiresAt, ['content' => $content->id]),
            'expires_at' => $expiresAt->toIso8601String(),
            'expires_in_minutes' => 30,
        ]]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ContentPost::class);
        $query = ContentPost::with(['category', 'tags', 'taxYear', 'author']);

        if (in_array($request->query('status'), ContentPost::STATUSES, true)) {
            $query->where('status', $request->query('status'));
        }
        if (in_array(strtolower((string) $request->query('type')), ContentPost::TYPES, true)) {
            $query->where('type', strtolower((string) $request->query('type')));
        }
        // A title/slug contains-search, matched server-side so a filtered listing paginates the
        // matches rather than the browser hiding rows of a single fetched page.
        if (($search = trim((string) $request->query('search'))) !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        return AdminContentResource::collection(
            $query->orderByDesc('updated_at')->paginate(min((int) $request->query('per_page', 20), 100))->withQueryString()
        )->additional(['success' => true, 'message' => null]);
    }

    public function store(WriteContentRequest $request): JsonResponse
    {
        $this->authorize('create', ContentPost::class);
        $post = $this->content->create($request->user(), $request->validated());

        return (new AdminContentResource($post))->response()->setStatusCode(201);
    }

    public function show(ContentPost $content): AdminContentResource
    {
        $this->authorize('view', $content);

        return new AdminContentResource($content->load(['category', 'tags', 'taxYear', 'author']));
    }

    public function update(WriteContentRequest $request, ContentPost $content): AdminContentResource
    {
        $this->authorize('update', $content);

        return new AdminContentResource($this->content->update($request->user(), $content, $request->validated()));
    }

    public function destroy(Request $request, ContentPost $content): Response
    {
        $this->authorize('delete', $content);
        $this->content->delete($request->user(), $content);

        return response()->noContent();
    }

    public function publish(PublishContentRequest $request, ContentPost $content): AdminContentResource
    {
        $this->authorize('publish', $content);

        return new AdminContentResource(
            $this->publishing->publish($request->user(), $content, $request->validated('published_at'))
        );
    }

    public function unpublish(Request $request, ContentPost $content): AdminContentResource
    {
        $this->authorize('publish', $content);

        return new AdminContentResource($this->publishing->unpublish($request->user(), $content));
    }

    public function archive(Request $request, ContentPost $content): AdminContentResource
    {
        $this->authorize('publish', $content);

        return new AdminContentResource($this->publishing->archive($request->user(), $content));
    }
}

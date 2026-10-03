<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ContentIndexRequest;
use App\Http\Resources\Api\V1\ContentCategoryResource;
use App\Http\Resources\Api\V1\ContentDetailResource;
use App\Http\Resources\Api\V1\ContentSummaryResource;
use App\Http\Resources\Api\V1\ContentTagResource;
use App\Models\ContentCategory;
use App\Models\ContentTag;
use App\Services\Content\ContentService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The public knowledge / news / guide / FAQ API.
 *
 * Milestone 08. No authentication, and no way to reach anything unpublished: every query here
 * starts from `ContentService::publicQuery()`, which is the single definition of public
 * visibility — published, with a `published_at` that has already passed.
 *
 * The same endpoints serve the web pages and any future mobile client; there is no second
 * content API.
 */
class ContentController extends Controller
{
    /** Kept small so a listing cannot be turned into a bulk export. */
    private const DEFAULT_PER_PAGE = 12;

    private const MAX_PER_PAGE = 100;

    public function __construct(private ContentService $content) {}

    public function index(ContentIndexRequest $request): AnonymousResourceCollection
    {
        $query = $this->content->publicQuery();

        if ($type = $request->validated('type')) {
            $query->where('type', strtolower($type));
        }
        if ($category = $request->validated('category')) {
            $query->whereHas('category', fn ($inner) => $inner->where('slug', $category));
        }
        if ($tag = $request->validated('tag')) {
            $query->whereHas('tags', fn ($inner) => $inner->where('slug', $tag)->where('active', true));
        }
        if ($year = $request->validated('tax_year')) {
            $query->whereHas('taxYear', fn ($inner) => $inner->where('year', $year));
        }
        if ($term = $request->validated('q')) {
            // Only the two fields a reader sees in a listing. M8 builds no search infrastructure.
            $escaped = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($inner) => $inner->where('title', 'like', $escaped)->orWhere('excerpt', 'like', $escaped));
        }
        $perPage = min((int) ($request->validated('per_page') ?? self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE);

        return ContentSummaryResource::collection(
            $query->orderByDesc('published_at')->orderByDesc('id')->paginate($perPage)->withQueryString()
        )->additional(['success' => true, 'message' => null]);
    }

    public function show(string $slug): ContentDetailResource
    {
        return new ContentDetailResource(
            $this->content->publicQuery()->where('slug', $slug)->firstOrFail()
        );
    }

    public function categories(): AnonymousResourceCollection
    {
        $categories = ContentCategory::where('active', true)
            ->withCount(['posts as published_posts_count' => fn ($query) => $query->publiclyVisible()])
            ->orderBy('sort_order')->orderBy('name')->get();

        return ContentCategoryResource::collection($categories)->additional(['success' => true, 'message' => null]);
    }

    public function category(string $slug): ContentCategoryResource
    {
        return new ContentCategoryResource(
            ContentCategory::where('active', true)->where('slug', $slug)->firstOrFail()
        );
    }

    public function tags(): AnonymousResourceCollection
    {
        return ContentTagResource::collection(
            ContentTag::where('active', true)->orderBy('name')->get()
        )->additional(['success' => true, 'message' => null]);
    }

    public function tag(string $slug): ContentTagResource
    {
        return new ContentTagResource(ContentTag::where('active', true)->where('slug', $slug)->firstOrFail());
    }

    /** A short strip of editor-chosen items. No personalization in M8. */
    public function featured(): AnonymousResourceCollection
    {
        $featured = $this->content->publicQuery()->where('featured', true)
            ->orderBy('sort_order')->orderByDesc('published_at')->limit(6)->get();

        return ContentSummaryResource::collection($featured)->additional(['success' => true, 'message' => null]);
    }

    public function faqs(ContentIndexRequest $request): AnonymousResourceCollection
    {
        $query = $this->content->publicQuery()->where('type', 'faq');

        if ($category = $request->validated('category')) {
            $query->whereHas('category', fn ($inner) => $inner->where('slug', $category));
        }
        if ($year = $request->validated('tax_year')) {
            $query->whereHas('taxYear', fn ($inner) => $inner->where('year', $year));
        }
        // An FAQ list is ordered by the editor, not by date.
        $faqs = $query->orderBy('sort_order')->orderBy('id')->limit(self::MAX_PER_PAGE)->get();

        return ContentDetailResource::collection($faqs)->additional(['success' => true, 'message' => null]);
    }
}

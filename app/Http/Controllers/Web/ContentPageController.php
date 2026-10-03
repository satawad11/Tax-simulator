<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ContentCategory;
use App\Models\ContentTag;
use App\Services\Content\ContentBodyFormat;
use App\Services\Content\ContentService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * The public knowledge, news, article and FAQ pages.
 *
 * Milestone 08. These read through the same `ContentService::publicQuery()` the API uses, so a
 * draft can no more appear on a page than in a JSON response. Bodies are rendered from
 * structured text through an escaping view component; no user string is ever emitted as markup.
 */
class ContentPageController extends Controller
{
    public function __construct(private ContentService $content) {}

    /**
     * The home page.
     *
     * Milestone 09.1 — the approved mockup puts a "บทความแนะนำ" strip under the hero, so the home
     * page needs content rather than a static view. It reads through the same public query as
     * every other page: a draft cannot surface here either. Featured posts come first and the
     * most recent published ones fill the row when fewer than three are featured, so the section
     * is never half-empty on a fresh environment.
     */
    public function home(): View
    {
        if (! Schema::hasTable('content_posts')) {
            return view('welcome', ['featured' => collect(), 'latestNews' => collect()]);
        }

        $featured = $this->content->publicQuery()->whereIn('type', ['article', 'guide'])
            ->where('featured', true)->orderByDesc('published_at')->limit(3)->get();

        if ($featured->count() < 3) {
            $featured = $featured->concat(
                $this->content->publicQuery()->whereIn('type', ['article', 'guide'])
                    ->whereNotIn('id', $featured->pluck('id')->all())
                    ->orderByDesc('published_at')->limit(3 - $featured->count())->get()
            );
        }

        return view('welcome', [
            'featured' => $featured,
            'latestNews' => $this->content->publicQuery()->where('type', 'news')
                ->orderByDesc('published_at')->limit(2)->get(),
        ]);
    }

    public function knowledge(Request $request): View
    {
        $query = $this->content->publicQuery()->whereIn('type', ['article', 'guide']);
        $this->applyFilters($query, $request);

        return view('content.index', [
            'heading' => 'ความรู้ภาษี',
            'intro' => 'บทความและคู่มือสำหรับทำความเข้าใจภาษีเงินได้บุคคลธรรมดา ก่อนเริ่มทดลองคำนวณ',
            'posts' => $query->orderByDesc('published_at')->paginate(12)->withQueryString(),
            'categories' => ContentCategory::where('active', true)->orderBy('name')->get(),
            'tags' => ContentTag::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function news(Request $request): View
    {
        $query = $this->content->publicQuery()->where('type', 'news');
        $this->applyFilters($query, $request);

        return view('content.index', [
            'heading' => 'ข่าวสาร',
            'intro' => 'ความเคลื่อนไหวและประกาศที่เกี่ยวข้องกับภาษีเงินได้บุคคลธรรมดา',
            'posts' => $query->orderByDesc('published_at')->paginate(12)->withQueryString(),
            'categories' => ContentCategory::where('active', true)->orderBy('name')->get(),
            'tags' => ContentTag::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function article(string $slug): View
    {
        $post = $this->content->publicQuery()->where('slug', $slug)->firstOrFail();

        return view('content.show', ['post' => $post, 'blocks' => ContentBodyFormat::blocks((string) $post->content)]);
    }

    public function faq(): View
    {
        $faqs = $this->content->publicQuery()->where('type', 'faq')
            ->orderBy('sort_order')->orderBy('id')->limit(100)->get();
        // Each answer is pre-parsed here rather than in the view, so the template stays a template.
        $faqs->each(fn ($faq) => $faq->blocks = ContentBodyFormat::blocks((string) $faq->content));

        return view('content.faq', ['faqs' => $faqs]);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        if ($category = $request->string('category')->trim()->value()) {
            $query->whereHas('category', fn ($inner) => $inner->where('slug', $category));
        }
        if ($tag = $request->string('tag')->trim()->value()) {
            $query->whereHas('tags', fn ($inner) => $inner->where('slug', $tag)->where('active', true));
        }
        if ($year = $request->integer('tax_year')) {
            $query->whereHas('taxYear', fn ($inner) => $inner->where('year', $year));
        }
        if ($term = $request->string('q')->trim()->value()) {
            $escaped = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($inner) => $inner->where('title', 'like', $escaped)->orWhere('excerpt', 'like', $escaped));
        }
    }
}

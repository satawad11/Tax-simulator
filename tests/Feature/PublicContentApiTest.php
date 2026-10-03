<?php

namespace Tests\Feature;

use App\Models\ContentCategory;
use App\Models\ContentPost;
use App\Models\ContentTag;
use App\Models\TaxYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Milestone 08 — the public knowledge / news / guide / FAQ API.
 *
 * The question every case here is really asking is the same one: can anything that is not
 * published be reached from outside? Nothing may be, by any filter, any slug, or any page.
 */
class PublicContentApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function article(array $attributes = []): ContentPost
    {
        $post = ContentPost::create([
            'type' => 'article', 'title' => 'บทความทดสอบ', 'slug' => 'test-'.uniqid(),
            'excerpt' => 'สรุปย่อ', 'content' => 'เนื้อหาทดสอบ',
            ...array_diff_key($attributes, array_flip(['status', 'published_at'])),
        ]);
        $post->status = $attributes['status'] ?? 'published';
        $post->published_at = array_key_exists('published_at', $attributes) ? $attributes['published_at'] : now()->subDay();
        $post->save();

        return $post->fresh();
    }

    public function test_a_listing_returns_only_published_content(): void
    {
        $this->article(['title' => 'เผยแพร่แล้ว', 'slug' => 'visible']);
        $this->article(['title' => 'ฉบับร่าง', 'slug' => 'hidden-draft', 'status' => 'draft']);
        $this->article(['title' => 'จัดเก็บ', 'slug' => 'hidden-archived', 'status' => 'archived']);
        // Published, but not yet: an explicit future date behaves as a schedule.
        $this->article(['title' => 'ตั้งเวลา', 'slug' => 'hidden-scheduled', 'published_at' => now()->addWeek()]);
        // Published with no date at all cannot be shown either.
        $this->article(['title' => 'ไร้วันที่', 'slug' => 'hidden-undated', 'published_at' => null]);

        $slugs = array_column($this->getJson('/api/v1/content/articles')->assertOk()->json('data'), 'slug');

        $this->assertSame(['visible'], $slugs);
    }

    /** @return array<string, array{string}> */
    public static function hiddenSlugs(): array
    {
        return ['draft' => ['hidden-draft'], 'archived' => ['hidden-archived'], 'scheduled' => ['hidden-scheduled']];
    }

    #[DataProvider('hiddenSlugs')]
    public function test_unpublished_content_is_not_reachable_by_slug(string $slug): void
    {
        $this->article(['slug' => 'hidden-draft', 'status' => 'draft']);
        $this->article(['slug' => 'hidden-archived', 'status' => 'archived']);
        $this->article(['slug' => 'hidden-scheduled', 'published_at' => now()->addWeek()]);

        $this->getJson('/api/v1/content/articles/'.$slug)->assertNotFound()
            ->assertJsonPath('message', 'Resource not found');
    }

    public function test_article_detail_exposes_public_fields_and_no_administrative_ones(): void
    {
        $category = ContentCategory::create(['name' => 'ความรู้ภาษี', 'slug' => 'tax-knowledge']);
        $tag = ContentTag::create(['name' => 'ภ.ง.ด.91', 'slug' => 'pnd91']);
        $year = TaxYear::where('year', 2568)->firstOrFail();
        $author = User::factory()->create();
        $post = $this->article(['slug' => 'prepare-pnd91', 'title' => 'เตรียมข้อมูลก่อนทดลอง ภ.ง.ด.91',
            'type' => 'guide', 'content_category_id' => $category->id, 'tax_year_id' => $year->id,
            'meta_title' => 'คู่มือ', 'meta_description' => 'คำอธิบาย']);
        $post->author_id = $author->id;
        $post->save();
        $post->tags()->sync([$tag->id]);

        $data = $this->getJson('/api/v1/content/articles/prepare-pnd91')->assertOk()
            ->assertJsonPath('data.type', 'GUIDE')
            ->assertJsonPath('data.category.slug', 'tax-knowledge')
            ->assertJsonPath('data.tags.0.slug', 'pnd91')
            ->assertJsonPath('data.tax_year', 2568)
            ->assertJsonPath('data.body_format', 'STRUCTURED_TEXT')
            ->json('data');

        foreach (['id', 'status', 'author', 'author_id', 'published_by', 'first_published_at', 'sort_order'] as $hidden) {
            $this->assertArrayNotHasKey($hidden, $data, $hidden.' must not be public');
        }
    }

    public function test_every_filter_narrows_the_listing(): void
    {
        $knowledge = ContentCategory::create(['name' => 'ความรู้', 'slug' => 'knowledge']);
        $other = ContentCategory::create(['name' => 'อื่น', 'slug' => 'other']);
        $tag = ContentTag::create(['name' => 'หักลดหย่อน', 'slug' => 'allowance']);
        $year = TaxYear::where('year', 2568)->firstOrFail();

        $match = $this->article(['slug' => 'match', 'title' => 'การหักลดหย่อนบุตร', 'type' => 'guide',
            'content_category_id' => $knowledge->id, 'tax_year_id' => $year->id]);
        $match->tags()->sync([$tag->id]);
        $this->article(['slug' => 'other-type', 'type' => 'news', 'content_category_id' => $other->id]);

        foreach (['type=GUIDE', 'category=knowledge', 'tag=allowance', 'tax_year=2568', 'q=ลดหย่อน'] as $filter) {
            $slugs = array_column($this->getJson('/api/v1/content/articles?'.$filter)->assertOk()->json('data'), 'slug');
            $this->assertSame(['match'], $slugs, $filter);
        }
        $this->getJson('/api/v1/content/articles?unknown=1')->assertUnprocessable();
    }

    public function test_a_search_term_cannot_smuggle_a_wildcard(): void
    {
        $this->article(['slug' => 'literal', 'title' => 'หัวข้อปกติ']);

        // A bare % would match everything if it reached LIKE unescaped.
        $this->getJson('/api/v1/content/articles?q=%25')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_the_listing_paginates_newest_first(): void
    {
        foreach (range(1, 5) as $index) {
            $this->article(['slug' => 'post-'.$index, 'published_at' => now()->subDays(10 - $index)]);
        }

        $first = $this->getJson('/api/v1/content/articles?per_page=2')->assertOk()
            ->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 5);
        $this->assertSame(['post-5', 'post-4'], array_column($first->json('data'), 'slug'));

        $second = $this->getJson('/api/v1/content/articles?per_page=2&page=2')->assertOk();
        $this->assertSame(['post-3', 'post-2'], array_column($second->json('data'), 'slug'));
    }

    public function test_featured_returns_at_most_six_published_items(): void
    {
        foreach (range(1, 8) as $index) {
            $this->article(['slug' => 'featured-'.$index, 'featured' => true, 'sort_order' => $index]);
        }
        $this->article(['slug' => 'featured-draft', 'featured' => true, 'status' => 'draft']);
        $this->article(['slug' => 'not-featured']);

        $slugs = array_column($this->getJson('/api/v1/content/featured')->assertOk()->json('data'), 'slug');

        $this->assertCount(6, $slugs);
        $this->assertNotContains('featured-draft', $slugs);
        $this->assertNotContains('not-featured', $slugs);
    }

    public function test_faqs_return_published_faqs_in_editor_order(): void
    {
        $category = ContentCategory::create(['name' => 'ทั่วไป', 'slug' => 'general']);
        $this->article(['slug' => 'faq-b', 'type' => 'faq', 'title' => 'คำถาม ข', 'sort_order' => 2,
            'content_category_id' => $category->id]);
        $this->article(['slug' => 'faq-a', 'type' => 'faq', 'title' => 'คำถาม ก', 'sort_order' => 1,
            'content_category_id' => $category->id]);
        $this->article(['slug' => 'faq-draft', 'type' => 'faq', 'status' => 'draft']);
        $this->article(['slug' => 'an-article']);

        $titles = array_column($this->getJson('/api/v1/content/faqs')->assertOk()->json('data'), 'title');
        $this->assertSame(['คำถาม ก', 'คำถาม ข'], $titles);

        $this->getJson('/api/v1/content/faqs?category=general')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_categories_and_tags_expose_only_active_rows(): void
    {
        $active = ContentCategory::create(['name' => 'ใช้งาน', 'slug' => 'active-category']);
        ContentCategory::create(['name' => 'ปิด', 'slug' => 'inactive-category', 'active' => false]);
        ContentTag::create(['name' => 'ใช้งาน', 'slug' => 'active-tag']);
        ContentTag::create(['name' => 'ปิด', 'slug' => 'inactive-tag', 'active' => false]);
        $this->article(['slug' => 'in-category', 'content_category_id' => $active->id]);

        $categories = array_column($this->getJson('/api/v1/content/categories')->assertOk()->json('data'), 'slug');
        $this->assertSame(['active-category'], $categories);

        $tags = array_column($this->getJson('/api/v1/content/tags')->assertOk()->json('data'), 'slug');
        $this->assertSame(['active-tag'], $tags);

        $this->getJson('/api/v1/content/categories/active-category')->assertOk()->assertJsonPath('data.slug', 'active-category');
        $this->getJson('/api/v1/content/categories/inactive-category')->assertNotFound();
        $this->getJson('/api/v1/content/tags/inactive-tag')->assertNotFound();
    }

    public function test_the_public_pages_render_published_content_only(): void
    {
        $this->article(['slug' => 'visible-page', 'title' => 'บทความที่เห็นได้', 'type' => 'guide']);
        $this->article(['slug' => 'draft-page', 'title' => 'บทความฉบับร่าง', 'status' => 'draft']);
        $this->article(['slug' => 'news-page', 'title' => 'ข่าวที่เห็นได้', 'type' => 'news']);
        $this->article(['slug' => 'faq-page', 'title' => 'คำถามที่เห็นได้', 'type' => 'faq']);

        $this->get('/knowledge')->assertOk()->assertSee('บทความที่เห็นได้')->assertDontSee('บทความฉบับร่าง');
        $this->get('/news')->assertOk()->assertSee('ข่าวที่เห็นได้');
        $this->get('/faq')->assertOk()->assertSee('คำถามที่เห็นได้');
        $this->get('/article/visible-page')->assertOk()->assertSee('บทความที่เห็นได้');
        $this->get('/article/draft-page')->assertNotFound();
    }
}

<?php

namespace Tests\Feature;

use App\Models\ContentPost;
use App\Models\TaxYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

/**
 * Phase 3 — the fields the content API has always accepted and no control ever set.
 *
 * Each one drives something a reader can already see. The FAQ page and the FAQ endpoint both
 * order by `sort_order`; the knowledge and news pages both offer a tax-year filter. Without a
 * control an administrator could publish a FAQ they could not order, and a post the year filter
 * could never find.
 *
 * It also covers the round-trip defect underneath them: `AdminContentResource` emits `type`
 * upper-cased while `ContentPost::TYPES` is lower case, so a client that read a post and sent it
 * back was refused for a field it had not touched — which is exactly what the console did on
 * every edit.
 */
class ContentEditingCompletenessTest extends TestCase
{
    use ActsAsAdmin, RefreshDatabase;

    protected $seed = true;

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return ['type' => 'article', 'title' => 'บทความทดสอบ', 'slug' => 'phase-3-test',
            'body' => 'เนื้อหาทดสอบสำหรับเฟสที่สาม', ...$overrides];
    }

    private function create(array $overrides = []): int
    {
        return $this->postJson('/api/v1/admin/content', $this->payload($overrides))
            ->assertCreated()->json('data.id');
    }

    private function publish(int $id): TestResponse
    {
        return $this->postJson("/api/v1/admin/content/$id/publish")->assertOk();
    }

    // ------------------------------------------------------------ round trip

    public function test_a_post_read_from_the_api_can_be_sent_straight_back(): void
    {
        $this->actingAsAdmin();
        $id = $this->create();

        $post = $this->getJson("/api/v1/admin/content/$id")->assertOk()->json('data');
        // The response really does upper-case it — that is the trap, not an assumption.
        $this->assertSame('ARTICLE', $post['type']);

        $this->patchJson("/api/v1/admin/content/$id", ['type' => $post['type'], 'title' => 'แก้ไขแล้ว'])
            ->assertOk()->assertJsonPath('data.title', 'แก้ไขแล้ว');

        $this->assertSame('article', ContentPost::find($id)->type);
    }

    public function test_an_unknown_type_is_still_refused(): void
    {
        // Normalising case must not become accepting anything.
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/content', $this->payload(['type' => 'ANNOUNCEMENT']))
            ->assertStatus(422)->assertJsonValidationErrorFor('type');
    }

    // -------------------------------------------------------------- ordering

    public function test_sort_order_is_stored_and_orders_the_faq_page(): void
    {
        $this->actingAsAdmin();
        $ids = [];
        foreach ([['second', 20], ['first', 10]] as [$slug, $order]) {
            $id = $this->create(['type' => 'faq', 'slug' => "faq-$slug",
                'title' => "คำถาม $slug", 'sort_order' => $order]);
            $this->publish($id);
            $ids[$slug] = $id;
        }

        $this->assertSame(10, ContentPost::find($ids['first'])->sort_order);

        $titles = array_column($this->getJson('/api/v1/content/faqs')->assertOk()->json('data'), 'title');
        $this->assertLessThan(array_search('คำถาม second', $titles, true),
            array_search('คำถาม first', $titles, true),
            'sort_order must decide the order the FAQ endpoint returns.');
    }

    // ------------------------------------------------------------- tax year

    public function test_a_tax_year_can_be_attached_and_the_public_filter_finds_it(): void
    {
        $this->actingAsAdmin();
        $year = TaxYear::query()->firstOrFail();

        $id = $this->create(['tax_year_id' => $year->id]);
        $this->publish($id);

        $this->assertSame($year->id, ContentPost::find($id)->tax_year_id);
        // The filter on /knowledge that nothing could previously populate.
        $this->get("/knowledge?tax_year={$year->year}")->assertOk()->assertSee('บทความทดสอบ');
        $this->get('/knowledge?tax_year=2400')->assertOk()->assertDontSee('บทความทดสอบ');
    }

    // ----------------------------------------------------------- attribution

    public function test_a_source_is_stored_and_shown_on_the_article(): void
    {
        $this->actingAsAdmin();
        $id = $this->create(['source_name' => 'กรมสรรพากร', 'source_url' => 'https://www.rd.go.th/example']);
        $this->publish($id);

        // Both the request *and* ContentService::attributes() have to know the field, or the
        // write is dropped in silence.
        $post = ContentPost::find($id);
        $this->assertSame('กรมสรรพากร', $post->source_name);
        $this->assertSame('https://www.rd.go.th/example', $post->source_url);

        $this->get('/article/phase-3-test')->assertOk()
            ->assertSee('ที่มา:')->assertSee('กรมสรรพากร')
            ->assertSee('https://www.rd.go.th/example', false)
            ->assertSee('rel="noopener noreferrer"', false);
    }

    public function test_the_public_api_carries_the_source_too(): void
    {
        $this->actingAsAdmin();
        $this->publish($this->create(['source_name' => 'กรมสรรพากร']));

        $this->getJson('/api/v1/content/articles/phase-3-test')->assertOk()
            ->assertJsonPath('data.source_name', 'กรมสรรพากร');
    }

    public function test_a_dangerous_source_url_is_refused(): void
    {
        // The value is rendered as an href, so the scheme is what makes it safe.
        $this->actingAsAdmin();

        foreach (['javascript:alert(1)', 'data:text/html,<script>x</script>', 'not a url'] as $hostile) {
            $this->postJson('/api/v1/admin/content', $this->payload(['source_url' => $hostile]))
                ->assertStatus(422)->assertJsonValidationErrorFor('source_url');
        }
    }

    public function test_an_article_without_a_source_shows_no_attribution(): void
    {
        $this->actingAsAdmin();
        $this->publish($this->create());

        $this->get('/article/phase-3-test')->assertOk()->assertDontSee('ที่มา:');
    }

    // ------------------------------------------------------------ cover image

    public function test_cover_image_cannot_be_written(): void
    {
        /*
         * Dropped from `$fillable` rather than half-built: there is no upload anywhere in this
         * codebase, no view renders it, and the request never accepted it, so it could only ever
         * have held a pasted URL. A model that advertises a field the product cannot set or show
         * is a promise it does not keep.
         */
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/content', $this->payload(['cover_image' => 'https://example.com/x.png']))
            ->assertStatus(422);

        $this->assertNotContains('cover_image', (new ContentPost)->getFillable());
    }

    // --------------------------------------------------------------- console

    public function test_the_form_offers_every_field_the_api_accepts(): void
    {
        $this->get('/admin/content/create')->assertOk()
            ->assertSee('name="tax_year_id"', false)
            ->assertSee('name="sort_order"', false)
            ->assertSee('name="source_name"', false)
            ->assertSee('name="source_url"', false)
            // Already present before Phase 3; asserted so a refactor cannot quietly drop it.
            ->assertSee('data-content-tags', false);
    }

    public function test_the_console_lower_cases_the_type_before_selecting_it(): void
    {
        $console = file_get_contents(resource_path('js/admin/console.js'));

        // The blank-select-then-422 defect, locked shut.
        $this->assertStringContainsString("form.elements.type.value = String(item.type ?? '').toLowerCase()", $console);
        $this->assertStringNotContainsString("['type', 'title', 'slug', 'excerpt', 'body'", $console);
    }
}

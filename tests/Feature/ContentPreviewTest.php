<?php

namespace Tests\Feature;

use App\Models\ContentPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

/**
 * Previewing a post before it is published.
 *
 * `ContentService::publicQuery()` excludes drafts and nothing else could render an article, so
 * `/article/{slug}` returned 404 for anything unpublished and **publishing was how an author saw
 * their own work**. On a product whose published content is the part readers are asked to trust,
 * that is the wrong way round.
 *
 * The property that matters most here is the one the fix could easily have broken: the public
 * query still means exactly one thing, and a draft is still invisible everywhere a reader looks.
 */
class ContentPreviewTest extends TestCase
{
    use ActsAsAdmin, RefreshDatabase;

    protected $seed = true;

    private function draft(array $overrides = []): ContentPost
    {
        return ContentPost::factory()->create(['status' => 'draft', 'published_at' => null,
            'title' => 'ร่างบทความทดสอบ', 'slug' => 'draft-under-review', ...$overrides]);
    }

    private function previewUrlFor(ContentPost $post): string
    {
        return $this->getJson("/api/v1/admin/content/{$post->id}/preview-url")->assertOk()->json('data.url');
    }

    // ------------------------------------------------------- minting the link

    public function test_an_administrator_can_mint_a_preview_link(): void
    {
        $this->actingAsAdmin();
        $draft = $this->draft();

        $response = $this->getJson("/api/v1/admin/content/{$draft->id}/preview-url")->assertOk();

        $this->assertStringContainsString("/content-preview/{$draft->id}", $response->json('data.url'));
        $this->assertStringContainsString('signature=', $response->json('data.url'));
        // The caller is told the link expires, because it is a credential in a string and will be
        // pasted to colleagues.
        $this->assertSame(30, $response->json('data.expires_in_minutes'));
        $this->assertNotNull($response->json('data.expires_at'));
    }

    public function test_a_member_cannot_mint_one(): void
    {
        $draft = $this->draft();
        $this->actingAsMember();

        $this->getJson("/api/v1/admin/content/{$draft->id}/preview-url")->assertStatus(403);
    }

    public function test_an_anonymous_caller_cannot_mint_one(): void
    {
        $this->getJson("/api/v1/admin/content/{$this->draft()->id}/preview-url")->assertStatus(401);
    }

    // -------------------------------------------------------- using the link

    public function test_a_signed_link_renders_the_draft(): void
    {
        $draft = $this->draft(['content' => "# หัวข้อทดสอบ\n\nเนื้อหาของฉบับร่าง"]);
        $this->actingAsAdmin();

        $this->get($this->previewUrlFor($draft))->assertOk()
            ->assertSee('ร่างบทความทดสอบ')
            ->assertSee('หัวข้อทดสอบ')
            ->assertSee('เนื้อหาของฉบับร่าง');
    }

    public function test_the_preview_says_what_it_is(): void
    {
        // A preview of a *published* post is pixel-identical to the live page, so without this an
        // administrator could believe an edit was live while it was still a draft.
        $draft = $this->draft();
        $this->actingAsAdmin();

        $this->get($this->previewUrlFor($draft))->assertOk()
            ->assertSee('ตัวอย่างก่อนเผยแพร่')
            ->assertSee('ฉบับร่าง')
            ->assertSee('ยังไม่ปรากฏบนเว็บไซต์จริง');
    }

    public function test_a_preview_is_never_indexed(): void
    {
        $draft = $this->draft();
        $this->actingAsAdmin();

        $this->get($this->previewUrlFor($draft))->assertOk()->assertSee('noindex', false);

        // The live article, by contrast, must stay indexable.
        $published = ContentPost::factory()->create(['status' => 'published', 'published_at' => now(),
            'slug' => 'live-one']);
        $this->get("/article/{$published->slug}")->assertOk()->assertDontSee('noindex', false);
    }

    public function test_an_unsigned_url_is_refused(): void
    {
        $draft = $this->draft();

        $this->get("/content-preview/{$draft->id}")->assertStatus(403);
    }

    public function test_a_tampered_signature_is_refused(): void
    {
        $draft = $this->draft();
        $this->actingAsAdmin();

        $this->get($this->previewUrlFor($draft).'x')->assertStatus(403);
    }

    public function test_the_link_expires(): void
    {
        $draft = $this->draft();
        $this->actingAsAdmin();
        $url = $this->previewUrlFor($draft);

        $this->travel(31)->minutes();

        $this->get($url)->assertStatus(403);
    }

    public function test_a_signature_for_one_post_does_not_open_another(): void
    {
        $draft = $this->draft();
        $other = $this->draft(['slug' => 'another-draft', 'title' => 'ร่างอีกชิ้น']);
        $this->actingAsAdmin();
        $url = $this->previewUrlFor($draft);

        $this->get(str_replace("/content-preview/{$draft->id}", "/content-preview/{$other->id}", $url))
            ->assertStatus(403);
    }

    // ------------------------------------------- the public query is untouched

    public function test_a_draft_is_still_invisible_everywhere_a_reader_looks(): void
    {
        /*
         * The point of reading the post directly in the preview controller, rather than adding a
         * `withDrafts()` flag to `publicQuery()`, is that the public query keeps one meaning. If
         * this ever fails, the fix leaked.
         */
        $draft = $this->draft(['type' => 'article']);

        $this->get("/article/{$draft->slug}")->assertNotFound();
        $this->get('/knowledge')->assertOk()->assertDontSee($draft->title);
        $this->get('/')->assertOk()->assertDontSee($draft->title);
        $this->getJson("/api/v1/content/articles/{$draft->slug}")->assertNotFound();
        $this->getJson('/api/v1/content/articles')->assertOk()->assertDontSee($draft->title);
    }

    // ---------------------------------------------------------------- console

    public function test_the_content_list_offers_preview_and_a_link_to_the_live_page(): void
    {
        $console = file_get_contents(resource_path('js/admin/console.js'));

        $this->assertStringContainsString("actionButton('ดูตัวอย่าง', 'preview')", $console);
        $this->assertStringContainsString("textNode('a', 'ดูหน้าจริง', 'ui-button-quiet')", $console);
        // The live link only makes sense once there is a live page.
        $this->assertStringContainsString("if (item.status === 'published') {", $console);
    }

    public function test_the_edit_form_offers_preview_once_the_post_exists(): void
    {
        $form = file_get_contents(resource_path('views/admin/content-form.blade.php'));

        $this->assertStringContainsString('data-content-preview', $form);
        // Preview opens what is stored, so the label says so rather than leaving the author to
        // wonder where their unsaved paragraph went.
        $this->assertStringContainsString('ตามที่บันทึกไว้ล่าสุด', $form);
        // Hidden until a saved post exists to preview.
        $this->assertMatchesRegularExpression('/data-content-preview[^>]*class="[^"]*hidden/', $form);
    }

    public function test_the_popup_is_opened_before_the_request_not_after(): void
    {
        // A window opened after an async gap is blocked by every browser, so the tab is opened
        // empty and pointed at the URL once it arrives.
        $console = file_get_contents(resource_path('js/admin/console.js'));
        $fn = substr($console, strpos($console, 'async function openPreview'));
        $fn = substr($fn, 0, strpos($fn, "\n}\n"));

        $this->assertLessThan(strpos($fn, 'await call('), strpos($fn, "window.open('', '_blank')"));
    }

    public function test_the_content_list_names_types_in_thai(): void
    {
        /*
         * The console used to carry its own copy of the vocabulary. It now comes from the API,
         * which reads it from `ContentPost::TYPE_LABELS` — the same constant the server-rendered
         * article page and card use — so the browser keeps no copy and cannot disagree with them.
         * Asserting the behaviour rather than the string also survives the next move.
         */
        $this->actingAsAdmin();
        $id = ContentPost::factory()->create(['type' => 'guide', 'status' => 'draft'])->id;

        $this->getJson("/api/v1/admin/content/$id")->assertOk()
            ->assertJsonPath('data.type', 'GUIDE')
            ->assertJsonPath('data.type_label', 'คู่มือ')
            ->assertJsonPath('data.status_label', 'ฉบับร่าง');

        $console = file_get_contents(resource_path('js/admin/console.js'));
        $this->assertStringContainsString('item.type_label', $console);
        // The raw code must not be printed straight into the cell any more.
        $this->assertStringNotContainsString("textNode('td', item.type,", $console);
    }
}

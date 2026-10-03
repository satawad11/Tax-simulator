<?php

namespace Tests\Feature;

use App\Models\ContentCategory;
use App\Models\ContentPost;
use App\Models\ContentTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Milestone 09.1 — the category and tag console page.
 *
 * M8 shipped the taxonomy API but no page called it, so an administrator could file content only
 * under labels a seeder had created. These cases cover the page the console gained, and the one
 * field it needed from the API: the number of posts filed under a label, which is what decides
 * whether deleting it removes the row or merely deactivates it.
 */
class AdminConsoleTaxonomyPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * In-memory SQLite builds the schema once for the whole run and seeds with that migration, so
     * a class that migrates first without asking to be seeded leaves every later class without a
     * baseline. This class sorts early, so it opts in.
     */
    protected $seed = true;

    private function admin(): User
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_the_console_page_renders_as_an_empty_shell(): void
    {
        // Like every console page, it must carry no privileged data server-side.
        $response = $this->get('/admin/taxonomy')->assertOk();

        $response->assertSee('หมวดหมู่และแท็ก');
        $response->assertSee('data-admin-page="taxonomy"', false);
        $response->assertSee('data-taxonomy-rows="category"', false);
        $response->assertSee('data-taxonomy-rows="tag"', false);
        // The table bodies are written by the console after an authorized request.
        $response->assertDontSee('data-taxonomy-row=', false);
    }

    public function test_the_admin_navigation_links_to_the_page(): void
    {
        $this->get('/admin')->assertOk()->assertSee('href="'.route('admin.taxonomy').'"', false);
    }

    public function test_the_taxonomy_api_reports_how_many_posts_use_each_label(): void
    {
        $this->admin();
        $used = ContentCategory::create(['name' => 'ความรู้ภาษี', 'slug' => 'tax-knowledge']);
        $unused = ContentCategory::create(['name' => 'ยังไม่ใช้', 'slug' => 'unused']);
        $tag = ContentTag::create(['name' => 'ค่าลดหย่อน', 'slug' => 'allowance']);

        $post = ContentPost::create(['type' => 'article', 'title' => 'บทความ', 'slug' => 'a-post',
            'content' => 'เนื้อหา', 'content_category_id' => $used->id]);
        $post->tags()->sync([$tag->id]);

        $categories = collect($this->getJson('/api/v1/admin/content/categories')->assertOk()->json('data'))
            ->keyBy('slug');
        $this->assertSame(1, $categories['tax-knowledge']['content_count']);
        $this->assertSame(0, $categories['unused']['content_count']);

        $tags = collect($this->getJson('/api/v1/admin/content/tags')->assertOk()->json('data'))->keyBy('slug');
        $this->assertSame(1, $tags['allowance']['content_count']);
    }

    public function test_a_label_in_use_is_deactivated_and_an_unused_one_is_deleted(): void
    {
        $this->admin();
        $used = ContentCategory::create(['name' => 'ใช้อยู่', 'slug' => 'in-use']);
        $unused = ContentCategory::create(['name' => 'ไม่ได้ใช้', 'slug' => 'not-in-use']);
        ContentPost::create(['type' => 'article', 'title' => 'บทความ', 'slug' => 'post-in-category',
            'content' => 'เนื้อหา', 'content_category_id' => $used->id]);

        $this->deleteJson("/api/v1/admin/content/categories/{$used->id}")->assertNoContent();
        $this->deleteJson("/api/v1/admin/content/categories/{$unused->id}")->assertNoContent();

        // Content already filed here keeps its category; only the label is retired.
        $this->assertDatabaseHas('content_categories', ['id' => $used->id, 'active' => false]);
        $this->assertDatabaseMissing('content_categories', ['id' => $unused->id]);
    }

    public function test_a_member_cannot_reach_the_taxonomy_api(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/admin/content/categories')->assertForbidden();
        $this->postJson('/api/v1/admin/content/tags', ['name' => 'x', 'slug' => 'x'])->assertForbidden();
    }
}

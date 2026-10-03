<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\ContentCategory;
use App\Models\ContentPost;
use App\Models\ContentTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Milestone 08 — the admin CMS.
 *
 * Authorization, the publish workflow, slug immutability, mass-assignment protection and the
 * refusal of markup are all one concern: an admin API is the one place where a mistake becomes
 * public, so each of them is asserted rather than assumed.
 */
class AdminContentApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function admin(): User
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        return $admin;
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return ['type' => 'guide', 'title' => 'คู่มือทดลอง ภ.ง.ด.91', 'slug' => 'pnd91-simulation-guide',
            'excerpt' => 'คำแนะนำก่อนเริ่มทดลองคำนวณ', 'body' => "# เตรียมข้อมูล\n\n- หนังสือรับรองเงินเดือน\n- เอกสารลดหย่อน",
            ...$overrides];
    }

    private function draft(array $overrides = []): int
    {
        return $this->postJson('/api/v1/admin/content', $this->payload($overrides))
            ->assertCreated()->json('data.id');
    }

    // ------------------------------------------------- authorization

    /** @return array<string, array{string, string}> */
    public static function adminRoutes(): array
    {
        return [
            'dashboard' => ['GET', '/api/v1/admin'],
            'content list' => ['GET', '/api/v1/admin/content'],
            'content create' => ['POST', '/api/v1/admin/content'],
            'categories' => ['GET', '/api/v1/admin/content/categories'],
            'tags' => ['GET', '/api/v1/admin/content/tags'],
            'tax sources' => ['GET', '/api/v1/admin/tax-sources'],
            'rule versions' => ['GET', '/api/v1/admin/tax-rule-versions'],
            'audit logs' => ['GET', '/api/v1/admin/audit-logs'],
        ];
    }

    #[DataProvider('adminRoutes')]
    public function test_an_unauthenticated_caller_is_refused_with_401(string $method, string $path): void
    {
        $this->json($method, $path)->assertUnauthorized();
    }

    #[DataProvider('adminRoutes')]
    public function test_a_member_is_refused_with_403(string $method, string $path): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->json($method, $path)->assertForbidden();
    }

    public function test_an_admin_is_allowed(): void
    {
        $this->admin();

        $this->getJson('/api/v1/admin')->assertOk()->assertJsonStructure(['data' => ['content', 'rule_versions']]);
    }

    public function test_the_admin_role_cannot_be_granted_through_registration_or_profile(): void
    {
        $this->postJson('/api/v1/auth/register', ['name' => 'Escalate', 'email' => 'escalate@example.com',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
            'device_name' => 'cli', 'role' => 'admin'])->assertUnprocessable();

        $this->postJson('/api/v1/auth/register', ['name' => 'Normal', 'email' => 'normal@example.com',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!', 'device_name' => 'cli'])
            ->assertCreated();
        $this->assertSame('member', User::where('email', 'normal@example.com')->firstOrFail()->role);
    }

    // ------------------------------------------------- authoring

    public function test_an_admin_creates_a_draft_that_is_not_public_until_published(): void
    {
        $admin = $this->admin();
        $id = $this->draft();

        $this->assertSame('draft', ContentPost::findOrFail($id)->status);
        $this->assertSame($admin->id, ContentPost::findOrFail($id)->author_id);
        $this->getJson('/api/v1/content/articles/pnd91-simulation-guide')->assertNotFound();

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/content/$id/publish")->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->getJson('/api/v1/content/articles/pnd91-simulation-guide')->assertOk()
            ->assertJsonPath('data.title', 'คู่มือทดลอง ภ.ง.ด.91');
    }

    public function test_unpublishing_and_archiving_remove_content_from_the_public_api(): void
    {
        $admin = $this->admin();
        $id = $this->draft();
        $this->postJson("/api/v1/admin/content/$id/publish")->assertOk();
        $this->getJson('/api/v1/content/articles/pnd91-simulation-guide')->assertOk();

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/content/$id/unpublish")->assertOk()->assertJsonPath('data.status', 'draft');
        $this->getJson('/api/v1/content/articles/pnd91-simulation-guide')->assertNotFound();

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/content/$id/publish")->assertOk();
        $this->postJson("/api/v1/admin/content/$id/archive")->assertOk()->assertJsonPath('data.status', 'archived');
        $this->getJson('/api/v1/content/articles/pnd91-simulation-guide')->assertNotFound();
    }

    public function test_a_soft_deleted_post_disappears_from_both_apis(): void
    {
        $admin = $this->admin();
        $id = $this->draft();
        $this->postJson("/api/v1/admin/content/$id/publish")->assertOk();

        Sanctum::actingAs($admin);
        $this->deleteJson("/api/v1/admin/content/$id")->assertNoContent();

        $this->assertSoftDeleted('content_posts', ['id' => $id]);
        $this->getJson('/api/v1/content/articles/pnd91-simulation-guide')->assertNotFound();
        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/admin/content')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_a_slug_is_unique_and_frozen_once_the_content_has_been_public(): void
    {
        $admin = $this->admin();
        $id = $this->draft();

        $this->postJson('/api/v1/admin/content', $this->payload(['title' => 'ซ้ำ']))
            ->assertUnprocessable()->assertJsonValidationErrors('slug');

        // Still a draft: the slug may change.
        $this->patchJson("/api/v1/admin/content/$id", ['slug' => 'renamed-while-draft'])
            ->assertOk()->assertJsonPath('data.slug', 'renamed-while-draft');

        $this->postJson("/api/v1/admin/content/$id/publish")->assertOk();
        Sanctum::actingAs($admin);
        $this->patchJson("/api/v1/admin/content/$id", ['slug' => 'renamed-after-publish'])
            ->assertUnprocessable()->assertJsonValidationErrors('slug');

        // Unpublishing does not thaw it: the URL has already been public.
        $this->postJson("/api/v1/admin/content/$id/unpublish")->assertOk();
        $this->patchJson("/api/v1/admin/content/$id", ['slug' => 'renamed-after-unpublish'])
            ->assertUnprocessable()->assertJsonValidationErrors('slug');
        $this->assertSame('renamed-while-draft', ContentPost::findOrFail($id)->slug);
    }

    // ------------------------------------------------- safety

    /** @return array<string, array{string}> */
    public static function markupPayloads(): array
    {
        return [
            'script tag' => ['<script>alert(1)</script>'],
            'uppercase script' => ['<SCRIPT>alert(1)</SCRIPT>'],
            'img onerror' => ['<img src=x onerror=alert(1)>'],
            'javascript url' => ['ดูที่ javascript:alert(1) นี้'],
            'iframe' => ['<iframe src="https://example.com"></iframe>'],
            'html comment' => ['<!-- <script>alert(1)</script> -->'],
            'data url' => ['data:text/html;base64,PHNjcmlwdD4='],
        ];
    }

    #[DataProvider('markupPayloads')]
    public function test_markup_is_refused_in_the_body(string $body): void
    {
        $this->admin();

        $this->postJson('/api/v1/admin/content', $this->payload(['body' => $body]))
            ->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->assertDatabaseCount('content_posts', 0);
    }

    public function test_markup_is_refused_in_the_title_and_seo_fields(): void
    {
        $this->admin();

        foreach (['title', 'excerpt', 'meta_title', 'meta_description'] as $field) {
            $this->postJson('/api/v1/admin/content', $this->payload([$field => '<script>alert(1)</script>']))
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }

    public function test_plain_text_that_merely_looks_dangerous_is_stored_and_rendered_escaped(): void
    {
        $admin = $this->admin();
        // No tag, no javascript: URL — this is legitimate prose about scripts.
        $id = $this->draft(['body' => "# เกี่ยวกับสคริปต์\n\nคำว่า script ใช้ได้ตามปกติ 5 > 3 และ a & b"]);
        $this->postJson("/api/v1/admin/content/$id/publish")->assertOk();

        $this->get('/article/pnd91-simulation-guide')->assertOk()
            ->assertSee('คำว่า script ใช้ได้ตามปกติ 5 &gt; 3 และ a &amp; b', false)
            ->assertDontSee('<script>', false);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function serverControlledFields(): array
    {
        return [
            'status' => [['status' => 'published']],
            'published_at' => [['published_at' => '2020-01-01T00:00:00Z']],
            'author_user_id' => [['author_user_id' => 1]],
            'author_id' => [['author_id' => 1]],
            'published_by' => [['published_by' => 1]],
            'first_published_at' => [['first_published_at' => '2020-01-01T00:00:00Z']],
        ];
    }

    #[DataProvider('serverControlledFields')]
    public function test_a_server_controlled_field_is_rejected_not_silently_ignored(array $field): void
    {
        $this->admin();

        $this->postJson('/api/v1/admin/content', $this->payload($field))
            ->assertUnprocessable()->assertJsonValidationErrors(array_key_first($field));
    }

    // ------------------------------------------------- taxonomy

    public function test_categories_and_tags_can_be_managed_and_are_retired_rather_than_orphaned(): void
    {
        $admin = $this->admin();
        $categoryId = $this->postJson('/api/v1/admin/content/categories',
            ['name' => 'ความรู้ภาษี', 'slug' => 'tax-knowledge'])->assertCreated()->json('data.id')
            ?? ContentCategory::where('slug', 'tax-knowledge')->firstOrFail()->id;
        $tagId = $this->postJson('/api/v1/admin/content/tags', ['name' => 'ลดหย่อน', 'slug' => 'allowance'])
            ->assertCreated()->json('data.id') ?? ContentTag::where('slug', 'allowance')->firstOrFail()->id;

        $this->patchJson("/api/v1/admin/content/categories/$categoryId", ['name' => 'ความรู้ภาษีบุคคลธรรมดา'])
            ->assertOk()->assertJsonPath('data.name', 'ความรู้ภาษีบุคคลธรรมดา');

        $id = $this->draft(['category_id' => $categoryId, 'tag_ids' => [$tagId]]);
        $this->assertSame($categoryId, ContentPost::findOrFail($id)->content_category_id);

        // In use: deactivated, not deleted, so published content keeps its label.
        $this->deleteJson("/api/v1/admin/content/categories/$categoryId")->assertNoContent();
        $this->assertDatabaseHas('content_categories', ['id' => $categoryId, 'active' => false]);
        $this->deleteJson("/api/v1/admin/content/tags/$tagId")->assertNoContent();
        $this->assertDatabaseHas('content_tags', ['id' => $tagId, 'active' => false]);
    }

    // ------------------------------------------------- audit

    public function test_every_content_action_is_audited_without_capturing_secrets(): void
    {
        $admin = $this->admin();
        $id = $this->draft();
        $this->patchJson("/api/v1/admin/content/$id", ['title' => 'ชื่อใหม่'])->assertOk();
        $this->postJson("/api/v1/admin/content/$id/publish")->assertOk();
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/content/$id/unpublish")->assertOk();

        $actions = AdminAuditLog::orderBy('id')->pluck('action')->all();
        $this->assertSame(['CONTENT_CREATED', 'CONTENT_UPDATED', 'CONTENT_PUBLISHED', 'CONTENT_UNPUBLISHED'], $actions);

        foreach (AdminAuditLog::all() as $log) {
            $this->assertSame($admin->id, $log->actor_user_id);
            $encoded = json_encode([$log->before_json, $log->after_json], JSON_THROW_ON_ERROR);
            foreach (['password', 'token', 'remember_token', 'result_snapshot'] as $secret) {
                $this->assertStringNotContainsString($secret, $encoded);
            }
        }

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/admin/audit-logs')->assertOk()->assertJsonCount(4, 'data');
    }
}

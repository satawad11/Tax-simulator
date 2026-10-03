<?php

namespace Tests\Feature;

use App\Models\ContentPost;
use App\Models\TaxSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Milestone 09.1 — every administrative capability the API offers has a way to reach it.
 *
 * The console had drifted into a mostly read-only view of a mostly writable API: rule rows could
 * not be edited at all, which is the single thing a draft rule version exists for; the source
 * registry could only be changed by editing a seeder; and the audit trail was visible only as the
 * last few lines of the overview.
 *
 * The first test here is the one that matters: it walks the registered admin API routes and fails
 * if a write route has no page that could plausibly invoke it. That is what stops the console
 * from silently falling behind the API again.
 */
class AdminConsoleCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    /**
     * Admin write routes that are deliberately not offered as a console control, with the reason.
     *
     * @var array<string, string>
     */
    private const INTENTIONALLY_ABSENT = [
        // A version is created by cloning an existing one, which is the only path that carries
        // the previous version's rules forward; a bare create would produce an empty version.
        'api.v1.admin.tax-rule-versions.store' => 'versions are created by cloning, never empty',
    ];

    private function adminPages(): string
    {
        $markup = '';
        foreach (glob(resource_path('views/admin/*.blade.php')) ?: [] as $view) {
            $markup .= (string) file_get_contents($view);
        }
        foreach (glob(resource_path('js/admin/*.js')) ?: [] as $script) {
            $markup .= (string) file_get_contents($script);
        }

        return $markup;
    }

    public function test_every_admin_write_route_is_reachable_from_the_console(): void
    {
        $console = $this->adminPages();
        $unreachable = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName() ?? '';
            if (! str_starts_with($name, 'api.v1.admin.')) {
                continue;
            }
            if (array_intersect($route->methods(), ['POST', 'PATCH', 'PUT', 'DELETE']) === []) {
                continue;
            }
            if (array_key_exists($name, self::INTENTIONALLY_ABSENT)) {
                continue;
            }

            // The console addresses these by path, so the distinctive segment of the URI is what
            // proves a control exists for it.
            $segment = collect(explode('/', $route->uri()))
                ->reject(fn (string $part): bool => $part === '' || str_starts_with($part, '{') || in_array($part, ['api', 'v1', 'admin'], true))
                ->last();

            if ($segment === null || ! str_contains($console, $segment)) {
                $unreachable[] = "{$name} ({$route->uri()})";
            }
        }

        $this->assertSame([], $unreachable,
            "These admin write routes have no console control:\n".implode("\n", $unreachable));
    }

    /** @return list<array{string}> */
    public static function consolePages(): array
    {
        return [['/admin'], ['/admin/content'], ['/admin/content/create'], ['/admin/taxonomy'],
            ['/admin/tax-rule-versions'], ['/admin/tax-sources'], ['/admin/audit-logs']];
    }

    public function test_every_console_page_renders_and_links_from_the_menu(): void
    {
        foreach (self::consolePages() as [$path]) {
            $this->get($path)->assertOk();
        }

        $menu = $this->get('/admin')->assertOk()->getContent();
        foreach (['admin.content', 'admin.taxonomy', 'admin.rule-versions', 'admin.tax-sources', 'admin.audit-logs'] as $name) {
            $this->assertStringContainsString('href="'.route($name).'"', $menu,
                "The console menu has no link to {$name}.");
        }
    }

    public function test_console_pages_carry_no_protected_data_server_side(): void
    {
        // A source and a draft post exist; neither may appear in the unauthenticated shell.
        TaxSource::create(['code' => 'SECRET_DOC', 'title' => 'เอกสารลับ', 'source_type' => 'OFFICIAL_FORM']);
        ContentPost::create(['type' => 'article', 'title' => 'ร่างที่ยังไม่เผยแพร่', 'slug' => 'unpublished-draft', 'content' => 'x']);

        foreach (['/admin/tax-sources', '/admin/content', '/admin/audit-logs'] as $path) {
            $body = $this->get($path)->assertOk()->getContent();
            $this->assertStringNotContainsString('SECRET_DOC', $body);
            $this->assertStringNotContainsString('ร่างที่ยังไม่เผยแพร่', $body);
        }
    }

    public function test_the_audit_log_api_filters_and_paginates(): void
    {
        Sanctum::actingAs($admin = User::factory()->admin()->create());

        // Produce real audit rows through the workflow rather than writing the table directly.
        $id = $this->postJson('/api/v1/admin/content', ['type' => 'article', 'title' => 'บทความตรวจสอบ',
            'body' => 'เนื้อหา', 'slug' => 'audit-probe'])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/admin/content/{$id}/publish", [])->assertOk();

        $all = $this->getJson('/api/v1/admin/audit-logs')->assertOk()->json('data');
        $this->assertNotEmpty($all);

        $published = $this->getJson('/api/v1/admin/audit-logs?action=CONTENT_PUBLISHED')->assertOk()->json('data');
        $this->assertNotEmpty($published);
        foreach ($published as $entry) {
            $this->assertSame('CONTENT_PUBLISHED', $entry['action']);
        }

        $byEntity = $this->getJson('/api/v1/admin/audit-logs?entity_type=content_post')->assertOk()->json('data');
        foreach ($byEntity as $entry) {
            $this->assertSame('content_post', $entry['entity_type']);
        }

        // The trail records who acted, and never a credential.
        $this->assertSame($admin->name, $published[0]['actor']);
        $this->assertArrayNotHasKey('password', $published[0]);
    }

    public function test_a_member_cannot_read_the_audit_log(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/admin/audit-logs')->assertForbidden();
    }

    /**
     * A source is deactivated, never removed — and the console must say so.
     *
     * The registry is evidence: a published rule has to keep pointing at the document it was read
     * from, so `DELETE` retires the row instead of dropping it. The console previously offered
     * "ลบ" for an uncited source, which named an outcome the API never produces.
     */
    public function test_deleting_a_source_deactivates_it_rather_than_removing_it(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $unused = TaxSource::create(['code' => 'UNUSED_DOC', 'title' => 'ไม่ได้ใช้', 'source_type' => 'ATTACHMENT']);

        $this->deleteJson("/api/v1/admin/tax-sources/{$unused->id}")->assertSuccessful();

        $this->assertDatabaseHas('tax_sources', ['id' => $unused->id, 'active' => false]);
    }

    public function test_the_source_control_does_not_promise_deletion(): void
    {
        $console = (string) file_get_contents(resource_path('js/admin/console.js'));

        // The label is what this guard is about — the endpoint deactivates and never deletes, so
        // the control must not say ลบ. How the button is built is not: it now goes through
        // `actionButton`, which pairs the same words with an icon.
        $this->assertStringContainsString("actionButton('ปิดใช้งาน', 'deactivate', 'ui-button-danger-quiet')", $console,
            'The tax-source control must offer deactivation, which is what the API performs.');
    }

    /**
     * The content form must offer the vocabulary the API validates against.
     *
     * It offered upper-case values while `ContentPost::TYPES` is lower case, so every save from
     * the console failed validation on `type` and an existing post's type never preselected.
     */
    public function test_the_content_form_offers_the_types_the_api_accepts(): void
    {
        $form = $this->get('/admin/content/create')->assertOk()->getContent();

        foreach (ContentPost::TYPES as $type) {
            $this->assertStringContainsString('value="'.$type.'"', $form,
                "The content form does not offer the \"{$type}\" type.");
            $this->assertStringNotContainsString('value="'.strtoupper($type).'"', $form,
                "The content form still offers \"{$type}\" in a case the API rejects.");
        }
    }

    public function test_content_can_actually_be_created_with_every_offered_type(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        foreach (ContentPost::TYPES as $index => $type) {
            $this->postJson('/api/v1/admin/content', ['type' => $type, 'title' => 'ทดสอบ '.$type,
                'body' => 'เนื้อหา', 'slug' => 'type-probe-'.$index])->assertCreated();
        }
    }
}

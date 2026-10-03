<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Milestone 09.1 — each audience is shown the navigation its role can use.
 *
 * Guest, member and administrator see different things, decided once from `/auth/me` and applied
 * through a single `data-visible-to` attribute. Before this there were three ad-hoc hooks that
 * each decided visibility slightly differently, which is how the admin link came to be shown to
 * ordinary members at desktop width.
 *
 * Two properties matter and are asserted here. The markup must be honest — every
 * audience-specific control starts hidden by the attribute, so a visitor with JavaScript
 * disabled is never shown a control meant for somebody else. And it must be only presentation:
 * whatever the browser chooses to display, the API decides what is permitted.
 */
class RoleAwareNavigationTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<array{string}> every shell that carries role-aware navigation */
    public static function shells(): array
    {
        return [['/'], ['/tax-simulator'], ['/knowledge'], ['/faq'], ['/dashboard'], ['/admin']];
    }

    public function test_every_audience_specific_control_starts_hidden_by_the_attribute(): void
    {
        foreach (self::shells() as [$path]) {
            $body = $this->get($path)->assertOk()->getContent();

            preg_match_all('/<(?:a|button|p|span|div)[^>]*data-visible-to[^>]*>/', $body, $tags);
            $this->assertNotEmpty($tags[0], "{$path} declares no audience-specific controls.");

            foreach ($tags[0] as $tag) {
                $this->assertMatchesRegularExpression('/\shidden(\s|>)/', $tag,
                    "{$path}: control must start hidden — {$tag}");
                /*
                 * The bare `hidden` class is what a later display utility overrides
                 * (`class="hidden lg:inline-flex"` shows the element again at lg). A *prefixed*
                 * variant such as `max-sm:hidden` only ever hides further and is safe, so the
                 * rule rejects the unprefixed token alone.
                 */
                preg_match('/class="([^"]*)"/', $tag, $classAttribute);
                $classes = preg_split('/\s+/', $classAttribute[1] ?? '') ?: [];
                $this->assertNotContains('hidden', $classes,
                    "{$path}: must not use the bare hidden class, which a breakpoint overrides — {$tag}");
            }
        }
    }

    public function test_the_three_audiences_are_each_addressed(): void
    {
        $body = $this->get('/')->assertOk()->getContent();

        foreach (['guest', 'member', 'admin'] as $audience) {
            $this->assertMatchesRegularExpression('/data-visible-to="[^"]*'.$audience.'[^"]*"/', $body,
                "The header offers nothing to the {$audience} audience.");
        }
    }

    public function test_only_the_admin_audience_is_offered_the_console(): void
    {
        $body = $this->get('/')->assertOk()->getContent();

        preg_match_all('/<a[^>]*href="[^"]*\/admin"[^>]*>/', $body, $tags);
        $this->assertNotEmpty($tags[0], 'The header has no link to the console at all.');

        foreach ($tags[0] as $tag) {
            $this->assertMatchesRegularExpression('/data-visible-to="admin"/', $tag,
                "A link to the console must be offered to administrators only — {$tag}");
        }
    }

    public function test_the_session_is_shared_so_one_sign_in_reaches_the_console(): void
    {
        $api = (string) file_get_contents(resource_path('js/api.js'));
        $console = (string) file_get_contents(resource_path('js/admin/console.js'));

        // The console must not keep a token of its own; it reads the site's session.
        $this->assertStringContainsString("const TOKEN_KEY = 'tax-simulator.session-token'", $api);
        $this->assertStringNotContainsString("'tax-simulator.admin-token'", $console);
        $this->assertStringContainsString('const token = authToken', $console);
    }

    public function test_the_role_is_asked_of_the_api_never_inferred_in_the_browser(): void
    {
        $session = (string) file_get_contents(resource_path('js/session.js'));

        $this->assertStringContainsString("api('/auth/me')", $session);
        $this->assertStringContainsString('user?.is_admin', $session);
    }

    public function test_the_api_reports_the_audience_for_each_kind_of_account(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.is_admin', true);

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.is_admin', false);
    }

    public function test_what_is_displayed_never_decides_what_is_permitted(): void
    {
        // A member is refused every administrative endpoint regardless of the markup they were
        // served, and the console page itself carries no privileged data to begin with.
        Sanctum::actingAs(User::factory()->create());

        $this->get('/admin')->assertOk();
        foreach (['/api/v1/admin', '/api/v1/admin/content', '/api/v1/admin/audit-logs',
            '/api/v1/admin/tax-sources', '/api/v1/admin/rule-references'] as $endpoint) {
            $this->getJson($endpoint)->assertForbidden();
        }

        $this->postJson('/api/v1/admin/content', ['type' => 'article', 'title' => 'x', 'body' => 'x'])
            ->assertForbidden();
    }

    public function test_a_guest_is_offered_sign_in_and_nothing_member_only(): void
    {
        $body = $this->get('/')->assertOk()->getContent();

        // The guest control exists and points at the public login, not the admin one.
        $this->assertMatchesRegularExpression(
            '/data-visible-to="guest"[^>]*href="[^"]*\/login"/', $body);
    }
}

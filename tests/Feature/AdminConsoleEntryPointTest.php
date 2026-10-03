<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Milestone 09.1 — an administrator can find the console.
 *
 * The console's own menu was complete and correct, and still unreachable: nothing on the public
 * site or in the member area linked to /admin, so an administrator who signed in normally saw no
 * administrative menu anywhere and had to know the path by heart.
 *
 * The link is revealed in the browser, from `is_admin` on /auth/me, so these cases cover both
 * halves: the API must answer the question, and the markup must carry a hidden link for the
 * script to reveal. Revealing it grants nothing, which the last case states.
 */
class AdminConsoleEntryPointTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_api_tells_the_client_whether_the_user_is_an_admin(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.is_admin', true);

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.is_admin', false);
    }

    public function test_the_api_never_exposes_the_raw_role_string(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        // `is_admin` answers the only question the browser has; the role vocabulary stays internal.
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonMissingPath('data.role');
    }

    /** @return list<array{string}> */
    public static function pagesWithTheEntryPoint(): array
    {
        return [['/'], ['/tax-simulator'], ['/knowledge'], ['/dashboard']];
    }

    public function test_every_shell_carries_a_hidden_admin_link_for_the_script_to_reveal(): void
    {
        foreach (self::pagesWithTheEntryPoint() as [$path]) {
            $body = $this->get($path)->assertOk()->getContent();

            $this->assertStringContainsString('data-visible-to="admin"', $body,
                "{$path} has no admin entry point for an administrator to find.");
            $this->assertStringContainsString(route('admin.dashboard'), $body,
                "{$path} does not link to the admin console.");
        }
    }

    /**
     * The link starts hidden by the `hidden` attribute, not by a utility class.
     *
     * `class="hidden lg:inline-flex"` puts the element back on a wide screen, so a control
     * toggled by adding and removing the `hidden` *class* still showed itself to ordinary
     * members at desktop width. The attribute plus an `!important` rule is absolute.
     */
    public function test_the_link_is_hidden_by_an_attribute_no_breakpoint_can_override(): void
    {
        $body = $this->get('/')->assertOk()->getContent();

        preg_match_all('/<a[^>]*data-visible-to="admin"[^>]*>/', $body, $tags);
        $this->assertNotEmpty($tags[0], 'No admin entry point was rendered.');

        foreach ($tags[0] as $tag) {
            $this->assertMatchesRegularExpression('/\shidden(\s|>)/', $tag,
                "The admin link must carry the hidden attribute: {$tag}");
            preg_match('/class="([^"]*)"/', $tag, $classAttribute);
            $this->assertNotContains('hidden', preg_split('/\s+/', $classAttribute[1] ?? '') ?: [],
                "The admin link must not rely on the bare hidden class, which a breakpoint overrides: {$tag}");
        }

        $css = (string) file_get_contents(resource_path('css/app.css'));
        $this->assertMatchesRegularExpression('/\[hidden\]\s*\{\s*display:\s*none\s*!important/', $css);
    }

    public function test_the_script_reveals_the_link_only_for_an_admin(): void
    {
        $session = (string) file_get_contents(resource_path('js/session.js'));

        // Visibility is decided from the API's answer, and applied through the attribute so no
        // breakpoint can put a hidden control back on screen.
        $this->assertStringContainsString('user?.is_admin', $session);
        $this->assertStringContainsString('data-visible-to', $session);
        $this->assertStringContainsString('element.hidden = !audiences.includes(audience)', $session);
    }

    public function test_seeing_the_link_grants_nothing(): void
    {
        // The console page is a shell either way, and the API refuses a member regardless of what
        // the browser chose to display.
        Sanctum::actingAs(User::factory()->create());

        $this->get('/admin')->assertOk();
        $this->getJson('/api/v1/admin')->assertForbidden();
        $this->getJson('/api/v1/admin/content')->assertForbidden();
        $this->getJson('/api/v1/admin/audit-logs')->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 — the pages that make the recovery endpoints reachable.
 *
 * An endpoint nobody can reach closes nothing. The reset link in particular has to land on a page
 * that already holds the token and the address, because the member arrives from a mail client
 * with nothing else in hand.
 */
class PasswordPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_sign_in_offers_a_way_out_for_someone_who_cannot_sign_in(): void
    {
        $this->get('/login')->assertOk()->assertSee(route('password.forgot'))->assertSee('ลืมรหัสผ่าน?');
    }

    public function test_the_forgot_page_renders(): void
    {
        $this->get('/password/forgot')->assertOk()
            ->assertSee('data-password-page="forgot"', false)
            ->assertSee('name="email"', false);
    }

    public function test_the_reset_page_carries_the_token_and_address_from_the_link(): void
    {
        $this->get('/password/reset/abc123?email=member%40tax-simulator.local')->assertOk()
            ->assertSee('data-password-page="reset"', false)
            ->assertSee('value="abc123"', false)
            ->assertSee('value="member@tax-simulator.local"', false);
    }

    public function test_the_reset_page_survives_a_link_with_no_address(): void
    {
        // A truncated or hand-edited link still has to render a usable form rather than error.
        $this->get('/password/reset/abc123')->assertOk()->assertSee('name="email"', false);
    }

    public function test_a_token_that_could_never_verify_never_reaches_the_page(): void
    {
        // The token lands in a value attribute, so the safest thing is for attacker-shaped text
        // not to arrive at all. The broker's token is hex; anything else is refused by the route.
        $this->get('/password/reset/'.urlencode('"><script>x'))->assertNotFound();
        $this->get('/password/reset/'.urlencode('../../etc/passwd'))->assertNotFound();
    }

    public function test_the_address_from_the_query_string_is_escaped(): void
    {
        // Unlike the token, the address is free text and must be rendered, so it must be escaped.
        $this->get('/password/reset/abc123?email='.urlencode('"><script>x</script>'))
            ->assertOk()->assertDontSee('<script>x</script>', false);
    }

    public function test_the_change_password_page_renders(): void
    {
        $this->get('/account/password')->assertOk()
            ->assertSee('data-password-page="change"', false)
            ->assertSee('name="current_password"', false);
    }

    public function test_the_recovery_pages_are_not_indexed(): void
    {
        // They exist for one person holding one link; a search engine has no business here.
        foreach (['/password/forgot', '/password/reset/abc123', '/account/password'] as $path) {
            $this->get($path)->assertOk()->assertSee('noindex', false);
        }
    }
}

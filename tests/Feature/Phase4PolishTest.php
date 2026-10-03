<?php

namespace Tests\Feature;

use App\Models\TaxReturn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 4 — polish and hardening.
 *
 * Four gaps that share nothing except being the things a real deployment notices on week one:
 * error pages in the product's own language, a result the reader can keep, a bound on what one
 * token can write, and the settings page for an endpoint that has had no caller since M5.
 */
class Phase4PolishTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    // ------------------------------------------------------- 7. error pages

    public function test_a_missing_page_stays_inside_the_product(): void
    {
        // Laravel's own 404 is in English, in a different typeface, with no way back — a reader
        // who mistyped an article slug left the product entirely.
        $response = $this->get('/article/no-such-article-exists')->assertNotFound();

        $response->assertSee('ไม่พบหน้าที่ต้องการ');
        $response->assertSee(route('home'));
        $response->assertSee(route('simulator.index'));
        $response->assertDontSee('Not Found', false);
    }

    public function test_every_error_page_renders_in_thai_with_a_way_onward(): void
    {
        foreach (['403', '404', '419', '429', '500', '503'] as $code) {
            $html = view("errors.$code", ['exception' => new \Exception('test')])->render();

            $this->assertStringContainsString($code, $html, "errors.$code must show its status code");
            $this->assertStringContainsString(route('home'), $html, "errors.$code must offer a way back");
            // A Thai character anywhere proves the page is the product's, not the framework's.
            $this->assertMatchesRegularExpression('/[\x{0E00}-\x{0E7F}]/u', $html);
        }
    }

    public function test_error_pages_are_not_indexed(): void
    {
        $this->get('/article/no-such-article-exists')->assertNotFound()->assertSee('noindex', false);
    }

    // ---------------------------------------------------- 8. printed result

    public function test_the_result_offers_a_way_to_keep_it(): void
    {
        $renderer = file_get_contents(resource_path('js/tax-result.js'));

        $this->assertStringContainsString('data-print-result', $renderer);
        $this->assertStringContainsString('พิมพ์ / บันทึกเป็น PDF', $renderer);
    }

    public function test_printing_keeps_the_caveats_and_drops_the_chrome(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $print = substr($css, strpos($css, '@media print'));
        // The hide list only — the rest of the block restyles elements for paper, and `.ui-note`
        // legitimately appears there (shadows removed), which is not the same as hiding it.
        $hideRule = substr($print, 0, strpos($print, 'display: none !important;'));

        // Chrome the reader cannot act on from paper.
        foreach (['[data-wizard-actions]', '[data-print-result]', '[data-planning]'] as $hidden) {
            $this->assertStringContainsString($hidden, $hideRule, "print CSS must hide $hidden");
        }
        // A printed figure that has outlived its caveats looks official and is worse than none,
        // so the warning and disclaimer notes must never join that list.
        $this->assertStringNotContainsString('.ui-note', $hideRule);
        $this->assertStringNotContainsString('[data-result]', $hideRule);
    }

    public function test_the_trace_is_expanded_for_the_print_and_restored_after(): void
    {
        // `<details>` cannot be opened by CSS, and the step-by-step trace lives inside one — the
        // most defensible thing this product makes would otherwise print as a closed summary.
        $wizard = file_get_contents(resource_path('js/simulator-wizard.js'));

        $this->assertStringContainsString("addEventListener('beforeprint'", $wizard);
        $this->assertStringContainsString("addEventListener('afterprint'", $wizard);
        $this->assertStringContainsString('details:not([open])', $wizard);
    }

    // ------------------------------------------------------- 9. rate limits

    public function test_creating_returns_in_a_loop_is_bounded(): void
    {
        Sanctum::actingAs(User::factory()->create());

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->postJson('/api/v1/tax-returns',
                ['tax_year' => 2568, 'form_code' => 'PND91', 'name' => "แบบที่ $attempt"])->assertCreated();
        }

        $this->postJson('/api/v1/tax-returns',
            ['tax_year' => 2568, 'form_code' => 'PND91', 'name' => 'เกินโควตา'])->assertStatus(429);
    }

    public function test_a_realistic_save_is_nowhere_near_the_limit(): void
    {
        /*
         * The wizard writes one request per item across five collections. A limit that a genuine
         * heavy return could trip would be worse than none, so this walks a return with more
         * items than any real filing and expects every write to succeed.
         */
        $member = User::factory()->create();
        Sanctum::actingAs($member);
        $id = $this->postJson('/api/v1/tax-returns',
            ['tax_year' => 2568, 'form_code' => 'PND91', 'name' => 'แบบหนัก'])->assertCreated()->json('data.id');

        for ($item = 0; $item < 40; $item++) {
            $this->postJson("/api/v1/tax-returns/$id/incomes", ['income_type' => 'SECTION_40_1',
                'gross_amount' => '1000.00', 'exempt_amount' => '0.00'])->assertCreated();
        }
    }

    public function test_reading_is_not_charged_against_the_creation_limit(): void
    {
        // The tight tier exists for unbounded growth, not for looking at a list.
        Sanctum::actingAs(User::factory()->create());

        for ($attempt = 0; $attempt < 40; $attempt++) {
            $this->getJson('/api/v1/tax-returns')->assertOk();
        }
    }

    public function test_duplication_sits_on_the_tight_tier(): void
    {
        // It creates a whole return with all its children, so it belongs with creation.
        $member = User::factory()->create();
        Sanctum::actingAs($member);
        $source = TaxReturn::factory()->create(['user_id' => $member->id]);

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->postJson("/api/v1/tax-returns/{$source->id}/duplicate", ['name' => "สำเนา $attempt"])->assertCreated();
        }

        $this->postJson("/api/v1/tax-returns/{$source->id}/duplicate", ['name' => 'เกินโควตา'])->assertStatus(429);
    }

    // ---------------------------------------------------- 10. account page

    public function test_the_account_page_exists_and_is_linked(): void
    {
        $this->get('/dashboard/account')->assertOk()->assertSee('data-member-page="account"', false);
        // Reachable from the member tabs, not only by knowing the URL.
        $this->get('/dashboard')->assertOk()->assertSee(route('dashboard.account'))->assertSee('บัญชีของฉัน');
    }

    public function test_the_identity_endpoint_reports_verification_state(): void
    {
        // The account page shows it and offers the link again when it is false.
        Sanctum::actingAs(User::factory()->unverified()->create());
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.email_verified', false);

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.email_verified', true);
    }

    public function test_a_member_can_correct_their_own_name(): void
    {
        // `PUT /auth/me` has existed since M5 with nothing calling it.
        $member = User::factory()->create(['name' => 'ชื่อเดิม']);
        Sanctum::actingAs($member);

        $this->putJson('/api/v1/auth/me', ['name' => 'ชื่อใหม่'])->assertOk()
            ->assertJsonPath('data.name', 'ชื่อใหม่');

        $this->assertSame('ชื่อใหม่', $member->fresh()->name);
    }

    public function test_the_account_page_does_not_duplicate_the_password_form(): void
    {
        /*
         * Changing a password has its own page from Phase 1, with its own rate limit and its own
         * current-password check. Two forms writing the same credential is how they drift apart,
         * so this one links there instead.
         */
        $dashboard = file_get_contents(resource_path('js/member-dashboard.js'));
        $account = substr($dashboard, strpos($dashboard, 'const account = async'));
        $account = substr($account, 0, strpos($account, 'const dashboard = async'));

        $this->assertStringContainsString('/account/password', $account);
        $this->assertStringNotContainsString('current_password', $account);
        $this->assertStringNotContainsString('password_confirmation', $account);
    }
}

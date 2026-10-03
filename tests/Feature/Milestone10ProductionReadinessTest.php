<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\ContentPost;
use App\Models\TaxSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone10ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_added_without_breaking_the_health_response(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_production_responses_include_the_compatible_content_security_policy(): void
    {
        $this->app['env'] = 'production';

        $this->get('/')->assertOk()->assertHeader(
            'Content-Security-Policy',
            "default-src 'self'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'; img-src 'self' data:; font-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'self'",
        );
    }

    public function test_public_admin_page_shells_never_embed_protected_database_data(): void
    {
        ContentPost::unguarded(fn () => ContentPost::create([
            'type' => 'article', 'title' => 'M10_SECRET_DRAFT', 'slug' => 'm10-secret-draft',
            'content' => 'M10_SECRET_BODY', 'status' => 'draft',
        ]));
        TaxSource::create([
            'code' => 'M10_SECRET_SOURCE', 'title' => 'M10 secret source',
            'source_type' => 'INTERNAL_APPROVED_REFERENCE',
        ]);
        AdminAuditLog::create([
            'action' => 'M10_SECRET_ACTION', 'entity_type' => 'content_post',
            'summary' => 'M10_SECRET_AUDIT_SUMMARY',
        ]);

        foreach (['/admin', '/admin/content', '/admin/content/1/edit', '/admin/tax-rule-versions', '/admin/tax-rule-versions/1', '/admin/tax-sources'] as $uri) {
            $this->get($uri)->assertOk()
                ->assertDontSee('M10_SECRET_DRAFT')
                ->assertDontSee('M10_SECRET_BODY')
                ->assertDontSee('M10_SECRET_SOURCE')
                ->assertDontSee('M10_SECRET_AUDIT_SUMMARY');
        }
    }

    public function test_expensive_public_tax_routes_are_rate_limited(): void
    {
        foreach (['api.v1.tax.calculate', 'api.v1.tax.plan', 'api.v1.forms.recommend'] as $name) {
            $middleware = Route::getRoutes()->getByName($name)?->gatherMiddleware() ?? [];
            $this->assertContains('throttle:30,1', $middleware, "$name must be rate limited.");
        }
    }
}

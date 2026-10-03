<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_endpoint_returns_public_json_without_a_session(): void
    {
        $this->get('/api/v1/health')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertCookieMissing(config('session.cookie'))
            ->assertExactJson([
                'success' => true,
                'message' => null,
                'data' => ['status' => 'ok', 'service' => 'tax-simulator-api'],
            ]);
    }

    public function test_unknown_api_routes_return_json_without_accept_header(): void
    {
        $this->get('/api/v1/missing')->assertNotFound()
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson(['success' => false, 'message' => 'Resource not found', 'errors' => null]);
    }

    public function test_unversioned_health_endpoint_is_not_exposed(): void
    {
        $this->getJson('/api/health')->assertNotFound();
    }

    public function test_api_validation_returns_422_with_field_errors(): void
    {
        Route::get('/api/v1/test-validation', function (): void {
            throw ValidationException::withMessages(['name' => ['Name is required.']]);
        });

        $this->get('/api/v1/test-validation')->assertUnprocessable()
            ->assertExactJson([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => ['name' => ['Name is required.']],
            ]);
    }

    public function test_api_server_errors_return_500_without_debug_details(): void
    {
        config(['app.debug' => true]);
        Route::get('/api/v1/test-error', function (): void {
            throw new RuntimeException('Private exception detail');
        });

        $this->get('/api/v1/test-error')->assertInternalServerError()
            ->assertSee('"errors":{}', false)
            ->assertExactJson(['success' => false, 'message' => 'Internal Server Error', 'errors' => []]);
    }

    public function test_health_rejects_post_with_405_and_preserves_allow_header(): void
    {
        $this->postJson('/api/v1/health')->assertMethodNotAllowed()
            ->assertHeader('Allow', 'GET, HEAD')
            ->assertSee('"errors":{}', false)
            ->assertExactJson(['success' => false, 'message' => 'Method Not Allowed', 'errors' => []]);
    }
}

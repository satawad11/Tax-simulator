<?php

use App\Exceptions\AccountSuspendedException;
use App\Exceptions\TaxMetadataConflictException;
use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(AddSecurityHeaders::class);
        $trustedProxies = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) (getenv('TRUSTED_PROXIES') ?: '')),
        )));
        if ($trustedProxies !== []) {
            $middleware->trustProxies(
                at: $trustedProxies,
                headers: Request::HEADER_X_FORWARDED_FOR
                    | Request::HEADER_X_FORWARDED_HOST
                    | Request::HEADER_X_FORWARDED_PORT
                    | Request::HEADER_X_FORWARDED_PROTO
                    | Request::HEADER_X_FORWARDED_PREFIX,
            );
        }
        $middleware->redirectGuestsTo(fn (Request $request): ?string => $request->is('api/*') ? null : '/');
        // M8 — admin authorization layered over the existing M5 Sanctum authentication.
        $middleware->alias(['admin' => EnsureUserIsAdmin::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            if (! $request->is('api/*') || $response->getStatusCode() < 400) {
                return $response;
            }

            $status = $response->getStatusCode();
            $payload = json_decode($response->getContent(), true);

            $response->setContent(json_encode([
                'success' => false,
                'message' => match (true) {
                    $status === 422 => 'Validation failed',
                    $status === 404 => 'Resource not found',
                    // Two exceptions may carry their own message through. Both are opt-in by class
                    // rather than by status, so the generic rule — an error never explains itself —
                    // holds everywhere it has not been deliberately relaxed.
                    $exception instanceof TaxMetadataConflictException,
                    $exception instanceof AccountSuspendedException => $exception->getMessage(),
                    default => Response::$statusTexts[$status] ?? 'Request failed',
                },
                'errors' => in_array($status, [404, 409], true) ? null : (object) ($status === 422 ? ($payload['errors'] ?? []) : []),
            ]));
            $response->headers->set('Content-Type', 'application/json');

            return $response;
        });
    })->create();

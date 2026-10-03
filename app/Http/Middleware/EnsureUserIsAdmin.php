<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Milestone 08 — the admin authorization layer over the existing M5 Sanctum authentication.
 *
 * There is no second authentication stack: `auth:sanctum` still establishes who the caller is
 * and answers 401 when nobody is signed in. This middleware only answers the separate question
 * of whether that caller may administer the platform, and a member gets 403.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isAdmin() === true, 403, 'Administrator access is required.');

        return $next($request);
    }
}

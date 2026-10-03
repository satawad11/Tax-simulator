<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A 401 whose reason is allowed to reach the caller.
 *
 * The API exception handler replaces the message on every error response with the generic status
 * text, which is right for authentication failures: a sign-in form must not explain *why* it
 * refused, or it becomes a way to enumerate accounts. That rule silently swallowed the one 401
 * that has to be specific — a suspended member told "invalid credentials" resets their password,
 * finds it still does not work, and resets it again.
 *
 * Its own class, recognised by the handler exactly as `TaxMetadataConflictException` is, so
 * carrying a message through is a deliberate opt-in per exception rather than a hole in the rule.
 * It is thrown only after the password has already been verified, so it tells nothing to anyone
 * who was not going to be signed in anyway.
 */
final class AccountSuspendedException extends HttpException
{
    public function __construct(string $message)
    {
        parent::__construct(401, $message, headers: ['WWW-Authenticate' => 'Bearer']);
    }
}

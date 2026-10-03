<?php

namespace App\Support;

use App\Http\Requests\Api\V1\StrictApiRequest;
use Illuminate\Validation\Rules\Password;

/**
 * The one definition of what this product accepts as a password.
 *
 * Phase 1. Registration has enforced twelve characters with mixed case, a number and a symbol
 * since M5. Reset and change are new doors into the same lock, and a rule copied into three
 * request classes is a rule that will disagree with itself the first time one of them is edited —
 * typically by weakening the least-visited door, which is exactly the one an attacker uses.
 *
 * The 72-byte ceiling is bcrypt's, not a policy choice: bcrypt silently truncates beyond it, so a
 * longer password would be accepted and then only partly checked. `StrictApiRequest` rejects it
 * for every request that carries a `password` field; it is repeated here so a rule read on its own
 * is still complete.
 *
 * @see StrictApiRequest
 */
final class PasswordPolicy
{
    /** @return list<mixed> */
    public static function rules(): array
    {
        return ['required', 'string', 'max:72', 'confirmed',
            Password::min(12)->mixedCase()->numbers()->symbols()];
    }

    /** The confirmation field that `confirmed` above requires. */
    public static function confirmationRules(): array
    {
        return ['required', 'string'];
    }
}

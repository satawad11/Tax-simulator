<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Phase 1 — account recovery.
 *
 * Until now a member who forgot their password lost every saved return permanently, and support
 * had no answer short of database access. This closes that, and the change-password door beside
 * it, on Laravel's own broker and the `password_reset_tokens` table the first migration has
 * always created.
 *
 * Two rules shape everything here:
 *
 * **A request never reveals whether an address is registered.** `forgot()` answers the same way
 * for a known address, an unknown one, and one asking again too soon. Anything else turns the
 * form into a membership oracle, and an unknown address is not an error the caller can fix.
 *
 * **A password change ends sessions.** A reset ends all of them, because the reason to reset is
 * usually that someone else may hold the old one, and a valid token left alive would outlive the
 * password it was issued against. A deliberate change keeps the session doing the changing and
 * ends the rest — the member asked for this and should not be signed out of the tab they are in.
 */
class PasswordService
{
    /**
     * Whether the caller is told anything is irrelevant: the answer is always the same.
     *
     * The broker's own per-user throttle (`auth.passwords.users.throttle`) stops a flood of mail
     * to one address; the route's rate limit stops a flood of requests from one caller.
     */
    public function forgot(string $email): void
    {
        /*
         * A suspended account gets no reset link, and the caller is told nothing — the same 202 as
         * every other address. Sending one would hand a suspended member the way back in, and
         * saying "suspended" here would answer, to anyone who typed an address, the question the
         * identical response exists to refuse.
         */
        if (User::where('email', $email)->value('suspended_at') !== null) {
            return;
        }

        Password::sendResetLink(['email' => $email]);
    }

    /**
     * @throws ValidationException when the token is wrong, expired, or issued for another address
     */
    public function reset(array $data): void
    {
        $status = Password::reset(
            ['email' => $data['email'], 'password' => $data['password'],
                'password_confirmation' => $data['password_confirmation'], 'token' => $data['token']],
            function (User $user, string $password): void {
                // A reset is the response to a password that may be in someone else's hands.
                // Every token issued under it goes with it, on every device.
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            // One message for every failure. Distinguishing "expired" from "never existed" tells
            // a caller holding a guessed token which half of the guess was right.
            throw ValidationException::withMessages([
                'token' => 'ลิงก์ตั้งรหัสผ่านใหม่ไม่ถูกต้องหรือหมดอายุแล้ว กรุณาขอลิงก์ใหม่อีกครั้ง',
            ]);
        }
    }

    /**
     * @throws UnauthorizedHttpException when the current password is wrong
     */
    public function change(User $user, string $current, string $password): void
    {
        if (! Hash::check($current, $user->password)) {
            // 401, not a validation error: this is a failed authentication, and answering 422
            // would let a form-error handler treat it as a typo to highlight.
            throw new UnauthorizedHttpException('Bearer', 'Current password is incorrect.');
        }

        $currentToken = $user->currentAccessToken();
        $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
        // The member asked for this from a session they are using; that one survives and the
        // others do not, which is what makes this useful after a shared or borrowed device.
        $user->tokens()->when($currentToken, fn ($query) => $query->whereKeyNot($currentToken->getKey()))->delete();

        event(new PasswordReset($user));
    }
}

<?php

namespace App\Services;

use App\Exceptions\AccountSuspendedException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AuthService
{
    public function register(array $data): array
    {
        $result = DB::transaction(function () use ($data): array {
            $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);

            return $this->token($user, $data['device_name']);
        });

        // Outside the transaction, and unable to fail the registration: the account is already
        // created and usable, nothing is gated on verification, and a mail transport that is down
        // or misconfigured must not cost someone their sign-up. They can ask again from their
        // account page. A failure is recorded without the address, which is personal data.
        $this->sendVerification($result['user']);

        return $result;
    }

    public function login(array $data): array
    {
        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw new UnauthorizedHttpException('Bearer', 'Invalid credentials.');
        }

        /*
         * The credentials are checked first, on purpose.
         *
         * Answering "this account is suspended" to anyone who types an address would turn the
         * sign-in form into a way to ask which addresses hold suspended accounts. Only someone who
         * already proved they hold the password learns the real reason.
         *
         * And it is the real reason, not a generic failure: a suspended member told "invalid
         * credentials" resets their password, finds it still does not work, and resets it again.
         * They need to be told to contact support.
         */
        if ($user->isSuspended()) {
            // Its own exception class, because the API handler replaces the message on every other
            // error with the generic status text — which is right for authentication failures and
            // would have silently swallowed the one 401 that has to be specific.
            throw new AccountSuspendedException('ACCOUNT_SUSPENDED: บัญชีนี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ');
        }

        return $this->token($user, $data['device_name']);
    }

    private function token(User $user, string $device): array
    {
        return ['user' => $user, 'token' => $user->createToken($device)->plainTextToken, 'token_type' => 'Bearer'];
    }

    /**
     * Changing the address clears verification — and now offers a way to earn it back.
     *
     * Clearing it has been correct since M5: the new address is unproven. What was missing was
     * the second half, so the column only ever went one way and a corrected typo was permanent.
     */
    public function update(User $user, array $data): User
    {
        $addressChanged = isset($data['email']) && $data['email'] !== $user->email;
        if ($addressChanged) {
            $user->email_verified_at = null;
        }
        $user->fill($data)->save();

        if ($addressChanged) {
            $this->sendVerification($user);
        }

        return $user;
    }

    private function sendVerification(User $user): void
    {
        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $exception) {
            Log::warning('Verification email could not be sent.',
                ['user_id' => $user->id, 'exception' => $exception::class]);
        }
    }

    public function logout(User $user, bool $all): void
    {
        if ($all) {
            $user->tokens()->delete();
        } else {
            $user->currentAccessToken()?->delete();
        }
    }
}

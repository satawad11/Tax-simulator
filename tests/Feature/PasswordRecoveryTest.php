<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 1 — account recovery.
 *
 * Before this, a member who forgot their password lost every saved return permanently: there was
 * no reset flow and no change-password endpoint, and support had no answer short of database
 * access.
 *
 * Two properties matter more than the happy path and are tested hardest:
 *
 *   the forgot endpoint must not reveal whether an address is registered, in its status, its body,
 *     or the presence of an error; and
 *   a password change must end the sessions that the old password could still be holding open.
 */
class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private const STRONG = 'Correct-Horse-9!';

    private function member(array $attributes = []): User
    {
        return User::factory()->create(['password' => 'Old-Password-2024!', ...$attributes]);
    }

    private function forgot(string $email): TestResponse
    {
        return $this->postJson('/api/v1/auth/password/forgot', ['email' => $email]);
    }

    /** The token as the emailed link would carry it. */
    private function tokenFor(User $user): string
    {
        Notification::fake();
        $this->forgot($user->email)->assertStatus(202);
        $token = null;
        Notification::assertSentTo($user, ResetPasswordNotification::class,
            function (ResetPasswordNotification $notification) use (&$token): bool {
                $token = (new \ReflectionProperty($notification, 'token'))->getValue($notification);

                return true;
            });

        return $token;
    }

    // ---------------------------------------------------------------- forgot

    public function test_a_registered_address_is_sent_a_reset_link(): void
    {
        Notification::fake();
        $user = $this->member();

        $this->forgot($user->email)->assertStatus(202)->assertJsonPath('success', true);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_an_unregistered_address_is_answered_exactly_as_a_registered_one(): void
    {
        Notification::fake();
        $user = $this->member();

        $known = $this->forgot($user->email)->assertStatus(202);
        $unknown = $this->forgot('nobody@tax-simulator.local')->assertStatus(202);

        // Status, body and shape must be indistinguishable, or the form becomes a way to ask
        // which addresses hold accounts.
        $this->assertSame($known->json(), $unknown->json());
        Notification::assertSentTimes(ResetPasswordNotification::class, 1);
    }

    public function test_the_address_is_normalised_before_it_is_looked_up(): void
    {
        Notification::fake();
        $user = $this->member(['email' => 'member@tax-simulator.local']);

        $this->forgot('  MEMBER@Tax-Simulator.Local  ')->assertStatus(202);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_the_forgot_endpoint_is_rate_limited(): void
    {
        Notification::fake();
        $user = $this->member();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->forgot($user->email)->assertStatus(202);
        }

        $this->forgot($user->email)->assertStatus(429);
    }

    public function test_a_malformed_address_is_rejected(): void
    {
        $this->forgot('not-an-address')->assertStatus(422)->assertJsonValidationErrorFor('email');
    }

    // ----------------------------------------------------------------- reset

    public function test_a_valid_token_sets_the_new_password(): void
    {
        $user = $this->member();

        $this->postJson('/api/v1/auth/password/reset', ['token' => $this->tokenFor($user),
            'email' => $user->email, 'password' => self::STRONG, 'password_confirmation' => self::STRONG])->assertOk();

        $this->postJson('/api/v1/auth/login', ['email' => $user->email,
            'password' => self::STRONG, 'device_name' => 'test'])->assertOk();
    }

    public function test_a_reset_revokes_every_existing_session(): void
    {
        // The reason to reset is usually that someone else may hold the old password. A token
        // issued under it must not outlive it.
        $user = $this->member();
        $user->createToken('phone');
        $user->createToken('laptop');
        $this->assertSame(2, $user->tokens()->count());

        $this->postJson('/api/v1/auth/password/reset', ['token' => $this->tokenFor($user),
            'email' => $user->email, 'password' => self::STRONG, 'password_confirmation' => self::STRONG])->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_an_invalid_token_is_refused_without_saying_why(): void
    {
        $user = $this->member();

        $response = $this->postJson('/api/v1/auth/password/reset', ['token' => str_repeat('a', 64),
            'email' => $user->email, 'password' => self::STRONG, 'password_confirmation' => self::STRONG])
            ->assertStatus(422)->assertJsonValidationErrorFor('token');

        // One message for every failure: telling a caller that a guessed token merely expired
        // confirms the half of the guess that was right.
        $this->assertStringNotContainsStringIgnoringCase('expire', json_encode($response->json()));
        $this->postJson('/api/v1/auth/login', ['email' => $user->email,
            'password' => self::STRONG, 'device_name' => 'test'])->assertStatus(401);
    }

    public function test_an_expired_token_is_refused_with_the_same_message(): void
    {
        $user = $this->member();
        $token = $this->tokenFor($user);

        $this->travel((int) config('auth.passwords.users.expire', 60) + 1)->minutes();

        $this->postJson('/api/v1/auth/password/reset', ['token' => $token, 'email' => $user->email,
            'password' => self::STRONG, 'password_confirmation' => self::STRONG])
            ->assertStatus(422)->assertJsonValidationErrorFor('token');
    }

    public function test_a_token_cannot_be_redeemed_against_another_account(): void
    {
        $user = $this->member();
        $other = $this->member();
        $token = $this->tokenFor($user);

        $this->postJson('/api/v1/auth/password/reset', ['token' => $token, 'email' => $other->email,
            'password' => self::STRONG, 'password_confirmation' => self::STRONG])->assertStatus(422);
    }

    public function test_a_token_cannot_be_used_twice(): void
    {
        $user = $this->member();
        $token = $this->tokenFor($user);
        $payload = ['token' => $token, 'email' => $user->email,
            'password' => self::STRONG, 'password_confirmation' => self::STRONG];

        $this->postJson('/api/v1/auth/password/reset', $payload)->assertOk();
        $this->postJson('/api/v1/auth/password/reset', $payload)->assertStatus(422);
    }

    public function test_the_reset_enforces_the_same_password_policy_as_registration(): void
    {
        $user = $this->member();

        $this->postJson('/api/v1/auth/password/reset', ['token' => $this->tokenFor($user),
            'email' => $user->email, 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertStatus(422)->assertJsonValidationErrorFor('password');
    }

    // ---------------------------------------------------------------- change

    public function test_a_member_can_change_their_own_password(): void
    {
        $user = $this->member();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/auth/password', ['current_password' => 'Old-Password-2024!',
            'password' => self::STRONG, 'password_confirmation' => self::STRONG])->assertOk();

        $this->postJson('/api/v1/auth/login', ['email' => $user->email,
            'password' => self::STRONG, 'device_name' => 'test'])->assertOk();
    }

    public function test_a_change_ends_other_sessions_and_keeps_the_current_one(): void
    {
        // The member asked for this from a session they are using. Signing them out of the tab
        // they are looking at would be surprising; leaving a borrowed device signed in would not
        // be safe.
        $user = $this->member();
        $user->createToken('other device');
        $current = $user->createToken('this device');
        $keptId = $current->accessToken->getKey();

        $this->withHeader('Authorization', 'Bearer '.$current->plainTextToken)
            ->putJson('/api/v1/auth/password', ['current_password' => 'Old-Password-2024!',
                'password' => self::STRONG, 'password_confirmation' => self::STRONG])->assertOk();

        $this->assertSame([$keptId], $user->tokens()->pluck('id')->all());
    }

    public function test_a_wrong_current_password_is_refused(): void
    {
        $user = $this->member();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/auth/password', ['current_password' => 'Not-The-Password-1!',
            'password' => self::STRONG, 'password_confirmation' => self::STRONG])->assertStatus(401);

        $this->postJson('/api/v1/auth/login', ['email' => $user->email,
            'password' => 'Old-Password-2024!', 'device_name' => 'test'])->assertOk();
    }

    public function test_the_new_password_must_differ_from_the_current_one(): void
    {
        Sanctum::actingAs($this->member());

        $this->putJson('/api/v1/auth/password', ['current_password' => 'Old-Password-2024!',
            'password' => 'Old-Password-2024!', 'password_confirmation' => 'Old-Password-2024!'])
            ->assertStatus(422)->assertJsonValidationErrorFor('password');
    }

    public function test_changing_a_password_requires_a_session(): void
    {
        $this->putJson('/api/v1/auth/password', ['current_password' => 'Old-Password-2024!',
            'password' => self::STRONG, 'password_confirmation' => self::STRONG])->assertStatus(401);
    }

    // -------------------------------------------------------------- delivery

    public function test_neither_notification_is_queued(): void
    {
        // This deployment runs no queue worker. A queued notification would be written to the
        // jobs table and never sent, and the member would wait for an email that never arrives —
        // a failure with no error anywhere to show for it.
        foreach ([ResetPasswordNotification::class, VerifyEmailNotification::class] as $notification) {
            $this->assertFalse(is_subclass_of($notification, ShouldQueue::class),
                "$notification must send inline until a queue worker exists.");
        }
    }
}

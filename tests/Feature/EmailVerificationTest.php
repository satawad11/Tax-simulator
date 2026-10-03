<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 1 — closing the `email_verified_at` trap.
 *
 * `AuthService::update()` has cleared the column on an address change since M5 and nothing ever
 * set it again, so a member who corrected a typo in their email was permanently unverified with
 * no way back. Harmless only while nothing consulted the column — and password recovery is
 * exactly the kind of thing that eventually would.
 *
 * The fix is a way to become verified, **not** a gate. Every test here that asserts nothing is
 * blocked is asserting the deliberate absence of a product decision nobody has made yet.
 */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function verificationUrl(User $user, ?string $email = null): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addHour(),
            ['id' => $user->getKey(), 'hash' => sha1($email ?? $user->getEmailForVerification())]);
    }

    public function test_registration_sends_a_verification_link(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', ['name' => 'สมชาย', 'email' => 'new@tax-simulator.local',
            'password' => 'Correct-Horse-9!', 'password_confirmation' => 'Correct-Horse-9!',
            'device_name' => 'test'])->assertCreated();

        Notification::assertSentTo(User::where('email', 'new@tax-simulator.local')->sole(), VerifyEmailNotification::class);
    }

    public function test_a_mail_failure_does_not_cost_the_member_their_registration(): void
    {
        // Nothing is gated on verification, so a transport that is down or unconfigured must not
        // turn a sign-up into an error. They can ask for the link again.
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('transport down'));

        $this->postJson('/api/v1/auth/register', ['name' => 'สมหญิง', 'email' => 'resilient@tax-simulator.local',
            'password' => 'Correct-Horse-9!', 'password_confirmation' => 'Correct-Horse-9!',
            'device_name' => 'test'])->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'resilient@tax-simulator.local']);
    }

    public function test_a_signed_link_verifies_the_address(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get($this->verificationUrl($user))->assertOk();

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_an_unsigned_link_is_refused(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get("/email/verify/{$user->getKey()}/".sha1($user->email))->assertStatus(403);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_a_tampered_signature_is_refused(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get($this->verificationUrl($user).'x')->assertStatus(403);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_an_expired_link_is_refused(): void
    {
        $user = User::factory()->unverified()->create();
        $url = $this->verificationUrl($user);

        $this->travel(2)->hours();

        $this->get($url)->assertStatus(403);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_a_link_issued_for_a_previous_address_no_longer_works(): void
    {
        // The hash is of the address the link was issued for. Changing the address again is what
        // makes every link already sent for the old one stale — which is the point.
        $user = User::factory()->unverified()->create(['email' => 'before@tax-simulator.local']);
        $staleUrl = $this->verificationUrl($user, 'before@tax-simulator.local');
        $user->forceFill(['email' => 'after@tax-simulator.local'])->save();

        $this->get($staleUrl)->assertOk();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_changing_the_email_clears_verification_and_sends_a_new_link(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->assertNotNull($user->email_verified_at);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/auth/me', ['email' => 'moved@tax-simulator.local'])->assertOk();

        $this->assertNull($user->fresh()->email_verified_at);
        Notification::assertSentTo($user->fresh(), VerifyEmailNotification::class);
    }

    public function test_changing_only_the_name_keeps_verification_and_sends_nothing(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/auth/me', ['name' => 'ชื่อใหม่'])->assertOk();

        $this->assertNotNull($user->fresh()->email_verified_at);
        Notification::assertNothingSent();
    }

    public function test_a_member_can_ask_for_the_link_again(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/email/resend')->assertStatus(202)
            ->assertJsonPath('data.verified', false);

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_asking_again_when_already_verified_is_not_an_error(): void
    {
        Notification::fake();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/auth/email/resend')->assertStatus(202)
            ->assertJsonPath('data.verified', true);

        Notification::assertNothingSent();
    }

    public function test_resending_requires_a_session(): void
    {
        // Otherwise it would mail anyone's address on request, and confirm which ones exist.
        $this->postJson('/api/v1/auth/email/resend')->assertStatus(401);
    }

    public function test_an_unverified_member_is_not_locked_out_of_anything(): void
    {
        // Verification is a capability, not a gate. Adding one is a product decision, and this
        // test exists so it cannot be made by accident.
        $user = User::factory()->unverified()->create(['password' => 'Old-Password-2024!']);

        $token = $this->postJson('/api/v1/auth/login', ['email' => $user->email,
            'password' => 'Old-Password-2024!', 'device_name' => 'test'])->assertOk()->json('data.token');

        $this->withHeader('Authorization', "Bearer $token")->getJson('/api/v1/auth/me')->assertOk();
        $this->withHeader('Authorization', "Bearer $token")->getJson('/api/v1/tax-returns')->assertOk();
    }
}

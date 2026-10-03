<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthenticationApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function registration(): array
    {
        return ['name' => 'Synthetic Member', 'email' => 'member@example.test', 'password' => 'SyntheticPassword123!',
            'password_confirmation' => 'SyntheticPassword123!', 'device_name' => 'Test device'];
    }

    public function test_register_hashes_password_and_exposes_only_public_user_and_plain_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->registration())->assertCreated()
            ->assertJsonPath('success', true)->assertJsonPath('data.user.email', 'member@example.test');
        $user = User::firstOrFail();
        $this->assertTrue(Hash::check('SyntheticPassword123!', $user->password));
        $this->assertSame('member', $user->role);
        // The payload is a closed list. M9.1 added `is_admin` so the browser can decide whether to
        // offer the admin console link, and Phase 4 added `email_verified` so the account page can
        // report the state and offer the link again; `role`, `password` and the timestamps stay
        // internal. A boolean, not the verification timestamp: when it happened is of no use to a
        // reader, and nothing in the product is gated on it.
        $this->assertSame(['id', 'name', 'email', 'is_admin', 'email_verified'],
            array_keys($response->json('data.user')));
        $this->assertFalse($response->json('data.user.is_admin'));
        $this->assertNotNull(PersonalAccessToken::findToken($response->json('data.token')));
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_duplicate_email_and_privilege_injection_are_rejected(): void
    {
        User::factory()->create(['email' => 'member@example.test']);
        $this->postJson('/api/v1/auth/register', $this->registration())->assertUnprocessable()->assertJsonValidationErrors('email');
        $payload = $this->registration();
        $payload['email'] = 'other@example.test';
        $payload['role'] = 'admin';
        $this->postJson('/api/v1/auth/register', $payload)->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_login_me_update_and_token_revocation(): void
    {
        $user = User::factory()->create(['email' => 'member@example.test', 'password' => 'SyntheticPassword123!']);
        $login = ['email' => $user->email, 'password' => 'SyntheticPassword123!', 'device_name' => 'Phone'];
        $token = $this->postJson('/api/v1/auth/login', $login)->assertOk()->json('data.token');
        $other = $user->createToken('Other')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.id', $user->id);
        $this->withToken($token)->putJson('/api/v1/auth/me', ['name' => 'Renamed', 'email' => 'renamed@example.test'])
            ->assertOk()->assertJsonPath('data.name', 'Renamed');
        $this->assertNull($user->fresh()->email_verified_at);
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->assertNull(PersonalAccessToken::findToken($token));
        $this->assertNotNull(PersonalAccessToken::findToken($other));
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($other)->postJson('/api/v1/auth/logout-all')->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();
        $this->withToken($other)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_invalid_credentials_and_missing_token_return_401(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', ['email' => 'missing@example.test', 'password' => 'incorrect', 'device_name' => 'Test'])
            ->assertUnauthorized()->assertJsonPath('message', 'Unauthorized');
    }

    public function test_registration_rejects_invalid_confirmation_and_overlong_multibyte_password(): void
    {
        $payload = $this->registration();
        $payload['password_confirmation'] = 'Mismatch';
        $this->postJson('/api/v1/auth/register', $payload)->assertUnprocessable()->assertJsonValidationErrors('password');
        $payload['password'] = $payload['password_confirmation'] = str_repeat('ก', 25).'Ab1!';
        $this->postJson('/api/v1/auth/register', $payload)->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertDatabaseCount('users', 0);
    }
}

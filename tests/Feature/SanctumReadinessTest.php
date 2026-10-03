<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class SanctumReadinessTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_users_can_issue_and_revoke_device_tokens(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('test-device');

        $this->assertTrue(PersonalAccessToken::findToken($token->plainTextToken)->tokenable->is($user));
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'test-device', 'tokenable_id' => $user->id]);

        $user->tokens()->delete();

        $this->assertNull(PersonalAccessToken::findToken($token->plainTextToken));
    }
}

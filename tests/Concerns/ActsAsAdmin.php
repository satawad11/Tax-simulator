<?php

namespace Tests\Concerns;

use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * Making an administrator, and switching who a request is from.
 *
 * Seven test classes each wrote `User::factory()->create()` followed by
 * `forceFill(['role' => User::ROLE_ADMIN])->save()`, and one of them wrote it three times. The
 * duplication is harmless until the shape changes — and it just did: `users` gained
 * `suspended_at`, so "an administrator who can actually use the console" is now two conditions
 * rather than one, and seven copies would have had to learn that separately.
 */
trait ActsAsAdmin
{
    /**
     * `role` is forced rather than passed to the factory on purpose: it is deliberately not
     * mass-assignable, so nothing — not even a test — can set it the easy way.
     */
    protected function admin(array $attributes = []): User
    {
        $admin = User::factory()->create($attributes);
        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();

        return $admin;
    }

    protected function actingAsAdmin(array $attributes = []): User
    {
        $admin = $this->admin($attributes);
        Sanctum::actingAs($admin);

        return $admin;
    }

    protected function actingAsMember(array $attributes = []): User
    {
        $member = User::factory()->create($attributes);
        Sanctum::actingAs($member);

        return $member;
    }

    /**
     * Drops the resolved user so the next request is authenticated by its own bearer token.
     *
     * Laravel's guard caches the user it resolved, and the application is rebuilt between test
     * methods rather than between requests inside one. So the first authenticated call in a test
     * fixes who every later call is, whatever `Authorization` header it carries — which makes a
     * revoked token look like it still works, or a suspended account look like it can still sign
     * in, the exact opposite of what those tests assert.
     *
     * Five test classes had learned this the hard way and only one wrote down why.
     */
    protected function forgetAuthenticatedUser(): void
    {
        $this->app['auth']->forgetGuards();
    }
}

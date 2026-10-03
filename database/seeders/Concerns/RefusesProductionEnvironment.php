<?php

namespace Database\Seeders\Concerns;

use RuntimeException;

/**
 * Milestone 09.1 — the guard that keeps synthetic development data out of production.
 *
 * A development seeder exists to make a developer's machine usable: it creates known accounts
 * and demo tax returns. Neither belongs in a real deployment, so the refusal is a runtime
 * check rather than a comment: `php artisan db:seed --class=DevelopmentAccountSeeder` on a
 * production host stops with an exception before it touches a single row.
 */
trait RefusesProductionEnvironment
{
    /**
     * @throws RuntimeException when the current environment is not an approved development one.
     */
    protected function assertDevelopmentEnvironment(): void
    {
        $approved = (array) config('seeding.development_environments', ['local']);
        $environment = (string) app()->environment();

        if (in_array($environment, $approved, true)) {
            return;
        }

        throw new RuntimeException(
            static::class.' seeds synthetic development data and refuses to run in the "'.$environment
            .'" environment. It is permitted only in: '.implode(', ', $approved).'.'
        );
    }
}

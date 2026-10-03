<?php

/**
 * Milestone 09.1 — configuration for the development seeders.
 *
 * The passwords the development accounts are created with live here rather than being read
 * with env() inside the seeder, for the two usual reasons: a config-cached deployment still
 * resolves them, and a test can override them without touching process environment.
 *
 * There is deliberately no default. DevelopmentAccountSeeder fails with a clear message when a
 * value is empty rather than inventing a well-known password, and it refuses to run outside an
 * approved development environment in the first place.
 */
return [

    'development_environments' => ['local'],

    'development_accounts' => [
        'admin_password' => env('DEV_ADMIN_PASSWORD'),
        'member_password' => env('DEV_USER_PASSWORD'),
    ],

];

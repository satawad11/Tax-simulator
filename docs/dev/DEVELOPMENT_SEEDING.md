# Development database seeding

Milestone 09.1 seeds the current MySQL development database without rebuilding the schema or deleting existing rows.

## Required environment

Set these values in the private `.env` file:

```dotenv
APP_ENV=local
DEV_ADMIN_PASSWORD=
DEV_USER_PASSWORD=
```

Choose local passwords for the two blank values. There are no defaults. The seeders hash the values and never print them. `DevelopmentAccountSeeder` and `DevelopmentDemoDataSeeder` refuse to run outside the explicitly approved `local` environment.

## Safe commands

Run the normal additive migration and the named seeders:

```sh
docker compose exec app php artisan migrate --no-interaction
docker compose exec app php artisan db:seed --class=ReferenceDataSeeder --no-interaction
docker compose exec app php artisan db:seed --class=TaxBaselineSeeder --no-interaction
docker compose exec app php artisan db:seed --class=CmsInitialDataSeeder --no-interaction
docker compose exec app php artisan db:seed --class=DevelopmentAccountSeeder --no-interaction
docker compose exec app php artisan db:seed --class=DevelopmentDemoDataSeeder --no-interaction
```

Do not use `migrate:fresh` for a populated development database. `ReferenceDataSeeder` reuses approved reference seeders, `TaxBaselineSeeder` only verifies published version 2568.1, CMS records use stable slugs, and demo records are scoped to the seeded member with names beginning `[DEMO]`. Rerunning the sequence adds no duplicate accounts, posts, returns, calculations, or scenarios and does not delete unrelated data.

## Development accounts

| Email | Role | Purpose |
|---|---|---|
| `admin@tax-simulator.local` | admin | Admin console and CMS/rule-source API checks |
| `user@tax-simulator.local` | member | Member dashboard, saved drafts, history, and planning checks |

Use the passwords set in the environment. Never place their actual values in source files, documentation, HTML, logs, or screenshots.

The member receives two marked drafts, one completed simulation with calculation history, and one planning scenario. These synthetic records are created through the existing application services. The admin receives seeded published content plus one draft article for CMS testing.

These development seeders are not deployment seeders. A production or staging process must use only the reference data appropriate to that environment and must never bypass the environment guard.

## Changing a development account password

The compose file passes `.env` to the app container with `env_file`, so the values become real
process environment variables at container-start time — and Laravel's Dotenv does not overwrite a
variable that is already set in the environment. Editing `.env` and clearing the config cache is
therefore **not** enough; the container has to be recreated before it sees the new value:

```sh
docker compose up -d --force-recreate app
docker compose exec app php artisan db:seed --class=DevelopmentAccountSeeder --force
```

The seeder looks the accounts up by email, so this replaces the stored hash rather than creating
a second account. Note that this project's app container has no bind mount for application code:
after a recreate, re-copy any files you had placed in the container by hand.

## When demo data drifts

`DevelopmentDemoDataSeeder` is deliberately non-destructive: it creates a `[DEMO]` record only if
one with that name does not already exist, and never rewrites one that does. That means a
developer's edits survive a rerun — and also that a demo record someone has since completed,
edited or calculated against stays that way.

To get the documented starting state back, delete the seeder's own records and rerun it. Only
rows belonging to the seeded member and named with the `[DEMO]` marker are involved:

```sh
docker compose exec app php artisan tinker --execute="\$m = App\Models\User::where('email','user@tax-simulator.local')->firstOrFail(); \$m->taxReturns()->where('name','like','[DEMO]%')->each(function (\$r) { \$r->scenarios()->delete(); \$r->calculations()->each->delete(); \$r->forceDelete(); });"
docker compose exec app php artisan db:seed --class=DevelopmentDemoDataSeeder --force
```

Never run this against a database holding real user data.

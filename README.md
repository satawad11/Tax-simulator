# tax-simulator

Laravel 13 application for a Thai Personal Income Tax Simulation Platform.
PHP 8.4, MySQL 8.4, Nginx, Node.js 24, Tailwind CSS 4 and Vite run through Docker Compose.
The source-backed tax year 2568 PND90/PND91 baseline, Sanctum Member accounts, history,
planning, content and administration are implemented through the versioned REST API.

## Start

Docker Desktop with Linux containers and Docker Compose v2.32+ are required. No host PHP, Composer, or Node installation is needed.

1. Copy `.env.example` to `.env` if it does not exist.
2. Set local database passwords, run `docker compose build`, and generate an application key:
   `docker compose run --rm --no-deps app php artisan key:generate --show` (after building).
   Copy the resulting `base64:...` value into `APP_KEY` in the host `.env`.
3. Run:

```sh
docker compose build
docker compose up -d --wait
docker compose exec app php artisan migrate
docker compose exec app php artisan test
```

The initial workspace already has a generated application key and local passwords.
Keep `.env` private. These services are configured for local development.

- App: http://localhost:8088
- REST API 
- Vite development server: http://localhost:5178

### Milestone 09 web UI

Public URLs: `/`, `/tax-simulator`, `/tax-simulator/pnd90`, `/tax-simulator/pnd91`, `/knowledge`, `/news`, `/faq`, `/login`, and `/register`.

Member URLs: `/dashboard`, `/dashboard/tax-returns`, `/dashboard/tax-returns/{id}`, `/dashboard/tax-returns/{id}/history`, and `/dashboard/tax-returns/{id}/planning`.

The member Web client uses the existing Sanctum token APIs and keeps its token in browser `sessionStorage`. Guest financial inputs also use session-only storage and are never persisted until the user explicitly saves them to a Member account. Build assets with `docker compose exec node npm run build`; run the live stack with `docker compose up -d --build`.

### Milestone 09.1 development data

The approved mockup reconciliation and safe development seeding workflow are documented in
[`docs/ui/M9_1_MOCKUP_RECONCILIATION.md`](docs/ui/M9_1_MOCKUP_RECONCILIATION.md) and
[`docs/dev/DEVELOPMENT_SEEDING.md`](docs/dev/DEVELOPMENT_SEEDING.md). The development accounts use
`admin@tax-simulator.local` and `user@tax-simulator.local`; their passwords come only from the
private `DEV_ADMIN_PASSWORD` and `DEV_USER_PASSWORD` environment values.

Seeding is additive and repeatable. Run `php artisan migrate`, then the named reference, baseline,
CMS, account, and demo seeders from the development-seeding guide. Do not use `migrate:fresh` on a
populated development database. The baseline check never writes rule version 2568.1, and demo tax
returns are scoped to the development member and marked with `[DEMO]`.

Change `APP_PORT`, `APP_URL`, and `VITE_PORT` in `.env` together if needed.
The database hostname is `mysql`, its internal port is 3306, and it is not published to the host.

## Development

Run `docker compose watch` in a separate terminal while editing. Source files are synchronized
into the containers, with Vite hot reload for Blade/CSS/JavaScript. This works even when
the workspace drive cannot be bind-mounted by Docker Desktop. Composer and npm lockfiles
are committed inputs for repeatable dependency installation. Restart Watch after changing its configuration.

Without Watch, apply changes with `docker compose up -d --build`.
Run Artisan/Composer commands with `docker compose exec app ...`, and npm commands with
`docker compose exec node ...`. Files generated inside containers must be copied back with
`docker compose cp`; Watch synchronizes host changes into containers only.

For example, after changing Composer dependencies in the app container:

```sh
docker compose cp app:/var/www/html/composer.json ./composer.json
docker compose cp app:/var/www/html/composer.lock ./composer.lock
docker compose up -d --build
```

Use the same approach for `package.json` and `package-lock.json` after npm dependency changes.
A source rebuild installs dependencies from the lockfiles.
For public static files, copy changes to the shared public directory with
`docker compose cp public/. app:/var/www/html/public`.

## Architecture and checks

- `routes/api.php`: stateless REST API routes, prefixed with `/api/v1` in `bootstrap/app.php`.
- `app/Http/Controllers/Api/V1`: versioned API controllers for web and future mobile clients.
- `routes/web.php`: Blade web routes.
- `resources/views`, `resources/css`, `resources/js`: Blade, Tailwind, and JavaScript client.
- `GET /api/v1/health`: public application liveness response:
  `{"success":true,"message":null,"data":{"status":"ok","service":"tax-simulator-api"}}`.
  This endpoint does not query MySQL; Compose checks database readiness separately.
- API errors use the success/message/errors envelope without debug details, even without an Accept header.
- Default Laravel user/cache/job migrations and Sanctum personal_access_tokens are retained alongside the Milestone 02 domain tables.
- PHPUnit explicitly overrides container environment settings and uses isolated in-memory SQLite,
  never the development MySQL database.
- Feature tests cover health JSON, statelessness, API errors, and the web page.

```sh
docker compose exec app php artisan migrate:status
docker compose exec app php artisan route:list --path=api
docker compose exec app php artisan test
docker compose exec node npm run build
docker compose ps
```

## Persistence and stopping

MySQL data is persisted in the `tax-simulator_mysql_data` named Docker volume.
`docker compose down` stops/removes containers while preserving database data.
`docker compose down -v` deletes volumes and all development database data.
Changing database passwords in `.env` does not change users in an already initialized volume.

The `public_assets` volume shares Vite's hot-file and compiled assets among Node, PHP and Nginx.
Application source lives on the host; the container copies are disposable.
This Compose configuration runs the Vite development server and is not a production deployment.

## Production readiness

Milestone 10 operating procedures are in [`docs/ops/DEPLOYMENT.md`](docs/ops/DEPLOYMENT.md),
including `APP_ENV=production`, `APP_DEBUG=false`, HTTPS/cookie settings, safe migration/seeding,
cache commands and smoke checks. Before deployment, follow the backup, production seeding and
rollback guides in `docs/ops`. Privacy behavior, the release checklist and release scope are also
documented there and in [`docs/releases/RELEASE_1_0.md`](docs/releases/RELEASE_1_0.md).

Member and admin bearer tokens use browser `sessionStorage`, are cleared on logout, and are
revoked through Sanctum. They remain accessible to JavaScript, so the markup-rejecting CMS,
escaped rendering and production CSP are security boundaries. Production must terminate TLS and
trust forwarded headers only from known proxies. Public `/api/v1/health` is liveness only; monitor
MySQL separately for readiness.

## Milestone 01 compliance

The Compose definition is `docker-compose.yml` (renamed from `compose.yaml` without changing the project or volume names).
The application uses Laravel 13 with PHP 8.4, and the MySQL development account is `tax_user`.
The local application key and generated passwords are retained in the ignored `.env`.

Port 8088 is retained because 8080 was occupied during the initial setup. Vite uses 5178.
The approved reference is `docs/ui-reference/tax-simulator-mockup.png`. The temporary Thai home
page uses its blue/white direction, rounded cards and navigation; navigation placeholders are
disabled until later milestones. The mobile menu is the only interactive UI behavior.

Verification commands (run from this directory):

```sh
docker compose config
docker compose up -d --build
docker compose exec app php artisan migrate
docker compose exec app php artisan test
docker compose exec node npm install
docker compose exec node npm run build
```

Composer runs in `app`; npm runs in `node`. Docker image builds use `npm ci`.
`app/DTO/Tax`, `app/Services/Tax` and JS module directories contain placeholders only.
Sanctum configuration and token persistence are ready; login, registration and tax functionality remain outside M1.

## Milestone 02 database foundation

Three grouped migrations create 27 domain tables: 12 tax master, 11 tax transaction,
and four content tables. Each has an unsigned BIGINT primary key. Money uses
DECIMAL(15,2); rates and multipliers use DECIMAL(7,4). Decimal model casts return
strings. Foreign keys, composite keys and unique indexes protect year/version/form
consistency, one-to-one profiles, mappings and master codes. Tax returns and content
posts use soft deletes. All domain models expose typed Eloquent relationships.

The separate approved ER diagram was not present in the supplied repository. The
schema was derived from PROJECT_REQUIREMENTS.md and the Milestone 02 table list
after the instruction to continue. Column choices should be reconciled with that
diagram when available. Form codes are unique within a year; rule codes within a
version. Saved returns belong to users. Future guest calculations remain transient
and do not need a tax_return record. No calculation services or new UI were added.

Seeders create year 2568, PND90/PND91, SECTION_40_1 through SECTION_40_8, nine form
mappings, published version 2568.1 and 19 allowance master codes (17 approved plus two retained legacy codes). They are repeatable
and do not create users or saved returns. TaxBracketSeeder inserts the eight
user-approved 2568 brackets, with percentage rates from 0 to 35, and then publishes
2568.1 in the same transaction. The final upper bound is null (unlimited).
Repeat seeding leaves the published version unchanged. Other quantitative rule
tables remain empty pending verification. Further rules require a new draft version
because 2568.1 is immutable. No calculation services have been implemented.

Published versions and their six child rule models reject edits, deletions and new
child rules through guarded Eloquent persistence, including quiet writes. Locks
serialize publication and rule changes; bulk mutation APIs are disabled. Rule edits
must use individual model saves/deletes. Raw SQL, DB query builders and the internal
guard bypass are not application editing interfaces and must not be used for rules.
Corrections require a new draft version; this is application-level protection, not
a database trigger or database-user permission boundary.

Recreate the development schema and seed it (deletes existing development data):

```sh
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan test
```

The standard test suite uses in-memory SQLite. Both PHPUnit environment and server
variables are forced because Docker-provided server variables can otherwise override
test settings. TestCase refuses to run against any database except in-memory SQLite
or the dedicated tax_simulator_test MySQL database.

For MySQL storage/constraint verification, create the isolated database using the
existing container credentials, then run PHPUnit directly (PowerShell):

```powershell
Get-Content docker/mysql/create-testing-database.sql -Raw | docker compose exec -T mysql sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot'
docker compose exec app vendor/bin/phpunit -c phpunit.mysql.xml
```

The MySQL database uses the existing tax_user account and persists in the MySQL
volume. The storage-type test is deliberately skipped on SQLite. Five factories
support relational fixtures. Tests cover the 27-table schema, seeds, form mappings,
relationships, uniqueness, foreign keys, soft deletes and published-rule protection.
Milestone 03 public metadata endpoints and form selection are implemented.
See [Milestone 03 API documentation](docs/api/MILESTONE_03_API.md) for routes,
requests, responses, publication rules and existing schema limitations.
Milestone 04 remains unimplemented.

See [schema reconciliation](docs/SCHEMA_RECONCILIATION.md) for the additive corrective migration, preserved data, and compatibility differences.

## Milestone 08 — content, CMS and tax administration

### Public content endpoints

No authentication; only published content is ever returned.

```
GET /api/v1/content/articles          ?type= &category= &tag= &tax_year= &q= &page= &per_page=
GET /api/v1/content/articles/{slug}
GET /api/v1/content/featured
GET /api/v1/content/faqs              ?category= &tax_year=
GET /api/v1/content/categories        /api/v1/content/categories/{slug}
GET /api/v1/content/tags              /api/v1/content/tags/{slug}
```

Web pages: `/knowledge`, `/news`, `/faq`, `/article/{slug}`.

Content bodies are **structured plain text**, not HTML — the API refuses markup and the views
render escaped text. See [docs/admin/CONTENT_CMS.md](docs/admin/CONTENT_CMS.md).

### Admin access

Administration lives under `/api/v1/admin/*`, behind the existing Sanctum authentication plus an
admin check: **401** unauthenticated, **403** for a member.

`users.role` defaults to `member` and is deliberately not mass-assignable, so no registration or
profile request can grant it. Promote an account explicitly:

```bash
docker compose exec app php artisan tinker --execute="App\Models\User::where('email','you@example.com')->firstOrFail()->forceFill(['role'=>'admin'])->save();"
```

Then sign in at `/admin/login`. The console pages are `/admin`, `/admin/content`,
`/admin/tax-rule-versions` and `/admin/tax-sources`; every write they perform goes through the
admin API.

### Rule-version workflow

Published rule versions are **immutable**. Rule version `2568.1` — the Milestone 7.x baseline —
is published and cannot be edited in place, through the API or through the models.

```
clone a published version  →  edit the draft  →  validate  →  publish
```

Publishing archives the tax year's previous published version so exactly one remains selectable.
It never moves a saved return onto the new version and never rewrites a stored calculation
snapshot: existing simulations stay tied to the rules they were calculated under.

Full detail: [docs/admin/TAX_RULE_VERSION_LIFECYCLE.md](docs/admin/TAX_RULE_VERSION_LIFECYCLE.md)
and [docs/admin/TAX_RULE_ADMINISTRATION.md](docs/admin/TAX_RULE_ADMINISTRATION.md).
API reference: [docs/api/MILESTONE_08_API.md](docs/api/MILESTONE_08_API.md).

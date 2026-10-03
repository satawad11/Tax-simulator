# Production readiness checklist

Run the read-only repository/database preflight first:

```sh
php artisan ops:production-readiness --repository-only
```

Run the full command inside the final production environment before traffic is enabled:

```sh
php artisan ops:production-readiness
```

The full command requires production mode, debug off, an HTTPS application URL, a present
application key whose value is never printed, secure session cookies, MySQL connectivity, no
pending migration files, and exactly one published 2568 baseline (`2568.1`) with its expected
rule-row counts. The command is read-only.

| Check | Status | Operator action |
|---|---|---|
| Production environment and unique `APP_KEY` | EXTERNAL | Set deployment secrets outside the image; record only the key identifier/rotation date |
| `APP_ENV=production`, `APP_DEBUG=false`, HTTPS `APP_URL` | EXTERNAL | Full readiness command must pass in the deployed environment |
| Database credentials and private network | EXTERNAL | Configure deployment secret store/firewall; never paste values into evidence |
| Safe migrations | PASS | Back up, then run `migrate --force`; no `migrate:fresh` |
| Reference seeding and 2568.1 verification | PASS | Run production seeding guide |
| Development/demo seeding excluded | PASS | Environment guards and named seeders |
| HTTPS, secure/SameSite cookies, known proxy trust | PASS | Configure at deployment perimeter |
| Logging without secrets/full financial payloads | PASS | Ship and retain logs per operator policy |
| Backup and isolated restore drill | PASS | Complete before traffic cutover |
| Liveness and DB readiness monitoring | PASS | Monitor app health plus MySQL health separately |
| Admin account provisioned securely | PASS | Create/promote named operator; do not use demo account |
| Published rule version 2568.1 present | PASS | Verify after migration/seeding |
| Frontend production build | PASS | Build from lockfile |
| SQLite/MySQL/security regression | PASS | Required before release |
| Production cache commands | PASS | Run in final environment |
| Public/Member/Admin smoke test | PASS | Repeat after deployment |
| Rollback images/assets/database plan | PASS | Record previous release and backup |
| External browser/device coverage | BLOCKED | Complete `EXTERNAL_BROWSER_DEVICE_CHECKLIST.md` when the environments are available |

Repository-side checks are complete. Production-only rows remain external until the operator
records evidence from the real environment; they must not be represented as locally verified.

## Secret checklist

- Store `APP_KEY`, database credentials, mail credentials and third-party tokens in the deployment
  secret store, outside images and source control.
- Use separate production values and least-privilege database accounts; restrict MySQL to the
  private network.
- Verify log/error tooling redacts authorization headers, cookies, credentials and complete tax
  payloads.
- Record secret owner, key identifier, creation/rotation date and next rotation date without
  recording the secret itself.

## Monitoring handoff

Assign an owner and evidence link for each signal before cutover: `/api/v1/health`, MySQL
readiness, 5xx and 429 rates, login/authorization failures, calculation exceptions, rule publish
audit events, p95 latency, disk space, backup job status and restore-drill recency. Record alert
destination, threshold approved by operations, and acknowledgement/escalation owner. No threshold
is invented by this repository.

Form fidelity and the field-role audit were completed before the final release verification. The final M10 re-run confirmed that the M9.2 and M9.2.1 changes preserve the existing production-readiness controls.

## Final M10 re-run

Re-run after Milestone 09.1 (UI reconciliation and development seeding) together with M9.2 /
M9.2.1. All rows above were re-verified and none changed status, with one correction: the browser
smoke row's evidence command could not execute in this environment until the node image was given
a runnable Chromium (Playwright's own build is glibc-linked; the image is Alpine). That is now
fixed in the Dockerfile and the procedure is recorded in `docs/qa/RELEASE_TEST_MATRIX.md`.

`php artisan ops:production-readiness --repository-only` passes: PHP 8.4.25, MySQL reachable, 20
migrations applied, exactly one published 2568 baseline (`2568.1`), and the expected rule-row
counts (8 brackets, 70 expense rules, 15 allowance rules, 2 donation rules, 4 recommendation
rules). The command performed no mutation.

External browser and device coverage remains BLOCKED and is the only non-PASS row; it is an
environment gate, not a code defect.

---

## New deployment prerequisites — Phase 1 (2026-09-15)

Account recovery introduces the first outbound mail this product has ever sent. Two infrastructure
facts now gate a real deployment, and neither is visible from the code alone.

### 1. Outbound mail is required — BLOCKING until configured

`MAIL_MAILER` defaults to `log`, which writes the message to `storage/logs/laravel.log` and sends
nothing. That is correct for development and **silently useless in production**: the endpoint still
answers 202, no error is raised anywhere, and the member simply never receives the link.

Before cutover:

| Setting | Requirement |
| --- | --- |
| `MAIL_MAILER` | a real transport — `smtp`, or a provider-specific mailer. Never `log` or `array`. |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` | from the chosen provider |
| `MAIL_FROM_ADDRESS` | a sender the receiving domains will accept — SPF/DKIM aligned |
| `APP_URL` | the public origin. Reset and verification links are built from it; a wrong value produces links that resolve nowhere. |

Verify after cutover by requesting a reset for a mailbox the operator controls and confirming
arrival, not by reading a 202.

### 2. There is no queue worker — do not queue these notifications

No `queue:work` process exists in the compose stack or the image, while `QUEUE_CONNECTION` is
`database`. Anything queued is therefore written to the `jobs` table and never runs.

Both notifications send inline for this reason, and
`PasswordRecoveryTest::test_neither_notification_is_queued` fails if that changes. If a worker is
added later, queueing them becomes a safe improvement — until then it would mean a member waiting
for an email that no process will ever send, with nothing in any log to show for it.

### 3. Rate limits that now matter

`POST /auth/password/forgot` and `/auth/password/reset` are limited to 5 per minute per caller, and
the broker adds its own per-address throttle of `auth.passwords.users.throttle` seconds. Both are
deliberate: the forgot endpoint answers identically for registered and unregistered addresses, so
the rate limit is the only thing between a caller and unlimited enumeration attempts. A 429 rate on
these paths is worth a monitoring signal.

### 4. Email verification is not a gate

`users.email_verified_at` can now be set, which it could not be before. **No route requires it** and
sign-in is unaffected. If a future change gates anything on verification, every account that
existed before this date and never clicked a link is affected —
`EmailVerificationTest::test_an_unverified_member_is_not_locked_out_of_anything` is the guard that
makes that a decision rather than an accident.

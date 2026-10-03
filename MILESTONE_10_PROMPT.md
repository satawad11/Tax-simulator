# MILESTONE_10_PROMPT.md

## Codex Task: Milestone 10 — QA / UX Polish / Security Hardening / Production Readiness

This is the production-readiness milestone for the Thai Personal Income Tax Simulation Platform.

Completed baseline:
- M1 Infrastructure
- M2 Database Foundation
- M3 Public Metadata API
- M3.1 Schema Reconciliation
- M4 PND91 Calculation Engine
- M5 Authentication / Member / History
- M6 Planning / Recommendation / Refund Guidance
- M7.x PND90 / PND91 Production Baseline — CLOSED
- M8 Content / News / Knowledge + Admin CMS + Tax Rule Administration
- M9 Public Simulator UI + Member Dashboard + Planning UI Integration

Do NOT add major new product features.
Do NOT reopen M7.x unless a real correctness regression is discovered.
Do NOT redesign the product.

Before changing files, read:
- PROJECT_REQUIREMENTS.md
- CODING_RULES.md
- MILESTONE_07_5_PROMPT.md
- MILESTONE_08_PROMPT.md
- MILESTONE_09_PROMPT.md

Also inspect:
- README.md
- docker-compose.yml
- Dockerfile*
- .env.example
- routes/
- app/
- resources/
- public/
- config/
- database/
- tests/
- docs/

# Feature Freeze

Allowed:
- bug fixes
- security fixes
- validation fixes
- UX polish
- accessibility fixes
- performance fixes
- production configuration
- observability
- deployment documentation
- test coverage

Not allowed unless required for a production-blocking defect:
- new tax rules
- new tax forms
- new calculation modes
- new recommendation engines
- new CMS modules
- new frontend frameworks
- major schema redesign
- major UI redesign

# Core Objective

At the end of M10 the system must be ready for a controlled production deployment, or clearly declared NOT READY.

The final report must answer:
- Can this release be deployed safely?
- What known limitations remain?
- What must operations/admin know?
- What should be monitored after release?

# A. Full Regression Audit

Run all existing automated tests and builds:
- full SQLite suite
- full MySQL suite
- Pint/lint
- frontend production build

Do not only run targeted M10 tests.
Do not delete or weaken assertions.

Explicitly verify unchanged:
- PND91 baseline
- PND90 supported baseline
- minimum tax
- family allowances
- allowance caps
- planning/recommendation
- refund/payment guidance
- Guest/Member parity
- M8 CMS/admin
- M9 public/member UI

If a tax result changes unexpectedly, stop and investigate. Do not update expected values unless it is a verified bug fix.

# B. End-to-End Functional QA

Use synthetic data only.

Guest:
- home
- PND91 simulation
- PND90 simulation
- result
- trace
- warnings
- recommendations
- refund/payment guidance
- planning

Authentication:
- register
- login
- logout
- invalid credentials
- revoked/expired auth

Member:
- save guest result
- create draft
- resume
- calculate
- history
- duplicate
- complete
- completed return read-only
- planning scenario

Content:
- knowledge
- news
- article
- FAQ

Admin:
- admin login
- member denied
- content create/edit/publish
- tax source inspect
- rule version inspect
- clone draft
- validate draft
- published version edit blocked
- audit log

# C. UX Polish

Review all pages for:
- clarity
- consistency
- Thai wording
- loading states
- empty states
- validation messages
- error states
- mobile usability
- navigation

Do not redesign the approved UI.

Avoid wording implying official filing:
- ยื่นภาษีสำเร็จ
- ส่งกรมสรรพากรแล้ว
- ได้รับเงินคืนแน่นอน

Prefer:
- เสร็จสิ้นการทดลอง
- ผลการประมาณการ
- ประมาณการเงินคืน
- แบบจำลอง

Normalize user-facing handling for:
- 401
- 403
- 404
- 409
- 422
- 429
- 500
- 503

Never show raw exception traces or SQL errors.

For 422:
- show field errors
- preserve entered values
- focus/scroll to first error where practical

# D. Accessibility

Verify/fix:
- labels linked to inputs
- keyboard navigation
- visible focus
- semantic buttons
- ARIA for accordion/disclosure/modal
- error association
- status not communicated by color alone
- heading order
- meaningful alt text

# E. Responsive QA

Verify at:
- 375px
- 768px
- 1280px+

Check:
- header
- wizard
- forms
- tables
- result page
- dashboard
- admin
- content pages

No unintended horizontal overflow.

# F. Security Hardening

Review authentication:
- Sanctum/session/token handling
- login throttling
- logout/logout-all
- token revocation
- cookies
- CSRF
- session fixation
- password validation
- remember-me if any

Prefer secure browser session/cookie auth where already supported.

If localStorage tokens remain:
- identify where
- document risk
- migrate to safer browser-session behavior if practical without redesigning auth

Verify authorization:
- member cannot access admin
- member cannot access other users' tax returns
- nested resource ownership
- scenario ownership
- calculation history ownership
- admin route protection

Perform IDOR audit for:
- tax returns
- calculations
- scenarios
- content drafts
- tax sources
- rule versions
- admin rule rows

Audit mass assignment for fields such as:
- user_id
- author_user_id
- rule_version_id
- status
- published_at
- created_by
- eligible_amount
- calculated values

Audit XSS on:
- CMS body
- article rendering
- user names/descriptions
- validation output
- admin notes

Test at least:
- <script>
- onerror=
- javascript:

Review query safety:
- whitelisted filters/sorts
- no unsafe raw SQL interpolation

Add/verify rate limits for:
- login
- register
- public tax calculation
- planning
- content search if useful

Add reasonable payload/count limits for:
- income rows
- dependents
- allowances
- donations
- withholdings
- scenarios
- pagination
- CMS body size

Audit logging so it does NOT contain:
- passwords
- access tokens
- Authorization headers
- full financial payloads
- sensitive PII

# G. HTTP Security Headers

Add/verify:
- X-Content-Type-Options: nosniff
- Referrer-Policy
- X-Frame-Options or CSP frame-ancestors
- Content-Security-Policy
- Permissions-Policy where appropriate

Do not introduce a CSP that breaks the application.

# H. HTTPS / Secure Cookie Readiness

Production config must support:
- HTTPS
- secure cookies
- SameSite
- trusted proxies where applicable
- APP_URL
- SESSION_DOMAIN
- SANCTUM_STATEFUL_DOMAINS if used

No development hostname hard-coding in production code.

# I. Production Environment Configuration

Audit:
- .env.example
- config/app.php
- config/database.php
- config/cache.php
- config/session.php
- config/logging.php
- config/filesystems.php
- config/cors.php
- Sanctum config

Ensure .env.example documents needed variables without secrets:
- APP_ENV
- APP_DEBUG
- APP_URL
- APP_KEY
- DB_*
- CACHE_*
- SESSION_*
- QUEUE_*
- LOG_*
- SANCTUM_*
- MAIL_* if used
- FILESYSTEM_* if used

Production docs must explicitly state:
APP_DEBUG=false

# J. Database Production Readiness

Review indexes for common query paths:
- users.email
- tax_returns user/status/year
- tax_calculations tax_return/calculated_at
- tax_scenarios source_tax_return_id
- content slug/status/published_at
- category/tag pivots
- rule-version status/year
- audit logs actor/date

Add missing indexes only where justified.

Review:
- foreign keys
- unique constraints
- soft deletes
- nullable relationships

Do not use migrate:fresh on active data.

Review migration safety and rollback limitations.

# K. Backup / Restore

Create:
docs/ops/BACKUP_RESTORE.md

Document MySQL backup and restore compatible with the Docker deployment.
Include restore verification.

Do not add cloud backup integrations unless already part of infrastructure.

# L. Performance Review

Review likely hot paths:
- public metadata
- tax calculation metadata resolution
- member dashboard
- history
- content list
- admin lists

Look for:
- N+1
- unbounded eager loads
- missing pagination
- repeated rule queries
- avoidable duplicate queries

Fix clear issues.

Use cache only where clearly safe, e.g.:
- published metadata
- category/tag lists
- current published rule-version lookup

Do NOT cache private financial/member results unless correctly scoped.

# M. Observability / Health

Review logging.

Add meaningful non-sensitive logs for:
- unexpected calculation exceptions
- rule-version publishing
- admin publish actions
- critical validation failures
- health failures

Do not log full tax payloads.

Document health endpoint behavior.
If useful, distinguish liveness/readiness.
Readiness may check DB/cache if critical.
Do not expose sensitive diagnostics publicly.

# N. Docker / Deployment Readiness

Review Docker configuration.

Create:
docs/ops/DEPLOYMENT.md

Document:
- build
- environment setup
- migrations
- safe seeding
- frontend build
- start
- health check
- production optimization

Do not assume Docker Desktop is production infrastructure.

Review use of:
- php artisan optimize
- config:cache
- route:cache
- view:cache

Only use cache commands if compatible.

If queues/scheduler are not required, do not add them.

# O. Production Seeding

Create/update:
docs/ops/PRODUCTION_SEEDING.md

Separate:
- required reference/master data
- development/demo data
- test data

Production must not seed synthetic member/tax-return data.

Published rule version 2568.1 must remain reproducible and must not be destructively replaced.

# P. Data Privacy Review

Create:
docs/ops/DATA_PRIVACY.md

Document factually:
- what Member data is stored
- what Guest data is not persisted
- what calculation history is stored
- what admin/content data is stored

Do not invent legal claims or retention periods.

If no formal retention policy exists, document current implementation only.

# Q. QA Release Matrix

Create:
docs/qa/RELEASE_TEST_MATRIX.md

Cover:
- PND91
- PND90
- Guest
- Member
- Planning
- Refund/Payment
- CMS
- Admin
- Rule lifecycle
- Security
- Mobile/responsive
- browser smoke

Use:
- PASS
- FAIL
- BLOCKED
- NOT_APPLICABLE

Do not claim browser/platform testing that was not actually performed.

# R. Production Readiness Checklist

Create:
docs/ops/PRODUCTION_READINESS_CHECKLIST.md

Include:
- environment
- secrets
- DB
- migrations
- seeders
- HTTPS
- cookies
- logging
- backups
- health
- admin user
- tax rule version
- frontend build
- tests
- smoke test
- rollback

# S. Rollback Plan

Create:
docs/ops/ROLLBACK.md

Document rollback for:
- application release
- frontend assets
- safe database rollback

Published tax-rule versions and member history must never be casually deleted or rewritten.

# T. Release Version / Notes

Create:
docs/releases/RELEASE_1_0.md

Summarize:
- supported PND90/PND91 baseline
- Guest/Member
- planning/recommendation
- CMS/admin
- guarded known limitations
- deployment notes

Do not claim legal completeness beyond the source-backed baseline.

# U. Known Limitations

Carry forward M7.5 guarded limitations.

Do not reopen them merely to make the release notes look cleaner.

Ensure UI/docs do not imply unsupported rules are supported.

# V. Final Security Regression Tests

Add/confirm automated tests for:
- login throttling
- guest denied admin
- member denied admin
- cross-user tax-return denial
- cross-user calculation denial
- cross-user scenario denial
- draft content not public
- published rule immutable
- mass assignment blocked
- XSS sanitized/escaped
- unsupported rule controlled error

# W. Final Manual Verification

Use synthetic data only.

Public:
1. Home
2. PND91 Guest
3. PND90 Guest
4. result
5. planning
6. knowledge/news/FAQ

Member:
7. register
8. login
9. save draft
10. resume
11. calculate
12. history
13. duplicate
14. complete
15. planning scenario
16. logout

Admin:
17. admin login
18. CMS publish
19. rule version list
20. draft clone
21. validation
22. published immutable
23. audit log

Security:
24. member attempts admin
25. User A attempts User B return
26. revoked token
27. malformed/oversized request
28. unsupported tax rule

Operations:
29. health endpoint
30. frontend build
31. migration status
32. production config review

# X. Performance Reporting

Do not invent SLA targets if requirements do not define them.

Report observed local behavior for:
- home
- content list
- tax calculation
- dashboard

Do not claim production capacity from local Docker timings.

# Required Documentation

Create/update:
- docs/qa/RELEASE_TEST_MATRIX.md
- docs/ops/PRODUCTION_READINESS_CHECKLIST.md
- docs/ops/BACKUP_RESTORE.md
- docs/ops/PRODUCTION_SEEDING.md
- docs/ops/DATA_PRIVACY.md
- docs/ops/ROLLBACK.md
- docs/ops/DEPLOYMENT.md
- docs/releases/RELEASE_1_0.md
- README.md
- .env.example

# Commands

Inspect first:

```bash
git status
docker compose ps
php artisan route:list
php artisan migrate:status
```

Run full test suite:

```bash
docker compose exec app php artisan test
```

Run MySQL-backed suite according to current project workflow.

Run Pint/lint:

```bash
docker compose exec app ./vendor/bin/pint --test
```

Run frontend build:

```bash
docker compose exec node npm run build
```

or current project equivalent.

Run safe production cache checks where compatible:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

If any are incompatible, report why.

Do not use migrate:fresh on active development data.

# Completion Criteria

M10 is complete only when:
- full SQLite suite passes
- full MySQL suite passes
- Pint/lint passes
- frontend production build passes
- PND91 baseline unchanged
- PND90 baseline unchanged
- planning/recommendation regression passes
- M8 CMS/admin regression passes
- M9 UI flows pass
- authentication security reviewed
- authorization/IDOR reviewed
- XSS reviewed
- mass assignment reviewed
- rate limiting present where appropriate
- payload limits present where appropriate
- sensitive logging reviewed
- production env documented
- APP_DEBUG=false documented
- HTTPS/cookie readiness documented
- DB indexes reviewed
- migrations reviewed
- backup/restore documented
- production-safe seeding documented
- health/readiness documented
- Docker/deployment flow documented
- data privacy behavior documented
- QA matrix complete
- production checklist complete
- rollback plan complete
- release notes complete
- no major new feature introduced
- M7 tax baseline not reopened
- guarded unsupported rules remain guarded

# Release Decision

At the end output exactly one:

READY_FOR_CONTROLLED_PRODUCTION_DEPLOYMENT

or:

NOT_READY_FOR_PRODUCTION

Do not use vague wording.

Production must be NOT READY if any remain:
- failing tax regression
- failing security-critical authorization test
- published rule version mutable
- cross-user data leakage
- draft/admin data publicly exposed
- unsanitized stored XSS path
- production debug enabled
- unsafe migration
- required production seed/reference data missing
- critical frontend flow broken

These can remain non-blocking if guarded/documented:
- M7.5 unsupported tax rules
- legal rounding unsupported
- rare optional unsupported rule paths
- minor UX polish
- no external APM
- no browser automation

# Required Final Report

Report exactly:

## Summary

## Release Decision

Exactly one:
READY_FOR_CONTROLLED_PRODUCTION_DEPLOYMENT
or
NOT_READY_FOR_PRODUCTION

## Blocking Issues
If none: None

## Tax Regression Status
Confirm:
- PND91
- PND90
- minimum tax
- family allowances
- planning/recommendation
- refund/payment guidance
- Guest/Member parity

## Functional QA
Summarize:
- Guest
- Member
- Content
- Admin

## UX / Accessibility

## Security Review
Report:
- authentication
- authorization
- IDOR
- mass assignment
- XSS
- rate limiting
- payload limits
- sensitive logging
- security headers

## Production Configuration
Report:
- APP_DEBUG
- HTTPS/cookies
- CORS/Sanctum
- cache
- session
- logging

## Database Readiness
Report:
- migrations
- indexes
- constraints
- backup/restore
- production seeding

## Performance Review

## Observability / Health

## Docker / Deployment

## Data Privacy

## Documentation Created / Updated

## Files Created

## Files Modified

## Commands Executed
Only actual commands.

## Test Results
Report:
- SQLite passed/failed/skipped/assertions
- MySQL passed/failed/skipped/assertions
- Pint/lint
- frontend build
- production cache commands

## Manual Verification

## Known Limitations

## Security-Critical Findings Fixed

## Regression Status
Confirm:
- M7.x tax baseline unchanged
- M8 CMS/admin intact
- M9 UI intact
- member history intact
- historical snapshots intact
- published rule immutability intact
- Guest stateless behavior intact

## Production Checklist Status
Summarize PASS / FAIL / BLOCKED counts.

## Release Notes
Reference:
docs/releases/RELEASE_1_0.md

## Architecture Compliance
Confirm:
- No new frontend framework introduced.
- No duplicate tax engine introduced.
- No unverified numeric tax rule added.
- Unsupported tax rules remain guarded.
- TaxCalculationService remains authoritative.
- Historical tax snapshots were not rewritten.
- Published tax rule versions remain immutable.
- Guest calculations remain stateless.
- Member data remains ownership-protected.

## Final Recommendation

If ready:
Proceed with a controlled production deployment using the documented deployment, backup, rollback, and monitoring procedures.

If not ready:
Do not deploy until the listed blocking issues are resolved and the full M10 regression/security suite is rerun.

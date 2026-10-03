# MILESTONE_09_1_PROMPT.md

## Codex Task: Milestone 09.1 — UI Mockup Reconciliation + Real Development Database Initial Data Seeding

You are working on:

**Thai Personal Income Tax Simulation Platform**

Milestones completed:

```text
M1–M8
M9 Public Simulator UI + Member Dashboard + Planning UI Integration
```

Milestone 10 production-readiness work must NOT continue until this UI reconciliation milestone is completed.

This task has TWO goals:

```text
A. Reconcile the actual Web UI with the approved mockup/reference.
B. Populate the real current development MySQL database with complete initial/demo data and development accounts.
```

This is NOT a tax-engine milestone.

Do NOT reopen M7.x tax logic.

Do NOT change tax calculation semantics.

---

# Before Changing Any File

Read:

```text
PROJECT_REQUIREMENTS.md
CODING_RULES.md
MILESTONE_07_5_PROMPT.md
MILESTONE_08_PROMPT.md
MILESTONE_09_PROMPT.md
```

Inspect:

```text
docs/ui/
docs/ui-reference/
resources/views/
resources/css/
resources/js/
routes/web.php
routes/api.php
database/migrations/
database/seeders/
app/Models/
tests/
```

Also inspect the current live UI in the Docker development stack before changing it.

The approved mockup/reference is the visual acceptance target.

Do NOT merely treat it as inspiration.

Treat it as:

```text
the visual acceptance reference
```

---

# NON-NEGOTIABLE UI RULE

The current UI is functionally acceptable but visually not close enough to the approved mockup.

M9.1 must reconcile:

```text
layout
spacing
typography
cards
buttons
navigation
forms
wizard
result presentation
empty states
knowledge/news/FAQ pages
member dashboard
```

with the approved mockup.

Do NOT redesign with a new visual language.

Do NOT introduce React/Vue/SPA frameworks.

Keep:

```text
Blade
Tailwind CSS
Vanilla JavaScript
```

---

# PART A — UI MOCKUP RECONCILIATION

## 1. Build a Small Design Token Layer

Centralize the actual visual language.

Define/reconcile:

```text
page background
surface/card background
primary text
secondary text
primary blue/accent
soft blue
border color
success/warning/error states
radius scale
shadow scale
spacing scale
heading scale
body scale
button scale
input scale
```

Prefer Tailwind theme/classes already available.

Do not build a design-system framework.

---

# 2. Header / Navigation

Current header feels like a default Tailwind navbar.

Rework it to match the mockup.

Reconcile:

```text
logo lockup
brand text
subtitle/tagline
navigation spacing
active item
login CTA
authenticated menu
mobile navigation
header height
container width
border/shadow
```

Keep navigation labels:

```text
หน้าแรก
ทดลองคำนวณภาษี
ความรู้ภาษี
ข่าวสาร
คำถามที่พบบ่อย
```

Authenticated:

```text
แดชบอร์ด
แบบภาษีของฉัน
ออกจากระบบ
```

---

# 3. Home Hero

Reconcile `/` with the approved mockup.

Must include:

```text
eyebrow/badge
large headline
supporting text
primary CTA
secondary CTA
visual side card/illustration
```

Fix:

```text
max-width
line-height
vertical rhythm
hero proportion
side-card proportion
CTA hierarchy
mobile stacking
```

Avoid excessive empty space.

---

# 4. Form Selection Page

Reconcile:

```text
/tax-simulator
```

The PND90/PND91 cards must NOT look like generic white boxes.

Each card should have:

```text
distinct visual icon
form badge/code
short description
supported income explanation
primary action
hover/focus state
consistent vertical rhythm
```

The two cards should feel intentionally designed and comparable.

---

# 5. Simulator Wizard

Reconcile both:

```text
/tax-simulator/pnd91
/tax-simulator/pnd90
```

with the approved mockup.

Key UI:

```text
stepper/progress
section title
form cards
income rows
family inputs
allowance sections
donation/withholding
review
result
```

Use one consistent component style.

Do not expose backend field names as user labels.

---

# 6. Form Controls

Standardize:

```text
text input
currency input
select
radio
checkbox
date
repeatable row
add/remove row buttons
validation state
help text
disabled/unsupported state
```

Inputs must clearly distinguish:

```text
required
optional
server-derived
unsupported
```

---

# 7. PND90 / PND91 Visual Differences

Keep the same design system but allow form-specific cues.

Do NOT create two unrelated page designs.

PND90 should make multi-income-type entry understandable.

PND91 should remain simpler.

---

# 8. Result Page

The result page must look materially more polished than an input form.

Primary hierarchy:

```text
PAYABLE
REFUND
ZERO
```

Show:

```text
headline
amount
status explanation
summary metrics
tax breakdown
warnings
recommendations
refund/payment guidance
planning CTA
trace/details
```

The result must visually communicate priority.

Do NOT calculate anything in frontend JavaScript.

---

# 9. Recommendation / Guidance Cards

Use consistent card patterns for:

```text
recommendations
refund guidance
payment guidance
warnings
```

Differentiate:

```text
information
warning
action
result
```

without relying only on color.

---

# 10. Planning UI

Reconcile Guest and Member planning interfaces.

Show:

```text
before
after
difference
estimated tax saving
warnings
recommendations
```

Use readable comparison layout.

Do not calculate differences in JavaScript if backend already returns them.

---

# 11. Login / Register

Reconcile:

```text
/login
/register
```

with the same visual system.

Avoid generic Laravel-auth appearance.

Include:

```text
clear title
short explanation
clean fields
password states
validation
navigation back to simulator
```

---

# 12. Member Dashboard

Reconcile:

```text
/dashboard
/dashboard/tax-returns
/dashboard/tax-returns/{id}
```

Dashboard should include:

```text
welcome/header area
summary cards
recent drafts
recent calculations
completed simulations
planning scenarios
quick actions
```

Avoid dense admin-style tables for normal members.

---

# 13. Draft / History / Scenario Pages

Use consistent member UI.

History:

```text
timestamp
status
amount
net income
calculated tax
detail action
```

Completed return:

```text
read-only visual state
duplicate CTA
```

Scenario:

```text
base/scenario comparison
source-return unchanged message
```

---

# 14. Knowledge / News / Article / FAQ

Current FAQ empty state is too plain.

Reconcile:

```text
/knowledge
/news
/article/{slug}
/faq
```

Knowledge/news cards should have:

```text
category
title
excerpt
date
tag/tax year where relevant
```

FAQ should use:

```text
category/filter where useful
accordion/disclosure
intentional empty state
```

If no FAQ records exist, render a designed empty state, not a blank white box.

---

# 15. Empty States

Create polished empty states for:

```text
no FAQ
no articles
no tax returns
no history
no scenarios
no search results
```

Each should include:

```text
message
context
next action where useful
```

---

# 16. Loading / Error States

Reconcile:

```text
loading
network error
validation error
401
403
404
409
422
500
```

Do not show raw API payloads.

---

# 17. Responsive Reconciliation

Verify UI at:

```text
375px
768px
1280px+
```

Fix:

```text
header collapse
wizard
cards
forms
result comparison
tables
dashboard
knowledge cards
FAQ
```

No accidental horizontal overflow.

---

# 18. Accessibility

Ensure:

```text
labels
focus states
keyboard navigation
button semantics
aria-expanded
field error association
heading structure
contrast
```

No status by color alone.

---

# 19. Mockup Comparison Evidence

Create:

```text
docs/ui/M9_1_MOCKUP_RECONCILIATION.md
```

For each major page:

```text
Home
Form selection
PND91
PND90
Result
Planning
Login/Register
Dashboard
Knowledge
FAQ
```

document:

```text
reference used
before issue
change made
intentional deviation
```

---

# PART B — REAL DEVELOPMENT DATABASE INITIAL DATA

The user explicitly wants the CURRENT REAL DEVELOPMENT MYSQL DATABASE populated with initial usable data.

This is NOT a test-only SQLite fixture.

Use the active Docker MySQL development database.

Do NOT use `migrate:fresh`.

Do NOT delete unknown existing data.

All seeding must be:

```text
idempotent
non-destructive
repeatable
environment-aware
```

---

# 20. Seeder Architecture

Create/reconcile production-safe and development/demo seeders.

Suggested hierarchy:

```text
DatabaseSeeder
  |
  +-- ReferenceDataSeeder
  +-- TaxBaselineSeeder
  +-- CmsInitialDataSeeder
  +-- DevelopmentAccountSeeder
  +-- DevelopmentDemoDataSeeder
```

Do not mix synthetic demo accounts into required production reference data.

---

# 21. Preserve Existing Tax Baseline

M7.5 baseline:

```text
rule version 2568.1
```

must remain unchanged semantically.

Do NOT delete/rebuild tax rule rows destructively.

Use existing approved tax seeders.

Verify the required baseline exists.

Do not invent new tax rules.

---

# 22. Required Reference Data

Ensure the real development DB contains the required existing baseline/reference data, including where applicable:

```text
tax years
tax forms
income types
income subtypes/activities
tax brackets
expense rules
allowance types
verified allowance rules
combined cap groups
donation rules
recommendation rules
published tax-rule version 2568.1
form/income mappings
tax source metadata
```

Use existing approved seeders where possible.

Do not duplicate master rows.

---

# 23. CMS Initial Data

Seed useful initial CMS data into the real development DB.

At minimum:

## Categories

Examples:

```text
ความรู้ภาษี
คู่มือการใช้งาน
ข่าวสารภาษี
การวางแผนภาษี
คำถามที่พบบ่อย
```

Use stable slugs.

---

# 24. Tags

Seed useful tags such as:

```text
ภ.ง.ด.90
ภ.ง.ด.91
ค่าลดหย่อน
เงินได้
ภาษีหัก ณ ที่จ่าย
เงินคืนภาษี
วางแผนภาษี
ปีภาษี 2568
```

---

# 25. Public Articles / Guides / News

Seed at least:

```text
3 GUIDE
3 ARTICLE
2 NEWS
6 FAQ
```

Use Thai content.

Content must be clearly educational.

Do not invent detailed legal claims beyond the approved project baseline.

Safe topics:

```text
PND90 vs PND91 overview
how to prepare data before simulation
how the simulator works
understanding PAYABLE/REFUND/ZERO
what calculation warnings mean
why unsupported rules may be blocked
how tax planning scenarios work
guest vs member differences
```

Do not publish invented legal deadlines/rates.

---

# 26. Featured Content

Mark a small subset as featured.

Home should immediately show meaningful content after seeding.

---

# 27. FAQ Seed Data

Seed useful FAQs such as:

```text
ต้องสมัครสมาชิกก่อนทดลองหรือไม่
ระบบนี้ยื่นภาษีจริงหรือไม่
ภ.ง.ด.90 กับ ภ.ง.ด.91 ต่างกันอย่างไร
ทำไมบางค่าลดหย่อนระบบยังไม่รองรับ
ผลประมาณการเงินคืนหมายถึงอะไร
บันทึกแบบร่างได้อย่างไร
```

Use project-accurate answers.

---

# 28. Development Accounts

Create BOTH:

```text
Admin development account
Normal member development account
```

These are DEVELOPMENT accounts, not production defaults.

Use stable emails:

```text
admin@tax-simulator.local
user@tax-simulator.local
```

Names:

```text
ผู้ดูแลระบบทดสอบ
ผู้ใช้งานทดสอบ
```

Roles:

```text
admin
member
```

---

# 29. Development Account Passwords

Do NOT hard-code production credentials.

Development seed passwords must come from environment variables:

```text
DEV_ADMIN_PASSWORD
DEV_USER_PASSWORD
```

Add these to:

```text
.env.example
```

with safe placeholder values only.

Example:

```text
DEV_ADMIN_PASSWORD=
DEV_USER_PASSWORD=
```

Do NOT commit real passwords.

If either variable is missing during development seeding:

fail with a clear message.

Do not silently create a known default password such as:

```text
password
12345678
admin123
```

---

# 30. Environment Guard

`DevelopmentAccountSeeder` and `DevelopmentDemoDataSeeder` must run only when:

```text
APP_ENV=local
```

or another explicitly approved development environment.

They must REFUSE to run in production.

Required safety guard:

```text
if production -> throw/stop
```

Do not rely only on comments.

---

# 31. Admin Account Behavior

Seed/update idempotently by email.

The admin account must:

```text
have admin role
be able to login
access /admin
access admin APIs
```

Do not create duplicate users on rerun.

---

# 32. Member Account Behavior

Seed/update idempotently by email.

The member account must:

```text
have member role
login normally
be denied admin
```

---

# 33. Development Demo Member Data

Create a SMALL amount of useful demo data for the seeded member.

Suggested:

```text
1 PND91 draft
1 PND90 draft
1 completed synthetic simulation if safe
1 calculation history example
1 planning scenario
```

Only use synthetic values.

Do NOT overwrite user-created rows.

Identify seeded demo records with stable names/markers.

Example:

```text
[DEMO] ภ.ง.ด.91 เงินเดือน
[DEMO] ภ.ง.ด.90 หลายประเภทเงินได้
```

---

# 34. Demo Data Must Use Real APIs/Services Where Practical

Do not insert fake calculated JSON that bypasses business logic if avoidable.

Prefer building demo records using:

```text
approved models/services/calculation engine
```

so result snapshots are structurally valid.

Do not alter tax engine for seeding.

---

# 35. Demo Data Cleanup / Rerun

Rerunning the seeder must not duplicate demo records.

Use stable keys/names or dedicated markers.

Do not delete unrelated user data.

---

# 36. CMS Seeder Publication State

Seed:

```text
published content
at least one draft content item for admin testing
```

Public UI should immediately have:

```text
knowledge cards
news cards
FAQ rows
featured content
```

Admin UI should have at least one draft to edit/publish.

---

# 37. Tax Source Metadata

If M8 tax_sources table exists, ensure existing repository-approved source documents are represented in development DB where appropriate.

Do NOT fabricate source evidence.

Use actual repository paths already approved.

---

# 38. Database Command

After code changes, run the real development DB migration safely:

```bash
docker compose exec app php artisan migrate
```

Do NOT use:

```bash
migrate:fresh
```

Then run approved required/reference seeders.

Then run the LOCAL development seeders.

Example shape:

```bash
docker compose exec app php artisan db:seed --class=ReferenceDataSeeder
docker compose exec app php artisan db:seed --class=CmsInitialDataSeeder
docker compose exec app php artisan db:seed --class=DevelopmentAccountSeeder
docker compose exec app php artisan db:seed --class=DevelopmentDemoDataSeeder
```

Use actual class names created/current in the project.

---

# 39. Verify Real MySQL Data

After seeding, verify on MySQL:

```text
admin user exists exactly once
member user exists exactly once
roles correct
published content count
FAQ count
category/tag counts
tax rule version 2568.1 still exactly one published version
demo member drafts exist
```

Do not rely only on SQLite tests.

---

# 40. Do Not Publish Synthetic Tax Rule Version

Do not create/publish a fake 2568.2.

The real development DB must remain:

```text
published baseline = 2568.1
```

unless the project already has another legitimate published version.

---

# 41. UI Must Use Seeded Real Data

After seeding, manually verify:

```text
Home shows featured content
Knowledge shows seeded articles/guides
News shows seeded news
FAQ shows seeded FAQs
Admin CMS shows content
Admin login works
Member login works
Dashboard shows seeded demo member data
```

This is important.

The seeded real database must make the application look complete enough for development/demo use.

---

# 42. Authentication Manual Verification

Using the seeded accounts:

## Admin

Login:

```text
admin@tax-simulator.local
```

with password from `DEV_ADMIN_PASSWORD`.

Verify:

```text
/admin accessible
admin APIs allowed
```

## Member

Login:

```text
user@tax-simulator.local
```

with password from `DEV_USER_PASSWORD`.

Verify:

```text
/dashboard accessible
/admin denied
```

Do NOT print actual password values in logs/final report.

---

# 43. Security

Seeders must never log plain-text passwords.

Do not expose seed passwords in:

```text
README
HTML
API
logs
database plaintext
```

Laravel password hashing must be used.

---

# 44. Tests

All existing M1–M9 tests must remain green.

Add tests for:

```text
development seeder environment guard
admin account idempotency
member account idempotency
CMS seed idempotency
demo seed idempotency
admin/member role correctness
```

Where practical.

---

# 45. Visual Regression Manual Checklist

Manually inspect after reconciliation:

```text
/
tax-simulator
tax-simulator/pnd91
tax-simulator/pnd90
result page
planning
login
register
dashboard
knowledge
news
faq
admin
```

Compare directly with approved mockup/reference.

---

# 46. Frontend Build

Run:

```bash
npm run build
```

or current project equivalent.

No broken assets/imports.

---

# 47. Final Database Safety Verification

Report:

```text
no migrate:fresh used
no unknown data deleted
no published 2568.1 mutation
no historical snapshots rewritten
development seeders production-guarded
```

---

# 48. Documentation

Create/update:

```text
docs/ui/M9_1_MOCKUP_RECONCILIATION.md
docs/dev/DEVELOPMENT_SEEDING.md
README.md
.env.example
```

`DEVELOPMENT_SEEDING.md` must include:

```text
required environment variables
safe seeding commands
development account emails
role descriptions
how to rerun safely
how demo data is identified
production warning
```

Do NOT include actual passwords.

---

# Completion Criteria

M9.1 is complete only when:

```text
[ ] UI visibly matches approved mockup much more closely
[ ] header reconciled
[ ] home reconciled
[ ] form selection reconciled
[ ] PND90/PND91 wizard reconciled
[ ] result/planning UI reconciled
[ ] login/register reconciled
[ ] member dashboard reconciled
[ ] knowledge/news/FAQ reconciled
[ ] responsive states verified
[ ] accessibility basics verified

[ ] current real development MySQL DB migrated safely
[ ] reference/master data present
[ ] published tax baseline 2568.1 preserved
[ ] CMS categories seeded
[ ] CMS tags seeded
[ ] GUIDE/ARTICLE/NEWS content seeded
[ ] FAQ content seeded
[ ] featured content seeded

[ ] admin development account exists
[ ] member development account exists
[ ] passwords come from env
[ ] development seeder refuses production
[ ] accounts seed idempotently

[ ] member demo tax-return data exists
[ ] demo data is synthetic
[ ] demo seed is idempotent
[ ] no unknown DB data deleted

[ ] Admin UI works with seeded admin
[ ] Member dashboard works with seeded member
[ ] public site shows seeded CMS data

[ ] all prior regression tests pass
[ ] frontend build passes
[ ] M7 tax engine was not reopened
```

---

# Required Final Report

Report exactly:

## Summary

## Mockup / Reference Files Used

## UI Reconciliation

For each:

```text
Home
Header
Tax Form Selection
PND91
PND90
Result
Planning
Login/Register
Dashboard
Knowledge
News
FAQ
Admin
```

report:

```text
before problem
change made
remaining deviation
```

## Design Tokens / Shared Components

## Responsive Verification

Report:

```text
375px
768px
desktop
```

## Accessibility Verification

## Database Target

Confirm the actual development MySQL database/container used.

## Migration Safety

Confirm:

```text
migrate:fresh was NOT used
```

## Reference Data Seeded

Give counts/types.

## CMS Initial Data Seeded

Report counts:

```text
categories
tags
GUIDE
ARTICLE
NEWS
FAQ
draft content
featured content
```

## Development Accounts

Report only:

```text
admin email
admin role
member email
member role
```

Do NOT print passwords.

Confirm passwords came from environment variables.

## Development Demo Member Data

Report:

```text
PND91 drafts
PND90 drafts
completed demo simulations
history records
scenarios
```

## Idempotency

Explain how duplicate seeding is prevented.

## Production Safety

Confirm development/demo seeders refuse production.

## Tax Baseline Integrity

Confirm:

```text
published 2568.1 preserved
no tax semantics changed
no historical snapshot rewritten
```

## Files Created

## Files Modified

## Commands Executed

Only actual commands.

## Test Results

Report:

```text
SQLite
MySQL
Pint/lint
frontend build
```

## Manual Verification

Report:

```text
public home
form selection
PND91
PND90
FAQ/content
admin login
member login
admin denial for member
dashboard demo data
```

## Known Issues

## Architecture Compliance

Confirm:

```text
Blade/Tailwind/vanilla JS retained.
No React/Vue/SPA introduced.
No frontend tax calculation engine added.
Backend remains source of truth.
Development seed accounts are environment-guarded.
No real/unknown user data was deleted.
M7 tax baseline remains closed.
```

## Next Step

After approval of the reconciled UI and seeded development environment:

```text
Resume Milestone 10 — QA / UX Polish / Security Hardening / Production Readiness
```

Do not start new feature development.

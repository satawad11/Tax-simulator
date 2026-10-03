# MILESTONE_08_PROMPT.md

## Codex Task: Milestone 08 — Content / News / Knowledge + Admin CMS + Tax Rule Administration

You are working on:

**Thai Personal Income Tax Simulation Platform**

Milestone 7.x is CLOSED.

The PND90 / PND91 production baseline is frozen at:

```text
rule version 2568.1
```

This milestone moves away from tax-engine development and into:

```text
public content
knowledge/news CMS
admin management
tax-rule administration workflow
```

Before changing any file, you MUST read:

```text
PROJECT_REQUIREMENTS.md
CODING_RULES.md

MILESTONE_01_PROMPT.md
MILESTONE_02_PROMPT.md
MILESTONE_03_PROMPT.md
MILESTONE_04_PROMPT.md
MILESTONE_05_PROMPT.md
MILESTONE_06_PROMPT.md
MILESTONE_07_PROMPT.md
MILESTONE_07_1_PROMPT.md
MILESTONE_07_2_PROMPT.md
MILESTONE_07_3_PROMPT.md
MILESTONE_07_4_PROMPT.md
MILESTONE_07_5_PROMPT.md
```

Also inspect:

```text
routes/api.php
routes/web.php
resources/
app/Models/
app/Policies/
app/Http/
app/Services/
database/migrations/
database/seeders/
tests/
docs/api/
docs/tax/
```

Do NOT reopen Milestone 7.x.

Do NOT modify tax calculation semantics unless a real regression defect is discovered.

Do NOT redesign the already approved public visual direction.

---

# Objective

Implement the first production baseline for:

```text
Public Knowledge / News / Tax Education Content
Admin CMS
Admin Content Management
Tax Rule Administration
Tax Rule Version Lifecycle
Source / Evidence Tracking
Auditability
```

This milestone must support both:

```text
Web
Future Mobile Application
```

through the same APIs.

---

# Product Scope

The public website should be able to show:

```text
tax knowledge articles
tax guides
news / updates
FAQs
featured content
tax-year related content
content categories
tags
```

Admin users should be able to:

```text
create/edit/publish/unpublish/archive content
manage categories/tags
manage tax source references
inspect tax rule versions
create draft rule versions
edit draft-only rule data
validate draft rules
publish a new rule version
see audit/history metadata
```

The M7 tax engine baseline must remain stable.

---

# Critical Rule — Published Tax Rules Are Immutable

Current baseline:

```text
2568.1 = published
```

Do NOT edit published 2568.1 in-place through the admin UI/API.

Do NOT allow admins to directly mutate published rule records.

Any future rule change must follow:

```text
create new draft rule version
edit draft data
validate draft
publish draft
```

Historical returns must stay tied to their original rule version.

Completed calculation snapshots must never be rewritten.

---

# Admin Authorization

Use the existing M5 authentication system.

Do not create a second authentication stack.

Use:

```text
auth:sanctum
```

plus an admin authorization layer.

Inspect the existing `users.role` or equivalent current implementation.

If the project already supports roles:

```text
member
admin
```

reuse it.

If not, add the minimum source-neutral role field/authorization mechanism required.

Do not build a full RBAC platform in this milestone.

---

# Admin Access Rules

Only admin users may access:

```text
/api/v1/admin/*
```

Members must receive:

```text
403
```

or the project's consistent authorization response.

Unauthenticated requests:

```text
401
```

Do not expose administrative metadata publicly.

---

# Part A — Public Content / Knowledge API

Implement public endpoints under:

```text
/api/v1/content
```

Suggested endpoints:

```text
GET /api/v1/content/articles
GET /api/v1/content/articles/{slug}

GET /api/v1/content/categories
GET /api/v1/content/categories/{slug}

GET /api/v1/content/tags
GET /api/v1/content/tags/{slug}

GET /api/v1/content/featured
GET /api/v1/content/faqs
```

Public endpoints require no authentication.

---

# Content Types

Support a clear small vocabulary:

```text
ARTICLE
NEWS
GUIDE
FAQ
```

Do not create an open arbitrary content-type system yet.

If the current project design already defines equivalent types, use them.

---

# Article / Content Model

Create/reconcile a content model, for example:

```text
Content
```

Suggested fields:

```text
id
type
title
slug
excerpt
body
status
published_at
featured
author_user_id
tax_year_id nullable
meta_title nullable
meta_description nullable
created_at
updated_at
deleted_at
```

Use SoftDeletes if consistent with the project.

Do not store rendered HTML only if the project needs structured content later.

For this milestone, storing sanitized HTML/Markdown-style body content is acceptable if the current frontend model is simple.

Choose one format and document it.

---

# Content Status

Use:

```text
draft
published
archived
```

Public API must return only:

```text
published
```

and only when:

```text
published_at <= now
```

if scheduled publication is supported.

---

# Slugs

Slugs must be:

```text
unique
stable
URL-safe
```

Do not use database ID as public URL identity when slug exists.

Admin may edit slug while draft.

For published content, either:

- allow explicit slug edit with clear redirect implications documented, or
- keep slug immutable after first publish.

Preferred for this milestone:

```text
slug immutable after first publish
```

unless current architecture already handles redirects.

---

# Categories

Implement:

```text
content_categories
```

Suggested fields:

```text
id
name
slug
description nullable
sort_order
active
timestamps
```

Content may belong to one primary category in this milestone.

If existing schema supports many-to-many cleanly, that is acceptable.

Do not over-engineer taxonomy.

---

# Tags

Implement:

```text
content_tags
content_tag
```

or equivalent many-to-many structure.

Suggested:

```text
name
slug
active
```

---

# FAQ

FAQ may be represented by:

```text
type = FAQ
```

rather than a separate table if that keeps the model simple.

FAQ public output should expose:

```text
question/title
answer/body
category
sort_order if needed
```

---

# Public Content Listing

`GET /api/v1/content/articles`

Support:

```text
type
category
tag
tax_year
q
page
per_page
```

Default:

```text
published_at desc
```

Suggested:

```text
per_page = 12
max = 100
```

Search only safe fields:

```text
title
excerpt
```

Do not build full-text search infrastructure yet.

---

# Public Article Detail

Example:

```text
GET /api/v1/content/articles/how-to-file-pnd91
```

Return:

```json
{
  "success": true,
  "message": null,
  "data": {
    "type": "GUIDE",
    "title": "วิธีเตรียมข้อมูลก่อนทดลอง ภ.ง.ด.91",
    "slug": "prepare-pnd91",
    "excerpt": "...",
    "body": "...",
    "category": {
      "name": "ความรู้ภาษี",
      "slug": "tax-knowledge"
    },
    "tags": [],
    "tax_year": 2568,
    "published_at": "2026-09-12T00:00:00Z"
  }
}
```

Do not expose:

```text
draft metadata
internal IDs unless useful
author email
admin notes
```

---

# Public Featured Endpoint

Implement:

```text
GET /api/v1/content/featured
```

Return a small number of published featured items.

Suggested max:

```text
6
```

Do not create personalization yet.

---

# Public FAQ Endpoint

Implement:

```text
GET /api/v1/content/faqs
```

Support optional:

```text
category
tax_year
```

Return only published FAQs.

---

# Part B — Admin CMS API

Implement admin endpoints under:

```text
/api/v1/admin/content
```

Protected by:

```text
auth:sanctum
admin authorization
```

Required:

```text
GET    /api/v1/admin/content
POST   /api/v1/admin/content
GET    /api/v1/admin/content/{id}
PATCH  /api/v1/admin/content/{id}
DELETE /api/v1/admin/content/{id}

POST   /api/v1/admin/content/{id}/publish
POST   /api/v1/admin/content/{id}/unpublish
POST   /api/v1/admin/content/{id}/archive
```

Also:

```text
GET    /api/v1/admin/content/categories
POST   /api/v1/admin/content/categories
PATCH  /api/v1/admin/content/categories/{id}
DELETE /api/v1/admin/content/categories/{id}

GET    /api/v1/admin/content/tags
POST   /api/v1/admin/content/tags
PATCH  /api/v1/admin/content/tags/{id}
DELETE /api/v1/admin/content/tags/{id}
```

---

# Admin Content Request

Example:

```json
{
  "type": "GUIDE",
  "title": "คู่มือทดลอง ภ.ง.ด.91",
  "slug": "pnd91-simulation-guide",
  "excerpt": "คำแนะนำก่อนเริ่มทดลองคำนวณ",
  "body": "<p>...</p>",
  "category_id": 2,
  "tag_ids": [1, 3],
  "tax_year": 2568,
  "featured": true,
  "meta_title": "คู่มือ ภ.ง.ด.91",
  "meta_description": "..."
}
```

Do not accept:

```text
author_user_id
published_at
status
```

as arbitrary trusted fields where dedicated actions should control them.

Author should come from authenticated admin.

---

# Publish Workflow

Admin create:

```text
draft
```

Publish endpoint:

```text
POST /api/v1/admin/content/{id}/publish
```

Server sets:

```text
status = published
published_at = now
```

If scheduled publishing is already desired and simple, support explicit future `published_at`.

Otherwise keep M8 publish immediate.

Do not build job scheduling just for CMS in this milestone.

---

# Content Validation

Validate:

```text
type
title
slug
body
category
tag IDs
tax_year if supplied
featured boolean
SEO field lengths
```

Slug must be unique.

Use focused FormRequests.

---

# Content Sanitization

If content body accepts HTML:

sanitize it before persistence or before rendering.

Do not allow:

```text
script
iframe
javascript: URLs
event handler attributes
```

Use an established sanitizer if already available.

Do not build a fragile regex HTML sanitizer.

If no safe HTML sanitizer exists in the current stack, store Markdown/plain structured text instead.

Document the chosen strategy.

---

# Part C — Tax Source Administration

Implement admin management of tax source references.

This is for auditability.

Create/reconcile:

```text
tax_sources
```

Suggested fields:

```text
id
code
title
source_type
file_path nullable
description nullable
tax_year_id nullable
document_date nullable
active
created_by
timestamps
```

Possible source types:

```text
OFFICIAL_FORM
FILING_INSTRUCTIONS
ATTACHMENT
INTERNAL_APPROVED_REFERENCE
```

Do NOT use arbitrary external URL fetching in this milestone.

Repository-approved files remain the real source basis.

---

# Tax Source Admin Endpoints

```text
GET    /api/v1/admin/tax-sources
POST   /api/v1/admin/tax-sources
GET    /api/v1/admin/tax-sources/{id}
PATCH  /api/v1/admin/tax-sources/{id}
DELETE /api/v1/admin/tax-sources/{id}
```

Deletion should be blocked if a published rule references the source.

Prefer soft delete / deactivate.

---

# Rule-to-Source Traceability

If current tax rule tables do not already link to source/evidence, introduce a minimal auditable mechanism.

Possible:

```text
tax_rule_sources
```

with:

```text
rule_version_id
rule_entity_type
rule_entity_id
tax_source_id
page_reference
section_reference
notes nullable
```

However, prefer proper foreign keys / dedicated relations where practical.

Do not create an unsafe polymorphic structure if the existing schema has a cleaner pattern.

The goal:

For any admin-visible tax rule, show:

```text
where did this rule come from?
```

---

# Part D — Tax Rule Version Administration

Implement admin endpoints under:

```text
/api/v1/admin/tax-rule-versions
```

Required:

```text
GET  /api/v1/admin/tax-rule-versions
POST /api/v1/admin/tax-rule-versions
GET  /api/v1/admin/tax-rule-versions/{id}
PATCH /api/v1/admin/tax-rule-versions/{id}

POST /api/v1/admin/tax-rule-versions/{id}/validate
POST /api/v1/admin/tax-rule-versions/{id}/publish
POST /api/v1/admin/tax-rule-versions/{id}/archive
```

Published versions must be immutable.

---

# Create Draft Rule Version

Example:

```json
{
  "tax_year": 2568,
  "version": "2568.2",
  "name": "2568 revision 2",
  "notes": "Draft rule update"
}
```

New version:

```text
status = draft
```

Do not auto-publish.

Do not auto-copy unless explicitly requested.

---

# Clone Rule Version

Add:

```text
POST /api/v1/admin/tax-rule-versions/{id}/clone
```

Example:

```json
{
  "version": "2568.2",
  "name": "2568 revision 2"
}
```

Clone from a source rule version into a new:

```text
draft
```

Copy rule data needed by the engine:

```text
tax brackets
income mappings
expense rules
allowance rules
combined caps
tiers
donation rules
recommendation rules
other rule data currently part of the calculation baseline
```

Do NOT clone:

```text
calculation history
tax returns
scenarios
member data
```

Use transaction.

---

# Draft Rule Editing

Only draft versions may be edited.

Admin tax-rule management should expose CRUD for rule entities already used by the engine.

At minimum admin must be able to inspect and edit draft copies of:

```text
tax brackets
expense rules
allowance rules
allowance cap groups
allowance tiers if present
donation rules
recommendation rules
```

Do NOT invent new rule classes just for admin.

---

# Suggested Admin Rule Endpoints

Under:

```text
/api/v1/admin/tax-rule-versions/{versionId}
```

Examples:

```text
GET /tax-brackets
PUT /tax-brackets

GET /expense-rules
POST /expense-rules
PATCH /expense-rules/{ruleId}
DELETE /expense-rules/{ruleId}

GET /allowance-rules
POST /allowance-rules
PATCH /allowance-rules/{ruleId}
DELETE /allowance-rules/{ruleId}

GET /allowance-cap-groups
POST /allowance-cap-groups
PATCH /allowance-cap-groups/{groupId}
DELETE /allowance-cap-groups/{groupId}

GET /donation-rules
POST /donation-rules
PATCH /donation-rules/{ruleId}
DELETE /donation-rules/{ruleId}

GET /recommendation-rules
POST /recommendation-rules
PATCH /recommendation-rules/{ruleId}
DELETE /recommendation-rules/{ruleId}
```

All write operations must reject non-draft versions.

---

# Admin Rule Validation

Before publishing a draft, run validation.

Create:

```text
TaxRuleVersionValidationService
```

Validate at minimum:

```text
tax year exists
version unique
version is draft
exactly expected tax bracket ordering
no bracket gaps/overlaps
final bracket open-ended
required form mappings exist
required income-type mappings exist
no duplicate expense rule keys
percentage bases valid
combined cap references valid
tier order valid
recommendation condition vocabulary valid
source references present for production numeric rules where required
```

Do not call TaxCalculationService with arbitrary random cases as the only validation.

Structural validation must exist.

---

# Validation Response

Example:

```json
{
  "success": true,
  "message": null,
  "data": {
    "valid": false,
    "errors": [
      {
        "code": "TAX_BRACKET_GAP",
        "path": "tax_brackets",
        "message": "..."
      }
    ],
    "warnings": []
  }
}
```

Publishing requires:

```text
valid = true
```

Warnings may remain if they correspond to explicitly unsupported/guarded features.

---

# Publish Rule Version

`POST /api/v1/admin/tax-rule-versions/{id}/publish`

Must:

1. verify admin authorization;
2. ensure version = draft;
3. run TaxRuleVersionValidationService;
4. fail if validation errors exist;
5. use DB transaction;
6. mark version published;
7. preserve older published versions;
8. never reassign existing saved returns;
9. not rewrite historical snapshots.

Do not mutate 2568.1.

---

# Published Version Behavior

Publishing a new version affects:

```text
new Guest calculation for that tax year
new Member return creation
```

according to the existing PublishedTaxRuleResolver.

Existing Member TaxReturns remain tied to their originally assigned rule version.

Add tests proving this.

---

# Archive Rule Version

Archive is allowed only when safe.

Do not delete rule versions referenced by:

```text
tax_returns
tax_calculations
scenarios
```

Archiving should mean:

```text
not selected for new calculations
still readable historically
```

---

# Tax Rule Admin Read API

Admin detail for a rule version should expose:

```text
version
status
tax year
counts of rule entities
validation summary
source/evidence coverage
created_at
published_at
```

Do not expose unnecessary internal ORM data.

---

# Part E — Audit Log

Admin CMS/rule changes need basic auditability.

Create/reuse:

```text
admin_audit_logs
```

Suggested fields:

```text
id
actor_user_id
action
entity_type
entity_id
summary
before_json nullable
after_json nullable
created_at
```

Do not log passwords/tokens.

Do not store full sensitive member tax payloads here.

This audit log is for:

```text
content changes
tax rule version changes
source metadata changes
publish actions
```

---

# Audit Actions

Suggested codes:

```text
CONTENT_CREATED
CONTENT_UPDATED
CONTENT_PUBLISHED
CONTENT_UNPUBLISHED
CONTENT_ARCHIVED

RULE_VERSION_CREATED
RULE_VERSION_CLONED
RULE_VERSION_VALIDATED
RULE_VERSION_PUBLISHED
RULE_VERSION_ARCHIVED

TAX_RULE_CREATED
TAX_RULE_UPDATED
TAX_RULE_DELETED

TAX_SOURCE_CREATED
TAX_SOURCE_UPDATED
TAX_SOURCE_ARCHIVED
```

Keep codes stable.

---

# Part F — Admin API Resources / Requests / Policies

Create focused:

```text
FormRequests
Policies
Resources
Services
```

Suggested:

```text
ContentPolicy
TaxRuleVersionPolicy
TaxSourcePolicy
```

If admin middleware makes per-entity policies redundant, still keep authorization explicit and testable.

---

# Suggested Services

```text
ContentService
ContentPublishingService
TaxRuleVersionService
TaxRuleVersionCloneService
TaxRuleVersionValidationService
TaxRuleVersionPublishingService
AdminAuditService
```

Do not create empty wrappers.

---

# Part G — Minimal Admin Web UI

The project currently has little/no real UI beyond the M1 skeleton.

For M8, implement a MINIMAL functional admin UI only.

Do NOT redesign the approved public site.

Use the existing frontend stack:

```text
HTML
Tailwind CSS
JavaScript
Laravel web routes/views if already configured
```

Admin UI may provide:

```text
/admin/login or reuse existing auth flow
/admin
/admin/content
/admin/content/create
/admin/content/{id}/edit
/admin/tax-rule-versions
/admin/tax-rule-versions/{id}
/admin/tax-sources
```

The UI should consume the same backend services/APIs where practical.

Do not build SPA infrastructure.

Do not introduce React/Vue unless already part of the project.

---

# Admin Dashboard

Minimal dashboard cards:

```text
Published content count
Draft content count
Current published tax rule version
Draft tax rule versions
Recent admin activity
```

No analytics platform.

---

# CMS Editor UI

Provide a practical form for:

```text
type
title
slug
excerpt
body
category
tags
tax year
featured
SEO title/description
```

Publish/unpublish buttons must call the proper workflow.

---

# Rule Version UI

Admin should be able to:

```text
list rule versions
view status
clone published version to draft
validate draft
see validation errors/warnings
publish valid draft
inspect rule counts/source references
```

Do not create a giant visual tax-rule builder in M8.

Basic structured tables/forms are enough.

---

# Rule Editing UI

For draft versions, support practical editing of current rule entities.

Priority:

```text
tax brackets
expense rules
allowance rules
combined cap groups
donation rules
recommendation rules
```

If a specific rule editor would be excessively complex, expose read-only table + API-backed JSON-safe form rather than inventing a visual DSL.

---

# Public Website Content Pages

M8 may add the basic public content pages needed for:

```text
/news
/knowledge
/article/{slug}
/faq
```

Use the previously approved public visual direction.

Do NOT redesign the simulator UI.

Keep pages responsive.

---

# SEO Basics

For public content pages support:

```text
<title>
meta description
canonical-friendly slug
basic Open Graph metadata if simple
```

Do not build a full SEO platform.

---

# Security

Admin endpoints must be protected.

Test:

```text
guest -> 401
member -> 403
admin -> allowed
```

Do not trust client ownership/author IDs.

Do not expose draft content publicly.

Do not expose unpublished tax-rule drafts to public metadata endpoints.

---

# XSS / Content Safety

If HTML content is supported:

- sanitize input;
- encode outputs appropriately;
- do not use raw Blade rendering unless sanitized;
- add tests for script/event-handler rejection.

---

# Mass Assignment

Protect sensitive/internal fields.

Do not allow CMS requests to set:

```text
author_user_id
published_by
created_by
status via generic update if workflow action exists
```

---

# Rule Administration Safety

Write tests proving:

```text
published version cannot be edited
published version cannot be deleted
draft version can be edited
invalid draft cannot be published
valid draft can be published
existing returns keep old version
new returns resolve newest published version
```

---

# Public API Regression

All existing endpoints from M1–M7.5 must remain compatible.

Especially:

```text
/api/v1/tax/calculate
/api/v1/tax/plan
/api/v1/tax-years/*
/api/v1/tax-returns/*
auth endpoints
recommendation/scenario endpoints
```

Do not change tax result semantics.

---

# Content Tests

At minimum:

```text
public list returns published only
draft not visible
archived not visible
article detail by slug
category filter
tag filter
tax year filter
search
pagination
featured
FAQ
```

---

# Admin CMS Tests

At minimum:

```text
guest denied
member denied
admin allowed

create draft content
update draft
publish
public visibility after publish
unpublish
archive
soft delete
slug uniqueness
published slug immutability
XSS/content sanitization
```

---

# Tax Source Tests

At minimum:

```text
admin CRUD
public user denied
source referenced by published rule cannot be destructively deleted
```

---

# Rule Version Tests

At minimum:

```text
list versions
create draft
clone 2568.1 -> new draft
draft editable
published immutable
structural validation
invalid publish rejected
valid publish succeeds
existing return remains on 2568.1
new return uses newly published version
history unchanged
```

Do not actually publish a fake production version in normal development data unless using isolated tests.

Manual verification should use a controlled synthetic draft.

---

# Audit Log Tests

Verify audit rows for:

```text
content create/update/publish
rule-version clone/publish
tax-source change
```

Ensure sensitive data is not captured.

---

# Frontend Build

Run existing frontend build.

Do not introduce an unnecessary framework migration.

---

# Documentation

Create:

```text
docs/api/MILESTONE_08_API.md
docs/admin/CONTENT_CMS.md
docs/admin/TAX_RULE_ADMINISTRATION.md
docs/admin/TAX_RULE_VERSION_LIFECYCLE.md
```

Update README with:

```text
admin access
public content endpoints
rule-version workflow
```

---

# Manual Verification

Use synthetic/test admin data only.

Verify:

1. admin login
2. member denied admin API
3. create knowledge article
4. edit article
5. publish article
6. article visible publicly
7. unpublish -> no longer public
8. create category/tag
9. open tax rule version list
10. clone 2568.1 into synthetic draft
11. modify a draft rule
12. validate draft
13. confirm published 2568.1 edit is blocked
14. inspect tax source reference
15. inspect audit log
16. admin UI loads
17. public knowledge page loads
18. frontend build succeeds
19. tax calculation regression unchanged

Do NOT publish a synthetic new production rule version during manual verification unless the test environment is isolated.

---

# Commands

Inspect:

```bash
git status
docker compose ps
php artisan route:list
```

Run non-destructive migrations.

Run:

```bash
docker compose exec app php artisan test
```

Run MySQL-backed test suite.

Run:

```bash
docker compose exec app ./vendor/bin/pint --test
```

or current project equivalent.

Run:

```bash
npm run build
```

or current frontend build command.

Do not use migrate:fresh on active development data unless absolutely necessary and explicitly reported.

---

# Completion Criteria

M8 is complete only when:

```text
[ ] public content API exists
[ ] articles/news/guides/FAQs supported
[ ] categories/tags supported
[ ] drafts are never public
[ ] admin CMS CRUD works
[ ] publish/unpublish/archive workflow works
[ ] admin authorization works
[ ] content is safely sanitized
[ ] tax source administration exists
[ ] rule-to-source traceability exists where needed
[ ] rule-version list/create/clone works
[ ] published rule versions are immutable
[ ] draft rule editing works
[ ] rule-version validation exists
[ ] invalid draft cannot publish
[ ] valid draft publish workflow exists
[ ] old returns retain old rule version
[ ] new returns resolve newest published version
[ ] admin audit log exists
[ ] minimal admin UI works
[ ] public knowledge/news pages work
[ ] no tax-engine semantics were changed
[ ] all M1–M7.5 regression tests pass
```

---

# Required Final Report

Report exactly:

## Summary

## Public Content Routes

## Admin CMS Routes

## Tax Source Routes

## Tax Rule Version Routes

## Draft Rule Editing Routes

## Models Created / Modified

## Migrations Created

## Services Created

## Policies / Middleware

## FormRequests

## API Resources

## Public Content Behavior

## Admin CMS Behavior

## Tax Rule Version Lifecycle

Explain:

```text
draft
validate
publish
archive
immutability
```

## Published 2568.1 Protection

Explicitly confirm how in-place edits are prevented.

## Historical Return Protection

Confirm existing TaxReturns/TaxCalculations remain tied to historical rule versions.

## Source Traceability

Explain how tax rules reference approved sources.

## Audit Logging

## Admin UI

List implemented pages.

## Public Content UI

List implemented pages.

## Security

Summarize:

```text
401
403
draft visibility
XSS protection
mass-assignment protection
```

## Commands Executed

Only commands actually run.

## Test Coverage

## Test Results

Report:

```text
SQLite passed/failed/skipped/assertions
MySQL passed/failed/skipped/assertions
```

## Manual Verification

## Regression Status

Confirm:

```text
PND91 baseline unchanged
PND90 baseline unchanged
planning/recommendation unchanged
member history intact
Guest stateless behavior intact
security/ownership intact
```

## Known Issues

List unresolved M8 issues or:

```text
None
```

## Architecture Compliance

Confirm:

```text
M7.x tax-engine baseline was not reopened.
Published rule versions are immutable.
Tax rule changes use draft -> validate -> publish.
No historical TaxReturn was auto-upgraded.
No historical calculation snapshot was rewritten.
Public and Mobile clients can consume the same content/tax metadata APIs.
Admin UI uses the existing stack.
No unnecessary frontend framework was introduced.
```

## Next Step

Recommend:

```text
Milestone 09 — Public Simulator UI + Member Dashboard + Planning UI Integration
```

Do not begin Milestone 09 until approved.

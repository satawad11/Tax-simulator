# FINAL_GAP_CLOSURE_PROMPT.md

## Codex Task: Final Gap Closure — Complete All Implementable Release 1.x Gaps in One Controlled Pass

You are working on the Thai Personal Income Tax Simulation Platform.

This task is based on:

```text
REMAINING_IMPLEMENTATION_GAPS.md
```

The purpose is to close every remaining gap that can be closed safely in one execution, while preserving the published 2568.1 baseline and refusing to invent tax rules that lack approved evidence.

This is a controlled closure task, not a broad redesign.

---

# Mandatory First Step

Read:

```text
REMAINING_IMPLEMENTATION_GAPS.md
PROJECT_REQUIREMENTS.md
CODING_RULES.md
docs/tax/
docs/api/
docs/ui/
docs/qa/
docs/ops/
docs/releases/
docs/tax-source/
```

Inspect current code, schema, seeders, routes, tests, frontend, and current MySQL development data.

Do not change anything until the gap inventory is reconciled against the current repository state.

---

# Non-Negotiable Safety Rules

1. Published tax rule version `2568.1` is immutable.
2. Do not use web research or model memory to invent tax rules.
3. Do not add numeric ceilings, rates, eligibility, rounding, or credit formulas unless supported by an approved repository source.
4. Do not rewrite historical TaxReturns or TaxCalculation snapshots.
5. Do not use `migrate:fresh`.
6. Do not delete unknown development data.
7. Do not implement real e-filing/payment/refund submission integrations in this task.
8. Web and future Mobile must keep the same API contract.
9. Frontend must not implement tax formulas in JavaScript.

---

# Overall Execution Strategy

Execute the work in this order:

```text
Phase 0 — Reconcile current gap inventory
Phase 1 — Evidence and source readiness
Phase 2 — Schema/API contract closure
Phase 3 — Draft tax rule version
Phase 4 — Backend implementation
Phase 5 — Frontend enablement
Phase 6 — Automated QA
Phase 7 — Browser/device readiness
Phase 8 — Operations readiness
Phase 9 — Final closure decision
```

Do not skip phases.

---

# Phase 0 — Reconcile Gap Inventory

Create/update:

```text
docs/qa/FINAL_GAP_CLOSURE_MATRIX.md
```

For every code below:

```text
BE-01 .. BE-18
FE-01 .. FE-07
QA-01 .. QA-05
Production/Operations tasks
```

record:

```text
current_status
can_close_now
blocking_dependency
planned_action
final_status
evidence
```

Use final statuses:

```text
CLOSED
GUARDED
BLOCKED_BY_SOURCE
BLOCKED_BY_EXTERNAL_ENVIRONMENT
OUT_OF_SCOPE
```

---

# Phase 1 — Evidence and Source Readiness

Audit repository-approved sources for:

```text
BE-01 PENSION_INSURANCE
BE-02 SOCIAL_SECURITY
BE-04 domestic tourism measure
BE-08 foreign tax credit
BE-10 legal final rounding
```

For each item:

## If sufficient approved source exists

Record:

```text
source file
page/section
exact rule semantics
test vectors that can be derived without guessing
```

and mark:

```text
SOURCE_READY
```

## If source is still insufficient

Keep runtime guarded.

Mark:

```text
BLOCKED_BY_SOURCE
```

Do not implement guessed rules.

This source decision must be explicit before code changes.

---

# Phase 2 — Schema / API Contract Closure

Design additive, backward-compatible contracts for:

```text
BE-05 dividend tax credit / gross-up
BE-06 separate-tax property income election
BE-07 income excluded from aggregation election
BE-11 exempt-income fund components
BE-12 disabled-person relation discriminator
```

Only implement a contract if the current repository sources and requirements define the necessary semantics.

Preferred principles:

```text
structured fields
explicit enums/codes
no free-text rule selection
nullable additive fields
server-side validation
mobile-compatible
```

Required outputs:

```text
docs/api/FINAL_GAP_API_CONTRACT.md
docs/tax/FINAL_GAP_DATA_MODEL.md
```

For each new field, document:

```text
request path
type
enum values
validation
persistence
calculation impact
backward compatibility
```

If semantics are insufficient, keep the item guarded and mark BLOCKED_BY_SOURCE.

---

# Phase 3 — Create a New Draft Rule Version

Do NOT modify `2568.1`.

If at least one source-backed tax rule will be added or a supported calculation result will change, create:

```text
2568.2
```

as:

```text
draft
```

Clone only rule data required from 2568.1.

Do not publish yet.

If no tax semantics are changed, do not create a new version unnecessarily.

Document the rule-version decision.

---

# Phase 4 — Backend Implementation

Implement only source-ready items.

Priority order:

```text
1. BE-01 PENSION_INSURANCE
2. BE-02 SOCIAL_SECURITY
3. BE-04 domestic tourism allowance
4. BE-08 foreign tax credit
5. BE-10 legal final rounding
6. BE-05 dividend tax credit/gross-up
7. BE-06 separate-tax property income
8. BE-07 excluded-from-aggregation election
9. BE-11 exempt-income component validation
10. BE-12 disabled-person relation limit
```

For every implemented item:

```text
DTO/request
validation
service/rule
trace
warning/error code
tests
API resource if needed
persistence if Member flow requires it
planning compatibility
Guest/Member parity
```

No frontend-specific calculation logic.

---

# BE-01 — PENSION_INSURANCE

If source fully resolves:

```text
individual ceiling
percentage rule
interaction/nesting with other insurance/retirement caps
shared cap membership
```

implement it in draft rule version.

Otherwise keep:

```text
422 PENSION_INSURANCE_RULE_PARTIAL
```

---

# BE-02 — SOCIAL_SECURITY

Implement only if approved repository source gives the exact ceiling/eligibility rule.

Otherwise keep:

```text
422 SOCIAL_SECURITY_RULE_UNSUPPORTED
```

---

# BE-04 — Domestic Tourism Measure

Implement only if source defines:

```text
eligible amount/rate
ceiling
eligible period
location/condition logic
```

Otherwise leave unsupported.

Do not infer a ceiling.

---

# BE-05 — Dividend Credit / Gross-Up

Implement only if source and approved contract define:

```text
input values
gross-up formula
credit formula
aggregation order
tax base interaction
```

Must have dedicated tests.

---

# BE-06 — Separate-Tax Property Income

Implement only if the source defines:

```text
election
eligible income type
separate calculation path
interaction with progressive base
final aggregation/payment behavior
```

Do not force this into normal progressive income if the source says separate taxation.

---

# BE-07 — Income Excluded From Aggregation

Implement only with a source-backed election and clear calculation effect.

Use explicit enum/boolean fields, never free text.

---

# BE-08 — Foreign Tax Credit

Implement only if source defines:

```text
eligible foreign tax
credit ceiling
Thai-tax limitation
ordering
carry/unused treatment if any
```

Otherwise keep:

```text
422 FOREIGN_TAX_CREDIT_UNSUPPORTED
```

---

# BE-10 — Legal Final Rounding

Implement only if source defines:

```text
rounding stage
rounding unit
rounding direction
```

Add exact boundary tests.

Otherwise keep:

```text
ROUNDING_RULE_PENDING
```

and do not claim official filing accuracy.

---

# BE-11 — Exempt Income Fund Components

If contract/source support it, split user-declared exempt income into structured components such as:

```text
PVD
GPF
private-teacher welfare fund
other approved component
```

Do not trust only an aggregate if the source requires component caps.

If insufficient semantics remain, keep user declaration and warning.

---

# BE-12 — Disabled Person Relation Limit

If source + schema support relation discrimination, add a relationship/role field sufficient to distinguish:

```text
family member
other person
```

Apply the supported limit.

Do not invent relationship semantics beyond source.

---

# BE-13 / BE-14

Keep as documented product constraints:

```text
BE-13 taxpayer-declared eligibility
BE-14 only tax year 2568 / rule version baseline
```

Do not invent government integrations or additional tax years.

Final status should normally be:

```text
GUARDED
```

or:

```text
OUT_OF_SCOPE
```

---

# BE-15 .. BE-18

These are NOT to be implemented in this task:

```text
real Revenue Department filing
digital signature/identity for filing
official document attachment submission
payment/refund submission integration
```

Mark:

```text
OUT_OF_SCOPE
```

Keep simulator wording clear.

---

# Phase 5 — Frontend Enablement

Enable frontend controls only for backend items that are actually implemented and source-backed.

Close:

```text
FE-01 .. FE-05
```

only where their backend dependency is CLOSED.

Rules:

```text
USER_INPUT / CONDITIONAL_INPUT only
progressive disclosure
clear Thai guidance
no tax formulas in JavaScript
unsupported items remain visibly disabled/explained
```

Do not create controls for blocked backend rules.

FE-06 remains OUT_OF_SCOPE.

FE-07 native mobile app remains roadmap / OUT_OF_SCOPE for this task.

---

# Phase 6 — Automated QA

Add/extend tests for every implemented gap.

At minimum:

```text
unit rule tests
feature API tests
Guest/Member parity
planning compatibility
Member persistence/resume
historical immutability
published version immutability
validation guard tests
frontend route/render tests
```

Run:

```bash
docker compose exec app php artisan test
```

Run MySQL-backed suite.

Run:

```bash
docker compose exec app ./vendor/bin/pint --test
```

Run frontend production build.

Do not weaken existing assertions.

---

# Phase 7 — Browser / Device Readiness

QA-01 Safari
QA-02 Firefox
QA-03 physical phone/tablet
QA-04 browser E2E
QA-05 load/capacity

## In-repository work that CAN be completed now

If no browser automation exists, add a lightweight Playwright smoke suite ONLY if it can be done without destabilizing the stack.

Target flows:

```text
home
PND91
PND90
login
member dashboard
knowledge/FAQ
```

Use Chromium + Firefox if the current environment supports them.

Do not claim Safari coverage from Playwright WebKit unless actual project acceptance allows it.

## External/manual work that CANNOT be honestly completed in the current environment

If real Safari/macOS/iOS or physical-device access is unavailable:

mark:

```text
BLOCKED_BY_EXTERNAL_ENVIRONMENT
```

Create a precise manual test checklist instead of claiming completion.

For QA-05 load/capacity:
- only run if a performance target/SLA is defined;
- otherwise keep NOT_APPLICABLE/BLOCKED pending SLA.

---

# Phase 8 — Operations Readiness

Do NOT deploy production from this task unless the actual production environment and explicit deployment authorization are available.

Complete all repository-side preparation:

```text
production env validation command/checklist
production-safe seeding verification
backup command verification
restore command verification
rollback steps
health/readiness verification
admin bootstrap procedure
monitoring checklist
secret checklist
```

Update:

```text
docs/ops/PRODUCTION_READINESS_CHECKLIST.md
docs/ops/DEPLOYMENT.md
docs/ops/BACKUP_RESTORE.md
docs/ops/ROLLBACK.md
docs/ops/PRODUCTION_SEEDING.md
docs/releases/RELEASE_1_0.md
```

Do not include real secrets.

---

# Phase 9 — Final Closure Matrix

Update:

```text
docs/qa/FINAL_GAP_CLOSURE_MATRIX.md
```

Every gap must end in exactly one status:

```text
CLOSED
GUARDED
BLOCKED_BY_SOURCE
BLOCKED_BY_EXTERNAL_ENVIRONMENT
OUT_OF_SCOPE
```

No vague TODO state.

---

# Completion Goal

This one pass is successful when:

```text
all source-ready implementation gaps are CLOSED
all unsupported source-insufficient rules remain safely GUARDED/BLOCKED_BY_SOURCE
all out-of-scope filing integrations are explicitly OUT_OF_SCOPE
all repository-side QA/ops preparation is complete
full regression passes
no 2568.1 mutation occurs
```

It is NOT necessary to fake completion of items that require:
- new approved legal/tax sources
- real Safari/iOS hardware
- actual production secrets/environment
- Revenue Department integrations

---

# Required Final Report

Report exactly:

## Summary

## Final Gap Closure Counts

Report counts for:

```text
CLOSED
GUARDED
BLOCKED_BY_SOURCE
BLOCKED_BY_EXTERNAL_ENVIRONMENT
OUT_OF_SCOPE
```

## Backend Gap Status

Report BE-01 through BE-18 individually.

## Frontend Gap Status

Report FE-01 through FE-07 individually.

## QA Gap Status

Report QA-01 through QA-05 individually.

## Production / Operations Status

## Approved Sources Used

List exact repository source paths for every newly implemented tax rule.

## Rule Version Decision

State whether 2568.2 draft was created and why.

Confirm 2568.1 remains immutable.

## Schema / API Changes

## Backend Implementation

## Frontend Implementation

## Runtime Guards Remaining

## Files Created

## Files Modified

## Commands Executed

Only actual commands.

## Test Results

Report:

```text
SQLite passed/failed/skipped/assertions
MySQL passed/failed/skipped/assertions
Pint/lint
frontend build
browser automation if added
```

## Manual Verification

## Historical Integrity

Confirm:

```text
no historical snapshots rewritten
no completed return auto-upgraded
published 2568.1 unchanged
```

## Known External Blockers

Only list items that genuinely require external sources/environment.

## Final Release Decision

Exactly one:

```text
READY_FOR_CONTROLLED_PRODUCTION_DEPLOYMENT
```

or:

```text
NOT_READY_FOR_PRODUCTION
```

Do not mark NOT_READY merely because guarded, out-of-scope Release 1.0 items remain.

Mark NOT_READY only if a supported path is incorrect, unsafe, or materially incomplete.

## Final Recommendation

If ready:

```text
Proceed with controlled production deployment for the documented Release 1.0 supported scope.
Track BLOCKED_BY_SOURCE and OUT_OF_SCOPE items as post-1.0 roadmap work.
```

If not ready:

```text
Do not deploy. Resolve the listed release-blocking defects, then rerun the full regression/security suite.
```

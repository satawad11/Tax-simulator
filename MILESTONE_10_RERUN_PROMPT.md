# MILESTONE_10_RERUN_PROMPT.md

## Codex Task: Final M10 Regression / Security Re-Run After M9.2 + M9.2.1

This is a SHORT final verification pass.

Do NOT add new features.
Do NOT redesign the UI.
Do NOT reopen M7.x tax-rule development.
Do NOT change published tax rule version 2568.1.
Do NOT create M9.3 or M10.1 unless a real blocker is found.

The only purpose of this task is to re-run the production-readiness checks affected by:

```text
M9.2   PND90 / PND91 Form Fidelity + Guided Data Entry
M9.2.1 Form Field Role Audit
```

The previous M10 was already completed.

This task verifies that the M9.2 / M9.2.1 changes did not introduce regressions.

---

# Read First

Read:

```text
PROJECT_REQUIREMENTS.md
CODING_RULES.md

MILESTONE_09_2_PROMPT.md
MILESTONE_09_2_1_PROMPT.md
MILESTONE_10_PROMPT.md

docs/ui/PND90_FORM_COVERAGE_MATRIX_2568.md
docs/ui/PND91_FORM_COVERAGE_MATRIX_2568.md
docs/ui/FORM_FIDELITY_GUIDELINES.md
docs/ui/M9_2_FORM_RECONCILIATION.md

docs/ops/PRODUCTION_READINESS_CHECKLIST.md
docs/qa/RELEASE_TEST_MATRIX.md
docs/releases/RELEASE_1_0.md
```

Inspect current code before changing anything.

---

# Scope

Re-run only checks relevant to:

```text
form fidelity
frontend field behavior
Member draft resume
Guest statelessness
validation UX
API compatibility
tax regression
security regression
frontend build
production readiness
```

Do not revisit unrelated completed milestone work unless a regression appears.

---

# 1. Form Fidelity Regression

Verify that:

```text
PND90 coverage matrix remains complete
PND91 coverage matrix remains complete
every material row has COVERAGE_STATUS
every material row has FIELD_ROLE
```

Allowed FIELD_ROLE values:

```text
USER_INPUT
CONDITIONAL_INPUT
DERIVED
INFORMATIONAL
FILING_ONLY
UNSUPPORTED
```

Confirm:

```text
no DERIVED field is editable
no required USER_INPUT is missing
no CONDITIONAL_INPUT is forced always-visible
no FILING_ONLY field enters calculation payload
no UNSUPPORTED field has misleading enabled numeric entry
```

---

# 2. PND91 UI Regression

Verify the supported PND91 flow:

```text
tax year / form
marital status
birth date
spouse when applicable
40(1) gross income
exempt income where supported
family/dependents
supported allowances
donations
withholding
PND93/PND94 where supported
review
calculate
result
```

Confirm all derived values remain backend-only:

```text
expense
personal allowance
net income
tax
PAYABLE / REFUND / ZERO
```

No editable field may appear for these derived values.

---

# 3. PND90 UI Regression

Verify:

```text
40(1)
40(2)
40(3)
40(4)
40(5)
40(6)
40(7)
40(8)
```

Confirm conditional behavior for:

```text
income subtype
expense activity
expense method
actual expense
holding years
tax treatment
family/dependent facts
supported prepayment types
```

Check especially:

```text
40(3) subtype + expense election
40(5) property/rental subtype + expense method
40(6) professional subtype + expense method
40(7) contract income + expense method
40(8) subtype/activity/holding years/tax treatment
```

---

# 4. Unsupported / Partial Rules

Verify unsupported items remain:

```text
visible/explained where appropriate
disabled
not submitted as supported numeric input
guarded by backend
```

At minimum re-check:

```text
foreign tax credit
other credit
unsupported allowance items
PND90 separate-tax unsupported paths
unsupported social-security/pension paths from M7.5
```

Do NOT implement them in this task.

---

# 5. Review Screen Regression

Verify PND91 review groups:

```text
income
expense
allowances
donations
withholding/prepayments
```

Verify PND90 review groups:

```text
income by 40(1)–40(8)
expense by source type/activity
family allowances
other allowances
donations
prepayments/credits
minimum-tax information
```

Review screen must not recompute tax logic in JavaScript.

---

# 6. Member Resume Fidelity

Create/use synthetic Member draft data.

Verify after save -> leave -> resume:

```text
income type
income subtype
expense activity
expense method
actual expense
holding years
tax treatment
family facts
dependents
allowance selections
donations
withholdings
```

all survive correctly.

Do not rewrite completed historical calculations.

---

# 7. Guest Statelessness

Verify Guest:

```text
can complete PND91 flow
can complete PND90 flow
can calculate
can plan
creates no backend Member/TaxReturn persistence
```

Browser-session state is acceptable.

Server-side anonymous persistence is not.

---

# 8. API Compatibility

Verify existing APIs still accept previous valid requests.

Do not remove/rename fields.

Any M9.2 metadata additions must remain additive.

Web and future Mobile must use the same API contracts.

---

# 9. Tax Regression

Run full regression and explicitly confirm unchanged:

```text
PND91 baseline
PND90 supported baseline
progressive tax
minimum tax
family allowances
expense rules
allowance caps
donations
withholding/prepayments
planning/recommendation
refund/payment guidance
Guest/Member parity
```

If any tax result changed unexpectedly:

STOP.

Do not update expected values casually.

---

# 10. Security Regression

Re-run critical checks:

```text
guest denied admin
member denied admin
cross-user tax-return denial
cross-user calculation/history denial
cross-user scenario denial
published rule version immutable
draft content not public
mass assignment protection
XSS protection
login throttling
public calculation/planning rate limits
```

Also verify M9.2 UI changes did not expose:

```text
raw internal enum values
raw validation paths
internal IDs unnecessarily
debug output
```

---

# 11. Validation UX Regression

Verify backend errors map to understandable Thai labels.

Examples:

```text
incomes.*.actual_expense
incomes.*.holding_years
dependents.*.birth_date
allowances.*
```

Do not display raw JSON field paths to users.

---

# 12. Responsive Smoke Check

Re-check affected simulator pages at:

```text
375px
768px
desktop
```

Focus only on:

```text
progressive disclosure
repeatable income rows
subtype/activity selects
expense method
review screen
unsupported-info cards
```

No accidental overflow.

---

# 13. Frontend Build

Run the current production frontend build.

It must pass with no broken imports/assets.

---

# 14. Full Test Suite

Run:

```bash
docker compose exec app php artisan test
```

Run the MySQL-backed test suite according to the current project workflow.

Run Pint/lint.

Run frontend build.

Do not weaken assertions.

---

# 15. Production Checklist Update

Update:

```text
docs/ops/PRODUCTION_READINESS_CHECKLIST.md
docs/qa/RELEASE_TEST_MATRIX.md
docs/releases/RELEASE_1_0.md
```

only where M9.2 / M9.2.1 changed the release baseline.

Do not rewrite unrelated historical documentation.

Add a short note that:

```text
Form fidelity and field-role audit were completed before final release verification.
```

---

# 16. Release Gate

At the end, choose exactly one:

```text
READY_FOR_CONTROLLED_PRODUCTION_DEPLOYMENT
```

or:

```text
NOT_READY_FOR_PRODUCTION
```

Use NOT_READY only if a release blocker exists.

Release blockers include:

```text
supported tax path returns wrong result
required user input missing
derived value editable by user
unsupported rule looks supported
Member resume loses required supported fields
Guest persistence/privacy regression
cross-user data leak
published rule mutable
critical frontend simulator path broken
full regression fails
```

---

# 17. Non-Blocking Limitations

Do NOT block release for already-documented guarded limitations such as:

```text
unsupported tax rules from M7.5
legal final rounding unsupported
external browser/device coverage not performed
non-critical cosmetic differences
```

provided they remain clearly guarded/documented.

---

# Required Final Report

Report exactly:

## Summary

## Release Decision

Exactly one:

```text
READY_FOR_CONTROLLED_PRODUCTION_DEPLOYMENT
```

or:

```text
NOT_READY_FOR_PRODUCTION
```

## Blocking Issues

If none:

```text
None
```

## PND91 Form Fidelity Regression

Confirm:

```text
required inputs present
derived fields non-editable
conditional fields behave correctly
unsupported fields guarded
```

## PND90 Form Fidelity Regression

Confirm the same for 40(1)–40(8).

## Field Role Audit Status

Report:

```text
incorrect editable DERIVED fields
missing USER_INPUT fields
incorrect always-visible CONDITIONAL_INPUT fields
incorrect FILING_ONLY inputs
misleading UNSUPPORTED inputs
```

Use `None` where applicable.

## Member Resume Fidelity

## Guest Statelessness

## API Compatibility

## Tax Regression Status

Confirm:

```text
PND91
PND90
minimum tax
family allowances
expense rules
allowance caps
donations
withholding/prepayments
planning/recommendation
refund/payment guidance
Guest/Member parity
```

## Security Regression

## Validation UX

## Responsive Smoke Check

Report:

```text
375px
768px
desktop
```

## Documentation Updated

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
```

## Production Checklist Status

Summarize:

```text
PASS
FAIL
BLOCKED
```

counts.

## Known Limitations

## Architecture Compliance

Confirm:

```text
No new tax formula was added.
No published 2568.1 rule was changed.
No frontend tax engine was added.
TaxCalculationService remains authoritative.
Guest remains stateless.
Member history remains intact.
Historical snapshots remain unchanged.
Web and future Mobile share the same API contract.
```

## Final Recommendation

If ready:

```text
Proceed with controlled production deployment using the documented deployment, backup, rollback, and monitoring procedures.
```

If not ready:

```text
Do not deploy until the listed blockers are resolved and this final M10 re-run is repeated.
```

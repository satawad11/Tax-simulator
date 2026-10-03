# MILESTONE_07_5_PROMPT.md

## Codex Task: Milestone 07.5 — PND90 / PND91 Production Baseline Audit & Closure

You are working on:

**Thai Personal Income Tax Simulation Platform**

This milestone is the FINAL Milestone 7.x task.

Its purpose is to close the first production baseline for:

```text
PND91
PND90
```

after Milestones 4–7.4.

After M7.5, do NOT create another Milestone 7.x unless a real correctness defect is later discovered and explicitly approved.

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
```

Also inspect all current:

```text
docs/tax-source/
docs/tax/*.md
docs/api/*.md

tax rule seeders
expense_rules
allowance_rules
donation_rules
recommendation_rules
tax bracket rules
income subtype/activity rules
combined-cap rules

TaxCalculationService
ProgressiveTaxCalculator
ExpenseCalculator
AllowanceCalculator
DonationCalculator
TaxCreditCalculator
MinimumTaxCalculator
TaxRefundService
TaxAnalysisService
TaxPlanningService
TaxRecommendationService

Guest tax API
Member tax-return calculation API
scenario/planning APIs
tests
```

Milestone 08 MUST NOT be started until this closure milestone is complete.

---

# Core Goal

Perform a final source-grounded audit of the PND91 and PND90 calculation baseline and close Milestone 7.x.

The goal is NOT to implement every possible Thai tax rule.

The goal is:

```text
Every supported calculation path is source-backed and deterministic.
Every unsupported/partial path is explicitly guarded and documented.
No path silently guesses tax rules.
PND91 and PND90 baselines are frozen for the next milestone.
```

At the end of M7.5, every material rule must have exactly one status:

```text
SUPPORTED
PARTIAL_BLOCKED
UNSUPPORTED
NOT_APPLICABLE
```

Do not leave ambiguous statuses such as:

```text
probably supported
temporary
maybe
TODO without runtime behavior
```

---

# Source Policy

Use only:

```text
repository-approved source documents
approved project requirements
already-approved project data
```

Do NOT use:

```text
web research
general tax knowledge
model memory
unsourced assumptions
```

Do not silently correct the source.

Do not fill missing numbers from memory.

If source support is incomplete:

```text
PARTIAL_BLOCKED
```

or:

```text
UNSUPPORTED
```

and guard the runtime path.

---

# Closure Principle

M7.5 is an AUDIT + CLOSURE milestone.

Do not introduce broad new architecture unless required to fix a real calculation defect.

Prefer:

```text
classification
guarding
documentation
tests
small source-supported fixes
```

over new framework design.

---

# 1. Build Final PND91 Checklist

Create/update:

```text
docs/tax/PND91_PRODUCTION_BASELINE_2568.md
```

Audit PND91 end-to-end against repository sources.

At minimum cover:

```text
form applicability
SECTION_40_1
income aggregation
exempt income handling
expense rule
PERSONAL allowance
SPOUSE allowance
CHILD allowance
PARENT allowance where supported
DISABLED_PERSON where supported
other VERIFIED allowances
donation handling
progressive tax
withholding
credits that are actually supported
PAYABLE
REFUND
ZERO
calculation trace
warnings
Guest flow
Member flow
planning/recommendation reuse
```

For every line, report:

```text
status
source
implementation
runtime behavior
known limitation
```

---

# PND91 Baseline Requirement

The existing no-family PND91 regression baseline must remain unchanged unless an actual source-backed defect is found.

Known baseline from previous milestones:

```text
net income = 620,000
calculated tax = 45,500
```

for the existing synthetic regression case.

Do not change that result casually.

---

# 2. Build Final PND90 Checklist

Create/update:

```text
docs/tax/PND90_PRODUCTION_BASELINE_2568.md
```

Audit PND90 end-to-end.

At minimum cover:

```text
SECTION_40_1
SECTION_40_2
SECTION_40_3
SECTION_40_4
SECTION_40_5
SECTION_40_6
SECTION_40_7
SECTION_40_8

income subtype/activity rules
expense rules
actual-expense paths where supported
minimum tax
family allowances
other VERIFIED allowances
donations
progressive tax
separate-tax paths only if supported
credits/prepayments only if supported
withholding
PAYABLE
REFUND
ZERO
calculation trace
warnings/errors
Guest flow
Member flow
planning compatibility
history persistence
```

Every material rule/path must be classified as:

```text
SUPPORTED
PARTIAL_BLOCKED
UNSUPPORTED
NOT_APPLICABLE
```

---

# 3. Final Status Classification Rules

Use these definitions.

## SUPPORTED

The repository sources provide enough information for deterministic calculation and the implementation/tests exist.

## PARTIAL_BLOCKED

Some semantics are known, but one or more required facts/rules are missing.

Runtime MUST block or safely refuse the affected path.

It must not calculate a potentially misleading tax result.

## UNSUPPORTED

The project intentionally does not support the rule/path in this baseline.

Runtime must clearly reject or omit it.

## NOT_APPLICABLE

The rule does not apply to the current form/scope.

---

# 4. Remaining M7.4 Blockers — Final Decisions

The previous milestone identified these remaining issues.

M7.5 must give each one a FINAL status and runtime behavior.

---

# 4.1 PENSION_INSURANCE

Known issue:

```text
source prints 90,000 and 200,000 semantics
nesting/interaction remains unclear
```

Required decision:

- if repository source fully resolves the relationship -> implement and mark SUPPORTED;
- otherwise mark PARTIAL_BLOCKED.

Do NOT guess whether 90,000 is inside or outside 200,000.

If PARTIAL_BLOCKED:

```text
do not auto-calculate eligible deduction
return stable warning/error
document exact missing clarification
```

---

# 4.2 SOCIAL_SECURITY

Known issue:

```text
repository source does not print the numeric maximum
```

Unless a repository-approved source now exists with the exact numeric rule:

```text
status = UNSUPPORTED or PARTIAL_BLOCKED
```

Do not seed a remembered numeric ceiling.

Runtime must not silently grant a deduction.

---

# 4.3 Tiered Allowance Item 22

Known issue:

```text
second band has multiplier but missing/unclear ceiling
```

If source does not resolve the second-band ceiling:

```text
status = PARTIAL_BLOCKED
```

Affected path must not return a fake calculated deduction.

---

# 4.4 Allowance Items 13 / 16 / 20

Known issue:

required payload facts may be missing, such as:

```text
special-zone location
investment-specific fact
construction cost or similar source-required fact
```

For each item:

- if current API/DTO now carries enough source-required facts -> implement;
- otherwise mark PARTIAL_BLOCKED.

Do NOT add speculative fields.

If a missing field is small, clearly source-required, and necessary to close the path, adding it is allowed.

Document it.

---

# 4.5 GPF / Private Teacher Welfare Fund

Known issue:

```text
source names them in a shared retirement basket
master allowance codes may be missing
```

If repository source clearly identifies these as distinct deductible items:

add explicit master codes and shared-cap membership.

Suggested stable codes only if source maps cleanly:

```text
GPF
PRIVATE_TEACHER_WELFARE_FUND
```

Do not force them into another allowance code just to avoid schema work.

If source semantics remain unclear:

```text
PARTIAL_BLOCKED
```

---

# 4.6 Item 5 Relation-Type Split

Known issue:

one source line needs relationship discrimination.

Resolve only if the source and current structured family/dependent data allow it.

Otherwise:

```text
PARTIAL_BLOCKED
```

Do not rely on free-text descriptions.

---

# 4.7 Legal Final Rounding

Known issue:

```text
ROUNDING_RULE_PENDING
```

If no repository-approved source explicitly specifies:

```text
rounding unit
rounding direction
rounding stage
```

then FINAL M7.5 status is:

```text
UNSUPPORTED
```

for legal final rounding.

Keep exact decimal calculation behavior already used by the project.

Keep a warning/disclaimer if appropriate.

This unresolved rounding rule does NOT block closure of the entire M7 baseline provided:

```text
the calculation remains deterministic
the absence is documented
the API does not claim official filing accuracy
```

Do not invent a rounding rule.

---

# 5. Review Allowance Coverage

Using:

```text
docs/tax/ALLOWANCE_RULE_MATRIX_2568.md
```

produce a final allowance coverage table.

For every allowance master code, record:

```text
SUPPORTED
PARTIAL_BLOCKED
UNSUPPORTED
NOT_APPLICABLE
```

At minimum include all current master codes.

Do not leave any code without status.

For SUPPORTED codes, identify:

```text
source
method
percentage/fixed amount
percentage base
individual cap
combined cap group
conditions
```

For partial/unsupported codes, identify:

```text
runtime guard behavior
```

---

# 6. Review PND90 Expense Coverage

Using:

```text
docs/tax/PND90_RULE_MATRIX.md
docs/tax/PND90_EXPENSE_SUBTYPE_MATRIX_2568.md
```

verify that all implemented PND90 income types/subtypes are source-backed.

Every machine code must map to:

```text
source row/item
expense method
rate/cap
actual-expense semantics
status
```

No free-text description may select a rule.

If an unsupported subtype exists:

```text
422 or equivalent domain rejection
```

---

# 7. Review Donation Coverage

Audit all donation behavior currently implemented.

For each donation code:

```text
source
multiplier
percentage cap
cap base
calculation order
status
```

If any code is not fully source-backed:

```text
PARTIAL_BLOCKED
```

and ensure it cannot create a tax benefit.

---

# 8. Review Credit / Prepayment Coverage

Audit:

```text
withholding
foreign_tax_credit
pnd93
pnd94
other_credit
```

For each, classify:

```text
SUPPORTED
PARTIAL_BLOCKED
UNSUPPORTED
NOT_APPLICABLE
```

Do not allow a partially verified credit to reduce tax.

Withholding behavior already used by PND91/PND90 must remain regression-safe.

---

# 9. Minimum-Tax Closure

Re-audit the M7.3 minimum-tax implementation against source.

Confirm:

```text
base definition
included income types
excluded income types
threshold
rate
pay-the-greater logic
small minimum-tax exception/floor behavior
interaction with expenses
interaction with allowances
interaction with donations
interaction with credits
```

Do not redesign it if current implementation matches source.

Add only missing tests or fix real source-backed defects.

---

# 10. Family-Allowance Closure

Re-audit:

```text
PERSONAL
SPOUSE
CHILD
PARENT
DISABLED_PERSON
```

Ensure:

```text
server-derived amounts
client values not trusted
structured facts used
PARTIAL eligibility conditions clearly flagged
Guest/Member parity
```

If a rule remains dependent on taxpayer declaration, document that explicitly.

A taxpayer-declared fact is acceptable if the simulator clearly states it is not independently verified.

---

# 11. PND91 / PND90 Runtime Guard Policy

By the end of M7.5:

Every PARTIAL_BLOCKED or UNSUPPORTED path must behave predictably.

Preferred:

```text
422 domain/validation error
```

for a material rule that would affect tax correctness.

Warnings may be used only if continuing calculation cannot materially mislead the final tax.

Do not silently set unknown deductions/credits to zero if the user expects that path to be supported and the omission could materially alter tax.

Use stable machine-readable codes.

Examples:

```text
PENSION_INSURANCE_RULE_PARTIAL
SOCIAL_SECURITY_RULE_UNSUPPORTED
ALLOWANCE_TIER_INCOMPLETE
FOREIGN_TAX_CREDIT_UNSUPPORTED
LEGAL_ROUNDING_UNSUPPORTED
```

Only add codes actually needed.

---

# 12. Production Baseline Freeze

Create:

```text
docs/tax/TAX_ENGINE_BASELINE_2568.md
```

This is the final milestone-7 baseline document.

It must summarize:

```text
PND91 supported scope
PND90 supported scope
SUPPORTED rules
PARTIAL_BLOCKED rules
UNSUPPORTED rules
NOT_APPLICABLE rules
known disclaimers
rule version
test baseline
API behavior
```

The document must clearly state:

```text
The simulator supports the listed paths only.
Unsupported or partially verified rules are explicitly blocked or disclosed.
The simulator is not an official tax filing system.
```

---

# 13. Freeze Rule Version

Review rule version policy one final time.

If M7.5 only:

```text
adds guards
adds documentation
adds missing master codes for previously unsupported paths
adds source-supported rules without changing previously supported semantics
```

then extending current reconciliation version may be acceptable according to project policy.

If a fix changes a previously supported calculation result:

```text
create a new internal rule version
```

and do NOT auto-upgrade historical returns.

Historical snapshots remain immutable.

Document the final decision.

---

# 14. Guest / Member / Planning Consistency

Verify:

```text
Guest calculation
Member calculation
Planning before/after
```

all use the same TaxCalculationService.

For equivalent inputs and same rule version:

```text
Guest result == Member result
```

Planning differences must arise only from changed scenario facts.

No separate formulas.

---

# 15. Historical Integrity

Do not rewrite:

```text
completed TaxCalculation snapshots
bracket snapshots
scenario historical results unless explicitly recalculated by user
```

Existing history must remain reproducible.

---

# 16. API Compatibility

Do not redesign the API.

Do not remove existing response fields.

Only additive guard/status fields are allowed if needed.

Do not begin frontend redesign.

---

# 17. Final PND91 Test Suite

Create or consolidate a final PND91 baseline test set covering at least:

```text
40(1)
expense
single taxpayer
spouse case
child case
supported family allowances
supported non-family allowance
donation if supported
withholding
PAYABLE
REFUND
ZERO
Guest/Member parity
planning regression
unsupported allowance guard
```

Use synthetic data only.

---

# 18. Final PND90 Test Suite

Create or consolidate a final PND90 baseline set covering at least:

```text
40(1)
40(2)
40(3)
40(4)
40(5)
40(6)
40(7)
40(8)

verified expense subtypes
mixed-income aggregation
minimum tax
family allowances
combined allowance cap
percentage allowance
supported donation
withholding
PAYABLE
REFUND
ZERO
Guest/Member parity
history persistence
planning compatibility
blocked partial allowance
blocked unsupported credit
```

Only test legal paths actually supported.

---

# 19. Source Coverage Audit

Create:

```text
docs/tax/SOURCE_COVERAGE_2568.md
```

For each implemented rule, identify:

```text
source file
page/item
machine rule/code
implementation class/service
test file
status
```

This should make future auditing easy.

Do not include invented references.

---

# 20. Documentation Cleanup

Ensure these docs are internally consistent:

```text
PND90_RULE_MATRIX.md
PND90_SOURCE_RECONCILIATION.md
PND90_EXPENSE_SUBTYPE_MATRIX_2568.md
PND90_PRODUCTION_BASELINE_2568.md

PND91_PRODUCTION_BASELINE_2568.md

ALLOWANCE_RULE_MATRIX_2568.md
ALLOWANCE_CAP_MODEL_2568.md
FAMILY_ALLOWANCE_MODEL_2568.md

MINIMUM_TAX_RECONCILIATION_2568.md
PND90_REMAINING_RULES_2568.md

TAX_ENGINE_BASELINE_2568.md
SOURCE_COVERAGE_2568.md

MILESTONE_07_API.md
```

Remove/replace stale statements that say a now-implemented rule is missing.

Do not delete historical milestone documents.

---

# 21. Do Not Chase Non-Blocking Completeness

Important closure rule:

The following does NOT automatically block M7.5 completion if explicitly classified/guarded:

```text
a source does not print a numeric limit
a legal rounding rule is unavailable
a rare optional deduction remains unsupported
a taxpayer fact is not modeled
an external-law reference is not included in repository sources
```

M7.5 may still close if:

```text
supported paths are correct
unsupported paths are blocked
documentation is explicit
tests are green
```

---

# 22. No M7.6

Do NOT recommend:

```text
Milestone 07.6
Milestone 07.7
another general reconciliation milestone
```

At the end of this task, either:

```text
M7 CLOSED — proceed to M8
```

or:

```text
M7 NOT CLOSED — list only correctness-critical blockers that prevent safe PND90/PND91 baseline use
```

A blocker is correctness-critical only if the current system can return a misleading/wrong tax result on a path it claims to support.

Unsupported guarded paths are not blockers.

---

# 23. Manual Verification

Use synthetic data only.

Verify at minimum:

## PND91

1. baseline no-family case
2. family allowance case
3. PAYABLE
4. REFUND
5. ZERO
6. Guest/Member parity

## PND90

7. mixed income
8. expense subtype
9. minimum tax
10. combined cap
11. percentage-based allowance
12. supported donation/credit behavior
13. blocked partial rule
14. blocked unsupported rule
15. Guest/Member parity

## Cross-system

16. planning still uses shared engine
17. historical snapshot unchanged
18. completed return immutable
19. cross-user security unchanged

---

# 24. Commands

Inspect:

```bash
git status
docker compose ps
find docs/tax-source -maxdepth 2 -type f
```

Use non-destructive migrations/seeding only.

Run:

```bash
docker compose exec app php artisan test
```

Run MySQL-backed tests.

Run Pint/lint.

Run frontend build regression if part of the project workflow.

Do not run migrate:fresh on active development data unless absolutely necessary and explicitly reported.

---

# 25. Completion Criteria

M7.5 is complete only when:

```text
[ ] PND91 production baseline document exists
[ ] PND90 production baseline document exists
[ ] TAX_ENGINE_BASELINE_2568.md exists
[ ] SOURCE_COVERAGE_2568.md exists

[ ] every material PND91 path has final status
[ ] every material PND90 path has final status
[ ] every allowance master code has final status
[ ] every donation rule has final status
[ ] every credit/prepayment type has final status
[ ] every PND90 expense subtype has final status

[ ] supported paths are source-backed
[ ] partial/unsupported material paths are guarded
[ ] no unverified numeric rule affects tax
[ ] no free-text rule selection exists
[ ] legal rounding is either sourced or explicitly unsupported
[ ] PND91 baseline remains correct
[ ] PND90 supported baseline remains correct
[ ] Guest/Member parity passes
[ ] planning reuses shared engine
[ ] historical snapshots remain unchanged
[ ] all regression tests pass
[ ] no UI redesign occurred
[ ] Milestone 08 was not started
```

---

# Required Final Report

Report exactly:

## Summary

## Final Milestone 7 Status

One of:

```text
M7 CLOSED
```

or:

```text
M7 NOT CLOSED
```

If NOT CLOSED, list only correctness-critical blockers.

Do not list safely guarded unsupported features as blockers.

## PND91 Production Baseline

List:

```text
SUPPORTED
PARTIAL_BLOCKED
UNSUPPORTED
NOT_APPLICABLE
```

for material PND91 areas.

## PND90 Production Baseline

List:

```text
SUPPORTED
PARTIAL_BLOCKED
UNSUPPORTED
NOT_APPLICABLE
```

for:

```text
SECTION_40_1
SECTION_40_2
SECTION_40_3
SECTION_40_4
SECTION_40_5
SECTION_40_6
SECTION_40_7
SECTION_40_8
minimum tax
allowances
donations
credits/prepayments
```

## Final Allowance Coverage

Give counts:

```text
SUPPORTED
PARTIAL_BLOCKED
UNSUPPORTED
NOT_APPLICABLE
```

and list codes in each group.

## Final Donation Coverage

## Final Credit / Prepayment Coverage

## Final Expense Coverage

Include PND90 subtypes/activity coverage.

## Final Minimum-Tax Status

## Final Rounding Status

## Rule Version Decision

## Runtime Guard Strategy

List stable guard/error codes added or confirmed.

## Files Created

## Files Modified

## Documentation Finalized

## Commands Executed

Only actual commands.

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
PND91 baseline
PND90 supported baseline
minimum tax
family allowances
planning/recommendation
member history
Guest stateless behavior
security/ownership
historical snapshot integrity
```

## Known Unsupported / Partial Features

These are NOT blockers if runtime is safely guarded.

List them clearly.

## Architecture Compliance

Confirm:

```text
TaxCalculationService remains the shared engine.
No duplicate PND90/PND91 engine exists.
Only repository-approved sources were used.
No web/general-knowledge tax rules were used.
No unverified numeric rule affects calculation.
Unsupported/partial material paths are explicitly guarded.
Historical snapshots were not rewritten.
No AI determines tax eligibility or tax amounts.
No approved UI redesign occurred.
Milestone 08 was not started.
```

## Closure Decision

If no correctness-critical blocker remains:

```text
Milestone 7.x is CLOSED.
PND90/PND91 production baseline is frozen.
Proceed to Milestone 08 — Content / News / Knowledge + Admin CMS + Tax Rule Administration.
```

If a correctness-critical blocker remains:

```text
Milestone 7.x cannot close yet.
```

Then list only the exact defect/path that can still return a wrong tax result despite being presented as supported.

Do NOT propose M7.6.

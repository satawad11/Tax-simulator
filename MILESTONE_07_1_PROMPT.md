# MILESTONE_07_1_PROMPT.md

## Codex Task: Milestone 07.1 — PND90 Tax Rule Reconciliation from Approved Source Documents

You are working on:

**Thai Personal Income Tax Simulation Platform**

This is a reconciliation milestone between the approved source documents and the partially completed PND90 engine from Milestone 07.

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
```

Also inspect all current:

```text
migrations
models
seeders
tax rule tables
expense rules
income type mappings
TaxCalculationService
IncomeCalculator
ExpenseCalculator
ProgressiveTaxCalculator
member workflows
planning/recommendation workflows
tests
docs/tax/PND90_RULE_MATRIX.md
docs/api/MILESTONE_07_API.md
```

Do not begin Milestone 08.

---

# Objective

Use the approved source documents stored in the repository to reconcile, verify, document, and implement PND90 expense rules for:

```text
SECTION_40_2
SECTION_40_3
SECTION_40_4
SECTION_40_5
SECTION_40_6
SECTION_40_7
SECTION_40_8
```

SECTION_40_1 is already verified and must remain unchanged unless the source documents clearly reveal a project implementation defect.

The goal is to convert source-supported rules into production tax-rule data and tests without guessing.

---

# Required Source Document Location

Before doing any tax-rule work, inspect:

```text
docs/tax-source/
```

Expected source files may include:

```text
PND90-2568.pdf
PND91-2568.pdf
PND90-attachments-2568.pdf
PND91-attachments-2568.pdf
tax-rates-2568.png
```

Actual filenames may differ.

Use the files that actually exist.

Do NOT assume a document exists merely because it is listed here.

---

# Hard Stop if Sources Are Missing

If the repository does not contain sufficient PND90 source documents to verify SECTION_40_2–SECTION_40_8:

STOP tax-rule implementation.

Do not invent rules.

Do not search the public internet unless explicitly instructed by the user in a separate task.

Do not use model memory or general Thai tax knowledge as a substitute.

In that case:

1. inspect current rule gaps;
2. update documentation to state exactly what source is missing;
3. run regression tests only;
4. report the blocking files required;
5. do not seed new numeric tax rules;
6. do not begin Milestone 08.

---

# Source-of-Truth Policy

Only the following may justify production numeric rule changes:

```text
approved repository source documents
approved project requirements
already-approved project tax data
```

If a document does not clearly support a percentage, cap, deduction method, condition, or exception:

```text
mark it PARTIAL or UNVERIFIED
```

Do not infer.

Do not “fix” source content using general knowledge.

Do not silently reconcile discrepancies.

Document them.

---

# Required Rule Status

For each income type:

```text
SECTION_40_1
SECTION_40_2
SECTION_40_3
SECTION_40_4
SECTION_40_5
SECTION_40_6
SECTION_40_7
SECTION_40_8
```

assign one of:

```text
VERIFIED
PARTIAL
UNVERIFIED
```

Definitions:

## VERIFIED

The source documents clearly support all production fields required for the implemented expense rule.

## PARTIAL

The source supports some but not all values/conditions needed for safe automatic calculation.

## UNVERIFIED

The source available in the repository does not support a safe numeric calculation rule.

Only VERIFIED rules may automatically affect eligible expense in production.

---

# Required Rule Matrix

Update:

```text
docs/tax/PND90_RULE_MATRIX.md
```

The matrix must contain, for every income type:

```text
Income Type
Section
Source File
Source Page / Section
Source-Supported Description
Expense Rule Status
Method
Percentage
Maximum Amount
Minimum Amount
Actual Expense Supported
Conditions
Special Notes
Production Seeder Status
Calculator Status
Warning / Error Behavior
```

Use precise source references.

Example:

```text
Source File: docs/tax-source/PND90-2568.pdf
Page: 2
Section: เงินได้ตามมาตรา 40(...)
```

Do not write source references that you did not inspect.

---

# Preserve Source Terminology

When documenting source-derived rules:

- preserve the terminology used in the PND90 source;
- do not rewrite legal/tax terms into a different meaning;
- summarize rather than over-interpret;
- clearly separate source-derived wording from implementation notes.

---

# Rule Reconciliation Procedure

For each SECTION_40_2–SECTION_40_8:

1. locate the corresponding section in source documents;
2. identify income description;
3. identify expense deduction method;
4. identify percentage, if explicitly stated;
5. identify maximum/minimum limits, if explicitly stated;
6. identify whether actual expense is allowed;
7. identify any source-stated conditions;
8. identify whether subcategories exist;
9. determine VERIFIED / PARTIAL / UNVERIFIED;
10. update the rule matrix;
11. seed only VERIFIED production rule data;
12. add tests only for implemented production behavior.

---

# Expense Rule Methods

Existing supported rule methods include:

```text
fixed
percentage
percentage_limit
actual
percentage_or_actual
custom
```

Use the method that matches the source-supported behavior.

Do not force a source rule into a simpler method if that changes meaning.

If source behavior requires unsupported complexity:

```text
method = custom
status = PARTIAL
```

and do not automate until the custom semantics are fully specified.

---

# Subcategories

If a Section 40 income type has multiple source-defined subcategories with different expense rules, do NOT collapse them into one percentage.

Determine whether the existing schema can represent the distinction safely.

If not:

1. document the schema gap;
2. propose the minimal safe schema extension;
3. add a migration only if the extension is directly supported by the source and necessary for calculation;
4. add tests;
5. do not invent a generic blended rule.

Possible examples of representation:

```text
income subtype code
conditions JSON
metadata
custom resolver
```

Prefer explicit, maintainable modeling.

Do not hide critical tax semantics inside opaque JSON if a proper field/model is warranted.

---

# Existing Schema Compatibility

Inspect:

```text
income_types
income_rules
expense_rules
tax_return_incomes
```

before adding columns.

Do not add fields that are not required.

If current `conditions` JSON is sufficient and keeps the rule auditable, use it.

If not sufficient, explain why before modifying schema.

---

# Published Rule Version Integrity

Current production rule version:

```text
2568.1
```

is already published and used by saved returns.

Be careful about adding rules to a published rule version.

Use this decision process:

## Case A — The rule was intended but omitted due to incomplete source ingestion

If adding source-verified rules is considered completion of the already-approved 2568.1 dataset and does not alter previously calculated behavior for existing supported inputs, adding rows to 2568.1 may be acceptable.

Document why.

## Case B — The new rule changes semantics of previously supported calculations

Do NOT mutate 2568.1 silently.

Propose/create a new internal rule version according to existing project versioning policy.

Assess impact on:

```text
saved tax returns
member drafts
completed returns
scenarios
history
```

Do not automatically upgrade old returns.

---

# Existing SECTION_40_1 Rule

Do not change:

```text
method = percentage_limit
percentage = 50
maximum_amount = 100000
```

unless the source documents prove the current project rule is wrong.

Any change requires explicit documentation and regression impact analysis.

---

# Production Seeder Requirements

Create/update idempotent seeders for VERIFIED rules only.

Seeder requirements:

```text
stable lookup by rule_version + income_type
no duplicate rules
safe repeat execution
no destructive deletion of unrelated rules
```

Do not seed PARTIAL / UNVERIFIED numeric rules.

For PARTIAL rules, metadata/documentation may record status, but eligible deduction must not be auto-calculated.

---

# Expense Rule Status in API

Extend metadata/detail responses if necessary so Web/Mobile can understand rule readiness.

Suggested fields:

```json
{
  "expense_rule": {
    "status": "VERIFIED",
    "method": "percentage",
    "percentage": "30.000",
    "maximum_amount": null,
    "actual_expense_supported": false
  }
}
```

For unsupported:

```json
{
  "expense_rule": {
    "status": "UNVERIFIED"
  }
}
```

Do not expose fake numeric values.

---

# Calculation Behavior by Status

## VERIFIED

Calculate according to seeded rule.

## PARTIAL

Preferred behavior:

```text
422
code = PARTIAL_EXPENSE_RULE
```

unless a fully safe path exists for that specific input.

## UNVERIFIED

Preferred behavior:

```text
422
code = UNVERIFIED_EXPENSE_RULE
```

Do not silently assume expense = 0.

If the project currently uses warning + zero-expense behavior, preserve consistency only if it is clearly documented and does not mislead.

For PND90 full-simulation quality, explicit rejection is preferred when a material expense rule is missing.

---

# Actual Expense

Only allow:

```text
actual_expense
```

if the verified source rule explicitly allows actual expense.

Validation:

```text
actual_expense numeric
actual_expense >= 0
actual_expense within source-supported limits/conditions
```

Do not accept actual expense just because the database schema has a column for it.

---

# Percentage-or-Actual Rules

If the source explicitly allows a choice between:

```text
percentage deduction
actual expense
```

implement a deterministic rule.

The API must make the user's selected method explicit if a choice is legally available.

Suggested field:

```text
expense_method_selection
```

Only add this if required by a verified source rule.

Do not infer user intent.

---

# Custom Rules

If source behavior requires:

```text
custom
```

create a dedicated, named strategy.

Example pattern:

```text
ExpenseStrategyInterface
CustomSection40XExpenseStrategy
```

Do not put custom formulas inside Controller or generic switch blocks.

Each custom strategy must have focused unit tests.

---

# Multi-Income Calculation

Once verified rules exist, PND90 must support mixed income.

Flow:

```text
group by income type
apply correct verified expense rule to each applicable group
sum post-expense income
apply allowances
apply donations
calculate combined net income
run shared ProgressiveTaxCalculator
apply credits
produce PAYABLE / REFUND / ZERO
```

Do not calculate separate progressive taxes per income category.

---

# 40(1) + 40(2) Interaction

If the source documents explicitly state a shared expense cap or combined rule between 40(1) and 40(2), model it exactly as the source states.

This is especially important.

Do not simply apply the 40(1) cap independently to 40(2).

If a combined rule is source-supported:

- implement a grouped expense strategy;
- document it;
- add boundary tests;
- verify PND91 remains unchanged.

If the repository source does not clearly support the interaction, mark 40(2) PARTIAL/UNVERIFIED.

---

# Income Type 40(3)–40(8)

For each type, inspect whether the source:

```text
defines a percentage
defines a cap
allows actual expense
defines category-specific rates
requires special computation
```

Do not compress multi-rate categories into one rule.

If subcategory information is required but not represented in the current request schema, document the blocker.

---

# Request Schema Changes

Only add fields necessary for source-verified calculation.

Potential examples:

```text
income_subtype
expense_method_selection
actual_expense
```

Every new field must have:

```text
validation
DTO support
API documentation
tests
backward compatibility
```

Do not add speculative fields.

---

# API Backward Compatibility

Existing PND91 API behavior must remain unchanged.

Existing PND90 structural input should continue to validate consistently.

Do not rename existing fields.

Additive fields are preferred.

---

# Member Workflow

For PND90 saved returns:

- verified supported combinations may calculate and persist history;
- PARTIAL / UNVERIFIED combinations must not complete successfully;
- clear error must explain which income type blocks calculation.

Do not mark a return completed if required expense logic is unresolved.

---

# Planning / Recommendation

M6 behavior must remain stable.

For PND90:

- planning is allowed only for a base return that can be fully calculated;
- if any required expense rule is PARTIAL/UNVERIFIED, planning must fail clearly;
- recommendation engine may still show verification reminders;
- no fake estimated tax saving.

---

# Required Unit Tests

For every newly VERIFIED expense rule:

```text
basic calculation
zero income
boundary percentage case
cap boundary if applicable
actual-expense path if applicable
invalid actual expense
method selection if applicable
```

For custom rules:

```text
dedicated strategy tests
```

---

# Required Integration Tests

Add:

```text
PND90 with each newly VERIFIED income type individually
PND90 mixed verified income types
PND90 mixed verified + unverified type => clear rejection
combined gross/post-expense totals
shared progressive tax result
PAYABLE
REFUND
ZERO
```

---

# 40(1) Regression

Must verify:

```text
PND90 SECTION_40_1 only
```

still matches equivalent PND91 tax result where the forms are otherwise equivalent.

---

# 40(1) + 40(2) Tests

If 40(2) becomes VERIFIED:

Add explicit combined-boundary tests.

Especially test any shared cap documented by the source.

Do not assume independent caps.

---

# Source Reference Tests

No need to automate PDF parsing.

But add documentation-oriented tests/data assertions where practical so seeded rule values match the intended verified dataset.

Example:

```text
expense rule row exists
method matches approved matrix
percentage matches approved matrix
cap matches approved matrix
```

---

# Rule Matrix Consistency Test

Add a test or static check if practical ensuring every SECTION_40_1–40_8 has a documented status.

At minimum:

```text
no income type omitted from rule matrix review
```

Do not parse Markdown with a brittle complex test unless useful.

A dedicated config/enum status map may be cleaner if already part of implementation.

---

# Warning / Error Codes

Use stable codes:

```text
UNVERIFIED_EXPENSE_RULE
PARTIAL_EXPENSE_RULE
ACTUAL_EXPENSE_NOT_SUPPORTED
EXPENSE_METHOD_SELECTION_REQUIRED
INCOME_SUBTYPE_REQUIRED
UNSUPPORTED_INCOME_SUBTYPE
```

Only implement codes that are actually needed.

---

# Documentation

Update:

```text
docs/tax/PND90_RULE_MATRIX.md
docs/api/MILESTONE_07_API.md
```

Add:

```text
docs/tax/PND90_SOURCE_RECONCILIATION.md
```

This reconciliation document must include:

```text
source files inspected
pages/sections inspected
rules verified
rules partial
rules unverified
schema gaps
implementation changes
rule-version decision
known unresolved items
```

---

# Source Citation Style in Project Docs

For each rule, cite repository source like:

```text
Source: docs/tax-source/PND90-2568.pdf, page 2
```

If the source is an attachment:

```text
Source: docs/tax-source/PND90-attachments-2568.pdf, page X
```

Do not fabricate page numbers.

---

# No Web Research

This milestone is specifically source reconciliation from repository-approved documents.

Do NOT use web search to fill missing tax rules.

If the repository source is insufficient:

```text
report blocker
leave rule unverified
```

External verification can be a separate approved task later.

---

# Pre-existing Known Issues

From M7/M6 reports there may be:

```text
legacy synthetic development records
previous Pint issues
migration rollback guards
```

Only fix issues that directly block M7.1.

Do not perform unrelated cleanup.

Do not delete real development data.

---

# Commands

Inspect first:

```bash
git status
docker compose ps
find docs/tax-source -maxdepth 2 -type f
```

Do not claim a source was read if it is not present.

Run only non-destructive migrations/seeding as needed.

Then run:

```bash
docker compose exec app php artisan test
```

Run MySQL-backed suite according to current project workflow.

Run Pint/lint.

Run frontend build regression if part of current workflow.

Do not run `migrate:fresh` against active development data unless absolutely necessary and explicitly reported.

---

# Manual Verification

Using synthetic data only, verify every newly supported production rule.

At minimum:

1. each newly VERIFIED income type;
2. mixed verified income types;
3. unverified type rejection;
4. actual expense path where source-supported;
5. combined-rule behavior where source-supported;
6. PND91 regression;
7. Member PND90 calculate/history;
8. PND90 completion blocked when unresolved rule exists;
9. PND91 planning regression;
10. PND90 planning only when fully calculable.

---

# Completion Criteria

M7.1 is complete only when:

```text
[ ] repository source documents were actually inspected
[ ] every SECTION_40_1–40_8 has VERIFIED/PARTIAL/UNVERIFIED status
[ ] source file/page references are documented
[ ] no unverified numeric rule was guessed
[ ] only VERIFIED production rules were seeded
[ ] every newly seeded rule has tests
[ ] PND90_RULE_MATRIX.md is complete
[ ] PND90_SOURCE_RECONCILIATION.md exists
[ ] PND90 mixed verified-income calculations work
[ ] unsupported combinations fail clearly
[ ] PND91 behavior remains unchanged
[ ] M6 planning/recommendation behavior remains unchanged
[ ] Guest/Member calculation consistency remains valid
[ ] all M1–M7 regression tests pass
[ ] no UI redesign occurred
[ ] Milestone 08 was not started
```

---

# Required Final Report

At the end, report exactly:

## Summary

## Source Documents Found

List exact repository paths.

## Source Documents Missing

List required/expected documents that were not present.

## Rule Reconciliation Matrix

For each:

```text
SECTION_40_1
SECTION_40_2
SECTION_40_3
SECTION_40_4
SECTION_40_5
SECTION_40_6
SECTION_40_7
SECTION_40_8
```

report:

```text
status
source file
page/section
method
percentage
cap
actual expense support
implementation status
```

Use `N/A` where source does not support a value.

## Production Rules Added

List only newly VERIFIED rules actually seeded.

## Partial Rules

Explain why each remains partial.

## Unverified Rules

Explain what source evidence is missing.

## Schema Changes

List migrations/fields added, if any.

## Rule Version Decision

Explain whether 2568.1 was extended or a new version was created, and why.

## Files Created

## Files Modified

## Tests Added

## Commands Executed

Only actual commands.

## Test Results

Report:

```text
passed
failed
skipped
assertions
```

for available suites.

## Manual Verification

Report synthetic flows.

## Regression Status

Confirm:

```text
PND91 unchanged
M6 planning/recommendation unchanged
member workflow unchanged
Guest API unchanged except additive PND90 support
```

## Remaining Blockers

List exact blockers before full PND90 can be considered complete.

## Architecture Compliance

Confirm:

```text
Only repository-approved source documents were used.
No web/general-knowledge tax rules were used to fill gaps.
No unverified numeric rule was guessed.
Shared TaxCalculationService remains the calculation engine.
ProgressiveTaxCalculator was not duplicated.
No AI determines tax eligibility or amounts.
No approved UI redesign occurred.
Milestone 08 was not started.
```

## Next Step

If all required PND90 rules are VERIFIED and implemented:

```text
Recommend Milestone 08 — Content / News / Admin CMS + Tax Rule Administration
```

If material PND90 rules remain PARTIAL/UNVERIFIED:

```text
Do NOT recommend Milestone 08 as the immediate next implementation step.
Instead list the exact source documents or rule clarifications required to complete PND90 first.
```

# MILESTONE_07_2_PROMPT.md

## Codex Task: Milestone 07.2 — PND90 Remaining Rules Completion

You are working on:

**Thai Personal Income Tax Simulation Platform**

This milestone continues Milestone 07 / 07.1.

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
```

Also inspect all current:

```text
docs/tax-source/
docs/tax/PND90_RULE_MATRIX.md
docs/tax/PND90_SOURCE_RECONCILIATION.md
expense_rules
allowance_rules
donation_rules
TaxCalculationService
TaxCreditCalculator
DonationCalculator
AllowanceCalculator
PND90 calculators
planning/recommendation services
tests
API docs
```

Do not begin Milestone 08.

---

# Objective

Complete the remaining high-value PND90 production rules that are now supported by repository source documents, while preserving strict source-grounded behavior.

The current known blockers from M7.1 are:

1. PND90 minimum-tax base / special minimum-tax path
2. donation rules described in PND90 line 11 items 4 and 6
3. allowance rules from the 2568 attachment schedule
4. PND94 prepayment / credit handling where source-supported
5. foreign tax credit treatment where source-supported
6. separate-taxation election paths where source-supported
7. three income subcategories whose percentages were still blank / unsupported
8. final legal rounding remains unresolved unless source documents clearly resolve it

Only implement items that are clearly supported by the repository-approved source documents.

Do not guess.

---

# Source-of-Truth Policy

Production rules may come only from:

```text
repository-approved PND90/PND91 source documents
repository-approved attachments
approved project requirements
already-approved project rule data
```

Do NOT use:

```text
web search
general tax knowledge
model memory
unsourced assumptions
```

If a rule is still ambiguous:

```text
leave it PARTIAL / UNVERIFIED
```

and report the exact blocker.

---

# Required Source Review

Inspect all relevant source documents under:

```text
docs/tax-source/
```

For every implemented rule, record:

```text
source file
page number
line/item number
section label
short source-derived summary
```

Do not fabricate page references.

---

# 1. Minimum-Tax Base Reconciliation

The M7.1 report identified ambiguity around the PND90 special/minimum tax computation, especially the base referenced by the form wording around:

```text
ข้อ 11 item 9
```

and wording similar to:

```text
ข้อ 1 ถึงข้อ 7.1 ถึง 4
```

You must:

1. locate the exact source wording;
2. determine the exact components included in the base;
3. determine which income categories are excluded/included;
4. determine the rate;
5. determine threshold conditions;
6. determine whether the special/minimum tax is compared against the normal progressive tax;
7. determine which tax amount prevails;
8. document the full computation sequence.

If any of these remain unclear, DO NOT implement the special/minimum tax path.

Instead:

```text
status = PARTIAL
```

and return a clear domain error/warning for affected PND90 cases.

---

# Minimum-Tax Service Boundary

If fully verified, create a dedicated service such as:

```text
MinimumTaxCalculator
```

or a clearly named equivalent.

Do not place the rule inside Controller or generic TaxCalculationService branching.

Suggested orchestration:

```text
normal tax = ProgressiveTaxCalculator(...)
minimum/special tax = MinimumTaxCalculator(...)

final base tax = max/selected amount according to source rule
```

Only implement comparison behavior if explicitly supported.

---

# Minimum-Tax Tests

If verified, test at minimum:

```text
below threshold
exact threshold
above threshold
mixed income base
excluded income categories
normal progressive tax greater
minimum tax greater
equal result
zero applicable base
```

Use exact decimal money strategy from M4–M7.

---

# 2. Donation Rules Reconciliation

M7.1 identified source-stated donation rules around:

```text
line 11 items 4 and 6
```

with wording indicating:

```text
special donation may be counted at 2x actual amount
subject to a percentage cap
general donation subject to a later percentage cap
```

Do not rely on this paraphrase alone.

Re-read the source and extract exact:

```text
multiplier
base amount
percentage cap
calculation order
which line/base the cap references
whether special and general donations use different bases
whether deduction ordering matters
```

If fully verified, seed:

```text
donation_rules
```

for rule version 2568.1 or the appropriate new rule version based on versioning policy.

Do not implement partially understood donation rules.

---

# Donation Calculation Order

The source-derived order must be preserved.

If the form shows:

```text
base before special donation
special donation deduction
new base
general donation deduction
net income
```

then the calculator must follow that exact sequence.

Do not combine all donations into one total cap.

---

# Donation Service

Reuse/extend:

```text
DonationCalculator
```

Do not duplicate donation logic elsewhere.

Return breakdown:

```json
{
  "donations": {
    "items": [
      {
        "code": "SPECIAL_DONATION",
        "input_amount": "10000.00",
        "multiplier": "2.000",
        "cap_base": "500000.00",
        "cap_percentage": "10.000",
        "eligible_amount": "20000.00"
      }
    ],
    "total_input": "10000.00",
    "total_eligible": "20000.00"
  }
}
```

Values above are illustrative only. Use source-derived values.

---

# Donation Tests

At minimum for each VERIFIED rule:

```text
zero donation
below cap
exact cap
above cap
multiplier behavior
ordering interaction
special + general donation together
negative amount rejected
unknown donation code rejected
```

---

# 3. Allowance Rules Reconciliation

Inspect the approved 2568 allowance attachment(s).

The current project may have allowance master codes but zero numeric allowance rules.

You must reconcile only source-supported allowance rules.

For each allowance code:

```text
PERSONAL
SPOUSE
CHILD
PARENT
DISABLED_PERSON
LIFE_INSURANCE
HEALTH_INSURANCE
PENSION_INSURANCE
PROVIDENT_FUND
NSF
RMF
HOME_LOAN_INTEREST
SOCIAL_SECURITY
EASY_E_RECEIPT
THAI_ESG
THAI_ESGX
OTHER
```

determine:

```text
source-supported rule status
fixed amount
percentage
maximum
minimum
combined cap
age/dependent conditions
date-range conditions
special-year conditions
interaction with other allowances
```

Only implement VERIFIED rules.

Do not infer legal eligibility beyond the source.

---

# Allowance Rule Status

For each code, document:

```text
VERIFIED
PARTIAL
UNVERIFIED
NOT_APPLICABLE
```

Update:

```text
docs/tax/ALLOWANCE_RULE_MATRIX_2568.md
```

Create this file if it does not exist.

Required columns:

```text
Allowance Code
Source File
Page/Item
Status
Method
Fixed Amount
Percentage
Maximum Amount
Conditions
Combined Cap
Implementation Status
Notes
```

---

# Allowance Seeder

Create/update idempotent:

```text
AllowanceRuleSeeder
```

or current equivalent.

Seed only VERIFIED rules.

Do not seed numeric zero values as placeholders for unknown rules.

Unknown should remain absent, not fake-zero production rules.

---

# Allowance Calculator

Reuse:

```text
AllowanceCalculator
```

Extend only as needed.

The calculator must:

```text
resolve active verified rule
apply fixed/percentage/cap logic
apply source-supported conditions
preserve warnings for partial/unverified rules
return input + eligible amount
```

Do not trust client-supplied `eligible_amount`.

---

# Combined Allowance Caps

If source documents define shared caps across multiple allowance types, model the shared cap explicitly.

Do not apply each maximum independently if the source says the cap is combined.

If the current schema cannot represent it safely:

1. document the gap;
2. add minimal schema support;
3. add focused tests;
4. do not hide the rule inside arbitrary JSON unless auditable.

---

# Allowance Tests

For each newly VERIFIED rule, test relevant:

```text
zero input
below cap
exact cap
above cap
fixed amount
percentage behavior
combined-cap behavior
eligibility condition boundary
date condition boundary
multiple allowances interacting
```

---

# 4. PND94 Reconciliation

The M7.1 report identified PND94 as source-visible but not yet implemented in credit handling.

Inspect source documents to determine:

```text
whether PND94 is a prepayment/credit in this PND90 flow
where it appears in calculation order
whether it is fully deductible from final tax
whether any limits/conditions apply
```

If fully verified:

1. add `pnd94` to the supported credit type vocabulary;
2. update validation;
3. update `TaxCreditCalculator`;
4. update response breakdown;
5. add tests.

Do not assume it behaves identically to PND93 unless source clearly supports that.

---

# Credit Response

If PND94 is verified, response may include:

```json
{
  "credits": {
    "withholding": "0.00",
    "foreign_tax_credit": "0.00",
    "pnd93": "0.00",
    "pnd94": "10000.00",
    "other_credit": "0.00",
    "total": "10000.00"
  }
}
```

Preserve backward compatibility.

---

# 5. Foreign Tax Credit Reconciliation

Source wording may state that foreign tax credit is subject to Thai law.

Do not implement a numeric limit unless the repository source actually provides enough detail.

If the repository source only says:

```text
subject to applicable Thai law
```

then keep:

```text
UNVERIFIED_FOREIGN_TAX_CREDIT_LIMIT
```

and do not grant the credit automatically.

If sufficient source exists, implement the verified limit with:

```text
TaxCreditCalculator
```

and focused tests.

---

# 6. Separate-Taxation Election Reconciliation

M7.1 identified separate-taxation election paths, including references around:

```text
item 8
item 9
15% / 10% elections
item 3
```

You must inspect exact source text.

Determine:

```text
which income categories are eligible
whether election is optional
what rate applies
what base applies
whether expense deductions apply before election
whether elected income is excluded from progressive net income
how tax credits interact
```

If fully verified, implement as explicit user choice.

Do not auto-select the lower tax option.

---

# Election API Input

If a verified election requires user choice, add a clear field such as:

```text
tax_treatment
```

or:

```text
separate_tax_election
```

Example structure:

```json
{
  "income_type": "SECTION_40_X",
  "gross_amount": "100000.00",
  "tax_treatment": "SEPARATE_RATE"
}
```

Only add fields actually required by verified source rules.

Do not infer user intent.

---

# Election Calculation Strategy

Use dedicated strategy/services.

Possible structure:

```text
IncomeTaxTreatmentResolver
SeparateTaxCalculator
```

Do not overload ProgressiveTaxCalculator with unrelated separate-rate formulas.

The final result must transparently show:

```text
progressive-tax component
separate-tax component
combined tax before credits
```

---

# Election Tests

If implemented, test:

```text
default/no election
explicit election
invalid election
rate boundary
income excluded from progressive base
combined final tax
Guest/Member consistency
```

---

# 7. Three Blank-Percentage Subcategories

M7.1 identified three source subcategories whose percentage values were blank / unsupported in available source.

Search only repository-approved source documents.

Do not use web search.

For each subcategory:

```text
identify exact category
identify source page/item
find percentage if present elsewhere in approved docs
determine method
determine whether actual expense option exists
```

If still missing:

```text
status = UNVERIFIED
```

and report the exact missing source evidence.

Do not invent.

---

# 8. Legal Rounding

Current project emits:

```text
ROUNDING_RULE_PENDING
```

Keep this behavior unless repository source documents clearly specify:

```text
which intermediate/final values are rounded
rounding unit
rounding direction
when rounding occurs
```

If not clearly stated:

```text
do not implement legal rounding
```

and keep the warning.

---

# Rule Version Decision

Before seeding new verified rules, decide whether:

```text
extend 2568.1
```

or:

```text
create a new internal rule version
```

Use the current versioning policy.

Key principle:

Existing completed/member results must remain reproducible.

If new rules affect previously unsupported inputs only and do not alter behavior for existing supported calculations, extending 2568.1 may be acceptable as reconciliation completion.

If existing supported outcomes change, create a new rule version.

Document the decision.

Do not auto-upgrade saved returns.

---

# Member Return Compatibility

For existing saved PND90 returns:

- if their rule_version remains older, preserve it;
- if the current return points to a version that receives source-completion rules, verify deterministic behavior;
- completed historical calculation snapshots must never be rewritten.

---

# Planning Compatibility

M6 planning must remain correct.

If newly verified allowance/donation rules enable real savings:

```text
TaxPlanningService
```

must automatically benefit by reusing `TaxCalculationService`.

Do not add tax-saving formulas to planning.

Add regression tests proving a verified allowance/donation scenario changes tax only through the shared engine.

---

# Recommendation Compatibility

Recommendation rules may be upgraded from “check this item” to actionable simulation only when the underlying numeric rule becomes VERIFIED.

Do not change recommendation language into certainty.

Example:

Before:

```text
ควรตรวจสอบสิทธิ
```

After verified rule exists, still prefer:

```text
หากคุณเข้าเงื่อนไข สามารถทดลองจำลองรายการนี้ได้
```

Do not claim eligibility automatically unless all conditions are actually evaluated.

---

# API Backward Compatibility

Do not remove or rename existing M4–M7 response fields.

Additive changes only where possible.

If new fields are added:

```text
pnd94
separate_tax
minimum_tax
allowance_rule_details
donation_breakdown
```

document them.

---

# Tax Result Transparency

If minimum tax or separate tax is implemented, response should expose both the component and the final selected tax.

Example structure:

```json
{
  "tax_components": {
    "progressive_tax": "40000.00",
    "minimum_tax": "45000.00",
    "separate_tax": "5000.00"
  },
  "calculated_tax": "50000.00"
}
```

This is illustrative only.

Use source-correct semantics.

---

# Calculation Trace

Extend trace only with source-supported new steps.

Possible codes:

```text
MINIMUM_TAX_BASE
MINIMUM_TAX
SPECIAL_DONATION
GENERAL_DONATION
PND94_CREDIT
SEPARATE_TAX_COMPONENT
```

Do not insert misleading trace steps for unimplemented rules.

---

# Required Documentation

Update:

```text
docs/tax/PND90_RULE_MATRIX.md
docs/tax/PND90_SOURCE_RECONCILIATION.md
docs/api/MILESTONE_07_API.md
```

Create:

```text
docs/tax/ALLOWANCE_RULE_MATRIX_2568.md
docs/tax/PND90_REMAINING_RULES_2568.md
```

The remaining-rules document must summarize:

```text
minimum-tax rule
donation rules
allowance rules
PND94
foreign tax credit
separate-tax elections
blank-percentage subcategories
rounding
```

with:

```text
VERIFIED
PARTIAL
UNVERIFIED
IMPLEMENTED
```

statuses.

---

# Tests — Minimum Tax

If implemented, include dedicated unit/integration tests.

If not implemented, test explicit rejection/warning for affected cases.

---

# Tests — Donation

For every newly implemented donation rule:

```text
below cap
at cap
above cap
multiple donation categories
calculation order
Guest/Member consistency
planning consistency
```

---

# Tests — Allowance

For every newly implemented allowance rule:

```text
input boundaries
caps
eligibility boundaries
combined caps
Guest/Member consistency
planning comparison
```

---

# Tests — PND94

If implemented:

```text
zero
single PND94 payment
multiple PND94 records if allowed
PAYABLE impact
REFUND impact
ZERO impact
negative rejected
```

---

# Tests — Foreign Tax Credit

If still unverified:

```text
credit input does not silently reduce tax
warning remains
```

If verified:

test source-supported cap/limit behavior.

---

# Tests — Separate Tax Election

If implemented:

```text
election off
election on
invalid option
tax-base exclusion
component tax
combined final tax
```

---

# Regression Tests

All M1–M7 tests must remain green.

Especially:

```text
PND91 results unchanged
PND91 planning unchanged
PND90 verified expense behavior unchanged
Member history unchanged
Guest stateless behavior unchanged
Recommendation behavior unchanged except source-supported additive capabilities
```

Do not weaken old tests.

---

# Manual Verification

Use synthetic data only.

Verify all newly implemented rule categories.

At minimum:

1. PND90 case triggering minimum tax, if implemented
2. special donation
3. general donation
4. newly verified allowance
5. allowance cap boundary
6. PND94 credit, if implemented
7. foreign tax credit rejection/warning or verified behavior
8. separate-tax election, if implemented
9. PND90 mixed-income result
10. Guest vs Member parity
11. Planning scenario using a newly verified allowance/donation
12. PND91 regression

---

# Commands

Inspect first:

```bash
git status
docker compose ps
find docs/tax-source -maxdepth 2 -type f
```

Run non-destructive migrations/seeding only as required.

Then run:

```bash
docker compose exec app php artisan test
```

Run MySQL suite according to project workflow.

Run Pint/lint.

Run frontend build regression if required.

Do not run `migrate:fresh` on active development data unless absolutely necessary and explicitly reported.

---

# Completion Criteria

M7.2 is complete only when:

```text
[ ] source documents were inspected
[ ] minimum-tax rule is VERIFIED+IMPLEMENTED or explicitly PARTIAL with blocker
[ ] donation rules are VERIFIED+IMPLEMENTED or explicitly PARTIAL with blocker
[ ] allowance rule matrix exists
[ ] every implemented allowance rule is source-supported
[ ] PND94 is VERIFIED+IMPLEMENTED or explicitly unresolved
[ ] foreign tax credit is either verified or safely blocked
[ ] separate-tax elections are either verified or safely blocked
[ ] blank-percentage subcategories are resolved or explicitly UNVERIFIED
[ ] rounding remains pending unless source resolves it
[ ] rule-version decision is documented
[ ] no unverified numeric rule was guessed
[ ] planning uses shared TaxCalculationService
[ ] Guest/Member parity remains valid
[ ] PND91 remains unchanged
[ ] all M1–M7 regression tests pass
[ ] no UI redesign occurred
[ ] Milestone 08 was not started
```

---

# Required Final Report

At the end, report exactly:

## Summary

## Source Documents Used

List exact paths.

## Minimum-Tax Rule

Report:

```text
status
source file/page/item
base definition
rate
threshold
comparison behavior
implementation status
```

## Donation Rules

For each implemented/source-reviewed rule:

```text
status
source
multiplier
cap
base
order
implementation status
```

## Allowance Rules

Provide counts:

```text
VERIFIED
PARTIAL
UNVERIFIED
IMPLEMENTED
```

and list implemented codes.

## PND94

Report source and implementation status.

## Foreign Tax Credit

Report source support and implementation status.

## Separate-Tax Elections

Report each election path and status.

## Blank-Percentage Subcategories

List all three and final status.

## Rounding

Report whether still pending.

## Rule Version Decision

Explain whether 2568.1 was extended or new version created.

## Schema Changes

## Seeders Changed

## Services Created / Modified

## API Changes

## Documentation Created / Updated

## Commands Executed

Only actual commands.

## Test Coverage

Summarize all newly implemented rule tests.

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

Report synthetic flows and outcomes.

## Regression Status

Confirm:

```text
PND91 unchanged
M6 planning/recommendation remains valid
member history unchanged
Guest stateless API unchanged
```

## Remaining Blockers

List exact blockers before PND90 can be considered fully production-complete.

## Architecture Compliance

Confirm:

```text
Only repository-approved source documents were used.
No web/general-knowledge tax rules were used.
No unverified numeric rule was guessed.
TaxCalculationService remains the shared engine.
Planning reuses the shared engine.
No AI determines tax eligibility or tax amounts.
No approved UI redesign occurred.
Milestone 08 was not started.
```

## Next Step

If all material PND90 tax rules are VERIFIED and implemented:

```text
Recommend Milestone 08 — Content / News / Admin CMS + Tax Rule Administration
```

If material gaps remain:

```text
Do NOT recommend Milestone 08 as the immediate next implementation step.
List the exact remaining source documents or clarifications required.
```

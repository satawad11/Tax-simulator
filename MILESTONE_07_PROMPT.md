# MILESTONE_07_PROMPT.md

## Codex Task: Milestone 07 — PND90 Calculation Engine (SECTION_40_1–SECTION_40_8)

You are working on:

**Thai Personal Income Tax Simulation Platform**

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
```

Also inspect the current:

```text
tax metadata
tax rule versions
income type mappings
expense rules
allowance rules
donation rules
TaxCalculationService
PND91 calculators
planning/recommendation services
tests
API documentation
```

Treat the approved project requirements and attached PND90 / PND91 source documents as the source of truth.

Milestones 01–06 are complete.

Do not redesign the approved UI.
Do not introduce AI-generated tax rules.
Do not silently fill gaps with general tax knowledge.
Do not weaken M1–M6 regression tests.

---

# Objective

Extend the existing tax calculation engine to support:

```text
PND90
```

with income types:

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

The implementation must:

- reuse the existing TaxCalculationService architecture;
- reuse the same progressive tax engine;
- preserve PND91 behavior;
- support multiple income categories in one PND90 return;
- calculate expenses only where rules are verified;
- return warnings for unverified rules instead of guessing;
- keep Web and Mobile on the same API contract.

---

# Source-Grounded Rule

The attached PND90 form shows income categories under Section 40(1)–40(8), with different expense treatments depending on income type.

Do NOT assume all income types use the same expense rule.

If a rule is not clearly supported by the approved project source documents:

```text
DO NOT IMPLEMENT A NUMERIC RULE
DO NOT COPY FROM GENERAL INTERNET KNOWLEDGE
DO NOT GUESS A PERCENTAGE
DO NOT GUESS A CAP
```

Instead:

- keep the income category structurally supported;
- return a warning such as `UNVERIFIED_EXPENSE_RULE`;
- allow the calculation only if a safe fallback is explicitly approved;
- otherwise reject the unsupported expense calculation path clearly.

---

# Existing PND91 Behavior Must Remain Untouched

Current verified PND91 rule:

```text
SECTION_40_1 expense:
50% of eligible employment income
maximum 100,000 baht
```

This must continue to work exactly as M4–M6.

PND90 with SECTION_40_1 may reuse the same verified Section 40(1) expense rule where appropriate.

Do not duplicate this formula in a new PND90-only calculator.

---

# PND90 Calculation Scope

PND90 may contain one or more income types.

Example:

```json
{
  "tax_year": 2568,
  "form_code": "PND90",
  "incomes": [
    {
      "income_type": "SECTION_40_1",
      "gross_amount": "600000.00",
      "exempt_amount": "0.00"
    },
    {
      "income_type": "SECTION_40_8",
      "gross_amount": "200000.00",
      "exempt_amount": "0.00"
    }
  ]
}
```

The engine must:

```text
group income by type
apply the correct verified expense rule per type/group
sum income after expense
apply allowances
apply donations
calculate net income
calculate progressive tax
apply credits / withholding
return PAYABLE / REFUND / ZERO
```

Do not calculate each income type in isolation and then sum separate taxes.

Progressive tax applies to the combined net taxable income according to the approved form flow.

---

# API

Continue using:

```text
POST /api/v1/tax/calculate
```

Extend it to accept:

```text
form_code = PND90
```

Do not create a second calculation endpoint unless there is a compelling architecture reason.

Guest calculation remains:

```text
public
stateless
non-persistent
```

Member calculation must continue reusing the same TaxCalculationService.

---

# PND90 Validation

Validate:

```text
tax_year exists and active
published rule version exists
form_code = PND90
form is active for selected tax year
income types are supported by PND90
income amounts >= 0
exempt amounts >= 0
exempt <= gross
```

PND90 must reject unknown income types.

PND91 must continue rejecting SECTION_40_2–SECTION_40_8.

---

# Income Aggregation

Create or extend a service responsible for income grouping.

Suggested:

```text
IncomeAggregationService
```

or extend the existing `IncomeCalculator` if clean.

Responsibilities:

```text
group by income type
sum gross amounts per type
sum exempt amounts per type
derive post-exemption amount per type
preserve source-line breakdown
return combined gross totals
```

Do not mix expense logic into the aggregation layer.

---

# Expense Architecture

Use a rule-driven expense strategy.

Suggested architecture:

```text
ExpenseCalculator
  |
  +-- rule lookup
  +-- strategy by method
        fixed
        percentage
        percentage_limit
        actual
        percentage_or_actual
        custom
```

Do not create:

```text
if SECTION_40_5 then ...
else if SECTION_40_6 then ...
```

throughout controllers/services.

Income-specific branching may exist inside a dedicated strategy/resolver, but keep it centralized and testable.

---

# Verified vs Unverified Expense Rules

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

inspect the approved source documents and existing production rule tables.

Create a matrix in code comments or docs:

```text
income_type
rule_status = VERIFIED | PARTIAL | UNVERIFIED
method
percentage
cap
actual_expense_allowed
notes
```

Only VERIFIED rules may affect eligible expense automatically.

PARTIAL / UNVERIFIED rules must not silently generate deductions.

---

# Unverified Expense Rule Behavior

Choose one consistent behavior for PND90 income types lacking a verified expense rule.

Preferred strategy:

If gross income exists for an income type with no verified expense rule:

```text
422 UNVERIFIED_EXPENSE_RULE
```

unless:

```text
actual_expense
```

is explicitly allowed and supported by an approved rule.

Do not default expense to zero silently if doing so could mislead the user.

Alternative accepted behavior:

```text
calculate with expense = 0
emit high-priority warning
```

only if that is documented and clearly marked in the response.

Pick one strategy and use it consistently.

---

# Actual Expense Input

If a verified rule allows actual expense, accept:

```json
{
  "income_type": "SECTION_40_X",
  "gross_amount": "100000.00",
  "actual_expense": "30000.00"
}
```

Validation:

```text
actual_expense >= 0
actual_expense <= gross_after_exemption unless rule explicitly permits otherwise
```

Do not accept actual expense for income types where no approved rule permits it.

---

# Expense Result Structure

Return breakdown per income type:

```json
{
  "expenses": {
    "items": [
      {
        "income_type": "SECTION_40_1",
        "gross_after_exemption": "600000.00",
        "method": "percentage_limit",
        "input_actual_expense": null,
        "eligible_amount": "100000.00",
        "rule_status": "VERIFIED"
      }
    ],
    "total": "100000.00"
  }
}
```

Include warnings for any unsupported/unverified income type.

---

# Combined Income Result

Return:

```json
{
  "income": {
    "items": [],
    "gross_income": "800000.00",
    "exempt_income": "0.00",
    "gross_after_exemption": "800000.00"
  },
  "income_after_expense": "..."
}
```

Preserve both:

```text
per-type
overall total
```

for UI transparency.

---

# Tax Calculation Flow

For PND90, preserve the same high-level flow:

```text
Gross income
Less exempt income
Less eligible expenses by income type
Income after expenses
Less allowances
Less donations
Net income
Progressive tax
Less tax credits
Less withholding/prepaid amounts
PAYABLE / REFUND / ZERO
```

If PND90 source flow includes an additional special computation path for specific income categories that is not yet verified in approved project data, do not invent it.

Document it as a rule gap.

---

# Progressive Tax

Reuse:

```text
ProgressiveTaxCalculator
```

from M4.

Do not fork it for PND90.

Tax brackets remain loaded from:

```text
published rule version 2568.1
```

for the current tax year.

---

# Allowances

Reuse existing `AllowanceCalculator`.

Do not invent new allowance numeric rules in M7.

Existing behavior for unverified allowance rules remains unchanged.

PND90 may use the same allowance infrastructure as PND91.

---

# Donations

Reuse existing `DonationCalculator`.

Do not invent donation limits/multipliers in M7.

Existing warning behavior remains unchanged.

---

# Credits

Reuse existing `TaxCreditCalculator`.

PND90 may structurally support:

```text
withholding
foreign_tax_credit
pnd93
pnd94
other_credit
```

Only verified credit behavior may reduce liability.

Do not grant unverified credits.

Note:

PND90 source structure may include PND94 prepayment.
If the schema/API already supports `pnd94`, enable it only if approved by source/project requirements.

Do not add it by assumption alone.

---

# Member PND90 Support

M5 member CRUD may currently be PND91-focused.

Extend member persisted returns to support PND90 safely.

Requirements:

```text
create PND90 draft
save multiple income types
calculate saved PND90 return
persist calculation snapshot
persist bracket rows
history works
duplicate works
complete works only when calculation succeeds
```

Do not break PND91 member workflow.

---

# Completed PND90 Return Behavior

Same as PND91:

```text
completed core input becomes read-only
duplicate to modify
```

No special bypass.

---

# Planning / Recommendation Compatibility

M6 planning and recommendation services must remain compatible.

For PND90:

- do not automatically enable planning for unsupported/unverified income-expense combinations;
- if planning calls the calculator and the calculator cannot verify a required expense rule, propagate the warning/error;
- do not fake savings.

If necessary, keep:

```text
PND90 planning = unsupported
```

until all required production expense rules are verified.

Document the choice.

Do not regress PND91 planning.

---

# Rule Seeder Work

Inspect existing `expense_rules`.

Add production rules ONLY for income types where values are explicitly supported by approved project source documents.

Use idempotent seeding.

Do not modify already published bracket rules.

Do not silently mutate historical `2568.1` semantics beyond approved corrections.

If adding rules to published version is considered a semantic change, prefer:

```text
new internal rule version
```

only if the current project versioning policy requires it.

Before creating a new version, assess impact on saved returns and report it.

Do not create a new rule version casually.

---

# PND90 Rule Matrix Documentation

Create:

```text
docs/tax/PND90_RULE_MATRIX.md
```

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

document:

```text
source-supported description
expense rule status
method
percentage
cap
actual-expense support
production implementation status
known gaps
warning/error behavior
```

Clearly distinguish:

```text
SOURCE-SUPPORTED
PROJECT-IMPLEMENTED
NOT YET VERIFIED
```

Do not fill unknown cells with general knowledge.

---

# DTO Changes

Extend existing DTOs only as needed.

Possible fields:

```text
actual_expense
metadata
income subtype if already represented by approved schema
```

Do not overload generic `metadata` to avoid proper fields when a field is clearly required.

But do not create speculative schema fields either.

---

# API Backward Compatibility

Existing PND91 request/response must remain valid.

Do not rename/remove existing fields.

PND90 may append:

```text
income items grouped by type
expense items grouped by type
rule status metadata
```

If extending the shared response, do so backward-compatibly.

---

# Warnings

Possible PND90 warning codes:

```text
UNVERIFIED_EXPENSE_RULE
PARTIAL_EXPENSE_RULE
ACTUAL_EXPENSE_NOT_SUPPORTED
UNVERIFIED_ALLOWANCE_RULE
UNVERIFIED_DONATION_RULE
UNVERIFIED_FOREIGN_TAX_CREDIT_LIMIT
ROUNDING_RULE_PENDING
```

Do not suppress M4/M6 warnings.

---

# Error Shape

Use standard project format:

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "incomes.1.income_type": [
      "Expense rule for this income type has not been verified."
    ]
  }
}
```

or a consistent domain-error envelope.

---

# Required Tests — PND90 Metadata and Validation

Test:

```text
PND90 accepts SECTION_40_1
PND90 accepts multiple supported income types structurally
PND90 rejects unknown income type
PND91 still rejects non-40(1)
inactive tax year rejected
missing published rule rejected
negative gross rejected
exempt > gross rejected
```

---

# Required Tests — SECTION_40_1 in PND90

Verify:

```text
PND90 + SECTION_40_1 only
```

produces the same core tax result as equivalent PND91 input when all other inputs are the same.

This is a critical regression test.

---

# Required Tests — Multi-Income Aggregation

Test at least:

```text
two SECTION_40_1 rows
SECTION_40_1 + another structurally supported type
multiple rows of same non-40(1) type
```

Verify totals:

```text
gross
exempt
post-exempt
expenses
income_after_expense
```

---

# Required Tests — Expense Rule Resolution

For each income type with a VERIFIED production rule:

```text
boundary tests
percentage/cap tests
actual-expense tests if applicable
```

For each UNVERIFIED type:

```text
correct warning or rejection
no invented expense deduction
```

---

# Required Tests — Progressive Tax

Reuse M4 boundary suite.

Add PND90 integration test proving combined net income flows into the same progressive tax engine.

Do not rewrite tax bracket unit tests unnecessarily.

---

# Required Tests — Credits

At minimum:

```text
PND90 with withholding
PAYABLE
REFUND
ZERO
```

must work where supported.

---

# Required Tests — Member PND90

Test:

```text
create PND90 draft
store supported income items
calculate saved PND90
history persists
duplicate works
complete only when valid
cross-user access blocked
```

If PND90 calculation remains partially unsupported due to rule gaps, test that completion is blocked with a clear error.

---

# Required Tests — PND91 Regression

All M4–M6 PND91 calculation tests must remain green.

Do not alter expected PND91 results.

---

# Required Tests — Guest/Member Consistency

For a PND90 case that is fully supported:

```text
Guest calculate
Member saved calculate
```

must produce identical tax outcomes.

---

# Required Tests — Planning Regression

PND91 planning from M6 must still work.

If PND90 planning remains unsupported, verify:

```text
422
```

or the chosen explicit response.

---

# Money Strategy

Reuse the exact M4/M5/M6 money strategy.

No float arithmetic.

No new monetary representation.

---

# Rounding

Keep existing `ROUNDING_RULE_PENDING` behavior unless an approved source explicitly resolves it.

Do not invent a legal rounding rule in M7.

---

# Suggested Services

Reuse existing services.

Potential additions:

```text
IncomeAggregationService
ExpenseRuleResolver
IncomeExpenseStrategyFactory
```

Only add abstractions that materially improve clarity.

Avoid over-engineering.

---

# Controllers

Do not create PND90-specific giant controllers.

Shared:

```text
TaxCalculationController
```

should remain thin.

Business logic remains in services/calculators.

---

# API Documentation

Create/update:

```text
docs/api/MILESTONE_07_API.md
```

Document:

```text
PND90 request
supported income types
verified/unverified rule behavior
actual expense field behavior
warnings
errors
member PND90 workflow
PND90 planning support status
backward compatibility with PND91
```

Also create:

```text
docs/tax/PND90_RULE_MATRIX.md
```

---

# Manual Verification

Use current project host/port from Docker config.

Verify with synthetic data only:

1. PND90 with SECTION_40_1 only
2. PND90 with multiple SECTION_40_1 rows
3. PND90 with one non-40(1) type whose expense rule is VERIFIED
4. PND90 with one UNVERIFIED income type
5. PND90 PAYABLE
6. PND90 REFUND
7. PND90 ZERO
8. Member PND90 draft
9. Member PND90 calculation/history
10. PND91 regression
11. PND91 planning regression
12. Cross-user protection

Do not use real financial data.

---

# Cleanup Before Final Verification

Address only safe pre-existing maintenance issues that directly affect milestone completion.

From M6 report:

- there may be 2 pre-existing Pint `method_argument_space` issues;
- synthetic manual-test records may remain in development DB.

You may fix the Pint issues if they are trivial and unrelated to business behavior.

For synthetic records:

- do not use destructive `migrate:fresh`;
- clean only known synthetic records if clearly identifiable;
- otherwise leave them and report them.

Do not delete real development data.

---

# Commands

Inspect:

```bash
git status
docker compose ps
```

Run targeted seed if needed.

Run:

```bash
docker compose exec app php artisan test
```

Run project Pint/lint.

Run frontend build regression if current workflow requires it.

Avoid `migrate:fresh` on active development data.

---

# Completion Criteria

M7 is complete only when:

```text
[ ] PND90 accepted by POST /api/v1/tax/calculate
[ ] SECTION_40_1–SECTION_40_8 structurally supported
[ ] no unverified numeric expense rule is guessed
[ ] verified expense rules are rule-driven
[ ] multi-income aggregation works
[ ] combined net income uses shared progressive tax engine
[ ] PND90 PAYABLE/REFUND/ZERO works for supported cases
[ ] Member PND90 persistence works
[ ] history works
[ ] duplicate works
[ ] completion works only for valid supported calculations
[ ] Guest/Member supported PND90 results match
[ ] PND91 behavior remains unchanged
[ ] PND91 planning remains unchanged
[ ] PND90 rule matrix documentation exists
[ ] warnings/errors clearly identify rule gaps
[ ] all M1–M6 tests remain green
[ ] no UI redesign performed
[ ] no AI tax-rule inference added
```

---

# Required Final Report

At the end, report exactly:

## Summary

## PND90 Income Types Supported

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

state:

```text
STRUCTURAL SUPPORT
VERIFIED EXPENSE RULE
PARTIAL
UNVERIFIED
```

## Production Expense Rules Implemented

List only verified implemented rules.

## Rules Intentionally Not Implemented

List gaps without guessing.

## Services Created / Modified

## API Changes

Explain how `POST /api/v1/tax/calculate` now handles PND90.

## Member Workflow Changes

## Warning / Error Strategy

## Rule Version Behavior

## Documentation Added

Include:

```text
docs/tax/PND90_RULE_MATRIX.md
docs/api/MILESTONE_07_API.md
```

## Commands Executed

Only actual commands.

## Test Coverage

Summarize:

```text
PND90 validation
multi-income aggregation
expense rule resolution
progressive tax integration
PAYABLE/REFUND/ZERO
member persistence/history
Guest/Member consistency
PND91 regression
planning regression
```

## Test Results

Report:

```text
passed
failed
skipped
assertions
```

## Manual Verification

Report synthetic test flows and statuses.

## Rule Gaps / Warnings

List all unresolved PND90 rule gaps.

## Known Issues

List unresolved issues or:

```text
None
```

## Architecture Compliance

Confirm:

```text
PND90 reuses shared TaxCalculationService.
ProgressiveTaxCalculator was not duplicated.
PND91 behavior remains unchanged.
No unverified numeric tax rule was guessed.
No AI determines tax eligibility or amounts.
No approved UI redesign was performed.
Web and Mobile use the same calculation API.
```

## Next Step

Recommend:

```text
Milestone 08 — Content / News / Admin CMS + Tax Rule Administration
```

Do not begin Milestone 08 until approved.

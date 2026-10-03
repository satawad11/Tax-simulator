# MILESTONE_04_PROMPT.md

## Codex Task: Milestone 04 — PND91 Tax Calculation Engine

You are working on:

**Thai Personal Income Tax Simulation Platform**

Before changing any file, you MUST read:

```text
PROJECT_REQUIREMENTS.md
CODING_RULES.md
MILESTONE_01_PROMPT.md
MILESTONE_02_PROMPT.md
MILESTONE_03_PROMPT.md
```

Also inspect the current M2/M3 schema, seeders, models, API resources, and tests.

Treat the project requirement files and approved source-derived rules as the source of truth.

Milestones 01–03 and the schema reconciliation pass are already completed.

Do not begin the Member CRUD milestone or PND90 calculation milestone.

---

# Objective

Implement the first real tax calculation engine for:

```text
PND91
```

Scope this milestone to income under:

```text
SECTION_40_1
```

only.

The engine must support:

```text
income
exempt income
expense deduction
allowances
donations
net income
progressive tax
tax credits / withholding
PAYABLE / REFUND / ZERO
calculation trace
tax analysis
```

The engine must be reusable by both:

- Web
- Future Mobile Application

The backend remains the single source of truth.

---

# Critical Rule: Do Not Guess Tax Law

Only implement numeric rules that are already verified by the approved project source documents or existing approved project data.

If a tax rule is not sufficiently supported:

```text
DO NOT GUESS
DO NOT SILENTLY FILL THE GAP
DO NOT COPY A VALUE FROM GENERAL INTERNET KNOWLEDGE
```

Instead:

1. keep the engine architecture capable of supporting the rule;
2. add a clear TODO;
3. return an explicit validation/warning where appropriate;
4. document the missing rule in the final report.

For tests of calculator mechanics, you may use test fixtures/factory rules that are clearly isolated from production seed data.

Do not convert test fixture values into production rules unless approved.

---

# Source-Derived PND91 Calculation Flow

Implement the calculation flow according to the approved PND91 structure:

```text
1. Gross employment income
2. Less exempt income
3. Remaining income
4. Less allowable expense
5. Income after expense
6. Less allowances
7. Remaining income
8. Less special donation deduction where applicable
9. Remaining income
10. Less general donation deduction where applicable
11. Net income
12. Progressive tax from net income
13. Less foreign tax credit where applicable
14. Tax after foreign tax credit
15. Less withholding / prepaid tax
16. PAYABLE / REFUND / ZERO
```

Do not collapse these steps into a single unexplained formula.

The API must expose a calculation trace corresponding to the calculation sequence.

---

# Verified Rules Already Approved for This Project

## PND91 Income Type

PND91 accepts:

```text
SECTION_40_1
```

only.

Any other income type must be rejected.

## Employment Expense

For the approved SECTION_40_1 / PND91 case, the source documents support:

```text
50% of eligible employment income
capped at 100,000 baht
```

Implement this rule through the rule/service architecture.

Do not hard-code it inside the Controller.

If the existing `expense_rules` production table does not yet contain the verified rule, add a corrective migration/seeder entry or approved seed update for rule version:

```text
2568.1
```

Represent it as:

```text
method = percentage_limit
percentage = 50
maximum_amount = 100000
```

Do not add unrelated expense rules.

## Progressive Tax Brackets

Use the existing published rule version:

```text
2568.1
```

and its 8 approved tax brackets from the database.

Do not hard-code bracket values in the calculator.

---

# Numeric Allowance Rules

The allowance master list exists, but not every numeric allowance rule has been fully verified/seeded.

Therefore:

- inspect the approved project source documents before adding production allowance rules;
- only seed numeric allowance rules that are unambiguously supported;
- do not infer special-case eligibility logic that is not explicitly approved;
- if a user submits an allowance with no verified active rule, do not silently grant it.

Preferred behavior for an unverified allowance rule:

```text
eligible_amount = 0
warning = UNVERIFIED_ALLOWANCE_RULE
```

or reject it with a clear 422 validation response if that is cleaner for API consistency.

Choose one strategy and document it.

Do not mix strategies unpredictably.

---

# Donation Rules

The PND91 source structure distinguishes donation deduction stages and percentage limits.

However, only implement production donation calculation rules that are explicitly supported by approved project rules/source documents.

If donation rules remain incomplete:

- design `DonationCalculator`;
- support empty donations cleanly;
- use isolated test fixtures for calculator mechanics if needed;
- do not guess production multipliers/limits.

The endpoint must work correctly when:

```text
donations = []
```

---

# Tax Credits and Withholding

Support these input types structurally:

```text
withholding
foreign_tax_credit
pnd93
other_credit
```

Do not allow the client to submit a calculated total credit as source of truth.

The server sums eligible credit entries.

For foreign tax credit:

- preserve a service boundary;
- do not exceed any verified limit when implemented;
- if production limit logic is not yet verified, do not guess it.

The engine must at minimum correctly handle:

```text
withholding
```

for PAYABLE / REFUND / ZERO scenarios.

---

# Public Calculation Endpoint

Implement:

```text
POST /api/v1/tax/calculate
```

No authentication required.

Guest calculations must not create a `tax_returns` row.

Guest calculations must not persist financial input by default.

---

# Request Contract

Initial PND91 request shape:

```json
{
  "tax_year": 2568,
  "form_code": "PND91",
  "profile": {
    "birth_date": "1990-05-20",
    "marital_status": "single"
  },
  "incomes": [
    {
      "income_type": "SECTION_40_1",
      "description": "เงินเดือน",
      "gross_amount": "720000.00",
      "exempt_amount": "0.00"
    }
  ],
  "allowances": [],
  "donations": [],
  "withholdings": [
    {
      "type": "withholding",
      "amount": "25000.00"
    }
  ]
}
```

Money may be accepted as JSON numbers or numeric strings, but internally normalize safely.

Do not use binary floating-point as authoritative money arithmetic.

---

# Request Validation

Validate at minimum:

```text
tax_year required
tax_year integer
tax_year exists
tax_year active for public simulation
published rule version exists

form_code required
form_code = PND91 in this milestone
form belongs to selected tax year
form active

incomes required
incomes array
minimum 1 income item

incomes.*.income_type required
income type must exist
income type must be SECTION_40_1 for PND91

incomes.*.gross_amount required
gross_amount numeric
gross_amount >= 0

incomes.*.exempt_amount optional
exempt_amount >= 0
exempt_amount <= gross_amount

allowances array
donations array
withholdings array

all monetary inputs >= 0
```

Reject PND90 calculation requests in this milestone with a clear 422 response.

---

# Standard API Response

Use:

```json
{
  "success": true,
  "message": null,
  "data": {}
}
```

---

# Calculation Response Contract

Return a response suitable for both Web and Mobile.

Example structure:

```json
{
  "success": true,
  "message": null,
  "data": {
    "tax_year": 2568,
    "form_code": "PND91",
    "rule_version": "2568.1",
    "income": {
      "gross_income": "720000.00",
      "exempt_income": "0.00",
      "gross_after_exemption": "720000.00"
    },
    "expenses": {
      "items": [],
      "total": "100000.00"
    },
    "income_after_expense": "620000.00",
    "allowances": {
      "items": [],
      "total_input": "0.00",
      "total_eligible": "0.00"
    },
    "donations": {
      "items": [],
      "total_input": "0.00",
      "total_eligible": "0.00"
    },
    "net_income": "620000.00",
    "progressive_tax": {
      "brackets": [],
      "total": "0.00"
    },
    "credits": {
      "withholding": "25000.00",
      "foreign_tax_credit": "0.00",
      "pnd93": "0.00",
      "other_credit": "0.00",
      "total": "25000.00"
    },
    "result": {
      "status": "PAYABLE",
      "amount": "0.00"
    },
    "analysis": {},
    "trace": [],
    "warnings": []
  }
}
```

Values above illustrate shape only. Actual values must come from the engine.

---

# Result Status

Exactly one of:

```text
PAYABLE
REFUND
ZERO
```

Always return a non-negative amount. Status carries direction.

---

# Money Arithmetic

Do not use PHP float as the authoritative representation of money.

Use one consistent safe strategy, for example:

- integer satang internally; or
- a decimal library/value object; or
- BCMath string decimal arithmetic.

Choose the simplest reliable solution supported by the Docker/PHP environment.

Document the chosen strategy.

Prefer API money output as decimal strings such as:

```text
"35150.00"
```

---

# Rounding

Do not invent a legal tax rounding rule.

If the approved project materials do not explicitly define final rounding behavior:

- preserve exact decimal arithmetic;
- serialize to appropriate precision;
- document the unresolved legal rounding rule;
- add a TODO.

Do not silently floor or ceil.

---

# Required Service Architecture

Create/use:

```text
app/Services/Tax/
```

At minimum:

```text
TaxCalculationService
IncomeCalculator
ExpenseCalculator
AllowanceCalculator
DonationCalculator
ProgressiveTaxCalculator
TaxCreditCalculator
TaxRefundService
TaxAnalysisService
```

You may add:

```text
PublishedTaxRuleResolver
Money
TaxCalculationResult
```

if useful.

Do not create empty wrapper services.

---

# TaxCalculationService

Suggested orchestration:

```text
resolve tax year
resolve PND91
resolve published rule version
calculate income
calculate exemptions
calculate expense
calculate allowances
calculate donations
calculate net income
calculate progressive tax
calculate credits
calculate final result
build analysis
build trace
build warnings
```

Detailed formulas belong in calculators.

---

# IncomeCalculator

Responsibilities:

- sum gross SECTION_40_1 income
- sum submitted exempt income
- subtract valid exempt income
- produce gross-after-exemption values

Do not calculate expenses here.

---

# ExpenseCalculator

Responsibilities:

- load verified expense rule
- apply percentage
- apply cap
- return breakdown
- return total eligible expense

Verified mechanics:

```text
eligible expense =
min(gross_after_exemption * 50%, 100000)
```

Use exact decimal arithmetic.

The 100,000 cap applies to the aggregated relevant SECTION_40_1 income in this milestone, not once per income row.

---

# AllowanceCalculator

Responsibilities:

- resolve allowance code
- resolve active verified rule
- calculate eligible amount only from server-side rule
- issue warning/rejection for unverified rule consistently
- ignore/reject client-provided calculated fields

Do not accept `eligible_amount` from the client as authoritative.

---

# DonationCalculator

Responsibilities:

- preserve calculation stage order
- calculate only verified production rules
- return zero cleanly for an empty donations array

---

# ProgressiveTaxCalculator

Responsibilities:

- load/use ordered published tax brackets
- calculate bracket by bracket
- support final open-ended bracket
- return bracket breakdown
- return total tax

Do not hard-code rates.

---

# TaxCreditCalculator

Responsibilities:

- aggregate supported withholding/credit entries
- preserve component totals
- reject negative amounts
- prepare values for the final result

---

# TaxRefundService

Return:

```text
status
amount
reason_code
components
```

Use simulation wording.

Do not claim an official refund.

---

# TaxAnalysisService

Return at least:

```text
gross_income
net_income
calculated_tax
effective_tax_rate
marginal_tax_rate
withholding_total
result_status
result_amount
```

Define effective rate for this milestone as:

```text
calculated_tax / gross_income * 100
```

If gross income is zero, return 0.

Marginal tax rate = highest bracket rate actually reached by net income.

---

# Calculation Trace

Return ordered trace entries with stable machine codes.

Example:

```json
[
  {
    "step": 1,
    "code": "GROSS_INCOME",
    "label": "เงินได้รวม",
    "amount": "720000.00"
  },
  {
    "step": 2,
    "code": "EXEMPT_INCOME",
    "label": "หักเงินได้ที่ได้รับยกเว้น",
    "amount": "0.00"
  },
  {
    "step": 3,
    "code": "INCOME_AFTER_EXEMPTION",
    "label": "คงเหลือหลังหักเงินได้ยกเว้น",
    "amount": "720000.00"
  },
  {
    "step": 4,
    "code": "EXPENSE",
    "label": "หักค่าใช้จ่าย",
    "amount": "100000.00"
  }
]
```

Continue through the final result.

---

# Warnings

Support warning objects:

```json
{
  "code": "UNVERIFIED_ALLOWANCE_RULE",
  "message": "รายการลดหย่อนนี้ยังไม่มีกฎตัวเลขที่ได้รับการยืนยันในระบบ",
  "path": "allowances.0"
}
```

Possible codes:

```text
UNVERIFIED_ALLOWANCE_RULE
UNVERIFIED_DONATION_RULE
UNVERIFIED_FOREIGN_TAX_CREDIT_LIMIT
ROUNDING_RULE_PENDING
```

Only emit warnings that actually apply.

---

# DTO / Input Objects

Do not pass raw Request objects into the tax engine.

Suggested DTOs:

```text
TaxCalculationData
IncomeData
AllowanceData
DonationData
WithholdingData
```

---

# Controller

Create:

```text
Api/V1/TaxCalculationController
```

Responsibility only:

```text
validated request
-> DTO
-> TaxCalculationService
-> Resource
```

No tax formulas in Controller.

---

# FormRequest

Create:

```text
CalculateTaxRequest
```

Use nested validation suitable for Web/Mobile clients.

---

# API Resource

Create a dedicated resource/result serializer such as:

```text
TaxCalculationResource
```

Do not expose Eloquent models directly.

---

# Production Rule Seed Update

If the SECTION_40_1 expense rule is absent, add an idempotent seed update for rule version 2568.1:

```text
method = percentage_limit
percentage = 50
maximum_amount = 100000
```

Do not alter the existing tax brackets.

Add a regression test for this seeded rule.

---

# Progressive Tax Boundary Tests

Must cover at least:

```text
0
150000
150000.01
300000
300000.01
500000
500000.01
750000
750000.01
1000000
1000000.01
2000000
2000000.01
5000000
5000000.01
```

Use the existing bracket boundary convention consistently.

Add tests proving there are no gaps or overlaps.

---

# Expense Boundary Tests

At minimum:

```text
gross after exemption = 0
100000
200000
200000.01
720000
```

Expected mechanics:

```text
0 -> 0
100000 -> 50000
200000 -> 100000
above 200000 -> 100000
```

---

# Result Status Tests

Create test scenarios for:

```text
PAYABLE
REFUND
ZERO
```

Ensure:

```text
result.amount >= 0
```

---

# Zero-Income Test

With gross income = 0:

```text
calculated tax = 0
effective tax rate = 0
```

No division-by-zero.

---

# Multiple SECTION_40_1 Income Items

Test multiple employers/payers.

Aggregate correctly.

Do not grant the expense cap separately for each row.

---

# Exempt Income Tests

At minimum:

```text
exempt = 0
exempt < gross
exempt = gross
exempt > gross => 422
```

Do not automate exemption eligibility law beyond approved rules.

---

# Allowance Tests

If numeric allowance rules remain unverified:

```text
allowances = [] => succeeds
unknown allowance code => 422
known but unverified rule => chosen warning/rejection behavior
client cannot set eligible_amount as authoritative input
```

---

# Donation Tests

If production donation rules remain unverified:

```text
donations = [] => succeeds
unknown donation code => clearly rejected/unsupported
```

Do not fabricate production savings.

---

# Withholding Tests

At minimum:

```text
no withholding
single withholding
multiple withholding entries
withholding > tax => REFUND
withholding = tax => ZERO
withholding < tax => PAYABLE
negative amount => 422
```

---

# PND91 Validation Tests

Must reject:

```text
SECTION_40_2
SECTION_40_8
PND90 form_code
unknown income type
unknown tax year
inactive tax year
missing published rule version
negative money
malformed arrays
```

---

# API Feature Tests

At minimum:

```text
POST /api/v1/tax/calculate valid PND91 -> 200
response rule_version = 2568.1
response contains expense breakdown
response contains tax bracket breakdown
response contains result
response contains analysis
response contains trace
response contains warnings
```

No authentication required.

Verify no `tax_returns` row is created.

---

# Unit Tests

Add focused unit tests for:

```text
ExpenseCalculator
ProgressiveTaxCalculator
TaxRefundService
TaxAnalysisService
```

Feature tests alone are not enough.

---

# Regression Tests

All M1/M2/M3 tests must continue to pass.

Do not delete tests to make M4 green.

---

# No Persistence for Guest Calculation

`POST /api/v1/tax/calculate` must not create:

```text
TaxReturn
TaxCalculation history
TaxScenario
```

It is a stateless simulation endpoint.

---

# Logging

Do not log full financial request payloads or sensitive personal data.

---

# API Documentation

Create/update:

```text
docs/api/MILESTONE_04_API.md
```

Document:

```text
POST /api/v1/tax/calculate
request
response
PND91-only scope
validation errors
result statuses
warning codes
money serialization
simulation disclaimer
known rule gaps
```

---

# Simulation Disclaimer

The API or documentation must provide wording equivalent to:

```text
ผลลัพธ์เป็นการประมาณการจากข้อมูลที่กรอกในระบบจำลอง
ไม่ใช่การยื่นแบบภาษีจริงหรือการรับรองผลโดยหน่วยงานราชการ
```

Keep wording reusable by Web and Mobile.

---

# Out of Scope

Do NOT implement:

```text
PND90 calculation
SECTION_40_2 through SECTION_40_8 calculation
full tax recommendation engine
tax planning scenario engine
member Tax Return CRUD
draft/history persistence
admin tax rule UI
full calculator frontend
UI redesign
```

---

# Commands

Inspect:

```bash
git status
docker compose ps
```

If rule seed changes:

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
```

Prefer targeted/idempotent seeding over destructive reset.

Do not run `migrate:fresh` on normal development data unless clearly necessary and explicitly report it.

Run:

```bash
docker compose exec app php artisan test
```

Run the project's existing Pint/lint command and frontend build regression if required.

---

# Manual API Verification

Use the current working Docker host port from project config, currently expected around:

```text
http://localhost:8088
```

Verify:

```text
POST /api/v1/tax/calculate
```

for:

```text
PAYABLE
REFUND
ZERO
validation failure
```

Do not change ports silently.

---

# Completion Criteria

Milestone 04 is complete only when:

```text
[ ] POST /api/v1/tax/calculate exists
[ ] endpoint is public/stateless
[ ] PND91 only
[ ] SECTION_40_1 only
[ ] expense 50% / 100,000 cap is rule-driven
[ ] published DB tax brackets are used
[ ] progressive tax boundary tests pass
[ ] expense boundary tests pass
[ ] PAYABLE test passes
[ ] REFUND test passes
[ ] ZERO test passes
[ ] multiple SECTION_40_1 rows aggregate correctly
[ ] guest request creates no tax-return persistence
[ ] calculation trace is returned
[ ] tax analysis is returned
[ ] warning mechanism exists
[ ] unverified tax rules are not guessed
[ ] money arithmetic does not rely on binary float
[ ] API documentation exists
[ ] all M1-M3 regression tests pass
[ ] no PND90 calculation engine was added
[ ] no member CRUD was started
[ ] no approved UI redesign was performed
```

---

# Required Final Report

At the end, report exactly:

## Summary

## Rules Implemented

List only production tax rules actually implemented and their approval basis.

Explicitly list rules intentionally not implemented.

## Files Created

## Files Modified

## Services Created

## API Added

Document:

```text
POST /api/v1/tax/calculate
```

## Money Strategy

Explain exact money representation/arithmetic.

## Validation

## Tax Calculation Flow

## Test Coverage

## Test Results

Report:

```text
passed
failed
skipped
assertions
```

## Manual Verification

Report actual PAYABLE / REFUND / ZERO requests and statuses using synthetic data only.

## Rule Gaps / Warnings

List unresolved:

```text
allowance numeric rules
donation rules
foreign tax credit limits
rounding behavior
```

where applicable.

## Regression Status

Confirm M1-M3 remain passing.

## Architecture Compliance

Confirm:

```text
Tax logic is in Services/Calculators, not Controllers.
No PND90 calculation engine was added.
No member CRUD was started.
No full recommendation/planning engine was added.
No approved UI redesign was performed.
Guest calculations are stateless.
Web and Mobile can consume the same calculation API.
```

## Next Step

Recommend:

```text
Milestone 05 — Authentication + Member Tax Return CRUD / Draft / History
```

Do not begin Milestone 05 until approved.

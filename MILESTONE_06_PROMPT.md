# MILESTONE_06_PROMPT.md

## Codex Task: Milestone 06 — Tax Planning + Recommendation + Refund Guidance

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
```

Also inspect the current M4/M5 tax engine, authentication/member APIs, schema, models, seeders, services, warnings, tests, and API documentation.

Treat the approved project requirement files and the current working implementation as the source of truth.

Milestones 01–05 are complete.

Do not begin the PND90 calculation milestone.
Do not redesign the approved UI.
Do not introduce AI-generated tax rules.

---

# Objective

Implement:

```text
Tax Planning
Tax Recommendation
Refund Guidance
Scenario Comparison
Recommendation Rules
Member Scenario Persistence
Guest Scenario Simulation
```

This milestone must build on the existing M4/M5 calculation engine.

The existing `TaxCalculationService` remains the only source of tax calculation truth.

Do not duplicate tax formulas.

---

# Product Goal

After a user completes a tax calculation, the system should not stop at:

```text
ภาษีที่ต้องชำระเพิ่ม
หรือ
ภาษีชำระไว้เกิน
```

The system should also help the user understand:

1. why the result occurred;
2. what items may be worth checking;
3. what a hypothetical tax-planning scenario would change;
4. how much tax may change under that scenario;
5. why a refund estimate exists;
6. what the user should verify before relying on the result.

This remains an educational simulation system.

It must not become an investment-advice engine.

---

# Critical Rule: Recommendations Must Be Rule-Based

The recommendation engine must be deterministic and rule-based.

Do NOT use an LLM or generative AI to decide:

```text
tax eligibility
deduction amount
tax rule
tax saving amount
refund entitlement
financial product recommendation
```

Recommendations may use:

```text
verified tax rules
verified recommendation rules
user-entered simulation data
calculation output
```

AI-generated prose may be considered in a future milestone, but must never override tax rules.

---

# Recommendation Categories

Support:

```text
MISSING_INFORMATION
POTENTIAL_ALLOWANCE
TAX_PLANNING
PAYMENT
REFUND
```

These types already exist in the approved project design.

Use stable machine-readable codes.

Examples:

```text
CHECK_SOCIAL_SECURITY
CHECK_RMF
CHECK_LIFE_INSURANCE
PAYABLE_DUE_TO_LOW_WITHHOLDING
REFUND_DUE_TO_EXCESS_WITHHOLDING
```

Only implement recommendations for which the underlying conditions are supported by approved project data/rules.

Do not guess legal eligibility.

---

# Recommendation Language Rules

Preferred language:

```text
ควรตรวจสอบสิทธิ
อาจมีสิทธิ
สามารถทดลองจำลองได้
จากข้อมูลที่กรอก
```

Avoid language such as:

```text
คุณมีสิทธิแน่นอน
คุณควรซื้อผลิตภัณฑ์นี้
คุณจะได้รับเงินคืนแน่นอน
วิธีนี้ทำให้เสียภาษีน้อยที่สุดแน่นอน
```

---

# Tax Planning Philosophy

Tax planning in this system means:

```text
simulate a hypothetical change
recalculate using the same tax engine
compare before / after
explain the difference
```

It does NOT mean:

```text
recommend purchasing specific financial products
optimize investments
give personalized regulated investment advice
guarantee legal eligibility
```

---

# Core Services

Create/use:

```text
TaxRecommendationService
TaxPlanningService
TaxRefundGuidanceService
```

Reuse:

```text
TaxCalculationService
TaxAnalysisService
TaxRefundService
PublishedTaxRuleResolver
```

Do not copy tax formulas into the new services.

---

# Recommended Architecture

Suggested flow:

```text
Base TaxCalculationResult
        |
        +--> TaxRecommendationService
        |
        +--> TaxRefundGuidanceService
        |
        +--> TaxPlanningService
                 |
                 +--> modified simulation input
                 |
                 +--> TaxCalculationService
```

Planning must call the same TaxCalculationService again.

---

# Recommendation Rules Data

Use the existing:

```text
recommendation_rules
```

table.

If required, create a corrective migration only for fields already required by the approved design.

Do not rewrite existing migrations unnecessarily.

Recommended rule fields already approved:

```text
rule_version_id
code
type
priority
title
message_template
conditions JSON
action_type
active
```

---

# Recommendation Rule Conditions

Conditions must remain explicit and deterministic.

Examples:

```json
{
  "income_types_contains": "SECTION_40_1",
  "allowance_not_present": "SOCIAL_SECURITY"
}
```

or:

```json
{
  "result_status": "PAYABLE",
  "withholding_ratio_below": "calculated_tax"
}
```

Do not build a general-purpose unsafe expression evaluator.

Implement a small supported condition vocabulary.

---

# Supported Recommendation Condition Vocabulary

Implement only what is required by seeded rules, for example:

```text
income_types_contains
income_types_only
allowance_present
allowance_not_present
result_status
gross_income_greater_than
net_income_greater_than
withholding_less_than_tax
withholding_greater_than_tax
warning_present
```

If a rule contains an unsupported condition:

- skip it safely;
- log a sanitized warning;
- do not crash;
- document it.

---

# Seed Recommendation Rules

Only seed recommendation rules that are safe and supported by current verified data.

At minimum, consider safe non-eligibility-confirming rules such as:

## 1. Missing Social Security Input

If:

```text
PND91 / SECTION_40_1
SOCIAL_SECURITY allowance not present
```

Then:

```text
type = POTENTIAL_ALLOWANCE
priority = medium
title = ตรวจสอบเงินสมทบประกันสังคม
message = หากคุณมีการจ่ายเงินสมทบประกันสังคมในปีภาษีนี้ ควรตรวจสอบว่ามีรายการที่ใช้สิทธิได้หรือไม่
```

This is a reminder to verify, not an eligibility grant.

## 2. Payable Due to Withholding Lower Than Calculated Tax

If:

```text
result_status = PAYABLE
withholding < calculated tax after applicable credits
```

Then:

```text
type = PAYMENT
priority = high
```

Explain that the simulated withholding is lower than the simulated liability.

## 3. Refund Due to Withholding Above Calculated Tax

If:

```text
result_status = REFUND
```

Then:

```text
type = REFUND
priority = medium
```

Explain that tax/credits entered exceed the simulated liability.

## 4. Existing Warning Reminder

If calculation contains:

```text
UNVERIFIED_ALLOWANCE_RULE
```

Return a recommendation to verify supporting tax rule/source data.

Do not transform an unverified allowance into a tax benefit.

Do not seed RMF/insurance/planning rules with numeric effects unless the underlying allowance numeric rules are actually verified and active.

---

# Recommendation Output

Return:

```json
{
  "recommendations": [
    {
      "code": "CHECK_SOCIAL_SECURITY",
      "type": "POTENTIAL_ALLOWANCE",
      "priority": "medium",
      "title": "ตรวจสอบเงินสมทบประกันสังคม",
      "message": "หากคุณมีการจ่ายเงินสมทบประกันสังคมในปีภาษีนี้ ควรตรวจสอบว่ามีรายการที่ใช้สิทธิได้หรือไม่",
      "action": {
        "type": "OPEN_ALLOWANCE",
        "allowance_code": "SOCIAL_SECURITY"
      }
    }
  ]
}
```

The client should not need to parse Thai prose to know the action.

Use machine-readable `action`.

---

# Recommendation Endpoint

For Guest simulation, recommendation data may be included directly in:

```text
POST /api/v1/tax/calculate
```

OR exposed through a calculation orchestration response if the current architecture prefers.

However:

- do not persist Guest recommendations;
- do not change the core M4 result semantics;
- preserve backward compatibility with existing tests.

For Member returns, implement:

```text
GET /api/v1/tax-returns/{id}/recommendations
```

Protected by ownership.

The endpoint should:

1. load saved current return data;
2. run/reuse the calculation engine as needed;
3. generate recommendations;
4. not create a new calculation snapshot unless explicitly designed to do so.

Preferred:

```text
recommendation endpoint should be read-only and not create history
```

Document the behavior.

---

# Tax Planning Endpoint — Guest

Implement:

```text
POST /api/v1/tax/plan
```

No authentication required.

Guest planning remains stateless.

Request structure:

```json
{
  "tax_year": 2568,
  "form_code": "PND91",
  "base": {
    "profile": {
      "birth_date": "1990-05-20",
      "marital_status": "single"
    },
    "incomes": [
      {
        "income_type": "SECTION_40_1",
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
  },
  "scenario": {
    "allowances": [
      {
        "code": "SOCIAL_SECURITY",
        "input_amount": "9000.00"
      }
    ]
  }
}
```

Important:

A scenario may only produce a changed eligible deduction if a verified active rule exists.

If the allowance rule is unverified:

```text
do not grant tax saving
return warning
```

---

# Scenario Merge Rules

Planning input must be deterministic.

Define explicitly whether scenario data:

```text
replaces
adds
removes
```

base values.

Recommended structure:

```json
{
  "scenario": {
    "allowances": {
      "upsert": [],
      "remove": []
    },
    "donations": {
      "upsert": [],
      "remove": []
    },
    "withholdings": {
      "upsert": [],
      "remove": []
    }
  }
}
```

However, if this makes M6 unnecessarily complex, a simpler full replacement structure is acceptable.

Whichever strategy you choose:

- document it clearly;
- test it;
- use the same strategy for Web and Mobile;
- do not infer ambiguous merge behavior.

---

# Tax Planning Response

Return:

```json
{
  "success": true,
  "message": null,
  "data": {
    "tax_year": 2568,
    "form_code": "PND91",
    "rule_version": "2568.1",

    "before": {
      "net_income": "620000.00",
      "calculated_tax": "45500.00",
      "result": {
        "status": "PAYABLE",
        "amount": "20500.00"
      }
    },

    "after": {
      "net_income": "611000.00",
      "calculated_tax": "44150.00",
      "result": {
        "status": "PAYABLE",
        "amount": "19150.00"
      }
    },

    "difference": {
      "net_income": "-9000.00",
      "calculated_tax": "-1350.00",
      "result_amount": "-1350.00"
    },

    "estimated_tax_saving": "1350.00",

    "warnings": [],

    "recommendations": []
  }
}
```

Numbers above are illustrative only.

Do not hard-code expected savings.

Always calculate `before` and `after` via TaxCalculationService.

---

# Tax Saving Definition

Define:

```text
estimated_tax_saving =
before.calculated_tax - after.calculated_tax
```

If negative:

```text
0
```

for the tax-saving headline, while raw difference may remain negative/positive as appropriate.

Do not define tax saving based only on final withholding balance.

Tax saving relates to tax liability, not merely payment timing.

Also return final result difference separately.

---

# Member Scenario Persistence

Use the existing:

```text
tax_scenarios
```

table.

Implement:

```text
GET    /api/v1/tax-returns/{id}/scenarios
POST   /api/v1/tax-returns/{id}/scenarios
GET    /api/v1/tax-returns/{id}/scenarios/{scenarioId}
PATCH  /api/v1/tax-returns/{id}/scenarios/{scenarioId}
DELETE /api/v1/tax-returns/{id}/scenarios/{scenarioId}
POST   /api/v1/tax-returns/{id}/scenarios/{scenarioId}/calculate
```

All require:

```text
auth:sanctum
ownership
```

---

# Scenario Persistence Rules

A TaxScenario stores:

```text
user_id
source_tax_return_id
tax_year_id
rule_version_id
name
payload
calculation_result
```

Do not mutate the source TaxReturn.

Scenario must remain tied to:

```text
tax year
rule version
```

of the source return.

Do not silently upgrade a scenario to a newer rule version.

---

# POST Member Scenario

Example:

```json
{
  "name": "ทดลองเพิ่มค่าลดหย่อน",
  "payload": {
    "allowances": {
      "upsert": [
        {
          "code": "SOCIAL_SECURITY",
          "input_amount": "9000.00"
        }
      ],
      "remove": []
    }
  }
}
```

Do not trust any client-submitted:

```text
calculation_result
estimated_tax_saving
eligible_amount
```

The server calculates them.

---

# Calculate Member Scenario

POST:

```text
/api/v1/tax-returns/{id}/scenarios/{scenarioId}/calculate
```

Flow:

```text
authorize
load source return
build base TaxCalculationData
apply scenario payload
call TaxCalculationService for before
call TaxCalculationService for after
compare
generate planning recommendations
persist calculation_result JSON on TaxScenario
return comparison
```

Do not add scenario results into TaxCalculation history unless explicitly required.

Preferred:

```text
Scenario result remains in tax_scenarios.calculation_result
```

so tax-return calculation history remains actual saved-return history.

---

# Scenario List

GET scenarios:

- owner only
- newest updated first
- return summary:

```text
id
name
updated_at
estimated_tax_saving
latest result status
```

Do not recalculate every row during list if `calculation_result` is already persisted.

---

# Scenario Detail

Return:

```text
payload
stored calculation_result
rule version
created_at
updated_at
```

---

# Scenario Update

Allow:

```text
name
payload
```

Updating payload must invalidate old stored result.

Set:

```text
calculation_result = null
```

until scenario is recalculated.

---

# Scenario Delete

Return:

```text
204
```

Do not affect source TaxReturn.

---

# Refund Guidance

Implement:

```text
TaxRefundGuidanceService
```

This service does not decide legal refund entitlement beyond the calculation result.

It explains the simulation result.

For:

```text
result.status = REFUND
```

Return:

```json
{
  "refund_guidance": {
    "estimated_refund": "4500.00",
    "reason_code": "CREDITS_EXCEED_CALCULATED_TAX",
    "reason": "ภาษีที่ถูกหักหรือเครดิตที่กรอกสูงกว่าภาษีที่คำนวณได้ในระบบจำลอง",
    "components": {
      "calculated_tax": "45500.00",
      "withholding": "50000.00",
      "other_credits": "0.00"
    },
    "checklist": [
      {
        "code": "VERIFY_WITHHOLDING",
        "label": "ตรวจสอบยอดภาษีหัก ณ ที่จ่าย"
      },
      {
        "code": "VERIFY_SUPPORTING_DOCUMENTS",
        "label": "ตรวจสอบเอกสารประกอบข้อมูลที่กรอก"
      },
      {
        "code": "VERIFY_REFUND_CHANNEL",
        "label": "ตรวจสอบช่องทางรับเงินคืนสำหรับการยื่นจริง"
      }
    ],
    "disclaimer": "จำนวนเงินเป็นเพียงประมาณการจากข้อมูลในระบบทดลอง"
  }
}
```

The actual values must come from the calculation.

---

# Refund Guidance for PAYABLE / ZERO

If PAYABLE:

```text
refund_guidance = null
```

or provide a payment explanation object separate from refund guidance.

Preferred:

```text
refund_guidance = null
payment_guidance = {...}
```

If ZERO:

both may be null or return neutral explanation.

Keep the API explicit.

---

# Payment Guidance

Implement safe explanatory guidance for PAYABLE.

Example:

```json
{
  "payment_guidance": {
    "amount": "20500.00",
    "reason_code": "WITHHOLDING_BELOW_CALCULATED_TAX",
    "reason": "ภาษีหัก ณ ที่จ่ายที่กรอกต่ำกว่าภาษีที่คำนวณได้ในระบบจำลอง"
  }
}
```

Do not provide payment instructions to a real government payment channel in this milestone.

---

# Recommendation + Refund Response Integration

For Guest calculation response, add fields without breaking old consumers:

```text
recommendations
refund_guidance
payment_guidance
```

If extending M4 response is too invasive, create an orchestration layer/resource that decorates TaxCalculationResult.

Do not rewrite TaxCalculationService to contain recommendation text.

---

# M4 Warning Preservation

Warnings from M4 must remain visible.

Examples:

```text
UNVERIFIED_ALLOWANCE_RULE
UNVERIFIED_DONATION_RULE
UNVERIFIED_FOREIGN_TAX_CREDIT_LIMIT
ROUNDING_RULE_PENDING
```

M6 must never hide warnings merely because a recommendation exists.

---

# Recommendation Priority

Allowed:

```text
low
medium
high
```

Sort:

```text
high
medium
low
```

Then deterministic secondary order by rule code or configured order.

Do not return nondeterministic order.

---

# Recommendation Deduplication

Do not return duplicate recommendation codes.

If multiple conditions produce the same recommendation:

```text
return once
```

---

# Completed Tax Return Scenario Policy

Member may create a planning scenario from:

```text
draft
completed
```

A scenario never modifies the source.

This is especially useful for completed simulations.

No need to duplicate the tax return merely to create a scenario.

---

# Scenario Ownership Tests

Must test:

```text
User A creates scenario on own return
User B cannot list/read/update/delete/calculate User A scenario
scenario must belong to route tax-return
```

Avoid insecure nested-resource ID handling.

---

# Recommendation Tests

At minimum:

```text
PND91 SECTION_40_1 without SOCIAL_SECURITY input -> CHECK_SOCIAL_SECURITY
PAYABLE -> payment recommendation
REFUND -> refund recommendation
warning present -> verification recommendation
duplicate recommendation codes removed
priority order stable
```

Only test rules actually seeded.

---

# Planning Tests

At minimum:

```text
guest /tax/plan works without auth
guest planning persists nothing
base calculation uses TaxCalculationService
after calculation uses TaxCalculationService
source payload unchanged
tax saving computed from tax liability difference
unverified allowance does not create fake tax saving
warnings propagated
```

---

# Scenario Tests

At minimum:

```text
member creates scenario
member lists scenarios
member reads scenario
member updates scenario
update invalidates stored result
member calculates scenario
result is persisted
member deletes scenario
source tax return remains unchanged
rule version preserved
```

---

# Refund Guidance Tests

At minimum:

```text
REFUND -> estimated_refund matches calculation result
REFUND -> checklist returned
PAYABLE -> no refund guidance
ZERO -> no false refund
refund guidance does not claim certainty
```

---

# Guest / Member Planning Consistency

For identical base + scenario input:

```text
Guest POST /api/v1/tax/plan
```

and:

```text
Member scenario calculate
```

must produce equivalent before/after tax outcomes under the same rule version.

Add regression test.

---

# No Investment Advice

Do not output recommendations like:

```text
buy RMF
buy Thai ESG
buy insurance
invest X baht
```

unless phrased purely as a simulation input and only where an underlying verified rule exists.

Safer wording:

```text
สามารถทดลองจำลองผล หากคุณมีรายการที่เข้าเงื่อนไขดังกล่าวจริง
```

Do not recommend brands, funds, financial products, or providers.

---

# No Rule Guessing

Do not seed numeric recommendation/planning rules for:

```text
RMF
Thai ESG
Thai ESGX
insurance
donations
foreign tax credit
```

unless current approved production data actually contains verified active rules.

A recommendation may remind the user to verify an item, but may not calculate a tax benefit without a verified numeric rule.

---

# API Documentation

Create/update:

```text
docs/api/MILESTONE_06_API.md
```

Document:

```text
POST /api/v1/tax/plan
GET /api/v1/tax-returns/{id}/recommendations
scenario CRUD endpoints
scenario calculate
recommendation structure
recommendation action structure
refund guidance
payment guidance
tax saving definition
scenario merge behavior
Guest vs Member behavior
warnings
simulation disclaimer
```

---

# Suggested Controllers

Use focused controllers such as:

```text
Api/V1/TaxPlanningController
Api/V1/TaxReturnRecommendationController
Api/V1/TaxScenarioController
Api/V1/TaxScenarioCalculationController
```

Do not add recommendation logic directly in controllers.

---

# Suggested Requests

Create focused FormRequests:

```text
PlanTaxRequest
StoreTaxScenarioRequest
UpdateTaxScenarioRequest
```

Reuse existing tax calculation validation components where practical.

Do not duplicate complex validation unnecessarily.

---

# Suggested API Resources

Examples:

```text
TaxPlanningResource
TaxRecommendationResource
TaxScenarioSummaryResource
TaxScenarioResource
RefundGuidanceResource
PaymentGuidanceResource
```

Do not leak internal DB IDs unless useful.

---

# Transactions

Use transaction for:

```text
scenario calculate + stored result update
```

where necessary.

Simple read-only recommendation endpoints do not need transactions.

---

# Money Strategy

Reuse the exact same safe decimal/money strategy from M4/M5.

Do not introduce floats.

All planning comparisons must use the same monetary representation.

---

# Rule Version Integrity

All planning and recommendation calculations must use:

```text
base/source rule_version
```

Do not automatically use newest published version for an existing member TaxReturn.

Guest planning should resolve the current published version for its selected tax year, consistent with M4.

---

# No History Pollution

Guest plan:

```text
no persistence
```

Member scenario calculation:

```text
persist to tax_scenarios.calculation_result
```

Do not automatically add planning calculations to:

```text
tax_calculations
```

unless this project later explicitly decides to maintain scenario-specific calculation history.

---

# Existing Member History

M5 TaxCalculation history behavior must remain unchanged.

Do not alter historical snapshots.

---

# API Backward Compatibility

M4 and M5 endpoints must continue to work.

If adding fields to responses:

```text
append fields
do not rename/remove existing fields
```

unless a current bug requires a breaking fix, which must be reported before changing.

---

# Regression Tests

All M1–M5 tests must remain green.

Do not delete or weaken existing tests.

---

# No PND90 Engine

Do NOT calculate PND90 or SECTION_40_2–SECTION_40_8 in this milestone.

If planning request uses PND90:

```text
return 422 unsupported calculation
```

consistent with M4.

---

# No UI Redesign

Do not implement the full frontend planning screens yet.

No public UI redesign.

UI implementation will use the approved mockup in a later milestone.

---

# Manual Verification

Use current Docker port from project configuration, expected around:

```text
http://localhost:8088
```

Verify with synthetic data:

1. Guest calculate PAYABLE -> payment guidance
2. Guest calculate REFUND -> refund guidance
3. Guest recommendation list
4. Guest plan base vs scenario
5. Login member
6. Create scenario from member return
7. Calculate scenario
8. Read persisted scenario result
9. Update scenario and confirm result invalidation
10. Recalculate
11. Cross-user scenario access denied
12. Source tax return remains unchanged

Do not use real financial/personal data.

---

# Commands

Inspect first:

```bash
git status
docker compose ps
```

Run:

```bash
docker compose exec app php artisan test
```

Run Pint/lint using the existing project command.

Run frontend build regression if it is part of the current workflow.

Do not use `migrate:fresh` on active development data unless clearly necessary and explicitly reported.

If recommendation seed data is added, use idempotent seeding.

---

# Completion Criteria

M6 is complete only when:

```text
[ ] rule-based recommendation engine exists
[ ] no AI determines tax rules
[ ] recommendation types are supported
[ ] recommendation actions are machine-readable
[ ] recommendations are deduplicated
[ ] recommendation order is deterministic
[ ] Guest /tax/plan exists
[ ] Guest planning is stateless
[ ] planning reuses TaxCalculationService
[ ] before/after comparison works
[ ] estimated tax saving uses liability difference
[ ] unverified rules do not create fake savings
[ ] refund guidance works for REFUND
[ ] payment guidance works for PAYABLE
[ ] ZERO does not create false refund
[ ] member recommendation endpoint works
[ ] scenario CRUD works
[ ] scenario ownership enforced
[ ] scenario calculation result persists
[ ] scenario update invalidates previous result
[ ] source TaxReturn remains unchanged
[ ] source rule version is preserved
[ ] Guest/Member scenario outcomes match for equivalent inputs
[ ] M4 warnings remain visible
[ ] no PND90 calculation was added
[ ] no investment advice was added
[ ] API docs updated
[ ] all M1–M5 regression tests pass
[ ] no approved UI redesign performed
```

---

# Required Final Report

At the end, report exactly:

## Summary

## Recommendation Rules Implemented

List seeded/implemented recommendation codes and conditions.

Explicitly list any planned rules intentionally not implemented.

## Planning API Added

Document:

```text
POST /api/v1/tax/plan
```

## Member Recommendation API

Document:

```text
GET /api/v1/tax-returns/{id}/recommendations
```

## Scenario Routes Added

List all scenario endpoints.

## Services Created

## Controllers Created

## FormRequests Created

## API Resources Created

## Scenario Merge Strategy

Explain exactly how base and scenario input are combined.

## Tax Saving Definition

Explain the exact formula.

## Refund Guidance

Explain structure and disclaimers.

## Payment Guidance

Explain structure.

## Rule Version Behavior

Explain Guest vs Member rule-version resolution.

## Persistence Behavior

Explain:

```text
Guest planning = stateless
Member scenario = persisted
TaxReturn source = unchanged
TaxCalculation history = unchanged by scenario
```

## Commands Executed

Only actual commands run.

## Test Coverage

Summarize:

```text
recommendations
planning
scenario CRUD
scenario ownership
refund guidance
payment guidance
Guest/Member consistency
regression
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

Report synthetic flows and HTTP/result statuses.

## Rule Gaps / Warnings

List unresolved production rules that remain unverified.

## Known Issues

List unresolved issues or:

```text
None
```

## Architecture Compliance

Confirm:

```text
Planning reuses TaxCalculationService.
Recommendation engine is deterministic/rule-based.
No AI determines tax eligibility or amounts.
No PND90 calculation engine was added.
No investment advice was added.
Guest planning is stateless.
Member scenarios do not mutate source TaxReturns.
No approved UI redesign was performed.
Web and Mobile can consume the same planning/recommendation APIs.
```

## Next Step

Recommend:

```text
Milestone 07 — PND90 Calculation Engine (SECTION_40_1–SECTION_40_8)
```

Do not begin Milestone 07 until approved.

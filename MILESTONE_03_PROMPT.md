# MILESTONE_03_PROMPT.md

## Codex Task: Milestone 03 — Public Tax Metadata API

You are working on:

**Thai Personal Income Tax Simulation Platform**

Before changing any file, you MUST read:

```text
PROJECT_REQUIREMENTS.md
CODING_RULES.md
MILESTONE_01_PROMPT.md
MILESTONE_02_PROMPT.md
```

Treat those files as the source of truth.

Milestone 01 and Milestone 02 are already completed.

Do not modify the approved architecture or UI direction.

Do not begin Milestone 04.

---

# Objective

Implement the public tax metadata API that will be consumed by both:

- Web Application
- Future Mobile Application

This milestone exposes tax master data created in Milestone 02.

Do NOT implement the full tax calculation engine yet.

---

# Architecture Rules

Use:

```text
Controller
-> Service/Query layer where useful
-> API Resource
-> JSON response
```

Use Laravel FormRequest for request validation where applicable.

Do not place tax calculation business logic in Controllers.

Do not duplicate database data in hard-coded arrays unless needed for safe enum-like validation.

---

# API Base Path

All endpoints must be under:

```text
/api/v1
```

Existing endpoint from M1 must remain:

```text
GET /api/v1/health
```

---

# Standard Response Format

All successful object responses:

```json
{
  "success": true,
  "message": null,
  "data": {}
}
```

Collection responses:

```json
{
  "success": true,
  "message": null,
  "data": []
}
```

Validation errors:

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {}
}
```

Not found:

```json
{
  "success": false,
  "message": "Resource not found",
  "errors": null
}
```

Do not expose Laravel stack traces or internal exception details in API responses.

---

# Required Public Endpoints

Implement:

```text
GET  /api/v1/tax-years
GET  /api/v1/tax-years/{year}

GET  /api/v1/tax-years/{year}/forms
GET  /api/v1/tax-years/{year}/forms/{form}

GET  /api/v1/tax-years/{year}/income-types
GET  /api/v1/tax-years/{year}/income-types/{code}

GET  /api/v1/tax-years/{year}/allowances
GET  /api/v1/tax-years/{year}/allowances/{code}

GET  /api/v1/tax-years/{year}/tax-brackets

POST /api/v1/tax/forms/recommend
```

No authentication is required for these endpoints.

---

# 1. GET /api/v1/tax-years

Return tax years that are available for public simulation.

Default behavior:

- return only active years
- order newest year first

Example response:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "year": 2568,
      "name": "ปีภาษี 2568",
      "active": true
    }
  ]
}
```

Do not expose internal DB IDs unless they are actually useful to the client.

Prefer stable public identifiers such as:

```text
year
code
version
```

---

# 2. GET /api/v1/tax-years/{year}

Example:

```text
GET /api/v1/tax-years/2568
```

Return:

```json
{
  "success": true,
  "message": null,
  "data": {
    "year": 2568,
    "name": "ปีภาษี 2568",
    "active": true,
    "filing_start_date": null,
    "filing_end_date": null,
    "rule_version": {
      "version": "2568.1",
      "status": "published"
    }
  }
}
```

Use the current published rule version for the requested year.

If no published rule version exists:

- do not silently fall back to draft
- return a clear API error
- use appropriate status code, preferably 409 or 404 depending on implementation semantics
- document the decision in the final report

If year does not exist:

```text
404
```

---

# 3. GET /api/v1/tax-years/{year}/forms

Example response:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "code": "PND90",
      "name": "ภ.ง.ด.90",
      "description": "สำหรับผู้มีเงินได้กรณีทั่วไป",
      "active": true
    },
    {
      "code": "PND91",
      "name": "ภ.ง.ด.91",
      "description": "สำหรับผู้มีเงินได้จากการจ้างแรงงานตามมาตรา 40(1) ประเภทเดียว",
      "active": true
    }
  ]
}
```

Default:

- return active forms only
- preserve a sensible display order
- do not infer new forms

---

# 4. GET /api/v1/tax-years/{year}/forms/{form}

Examples:

```text
GET /api/v1/tax-years/2568/forms/PND90
GET /api/v1/tax-years/2568/forms/PND91
```

Response example:

```json
{
  "success": true,
  "message": null,
  "data": {
    "code": "PND91",
    "name": "ภ.ง.ด.91",
    "description": "สำหรับผู้มีเงินได้จากการจ้างแรงงานตามมาตรา 40(1) ประเภทเดียว",
    "active": true,
    "supported_income_types": [
      {
        "code": "SECTION_40_1",
        "section_code": "40(1)",
        "name": "เงินได้ตามมาตรา 40(1)"
      }
    ]
  }
}
```

For PND90 the response must include all approved income types:

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

Do not implement wizard steps as DB logic in this milestone unless already represented cleanly in existing project configuration.

---

# 5. GET /api/v1/tax-years/{year}/income-types

Optional query parameter:

```text
form=PND90
```

Examples:

```text
GET /api/v1/tax-years/2568/income-types
GET /api/v1/tax-years/2568/income-types?form=PND91
```

Without `form`:

- return all income types available for that tax year / published rule context

With `form`:

- return only income types mapped to that form

Example response:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "code": "SECTION_40_1",
      "section_code": "40(1)",
      "name": "เงินได้ตามมาตรา 40(1)",
      "description": null
    }
  ]
}
```

For `?form=PND91`, only SECTION_40_1 may be returned.

Validate the form belongs to the requested tax year.

---

# 6. GET /api/v1/tax-years/{year}/income-types/{code}

Example:

```text
GET /api/v1/tax-years/2568/income-types/SECTION_40_1
```

Response:

```json
{
  "success": true,
  "message": null,
  "data": {
    "code": "SECTION_40_1",
    "section_code": "40(1)",
    "name": "เงินได้ตามมาตรา 40(1)",
    "description": null,
    "rule": {
      "active": true
    },
    "expense_rule": null
  }
}
```

Important:

Expense rules were not fully approved/seeded in Milestone 02.

Therefore:

- if an approved expense rule exists, expose it
- if no verified expense rule exists, return `null`
- do not invent percentages or limits
- do not hard-code unverified values

---

# 7. GET /api/v1/tax-years/{year}/allowances

Optional query parameter:

```text
category=family
```

Examples:

```text
GET /api/v1/tax-years/2568/allowances
GET /api/v1/tax-years/2568/allowances?category=family
```

Response example:

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "code": "PERSONAL",
      "name": "ค่าลดหย่อนผู้มีเงินได้",
      "category": "personal",
      "description": null,
      "rule": null
    }
  ]
}
```

Rules:

- expose master allowance types even if verified numeric allowance rules do not yet exist
- if an approved rule exists for published version, include it
- if no approved rule exists, `rule` must be `null`
- do not guess numeric values

---

# 8. GET /api/v1/tax-years/{year}/allowances/{code}

Example:

```text
GET /api/v1/tax-years/2568/allowances/PERSONAL
```

Response example:

```json
{
  "success": true,
  "message": null,
  "data": {
    "code": "PERSONAL",
    "name": "ค่าลดหย่อนผู้มีเงินได้",
    "category": "personal",
    "description": null,
    "rule": null
  }
}
```

If a verified rule exists later, structure should support:

```json
{
  "rule": {
    "method": "fixed",
    "fixed_amount": "60000.00",
    "percentage": null,
    "maximum_amount": null,
    "minimum_amount": null,
    "conditions": null
  }
}
```

Do not cast money to float.

---

# 9. GET /api/v1/tax-years/{year}/tax-brackets

Use the published rule version only.

Response:

```json
{
  "success": true,
  "message": null,
  "data": {
    "tax_year": 2568,
    "rule_version": "2568.1",
    "brackets": [
      {
        "from": "0.00",
        "to": "150000.00",
        "rate": "0.000",
        "sort_order": 1
      },
      {
        "from": "150000.00",
        "to": "300000.00",
        "rate": "5.000",
        "sort_order": 2
      },
      {
        "from": "5000000.00",
        "to": null,
        "rate": "35.000",
        "sort_order": 8
      }
    ]
  }
}
```

Requirements:

- order by sort_order
- expose all 8 seeded brackets
- use only published rule version
- do not calculate tax in this endpoint
- preserve decimal precision

---

# 10. POST /api/v1/tax/forms/recommend

Purpose:

Help users determine whether the simulation should use PND90 or PND91 based on income types.

This is a small form-selection rule, NOT the full tax calculation engine.

Request:

```json
{
  "tax_year": 2568,
  "income_types": [
    "SECTION_40_1"
  ]
}
```

Expected result:

```json
{
  "success": true,
  "message": null,
  "data": {
    "recommended_form": {
      "code": "PND91",
      "name": "ภ.ง.ด.91"
    },
    "reason_code": "ONLY_SECTION_40_1",
    "reason": "มีเงินได้ตามมาตรา 40(1) ประเภทเดียว"
  }
}
```

If request contains SECTION_40_1 plus another income type, or only a non-40(1) income type, recommend PND90.

---

# Recommendation Validation

Validate:

```text
tax_year required
tax_year integer
income_types required
income_types array
income_types minimum 1 item
income_types items unique
income type codes must exist
income types must be supported for that tax year
```

Do not accept unknown codes.

---

# Recommendation Service

Do not put recommendation logic directly in the Controller.

Create a small dedicated service, for example:

```text
TaxFormRecommendationService
```

Suggested responsibility:

```text
input:
- tax year
- selected income types

output:
- recommended form
- reason code
- reason text
```

This is permitted in M3 because it is metadata/form selection logic, not tax amount calculation.

---

# Resource Classes

Use API Resources.

Suggested resources:

```text
TaxYearResource
TaxFormResource
IncomeTypeResource
AllowanceTypeResource
TaxBracketResource
```

Avoid leaking internal timestamps, internal IDs, pivot data, or Laravel relationship internals unless required.

---

# Controllers

Suggested:

```text
Api/V1/TaxYearController
Api/V1/TaxFormController
Api/V1/IncomeTypeController
Api/V1/AllowanceController
Api/V1/TaxBracketController
Api/V1/TaxFormRecommendationController
```

Do not make one giant TaxController.

---

# Shared Metadata Logic

Shared logic such as:

```text
find active tax year
find current published rule version
validate form belongs to year
```

should be centralized where useful, for example:

```text
TaxMetadataService
PublishedTaxRuleResolver
```

Do not over-engineer with unnecessary repository abstractions.

---

# Public Stable Identifiers

The public API uses:

```text
year
form code
income type code
allowance code
```

Do not require DB IDs for public metadata URLs.

---

# Published Rule Resolver

Create a clear mechanism to resolve the published rule version for a tax year.

Rules:

- use published version only
- do not use draft automatically
- if multiple published versions exist unexpectedly, fail clearly rather than selecting arbitrarily

---

# API Documentation

Update README or create:

```text
docs/api/MILESTONE_03_API.md
```

Document:

- endpoints
- request examples
- response examples
- validation errors
- HTTP status codes

---

# Tests

Add Feature Tests for all required endpoints.

At minimum implement:

## Tax Years

```text
GET /api/v1/tax-years -> 200
contains 2568
inactive year is not returned by default
```

## Tax Year Detail

```text
GET /api/v1/tax-years/2568 -> 200
GET /api/v1/tax-years/9999 -> 404
published version = 2568.1
```

## Forms

```text
GET /api/v1/tax-years/2568/forms -> PND90 and PND91
GET /api/v1/tax-years/2568/forms/PND91 -> 200
unknown form -> 404
```

## Income Types

```text
GET /api/v1/tax-years/2568/income-types -> 8 types
?form=PND91 -> exactly SECTION_40_1
?form=PND90 -> 8 types
invalid form -> validation/not-found response
```

## Allowances

```text
GET /api/v1/tax-years/2568/allowances -> 200
GET /api/v1/tax-years/2568/allowances/PERSONAL -> 200
unknown allowance -> 404
category filter works
```

## Tax Brackets

```text
GET /api/v1/tax-years/2568/tax-brackets -> 200
returns version 2568.1
returns exactly 8 brackets
brackets ordered by sort_order
last bracket has to = null
```

## Form Recommendation

Test:

```text
[SECTION_40_1] -> PND91
[SECTION_40_1, SECTION_40_8] -> PND90
[SECTION_40_8] -> PND90
unknown income type -> 422
empty income_types -> 422
missing tax_year -> 422
unknown tax year -> appropriate error
```

---

# Regression Tests

All M1 and M2 tests must continue to pass.

Run the full suite, not only new tests.

---

# Security / Privacy

These endpoints are public metadata endpoints.

They must not expose member data, tax return data, emails, internal tokens, secrets, or sensitive personal information.

---

# Performance

Avoid N+1 queries.

Metadata volume is small, so favor clarity over premature caching.

Do not introduce Redis/caching infrastructure in this milestone.

---

# No Tax Amount Calculation Yet

Do NOT implement:

```text
POST /api/v1/tax/calculate
POST /api/v1/tax/plan
ProgressiveTaxCalculator
ExpenseCalculator
AllowanceCalculator
DonationCalculator
TaxRefundService
TaxRecommendationService for financial recommendations
```

Those belong to later milestones.

`TaxFormRecommendationService` is allowed because it only chooses PND90/PND91 based on supported income types.

---

# No UI Redesign

Do not redesign the approved UI.

No need to build simulator screens yet.

---

# Commands

Inspect first:

```bash
git status
docker compose ps
```

Run tests:

```bash
docker compose exec app php artisan test
```

Inspect routes:

```bash
docker compose exec app php artisan route:list --path=api/v1
```

If database fixtures require a fresh verification, you may run:

```bash
docker compose exec app php artisan migrate:fresh --seed
```

but report that it is destructive to local development data.

---

# Manual API Verification

Verify at least:

```text
GET http://localhost:8088/api/v1/tax-years
GET http://localhost:8088/api/v1/tax-years/2568/forms
GET http://localhost:8088/api/v1/tax-years/2568/income-types?form=PND91
GET http://localhost:8088/api/v1/tax-years/2568/tax-brackets
POST http://localhost:8088/api/v1/tax/forms/recommend
```

Important:

The current working project uses port `8088` according to the M1/M2 environment shown by the user.

Do not silently change it back to 8080.

Inspect existing Docker configuration and preserve the working port.

---

# Completion Criteria

Milestone 03 is complete only when:

```text
[ ] all required metadata routes exist
[ ] all endpoints use /api/v1
[ ] tax years endpoint works
[ ] tax forms endpoints work
[ ] income types endpoints work
[ ] allowance endpoints work
[ ] tax brackets endpoint returns 8 approved brackets
[ ] form recommendation endpoint works
[ ] public API uses stable identifiers
[ ] published rule resolver does not fall back to draft
[ ] API resources prevent DB leakage
[ ] validation errors are consistent
[ ] 404 responses are consistent
[ ] no full tax calculation logic was added
[ ] M1/M2 regression tests still pass
[ ] all M3 tests pass
```

---

# Required Final Report

At the end, report exactly these sections:

## Summary

## Routes Added

List all new endpoints with HTTP methods.

## Controllers Created

## Services Created

## API Resources Created

## Validation Added

## Documentation

## Commands Executed

Only commands actually run.

## Test Results

Report:

```text
total tests
passed
failed
skipped
assertions if available
```

## Manual API Verification

Report status for:

```text
/tax-years
/tax-years/2568/forms
/tax-years/2568/income-types?form=PND91
/tax-years/2568/tax-brackets
/tax/forms/recommend
```

## Example Responses

Include concise actual response examples from the running API.

## Known Issues

List unresolved issues or:

```text
None
```

## Architecture Compliance

Confirm:

```text
No tax amount calculation engine was implemented.
No member CRUD milestone was started.
No approved UI redesign was performed.
Web and Mobile can consume the same metadata API.
```

## Next Step

Recommend:

```text
Milestone 04 — PND91 Tax Calculation Engine
```

Do not begin Milestone 04 until approved.

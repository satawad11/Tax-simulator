# MILESTONE_05_PROMPT.md

## Codex Task: Milestone 05 — Authentication + Member Tax Return CRUD / Draft / History

Before changing any file, read:
- PROJECT_REQUIREMENTS.md
- CODING_RULES.md
- MILESTONE_01_PROMPT.md
- MILESTONE_02_PROMPT.md
- MILESTONE_03_PROMPT.md
- MILESTONE_04_PROMPT.md

Milestones 01–04 are complete.

Do not begin PND90 calculation, Tax Planning, Recommendation, or UI redesign.

## Objective

Implement:
- Laravel Sanctum authentication
- Member Tax Return CRUD
- Draft / Resume
- Nested profile / spouse / dependents / incomes / allowances / donations / withholdings
- Persistent member calculation history
- Complete simulation
- Duplicate tax return
- Ownership authorization

The existing public Guest endpoint:
POST /api/v1/tax/calculate
must remain public and stateless.

Member calculation MUST reuse the existing M4 TaxCalculationService.
Do not duplicate tax formulas.

## Authentication Endpoints

POST /api/v1/auth/register
POST /api/v1/auth/login
POST /api/v1/auth/logout
POST /api/v1/auth/logout-all
GET  /api/v1/auth/me
PUT  /api/v1/auth/me

Use auth:sanctum for protected endpoints.

Registration request:
{
  "name": "สมชาย ใจดี",
  "email": "somchai@example.com",
  "password": "StrongPassword123!",
  "password_confirmation": "StrongPassword123!",
  "device_name": "Chrome on Windows"
}

Login request:
{
  "email": "somchai@example.com",
  "password": "StrongPassword123!",
  "device_name": "iPhone"
}

Do not expose password hashes or token internals.

## Member Tax Return Endpoints

GET    /api/v1/tax-returns
POST   /api/v1/tax-returns
GET    /api/v1/tax-returns/{id}
PATCH  /api/v1/tax-returns/{id}
DELETE /api/v1/tax-returns/{id}

PUT    /api/v1/tax-returns/{id}/profile

PUT    /api/v1/tax-returns/{id}/spouse
DELETE /api/v1/tax-returns/{id}/spouse

POST   /api/v1/tax-returns/{id}/dependents
PATCH  /api/v1/tax-returns/{id}/dependents/{dependentId}
DELETE /api/v1/tax-returns/{id}/dependents/{dependentId}

POST   /api/v1/tax-returns/{id}/incomes
PATCH  /api/v1/tax-returns/{id}/incomes/{incomeId}
DELETE /api/v1/tax-returns/{id}/incomes/{incomeId}

POST   /api/v1/tax-returns/{id}/allowances
PATCH  /api/v1/tax-returns/{id}/allowances/{allowanceId}
DELETE /api/v1/tax-returns/{id}/allowances/{allowanceId}

POST   /api/v1/tax-returns/{id}/donations
PATCH  /api/v1/tax-returns/{id}/donations/{donationId}
DELETE /api/v1/tax-returns/{id}/donations/{donationId}

POST   /api/v1/tax-returns/{id}/withholdings
PATCH  /api/v1/tax-returns/{id}/withholdings/{withholdingId}
DELETE /api/v1/tax-returns/{id}/withholdings/{withholdingId}

POST   /api/v1/tax-returns/{id}/calculate
GET    /api/v1/tax-returns/{id}/calculations
GET    /api/v1/tax-returns/{id}/calculations/{calculationId}

POST   /api/v1/tax-returns/{id}/complete
POST   /api/v1/tax-returns/{id}/duplicate

Do not implement scenario persistence yet.

## Ownership

A member may access only their own tax returns and nested resources.

Use Policy or equivalent authorization, at minimum TaxReturnPolicy.

Must prevent:
- User A reading User B return
- User A updating User B return
- User A deleting User B return
- User A calculating User B return
- User A reading User B calculation history
- User A mutating nested resources of User B

Use one consistent 403 or scoped 404 strategy and document it.

## Tax Return Lifecycle

Statuses:
draft
completed
archived

Create = draft.

Completed means completed simulation only, never official filing.

Use wording:
"Simulation completed"
"เสร็จสิ้นการทดลอง"

Never say official filing/submission.

## Create Tax Return

Request:
{
  "tax_year": 2568,
  "form_code": "PND91",
  "name": "ทดลองภาษีของฉัน"
}

Server resolves:
user_id
tax_year_id
tax_form_id
published rule_version_id

Do not accept internal IDs from client.

PND90 draft creation may be allowed, but calculation must remain unsupported.
If restricted to PND91 only in M5, document that choice.

## Tax Return List

Support filters:
status
tax_year
form
q
page
per_page

Default sort:
updated_at desc

Default per_page 20
Max per_page 100

Return only authenticated user's data.

## Tax Return Detail

Include:
profile
spouse
dependents
incomes
allowances
donations
withholdings
latest_calculation

Avoid N+1 queries.

## Tax Return Update

Allow safe metadata only:
name
current_step

Do not allow direct changes to:
user_id
tax_year_id
tax_form_id
rule_version_id

Do not set completed via generic PATCH.

## Delete

Use SoftDeletes.
Return 204.

## Profile

PUT /tax-returns/{id}/profile

Example:
{
  "birth_date": "1990-05-20",
  "marital_status": "single",
  "filing_status": null
}

Do not add unnecessary national-ID fields.

## Spouse

PUT /tax-returns/{id}/spouse

Example:
{
  "birth_date": "1992-01-10",
  "has_income": false,
  "filing_status": "combined"
}

Do not implement full spouse-combined tax calculation yet.

DELETE spouse returns 204.

## Dependents

POST /tax-returns/{id}/dependents

Example:
{
  "relation_type": "child",
  "birth_date": "2020-05-12"
}

Do not trust client-provided allowance_amount.

Nested records must belong to the parent return.

## Incomes

For PND91:
{
  "income_type": "SECTION_40_1",
  "description": "เงินเดือนบริษัท A",
  "gross_amount": "720000.00",
  "exempt_amount": "0.00"
}

Do not accept calculated_expense or net_amount as authoritative input.

## Allowances

Example:
{
  "code": "LIFE_INSURANCE",
  "input_amount": "75000.00"
}

Resolve allowance_type_id server-side.
Do not accept eligible_amount as authoritative.

If numeric allowance rule is unverified, preserve M4 warning behavior.

## Donations

Example:
{
  "donation_code": "GENERAL_DONATION",
  "input_amount": "10000.00"
}

Do not accept eligible_amount as authoritative.
Unsupported donation codes must not silently become valid deductions.

## Withholdings

Example:
{
  "type": "withholding",
  "payer_name": "บริษัทตัวอย่าง จำกัด",
  "payer_tax_id": null,
  "amount": "25000.00"
}

Support structural types already recognized by M4:
withholding
foreign_tax_credit
pnd93
other_credit

Do not guess eligibility.

## Persistent Member Calculation

POST /api/v1/tax-returns/{id}/calculate

Must reuse M4 TaxCalculationService.

Flow:
1. authorize return ownership
2. load saved data
3. map persisted entities into existing TaxCalculationData DTO
4. call TaxCalculationService
5. persist a new TaxCalculation snapshot
6. persist TaxCalculationBracket rows
7. return calculation response

Do not overwrite old calculation snapshots.

Each calculation action creates a new history row.

## Guest / Member Consistency

For identical data:
POST /api/v1/tax/calculate
and
POST /api/v1/tax-returns/{id}/calculate

must produce the same tax outcome under the same rule version.

Add regression test.

## Calculation History

GET /tax-returns/{id}/calculations
- newest first
- paginated

GET /tax-returns/{id}/calculations/{calculationId}
- return stored snapshot
- return bracket breakdown
- return trace
- do not recalculate historical result

## Complete Simulation

POST /tax-returns/{id}/complete

Preferred behavior:
1. authorize
2. validate current saved data
3. run TaxCalculationService again
4. persist final snapshot
5. mark status = completed
6. set completed_at
7. commit in transaction

Do not say "tax return filed".

## Completed Return Policy

Completed return core input should become read-only.

Reject writes to:
profile
spouse
dependents
incomes
allowances
donations
withholdings

Recommended:
409 Conflict

If user wants to modify a completed return:
duplicate it to a new draft.

Document the policy.

## Duplicate

POST /tax-returns/{id}/duplicate

Request:
{
  "name": "Scenario B - สำเนาจากแบบเดิม"
}

Use transaction.

Copy:
tax return metadata
profile
spouse
dependents
incomes
allowances
donations
withholdings

Do NOT copy:
calculation history
completed_at
completed status

New record:
status = draft
completed_at = null

Preserve:
tax_year
tax_form
rule_version

## Services

Suggested:
TaxReturnService
TaxReturnDuplicationService
MemberTaxCalculationService

MemberTaxCalculationService adapts persisted data to M4 TaxCalculationService.
It must not contain duplicate tax formulas.

## FormRequests

Create focused requests, e.g.:
RegisterRequest
LoginRequest
UpdateProfileRequest
StoreTaxReturnRequest
UpdateTaxReturnRequest
UpdateTaxReturnProfileRequest
UpdateTaxReturnSpouseRequest
StoreTaxReturnDependentRequest
UpdateTaxReturnDependentRequest
StoreTaxReturnIncomeRequest
UpdateTaxReturnIncomeRequest
StoreTaxReturnAllowanceRequest
UpdateTaxReturnAllowanceRequest
StoreTaxReturnDonationRequest
UpdateTaxReturnDonationRequest
StoreTaxReturnWithholdingRequest
UpdateTaxReturnWithholdingRequest
DuplicateTaxReturnRequest

## API Resources

Suggested:
UserResource
TaxReturnSummaryResource
TaxReturnResource
TaxReturnProfileResource
TaxReturnIncomeResource
TaxReturnAllowanceResource
TaxReturnDonationResource
TaxReturnWithholdingResource
TaxCalculationSummaryResource
TaxCalculationDetailResource

Do not expose raw models unnecessarily.

## Rule Version Integrity

Existing tax return stays tied to its original:
tax_year
tax_form
rule_version

Do not automatically migrate old returns to a newer published rule version.

## Guest-to-Member Compatibility

Do not build anonymous server-side persistence.

Keep Member endpoints compatible with M4 Guest payload so future Web/Mobile can:
guest payload -> login/register -> save same input

## Security

Do not expose:
password
password hash
remember token
Sanctum internals
other users' financial data

Do not log full financial payloads.

## Tests

Authentication:
- register succeeds
- duplicate email rejected
- login succeeds
- invalid login => 401
- me requires auth
- logout revokes current token
- logout-all revokes all tokens

Ownership:
- User A accesses own return
- User B cannot read/update/delete/calculate User A return
- User B cannot access User A calculation history
- User B cannot mutate User A nested resources

CRUD:
- create
- list own
- filters
- detail
- rename/current_step
- soft delete

Nested:
- profile
- income
- allowance
- withholding
- representative spouse/dependent/donation coverage
- nested ownership

Member calculation:
- saved PND91 calculates
- calculation persists
- bracket rows persist
- second calculation creates second history row
- old history not overwritten
- Guest and Member identical inputs produce same outcome

Complete:
- draft -> completed
- completed_at populated
- final snapshot exists
- completed core input mutation rejected

Duplicate:
- all input entities copied
- history not copied
- completed status not copied
- new copy is draft

Guest regression:
POST /api/v1/tax/calculate still:
- public
- stateless
- creates no TaxReturn
- creates no TaxCalculation history

All M1–M4 tests must remain green.

## API Documentation

Create:
docs/api/MILESTONE_05_API.md

Document:
- auth
- bearer tokens
- member CRUD
- nested resources
- saved calculation
- calculation history
- complete
- duplicate
- ownership behavior
- completed return policy
- Guest vs Member behavior

## Out of Scope

Do NOT implement:
- PND90 calculation engine
- SECTION_40_2 through SECTION_40_8 calculation
- tax scenario persistence
- tax planning
- personalized recommendation engine
- full login/register UI
- member dashboard UI
- admin tax-rule UI
- UI redesign

## Commands

Inspect:
git status
docker compose ps

Run:
docker compose exec app php artisan test

Run project Pint/lint command.
Run frontend build regression if current workflow expects it.

Do not run migrate:fresh against active development data unless clearly necessary and explicitly reported.

## Manual Verification

Use current project host port from Docker config, currently expected around:
http://localhost:8088

Verify with synthetic data:
1. register
2. login
3. me
4. create tax return
5. save SECTION_40_1 income
6. save withholding
7. calculate saved return
8. list calculation history
9. duplicate
10. complete
11. cross-user access denied
12. logout

Do not use real personal data.

## Completion Criteria

M5 complete only when:
- Sanctum auth works
- member CRUD works
- ownership enforced
- nested resources persist
- member calculation reuses M4 TaxCalculationService
- history snapshots persist
- bracket snapshots persist
- old history not overwritten
- complete works
- completed inputs protected
- duplicate creates draft
- calculation history not copied
- Guest calculation remains public/stateless
- Guest/Member calculation results match
- pagination/filtering work
- docs updated
- all M1–M4 tests pass
- no PND90 engine
- no tax-planning engine
- no UI redesign

## Required Final Report

Report exactly:

### Summary
### Authentication Implemented
### Member Routes Added
### Controllers Created
### Services Created
### Policies Created
### FormRequests Created
### API Resources Created
### Persistence Behavior
### Calculation Reuse
### Ownership Strategy
### Completed Return Policy
### Duplicate Behavior
### Commands Executed
### Test Coverage
### Test Results
### Manual Verification
### Known Issues
### Architecture Compliance

Confirm:
- Member calculation reuses M4 TaxCalculationService
- No duplicate tax formulas
- Guest calculation remains public/stateless
- No PND90 calculation engine
- No tax-planning/recommendation engine
- No approved UI redesign
- Web and Mobile can use the same auth/member APIs

### Next Step

Recommend:
Milestone 06 — Tax Planning + Recommendation + Refund Guidance

Do not begin Milestone 06 until approved.

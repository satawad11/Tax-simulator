# Milestone 05 — Authentication and member simulations

## Scope

API base: `http://localhost:8088/api/v1`.
Send `Accept: application/json` and `Content-Type: application/json`.
The API also returns JSON 401 when Accept is absent.

M5 permits creation of **PND91 drafts only**. SECTION_40_1 is the only calculable income.
No PND90 engine, scenarios, planning, recommendations, authentication UI, or UI changes were added.

## Authentication

| Method | Path | Purpose |
|---|---|---|
| POST | /auth/register | Create member and issue device token (201) |
| POST | /auth/login | Check credentials and issue device token (200) |
| GET | /auth/me | Current public user profile (200) |
| PUT | /auth/me | Update name/email (200) |
| POST | /auth/logout | Revoke current token (204) |
| POST | /auth/logout-all | Revoke all current user's tokens (204) |

Register:

```json
{
  "name": "Synthetic Member",
  "email": "member@example.test",
  "password": "SyntheticPassword123!",
  "password_confirmation": "SyntheticPassword123!",
  "device_name": "Chrome on Windows"
}
```

Login uses email, password and device_name. Email is trimmed/lowercased.
Passwords require at least 12 characters with uppercase/lowercase, numbers and symbols;
the maximum is 72 bytes, matching the configured bcrypt boundary. Passwords use Laravel hashing.
Registration/login are throttled to 10 requests/minute by the existing Laravel throttle middleware.

Successful authentication:

```json
{
  "success": true,
  "message": null,
  "data": {
    "user": {"id": 1, "name": "Synthetic Member", "email": "member@example.test"},
    "token": "<one-time-returned-plain-bearer-token>",
    "token_type": "Bearer"
  }
}
```

Use `Authorization: Bearer <token>` for protected endpoints. Only the token hash is stored by
Sanctum. User resources never expose password, remember_token, role control, or token internals.
Treat the returned bearer token as a secret. Logout returns no body.
PUT /auth/me accepts name/email only; changing email clears email_verified_at.
Password changes, email verification flows, cookie/session login and password recovery are not part of M5.
Invalid credentials or absent/revoked bearer tokens return 401.

## Member routes

All routes below require auth:sanctum.

| Method | Path |
|---|---|
| GET, POST | /tax-returns |
| GET, PATCH, DELETE | /tax-returns/{id} |
| PUT | /tax-returns/{id}/profile |
| PUT, DELETE | /tax-returns/{id}/spouse |
| POST | /tax-returns/{id}/dependents |
| PATCH, DELETE | /tax-returns/{id}/dependents/{dependentId} |
| POST | /tax-returns/{id}/incomes |
| PATCH, DELETE | /tax-returns/{id}/incomes/{incomeId} |
| POST | /tax-returns/{id}/allowances |
| PATCH, DELETE | /tax-returns/{id}/allowances/{allowanceId} |
| POST | /tax-returns/{id}/donations |
| PATCH, DELETE | /tax-returns/{id}/donations/{donationId} |
| POST | /tax-returns/{id}/withholdings |
| PATCH, DELETE | /tax-returns/{id}/withholdings/{withholdingId} |
| POST | /tax-returns/{id}/calculate |
| GET | /tax-returns/{id}/calculations |
| GET | /tax-returns/{id}/calculations/{calculationId} |
| POST | /tax-returns/{id}/complete |
| POST | /tax-returns/{id}/duplicate |

Creation and duplicate return 201. Nested POST returns 201. PUT/PATCH, calculate and complete return
200. DELETE returns 204. Standard success envelope: success, message, data.
List resources additionally include Laravel pagination links and meta.

## Draft creation, resume, and list

Create:

```json
{"tax_year":2568,"form_code":"PND91","name":"Synthetic simulation"}
```

Server resolves owner, active year/form and the sole published rule version. The client cannot set
internal IDs, status, calculated values, or a different rule version. Missing/ambiguous published
configuration fails closed (409); unknown/inactive year fails validation (422); unavailable form
lookup returns 404.

Drafts persist immediately. PATCH accepts only name and current_step (integer 1–65535).
GET detail loads profile, spouse, dependents, incomes, allowances, donations, withholdings and
latest_calculation with eager loading. GET is the resume operation.

List filters: status (draft/completed/archived), tax_year, form (PND90/PND91), q (name substring),
page, per_page. Default per_page=20, maximum=100; over-limit values return 422.
Sort: updated_at DESC, id DESC. Only the current user's non-deleted rows appear.
The archived status is recognized for existing records; no archive transition is exposed in M5.
DELETE soft-deletes the return and makes its nested resources/history inaccessible through this API.

## Nested input contracts

Profile PUT replaces the profile's fields; omitted nullable fields clear to null:

```json
{"birth_date":"1990-05-20","marital_status":"single","filing_status":null}
```

Spouse PUT replaces fields (has_income required):

```json
{"birth_date":"1992-01-10","has_income":false,"filing_status":"combined"}
```

Dates must be real YYYY-MM-DD dates no later than today. Marital status:
single, married, divorced, widowed. filing_status is nullable descriptive metadata (max 50 characters);
saving it does not enable spouse-combined calculations.

Dependent POST:

```json
{"relation_type":"child","birth_date":"2020-05-12"}
```

Allowed relations: child, father, mother, spouse_father, spouse_mother, disabled_person.
Client eligibility and allowance_amount are rejected. Profile, spouse and dependent data are saved
for draft/resume; no unverified family eligibility or combined-spouse tax formulas are applied.

Income POST:

```json
{"income_type":"SECTION_40_1","description":"Synthetic salary","gross_amount":"720000.00","exempt_amount":"0.00"}
```

Exempt amount defaults to zero and cannot exceed gross, including partial PATCH updates against
the existing row. The server resolves income_type_id and checks the form mapping.

Allowance POST:

```json
{"code":"LIFE_INSURANCE","input_amount":"75000.00"}
```

Resolve the active master code server-side. One row per allowance code is permitted by the M5 API
for consistency with M4 input; existing database schema remains without a new type uniqueness
constraint. Writes serialize under a parent lock. Unverified allowances remain zero eligible
with M4 warnings when calculated.

Donation POST:

```json
{"donation_code":"GENERAL_DONATION","input_amount":"10000.00"}
```

This illustrates the shape, **not an enabled production code**. The code must exist and be active
under the saved rule version. Currently no production donation rules are seeded; unsupported codes
return 422. Structure-only test fixtures are not production rules.

Withholding POST:

```json
{"type":"withholding","payer_name":"Synthetic payer","payer_tax_id":null,"amount":"25000.00"}
```

Types: withholding, foreign_tax_credit, pnd93, other_credit. M4 warning/eligibility behavior remains.
Only withholding is currently granted. payer_tax_id is optional and is not required for simulation.
No national-ID fields were added.

PATCH accepts only the same safe fields as POST, with required-on-create fields optional when absent.
Each list has at most 100 rows. Unknown/server-calculated fields are rejected rather than trusted.
Nested IDs are always looked up through their parent relationship.
Money input uses M4 MoneyInput: decimal strings (13 integer digits, up to 2 fractional digits) or
integer JSON numbers. Quote fractional values. Negative values, binary-float JSON input and excess
precision are rejected.

## Saved calculation and history

POST /tax-returns/{id}/calculate accepts an empty body and reads saved inputs. The response is:

```json
{
  "success":true,
  "message":null,
  "data":{
    "id":1,
    "calculated_at":"2026-09-12T00:00:00+00:00",
    "calculation":{"rule_version":"2568.1","result":{"status":"PAYABLE","amount":"20500.00"}},
    "brackets":[],
    "trace":[]
  }
}
```

The example abbreviates calculation/brackets/trace: calculation is the **full M4 response data**,
including income, expenses, allowances, donations, net income, progressive tax, credits, result,
analysis, 16-step trace, warnings and disclaimer. Brackets contains all eight stored breakdown rows.
The synthetic salary 720000 and withholding 25000 produce PAYABLE 20500.

MemberTaxCalculationService validates saved input, maps it to TaxCalculationData, then calls the
existing TaxCalculationService. The engine's optional server-supplied historical version is never
accepted from an HTTP client. It is resolved from the owned return. Published/retired original
versions remain tied to the same tax year/form; a later published version does not replace them.
No duplicate tax formulas exist in member services.

Every successful calculation appends one TaxCalculation and eight TaxCalculationBracket snapshots.
Old history is never overwritten or recalculated when read. List history is newest id first with
page/per_page (20 default, 100 maximum). GET history detail returns stored result, bracket snapshots
and trace. IDs must belong to the authorized parent.

Guest and member results are identical for identical supported calculation input and rule version.
Descriptive saved profile/spouse/dependent/payer fields do not invent new tax eligibility mechanics.

## Exact snapshot storage and migration

Migration: 2026_09_12_011351_preserve_exact_member_calculation_snapshots.php.

Adds nullable result_snapshot JSON to tax_calculations and tax_calculation_brackets.
Existing money columns retain DECIMAL(15,2); calculated monetary projections become nullable.
The authoritative JSON stores exact decimal **strings**, preserving M4 fractional-satang amounts
and aggregate totals exceeding a single DECIMAL(15,2) column's range.

If a value fits exactly in DECIMAL(15,2), the typed column is populated. Otherwise it is null;
no rounded approximation is substituted. API history reads exact snapshots rather than projections.
For example tax "0.0005" remains "0.0005" in history, while calculated_tax/tax_amount projections are null.
Existing input snapshots, traces, foreign keys and data are preserved.
Legacy rows predating the new snapshot have calculation=null, with their existing trace/bracket
columns still readable; no historical result is fabricated.

Rollback refuses rows whose null projections cannot fit the old schema. Resolve/export such data
before an explicit rollback; it never rounds it silently. Normal migration is nondestructive.
No migrate:fresh was used against development data.

## Complete and duplicate

POST complete accepts an empty body. In one transaction it locks the owned draft, validates input,
calculates again with M4, writes final history/brackets, and marks completed with completed_at.
Failures roll back snapshots and lifecycle changes.

Message: **Simulation completed — เสร็จสิ้นการทดลอง**.
This means completed simulation, never official filing.

Completed/archived core inputs are read-only: profile, spouse, dependents, incomes, allowances,
donations and withholdings reject writes with 409. Repeated calculate/complete also returns 409.
Safe name/current_step metadata may still change; soft deletion and duplication remain available.

Duplicate request:

```json
{"name":"Synthetic comparison copy"}
```

Copies all seven input relations and metadata in a transaction. Preserves owner/year/form/rule
version/current_step, creates new parent/child IDs, sets status=draft and completed_at=null.
No calculation history or scenarios are copied. The source is unchanged.

## Ownership and errors

TaxReturnPolicy plus can:view,taxReturn route middleware enforce owner access before validation.
Service writes repeat authorization after obtaining the parent row lock.
All cross-owner access returns scoped 404, including calculations, history and nested mutations.
Even another owner-accessible parent cannot address a child/history ID from a different return.
There is no administrator bypass to other members' financial information.

401: unauthenticated/invalid credentials.
404: missing, deleted, foreign-owned return or mismatched nested/history parent.
409: read-only lifecycle or invalid rule configuration.
422: validation, unsafe fields, unsupported codes/PND90, incomplete saved data.
429: authentication rate limit.
Unexpected failures use the existing sanitized JSON envelope.
Passwords, tokens and full financial payloads are not explicitly logged by this implementation.

## Verification

Commands:

```sh
docker compose exec app php artisan migrate --no-interaction
docker compose exec app php artisan test
docker compose exec app vendor/bin/phpunit --configuration phpunit.mysql.xml --colors=never
docker compose config --quiet
docker compose exec node npm run build
```

Pint runs on the changed PHP paths because this workspace has no .git directory.
SQLite tests use :memory:; MySQL tests use only the guarded tax_simulator_test database.

Manual synthetic HTTP flow verified register, login, me, draft creation, income/withholding saving,
calculation (PAYABLE 20500.00), history, duplicate (draft), complete, frozen-input 409,
cross-user 404, logout and revoked-token 401. All manually issued tokens were revoked.
Synthetic accounts and draft/completed records remain available in the development database.

## Remaining rule gaps and scope

M4 unverified allowance/donation/foreign-credit/other-credit rules and legal rounding remain pending.
No numeric tax law was added in M5. No full spouse-combined calculation was implemented.
No Milestone 06 work has started.

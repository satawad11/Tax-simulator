# Milestone 03 — Public Tax Metadata API

Base URL: `http://localhost:8088/api/v1`. All endpoints are public, stateless,
and shared by Blade/JavaScript and future mobile clients. No authentication token
or browser session is required. No endpoint stores guest inputs or calculates tax.

## Endpoints

| Method | Path | Result |
| --- | --- | --- |
| GET | /health | Existing application liveness |
| GET | /tax-years | Active years, newest first |
| GET | /tax-years/{year} | Year and its single published version |
| GET | /tax-years/{year}/forms | Active forms, ordered by code |
| GET | /tax-years/{year}/forms/{form} | Form with mapped income types |
| GET | /tax-years/{year}/income-types | Income types mapped to active forms in that year |
| GET | /tax-years/{year}/income-types/{code} | Income detail and optional verified expense rule |
| GET | /tax-years/{year}/allowances | Active allowance masters and optional verified rules |
| GET | /tax-years/{year}/allowances/{code} | Allowance detail |
| GET | /tax-years/{year}/tax-brackets | Published brackets ordered by position |
| POST | /tax/forms/recommend | PND90/PND91 form selection |

Identifiers are the Buddhist-calendar year and stored public codes, never database
IDs. For example: `2568`, `PND91`, `SECTION_40_1`, `PERSONAL`.

Optional filters:

- `GET /tax-years/2568/income-types?form=PND91`: only SECTION_40_1.
- `GET /tax-years/2568/income-types?form=PND90`: all eight approved types.
- `GET /tax-years/2568/allowances?category=personal`: exact stored category.
- `category=family` is supported as an exact filter; the existing seed uses separate
  spouse/child/parent categories, so family currently returns an empty array.

Filters must be nonempty strings when supplied. Unknown form codes, inactive forms,
and forms belonging to another year return 404. Unknown allowance categories return
200 with an empty collection.

## Responses

Successful responses use `success: true`, `message: null`, and `data` (object or
array). Resources explicitly select public fields; IDs, timestamps, pivot data,
source references and member records are not serialized.

Actual examples from the running seeded API (Unicode decoded for readability):

`GET /tax-years`

```json
{"data":[{"year":2568,"name":"ปีภาษี 2568","active":true}],"success":true,"message":null}
```

`GET /tax-years/2568`

```json
{"data":{"year":2568,"name":"ปีภาษี 2568","active":true,"filing_start_date":null,"filing_end_date":null,"rule_version":{"version":"2568.1","status":"published"}},"success":true,"message":null}
```

`GET /tax-years/2568/income-types?form=PND91`

```json
{"data":[{"code":"SECTION_40_1","section_code":"40(1)","name":"เงินได้ตามมาตรา 40(1)","description":null}],"success":true,"message":null}
```

Tax bracket data contains `tax_year`, `rule_version`, and `brackets`. All eight
approved brackets are returned. The first and final bracket objects are:

```json
{"from":"0.00","to":"150000.00","rate":"0.0000","sort_order":1}
{"from":"5000000.00","to":null,"rate":"35.0000","sort_order":8}
```

Rates are percentage points, not fractional rates. Money is a decimal string with
two places; rates retain the existing schema's four decimal places. No float
conversion or rounding is performed. A null upper bound means no upper limit.

## Form recommendation

`POST /tax/forms/recommend`, with `Content-Type: application/json`:

```json
{"tax_year":2568,"income_types":["SECTION_40_1"]}
```

Actual response:

```json
{"data":{"recommended_form":{"code":"PND91","name":"ภ.ง.ด.91"},"reason_code":"ONLY_SECTION_40_1","reason":"มีเงินได้ตามมาตรา 40(1) ประเภทเดียว"},"success":true,"message":null}
```

A mix containing another supported type, or a single non-40(1) type, selects PND90
with reason code `OTHER_INCOME_TYPES`. The service checks that the target form is
active and supports every selected code. It does not infer missing form mappings.

Validation requires an existing active integer tax year and a nonempty list of
distinct string income codes. Codes must exist and be mapped to an active form in
that year. Empty payloads, arrays in scalar fields, unknown codes, duplicates and
unsupported codes return 422. Unknown/inactive POST tax years also return 422.

Validation envelope example for a code that exists but is unsupported that year:

```json
{"success":false,"message":"Validation failed","errors":{"income_types.0":["This income type is not supported for the requested tax year."]}}
```

## Publication and errors

The year list returns active years. Every year-specific endpoint and form
recommendation requires exactly one published version for the requested year.
Draft versions are never a fallback. The resolver does not select the newest row
arbitrarily when more than one published version exists.

| Status | Meaning |
| --- | --- |
| 200 | Successful object/collection, including empty filtered collections |
| 404 | Unknown/inactive year, form, income type or allowance; invalid route |
| 409 | Missing/multiple published versions, ambiguous verified rules, or unavailable recommended form |
| 422 | Request validation failure |
| 405 | Unsupported method |
| 500 | Unexpected server error; no internal exception details |

404:

```json
{"success":false,"message":"Resource not found","errors":null}
```

409 examples:

```json
{"success":false,"message":"No published rule version is available for this tax year.","errors":null}
{"success":false,"message":"Multiple published rule versions exist for this tax year.","errors":null}
```

Only application-defined metadata conflict messages are exposed; unexpected
exceptions retain the generic error message even when APP_DEBUG is enabled.

## Reconciled schema

The corrective migration adds all 74 missing M2 fields while preserving old columns
and their data. See [schema reconciliation](../SCHEMA_RECONCILIATION.md) for the
exact inventory and compatibility differences.

- Resources use stored active flags, filing dates and section_code.
- Bracket min_amount/max_amount/sort_order map to from/to/sort_order.
- There are 19 allowance masters: all 17 approved codes and two retained legacy records.
- Expense and allowance rules must be active, in the published version, and have a
  nonempty source_reference. Absent/unverified numeric rules return null.
- Rule method/fixed_amount/maximum_amount/minimum_amount are now read from storage,
  without inventing defaults. Filing dates remain null until verified dates are supplied.
- Income rule active metadata is read from the published version when present.
- Allowance and income rules are unique per version/type. Multiple verified expense
  rules still return 409 rather than arbitrarily selecting one.
- Money and rates retain two and four decimal places respectively.
- Published 2568.1 and existing form mappings are unchanged.

## Verification

```sh
docker compose config --quiet
docker compose exec app php artisan route:list --path=api/v1
docker compose exec app php artisan test --compact
docker compose exec app vendor/bin/phpunit -c phpunit.mysql.xml
docker compose exec node npm run build
```

The standard suite runs on isolated in-memory SQLite. Two MySQL-specific checks
(schema types and full DECIMAL monetary precision) skip there and run on the
dedicated tax_simulator_test database. Neither suite uses development member data.

M3 tests cover public responses, filters, publication conflicts, cross-year
isolation, null/unverified rules, monetary strings, validation, form recommendation,
and absence of internal fields or guest persistence.

Milestone 04 — PND91 Tax Calculation Engine is not started.

# Milestone 06 — Tax planning, recommendations and refund guidance

> **Superseded figures (M9.1).** The example request on this page declares a `profile`, so it
> now also claims ใบแนบ item 1 — `PERSONAL` 60,000 — and every derived amount shown below moves
> with it: net 560,000, tax 36,500, PAYABLE 11,500. The mechanics, fields, validation and
> warnings described on this page are unchanged. See
> [FAMILY_ALLOWANCE_AUTOMATIC_CLAIM.md](../tax/FAMILY_ALLOWANCE_AUTOMATIC_CLAIM.md).

## Scope

API base: `http://localhost:8088/api/v1`.
Send `Accept: application/json` and `Content-Type: application/json`.

M6 adds rule-based recommendations, refund/payment guidance, Guest tax planning and Member
scenario persistence on top of the M4 calculation engine. It adds **no** tax formula:
`TaxCalculationService` remains the only source of calculation truth, and planning calls it
once for `before` and once for `after`.

PND91 / SECTION_40_1 only. A planning request for `PND90` returns `422`, consistent with M4.

### Simulation disclaimer

> ผลลัพธ์เป็นการประมาณการจากข้อมูลที่กรอกในระบบจำลอง ไม่ใช่การยื่นแบบภาษีจริงหรือการรับรองผลโดยหน่วยงานราชการ

Refund and payment guidance carry their own disclaimer:

> จำนวนเงินเป็นเพียงประมาณการจากข้อมูลในระบบทดลอง ไม่ใช่การรับรองสิทธิหรือการยืนยันการคืนภาษีจริง

### Recommendations are rule-based, never generative

Recommendations are produced by `TaxRecommendationService` from rows in `recommendation_rules`
plus user-entered input and calculation output. No language model decides eligibility, a
deduction amount, a tax rule, a saving, a refund entitlement, or a financial product. No rule
recommends buying anything; rules only ask the user to verify an item or explain the result.

---

## Routes added

| Method | Path | Auth |
|---|---|---|
| POST | /tax/plan | none (Guest) |
| GET | /tax-returns/{id}/recommendations | auth:sanctum + ownership |
| GET | /tax-returns/{id}/scenarios | auth:sanctum + ownership |
| POST | /tax-returns/{id}/scenarios | auth:sanctum + ownership |
| GET | /tax-returns/{id}/scenarios/{scenarioId} | auth:sanctum + ownership |
| PATCH | /tax-returns/{id}/scenarios/{scenarioId} | auth:sanctum + ownership |
| DELETE | /tax-returns/{id}/scenarios/{scenarioId} | auth:sanctum + ownership |
| POST | /tax-returns/{id}/scenarios/{scenarioId}/calculate | auth:sanctum + ownership |

Ownership follows the M5 strategy: a foreign or mismatched id is a scoped **404**, never a leak.
A scenario is always resolved through its route tax return, so `/tax-returns/{other}/scenarios/{id}`
is a 404 even when the caller owns both rows.

---

## Appended calculation fields (backward compatible)

`POST /api/v1/tax/calculate` keeps every M4 field and **appends** three read-only fields:

```text
recommendations
refund_guidance
payment_guidance
```

The same three fields are appended to `data.calculation` on:

```text
POST /api/v1/tax-returns/{id}/calculate
POST /api/v1/tax-returns/{id}/complete
GET  /api/v1/tax-returns/{id}/calculations/{calculationId}
```

They are **derived on read** from the stored snapshot and its stored input snapshot. They are
never written into `tax_calculations`, so M5 history rows are byte-for-byte unchanged and a
history read still performs no calculation. M4 warnings remain visible in `warnings` in every
case; a recommendation never replaces or hides a warning.

### Recommendation object

```json
{
  "code": "CHECK_SOCIAL_SECURITY",
  "type": "POTENTIAL_ALLOWANCE",
  "priority": "medium",
  "title": "ตรวจสอบเงินสมทบประกันสังคม",
  "message": "หากคุณมีการจ่ายเงินสมทบประกันสังคมในปีภาษีนี้ ควรตรวจสอบว่ามีรายการที่ใช้สิทธิได้หรือไม่ จากข้อมูลที่กรอกยังไม่พบรายการนี้ในระบบจำลอง",
  "action": {
    "type": "OPEN_ALLOWANCE",
    "allowance_code": "SOCIAL_SECURITY"
  }
}
```

`type` is one of `MISSING_INFORMATION`, `POTENTIAL_ALLOWANCE`, `TAX_PLANNING`, `PAYMENT`, `REFUND`.
`priority` is `high`, `medium` or `low`.

**Action structure.** `action` is machine-readable so a client never parses Thai prose:

| action.type | Extra fields | Meaning |
|---|---|---|
| OPEN_ALLOWANCE | `allowance_code` | Open that allowance input for the user to verify |
| REVIEW_WITHHOLDING | — | Send the user back to the withholding step |
| REVIEW_REFUND_GUIDANCE | — | Show the refund guidance block |
| REVIEW_ALLOWANCE_SOURCE | — | Ask the user to check the supporting documents |

`action` is `null` when the rule declares no `action_type`. `OPEN_ALLOWANCE` takes its
`allowance_code` from the rule's `allowance_not_present` / `allowance_present` condition, so
one rule cannot disagree with itself about which allowance it means.

**Order and deduplication.** Sorted `high` → `medium` → `low`, then by rule `code` ascending.
The order is fully deterministic. A code is returned at most once.

### Refund guidance (`result.status = REFUND`)

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
      {"code": "VERIFY_WITHHOLDING", "label": "ตรวจสอบยอดภาษีหัก ณ ที่จ่าย"},
      {"code": "VERIFY_SUPPORTING_DOCUMENTS", "label": "ตรวจสอบเอกสารประกอบข้อมูลที่กรอก"},
      {"code": "VERIFY_REFUND_CHANNEL", "label": "ตรวจสอบช่องทางรับเงินคืนสำหรับการยื่นจริง"}
    ],
    "disclaimer": "จำนวนเงินเป็นเพียงประมาณการจากข้อมูลในระบบทดลอง ไม่ใช่การรับรองสิทธิหรือการยืนยันการคืนภาษีจริง"
  },
  "payment_guidance": null
}
```

Every amount comes from the calculation result. `TaxRefundGuidanceService` decides no legal
entitlement: it explains the simulated result and asks the user to verify. `other_credits` is
`credits.total - credits.withholding`; with the current verified rules it is always `0.00`
because non-withholding credits remain unverified (M4 behaviour, unchanged).

### Payment guidance (`result.status = PAYABLE`)

```json
{
  "refund_guidance": null,
  "payment_guidance": {
    "amount": "20500.00",
    "reason_code": "WITHHOLDING_BELOW_CALCULATED_TAX",
    "reason": "ภาษีหัก ณ ที่จ่ายที่กรอกต่ำกว่าภาษีที่คำนวณได้ในระบบจำลอง",
    "components": {"calculated_tax": "45500.00", "withholding": "25000.00", "other_credits": "0.00"},
    "checklist": [
      {"code": "VERIFY_WITHHOLDING", "label": "ตรวจสอบยอดภาษีหัก ณ ที่จ่าย"},
      {"code": "VERIFY_SUPPORTING_DOCUMENTS", "label": "ตรวจสอบเอกสารประกอบข้อมูลที่กรอก"}
    ],
    "disclaimer": "…"
  }
}
```

No real government payment channel or payment instruction is provided in this milestone.

### `result.status = ZERO`

Both `refund_guidance` and `payment_guidance` are `null`. A zero balance never produces a refund
claim, and no `PAYMENT`/`REFUND` recommendation fires.

---

## Seeded recommendation rules (published version 2568.1)

| code | type | priority | conditions | action_type |
|---|---|---|---|---|
| CHECK_SOCIAL_SECURITY | POTENTIAL_ALLOWANCE | medium | `income_types_only: SECTION_40_1`, `allowance_not_present: SOCIAL_SECURITY` | OPEN_ALLOWANCE |
| PAYABLE_DUE_TO_LOW_WITHHOLDING | PAYMENT | high | `result_status: PAYABLE`, `withholding_less_than_tax: true` | REVIEW_WITHHOLDING |
| REFUND_DUE_TO_EXCESS_WITHHOLDING | REFUND | medium | `result_status: REFUND` | REVIEW_REFUND_GUIDANCE |
| VERIFY_UNVERIFIED_ALLOWANCE_RULE | MISSING_INFORMATION | high | `warning_present: UNVERIFIED_ALLOWANCE_RULE` | REVIEW_ALLOWANCE_SOURCE |

`CHECK_SOCIAL_SECURITY` is a reminder to verify, not a grant of eligibility.
`VERIFY_UNVERIFIED_ALLOWANCE_RULE` never turns an unverified allowance into a deduction.

Seeding is idempotent (`RecommendationRuleSeeder`): an existing row that differs from the
approved values aborts the seed rather than overwriting a published rule version.

### Supported condition vocabulary

```text
income_types_contains      income_types_only
allowance_present          allowance_not_present
result_status              warning_present
gross_income_greater_than  net_income_greater_than
withholding_less_than_tax  withholding_greater_than_tax
```

Conditions on a rule are combined with AND. There is no general-purpose expression evaluator.

A rule carrying a condition outside this vocabulary — or an unusable `type`, `priority`, `title`
or `message_template` — is **skipped safely**: it is omitted from the response, a sanitized
warning is logged (`rule_code`, `field`, `reason` only — never user financial data), and the
request still succeeds.

### Message placeholders

`message_template` may contain `{{gross_income}}`, `{{net_income}}`, `{{calculated_tax}}`,
`{{withholding}}`, `{{result_amount}}`, `{{result_status}}`. Any other placeholder is left
literal and logged as unsupported.

---

## POST /api/v1/tax/plan (Guest)

No authentication. Stateless: it creates no `tax_returns`, `tax_calculations`,
`tax_calculation_brackets` or `tax_scenarios` row.

Request:

```json
{
  "tax_year": 2568,
  "form_code": "PND91",
  "base": {
    "profile": {"birth_date": "1990-05-20", "marital_status": "single"},
    "incomes": [{"income_type": "SECTION_40_1", "gross_amount": "720000.00", "exempt_amount": "0.00"}],
    "allowances": [],
    "donations": [],
    "withholdings": [{"type": "withholding", "amount": "25000.00"}]
  },
  "scenario": {
    "allowances": {
      "upsert": [{"code": "SOCIAL_SECURITY", "input_amount": "9000.00"}],
      "remove": []
    }
  }
}
```

`base` uses the M4 `POST /tax/calculate` contract verbatim, one level deeper; its validation
rules are reused, not duplicated. `scenario` must be present; `{}` is a valid "change nothing"
comparison.

Response:

```json
{
  "success": true,
  "message": null,
  "data": {
    "tax_year": 2568,
    "form_code": "PND91",
    "rule_version": "2568.1",
    "before": {"net_income": "620000.00", "calculated_tax": "45500.00", "result": {"status": "PAYABLE", "amount": "20500.00"}},
    "after":  {"net_income": "620000.00", "calculated_tax": "45500.00", "result": {"status": "PAYABLE", "amount": "20500.00"}},
    "difference": {"net_income": "0.00", "calculated_tax": "0.00", "result_amount": "0.00"},
    "estimated_tax_saving": "0.00",
    "warnings": [],
    "recommendations": [],
    "disclaimer": "…"
  }
}
```

`recommendations` describe the **after** scenario — the situation the user is considering.

`warnings` are the after-scenario warnings, followed by any before-scenario warning the scenario
removed. M4 warning codes (`UNVERIFIED_ALLOWANCE_RULE`, `UNVERIFIED_DONATION_RULE`,
`UNVERIFIED_FOREIGN_TAX_CREDIT_LIMIT`, `ROUNDING_RULE_PENDING`) are never suppressed.

---

## Scenario merge behaviour

The same strategy is used by Guest planning (`scenario`) and Member scenarios (`payload`), and
therefore by Web and Mobile.

Only three collections can be changed: **allowances**, **donations**, **withholdings**.
Incomes, the profile, the tax year, the form and the rule version cannot be changed by a
scenario — change those by editing the return itself.

Each collection is keyed: allowances and donations by `code`, withholdings by `type`. A scenario
declares the change explicitly:

```json
{
  "allowances": {"upsert": [{"code": "SOCIAL_SECURITY", "input_amount": "9000.00"}], "remove": []},
  "donations": {"upsert": [], "remove": []},
  "withholdings": {"upsert": [{"type": "withholding", "amount": "50000.00"}], "remove": []}
}
```

Applied per collection, in this order:

1. every base entry whose key appears in `remove` is dropped, including duplicates;
2. the first base entry whose key appears in `upsert` is **replaced in place**; further base
   entries sharing that key are dropped (so two base withholdings of the same type collapse into
   the single upserted amount);
3. `upsert` keys absent from the base are appended in the order given.

Nothing is inferred. A key listed in both `upsert` and `remove` is a `422`, never a silent
resolution. Duplicate keys inside one `upsert` list are a `422`. Omitting a collection leaves it
untouched.

An upsert item accepts **exactly one** of `amount` or `input_amount` (allowances/donations);
supplying both or neither is a `422`. Withholding upserts use `amount`.

A scenario can only change a deduction that has a **verified active rule**. With the current
verified data set no numeric allowance or donation rule exists, so an allowance scenario changes
`total_input` only, produces `eligible_amount = 0.00`, returns `UNVERIFIED_ALLOWANCE_RULE` and
yields `estimated_tax_saving = 0.00`. No fake saving is ever produced.

---

## Tax saving definition

```text
estimated_tax_saving = max(0, before.calculated_tax - after.calculated_tax)
```

where `calculated_tax` is the progressive tax on net income (tax **liability**), not the final
balance. A scenario that only moves withholding therefore reports `estimated_tax_saving = 0.00`
even when the balance flips from PAYABLE to REFUND — that is payment timing, not a saving.

The raw, signed differences are reported separately and may be negative:

```text
difference.net_income     = after.net_income     - before.net_income
difference.calculated_tax = after.calculated_tax - before.calculated_tax
difference.result_amount  = signed(after.result) - signed(before.result)
```

`signed(result)` is `+amount` for PAYABLE, `-amount` for REFUND, `0` for ZERO, so the direction
of the final balance is preserved.

---

## GET /api/v1/tax-returns/{id}/recommendations (Member)

**Read-only. It creates no calculation history.** The saved return is recalculated in memory
under its own `rule_version` so the advice matches the saved data, then discarded. No
`tax_calculations`, `tax_calculation_brackets` or `tax_scenarios` row is written, and the source
return is not modified. Available for `draft` and `completed` returns alike.

```json
{
  "success": true,
  "message": null,
  "data": [ { "code": "…", "type": "…", "priority": "…", "title": "…", "message": "…", "action": {} } ],
  "meta": {
    "tax_return_id": 1,
    "rule_version": "2568.1",
    "result": {"status": "PAYABLE", "amount": "20500.00"},
    "refund_guidance": null,
    "payment_guidance": { },
    "warnings": [],
    "persisted": false
  }
}
```

If the saved return cannot be calculated (for example it has no income row), the endpoint
returns `422` with the same validation errors as `POST /tax-returns/{id}/calculate`.

---

## Member scenario endpoints

### POST /tax-returns/{id}/scenarios → 201

```json
{
  "name": "ทดลองเพิ่มค่าลดหย่อน",
  "payload": {
    "allowances": {"upsert": [{"code": "SOCIAL_SECURITY", "input_amount": "9000.00"}], "remove": []}
  }
}
```

`name` and `payload` are the only accepted fields. Server-controlled values —
`calculation_result`, `estimated_tax_saving`, `eligible_amount`, `rule_version_id`,
`user_id`, `tax_year_id` — are rejected with `422`. The server computes all of them.

The new scenario stores `calculation_result: null` until it is calculated. `user_id`,
`tax_year_id` and `rule_version_id` are copied from the source return and are enforced at the
model level: they can never diverge from the source.

Available for a `draft` or `completed` source return. Any other status returns `409`. Creating a
scenario never duplicates and never modifies the source return.

### GET /tax-returns/{id}/scenarios

Newest updated first (`updated_at desc`, then `id desc`), paginated (`page`, `per_page`,
default 20, max 100). Summary rows are read straight from the stored `calculation_result`;
**the list never recalculates**:

```json
{
  "id": 1,
  "name": "ทดลองเพิ่มค่าลดหย่อน",
  "updated_at": "2026-09-12T08:00:00+00:00",
  "estimated_tax_saving": "0.00",
  "result": {"status": "PAYABLE", "amount": "20500.00"},
  "calculated_at": "2026-09-12T08:00:00+00:00"
}
```

`estimated_tax_saving` and `result` are `null` while the scenario has not been calculated.

### GET /tax-returns/{id}/scenarios/{scenarioId}

Adds `source_tax_return_id`, `tax_year`, `rule_version`, `payload`, the full stored
`calculation_result` and `created_at`.

### PATCH /tax-returns/{id}/scenarios/{scenarioId}

Accepts `name` and/or `payload`. **Changing `payload` invalidates the stored result**:
`calculation_result` is set to `null` (and the stored `before_tax`, `after_tax`,
`estimated_tax_saving`, `calculated_at` are cleared) until the scenario is recalculated.
Changing only `name` keeps the stored comparison.

### DELETE /tax-returns/{id}/scenarios/{scenarioId} → 204

Deletes the scenario only. The source tax return and its calculation history are untouched.

### POST /tax-returns/{id}/scenarios/{scenarioId}/calculate

Flow: authorize → load and validate the saved return's input → merge the scenario payload →
`TaxCalculationService` for `before` → `TaxCalculationService` for `after` → compare → generate
recommendations → persist to `tax_scenarios.calculation_result` inside a transaction → return
the scenario.

Returns the scenario detail, whose `calculation_result` is exactly the Guest planning payload
(`tax_year`, `form_code`, `rule_version`, `before`, `after`, `difference`,
`estimated_tax_saving`, `warnings`, `recommendations`, `disclaimer`).

---

## Rule version behaviour

| Context | Rule version used |
|---|---|
| Guest `POST /tax/plan` | the currently **published** version for the requested tax year (M4 behaviour) |
| Member scenario / recommendations | the **source tax return's** `rule_version`, always |

A member scenario is never silently upgraded to a newer published version: publishing `2568.2`
leaves an existing `2568.1` scenario on `2568.1`. Both `before` and `after` are always computed
under one and the same version, so a comparison can never mix rule versions.

## Guest / Member consistency

For identical base input and an identical scenario under the same rule version, Guest
`POST /tax/plan` and Member scenario calculate return the same `before`, `after`, `difference`
and `estimated_tax_saving`. This is covered by a regression test.

## Persistence behaviour

```text
Guest planning        = stateless, nothing persisted
Guest calculation     = stateless (M4, unchanged)
Member scenario       = persisted in tax_scenarios.calculation_result only
Member recommendations= read-only, nothing persisted
TaxReturn source      = never mutated by a scenario or a recommendation read
TaxCalculation history= never written by a scenario or a recommendation read (M5, unchanged)
```

Guest planning payloads are never logged. Recommendation logging is limited to rule metadata.

## Money strategy

Unchanged from M4/M5: `App\ValueObjects\Money` over `Brick\Math\BigDecimal`, no binary floats,
serialized as decimal strings such as `"20500.00"`. Planning differences, tax saving and
guidance components all use the same representation. The persisted `tax_scenarios.before_tax`,
`after_tax` and `estimated_tax_saving` columns are exact `DECIMAL(15,2)` projections and are
written as `NULL` when a value cannot be represented exactly at that scale — the
`calculation_result` JSON stays authoritative, exactly as M5 does for `tax_calculations`.

## Known rule gaps

`ROUNDING_RULE_PENDING` and the M4 unverified-rule warnings still apply. No numeric allowance,
donation or foreign-tax-credit rule is verified, so no recommendation or scenario in this
milestone computes a tax benefit for one.

# Milestone 07 — PND90 calculation engine

> **Superseded figures (M9.1).** The example request on this page declares a `profile`, so it
> now also claims ใบแนบ item 1 — `PERSONAL` 60,000 — and every derived amount shown below moves
> with it: net 560,000, tax 36,500, PAYABLE 11,500. The mechanics, fields, validation and
> warnings described on this page are unchanged. See
> [FAMILY_ALLOWANCE_AUTOMATIC_CLAIM.md](../tax/FAMILY_ALLOWANCE_AUTOMATIC_CLAIM.md).

> **Milestone 7.x is closed.** This file is the API history of the whole 7.x series, newest
> section last. For the finished contract read
> [TAX_ENGINE_BASELINE_2568.md](../tax/TAX_ENGINE_BASELINE_2568.md) and the M7.5 section at the
> bottom of this file; the sections between are historical records of each step.
>
> Two earlier banners on this file are **withdrawn**. The first said only SECTION_40_1 had a
> verified expense rule: all eight Section 40 categories and all thirteen subtypes are verified
> as of M7.4. The second said the filing-instruction PDFs were the blank forms renamed and that
> M7.3 added no verified numeric rule: the genuine booklets are in the repository and M7.3,
> M7.4 and M7.5 were executed against them.


## Scope

API base: `http://localhost:8088/api/v1`.
Send `Accept: application/json` and `Content-Type: application/json`.

M7 extends the existing engine to PND90 with income types SECTION_40_1–SECTION_40_8. It adds
**no** new endpoint and **no** new tax formula: `POST /api/v1/tax/calculate` now accepts
`form_code: "PND90"`, `TaxCalculationService` is still the only calculation path, and
`ProgressiveTaxCalculator` is unchanged and unforked.

Which income types a form accepts comes from the stored `tax_form_income_types` mapping, not
from code. PND91 therefore still accepts SECTION_40_1 only.

**Read `docs/tax/PND90_RULE_MATRIX.md` first.** *(At M7, exactly one income type had a verified
expense rule — SECTION_40_1 — and the other seven were rejected rather than guessed. M7.1
through M7.4 verified the rest; all eight categories and all thirteen subtypes are SUPPORTED as
of the closed baseline. The rejection machinery described below is unchanged and still guards
anything that is not.)*

---

## PND90 request

```json
{
  "tax_year": 2568,
  "form_code": "PND90",
  "profile": {"birth_date": "1990-05-20", "marital_status": "single"},
  "incomes": [
    {"income_type": "SECTION_40_1", "description": "Synthetic salary", "gross_amount": "600000.00", "exempt_amount": "0.00"},
    {"income_type": "SECTION_40_8", "gross_amount": "200000.00", "exempt_amount": "0.00"}
  ],
  "allowances": [],
  "donations": [],
  "withholdings": [{"type": "withholding", "amount": "25000.00"}]
}
```

Validation, in addition to every M4 rule:

| Check | Failure |
|---|---|
| `form_code` is `PND90` or `PND91` | 422 on `form_code` |
| the form is active for the tax year and has mapped income types | 422 on `form_code` |
| each `income_type` is mapped to the selected form | 422 on `incomes.{i}.income_type` |
| each income type carrying positive post-exemption income has a verified expense rule | 422 on `incomes.{i}.income_type` |
| `actual_expense` only where the resolved rule's method is `actual` | 422 on `incomes.{i}.actual_expense` |
| declared `actual_expense` per type ≤ that type's income after exemption | 422 on `incomes.{i}.actual_expense` |
| stored rule data is inconsistent (inactive, unsourced, duplicated, unsupported mechanics) | 409 |

`exempt_amount ≤ gross_amount`, non-negative money and the unknown-field rejection are
unchanged from M4.

### `actual_expense` (new, optional)

```json
{"income_type": "SECTION_40_5", "gross_amount": "100000.00", "actual_expense": "30000.00"}
```

Accepted only when the resolved rule's `method` is `actual`. **No production rule uses that
method today**, so supplying `actual_expense` currently always returns 422 with
*"Actual expense is not supported for this income type."* The field exists so that seeding an
`actual` rule later needs no API change. Values are summed per income type, then capped at that
type's income after exemption.

---

## Unverified expense rules: the chosen strategy

**An income type with positive income and no verified expense rule is rejected with 422.** This
is the milestone's preferred option, taken over "calculate with expense = 0 and warn", because
a zero expense would silently overstate the user's tax and read as a real result.

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "incomes.1.income_type": [
      "Expense rule for this income type has not been verified; simulation is not available for it yet."
    ]
  }
}
```

The single exception: an income row of an unverified type whose amount is **zero** cannot
mislead anyone, so it is accepted, contributes nothing, and returns a warning instead:

```json
{"code": "UNVERIFIED_EXPENSE_RULE",
 "message": "No verified expense rule exists for this income type; no expense was deducted.",
 "path": "expenses.items.1"}
```

The distinction between 422 (no rule established) and 409 (a stored rule that cannot be
trusted) is described in the rule matrix.

---

## Response

Every M4/M5/M6 field keeps its name and meaning. PND90 adds fields; nothing was renamed or
removed, so an existing PND91 client keeps working untouched.

### `income` — now grouped by type

```json
{
  "income": {
    "items": [
      {
        "income_type": "SECTION_40_1",
        "gross_income": "600000.00",
        "exempt_income": "0.00",
        "gross_after_exemption": "600000.00",
        "actual_expense": null,
        "lines": [
          {"index": 0, "description": "Synthetic salary", "gross_amount": "600000.00",
           "exempt_amount": "0.00", "actual_expense": null}
        ]
      }
    ],
    "gross_income": "800000.00",
    "exempt_income": "0.00",
    "gross_after_exemption": "800000.00"
  }
}
```

`items` is new. The three totals are the pre-existing M4 fields, unchanged. Groups appear in
first-appearance order, and `lines` preserves the submitted breakdown so a UI can show both the
per-type aggregate and the rows behind it.

### `expenses` — one item per income type

```json
{
  "expenses": {
    "items": [
      {
        "code": "PND91_SECTION_40_1_EXPENSE",
        "income_type": "SECTION_40_1",
        "basis": "600000.00",
        "gross_after_exemption": "600000.00",
        "method": "percentage_limit",
        "percentage": "50.0000",
        "maximum_amount": "100000.00",
        "fixed_amount": null,
        "input_actual_expense": null,
        "eligible_amount": "100000.00",
        "rule_status": "VERIFIED"
      }
    ],
    "total": "100000.00"
  }
}
```

`code`, `basis`, `percentage`, `maximum_amount` and `eligible_amount` are the M4 fields.
`income_type`, `gross_after_exemption`, `method`, `fixed_amount`, `input_actual_expense` and
`rule_status` are new. `rule_status` is `VERIFIED` or `UNVERIFIED`; `basis` and
`gross_after_exemption` carry the same value, the former kept for M4 compatibility.

The rule code still reads `PND91_SECTION_40_1_EXPENSE` because it is the published 2568.1 row
approved in M4. It is a **Section 40(1)** rule and is used by both forms; it was deliberately
not renamed, since editing a published rule row is exactly what the versioning policy forbids.

### Everything downstream is unchanged

Expenses are summed across types, then `income_after_expense`, allowances, donations,
`net_income`, `progressive_tax`, `credits`, `result`, `analysis`, the 16-step `trace`,
`warnings`, `disclaimer`, and the M6 `recommendations` / `refund_guidance` /
`payment_guidance` all behave exactly as before.

**Progressive tax is calculated once, on the combined net income of every income type.** Tax is
never computed per income type and summed.

---

## PND90 ≡ PND91 for SECTION_40_1

A PND90 request whose only income is SECTION_40_1 produces byte-identical `income`, `expenses`,
`income_after_expense`, `net_income`, `progressive_tax`, `credits`, `result`, `analysis`,
`trace` and `warnings` to the equivalent PND91 request. Only `form_code` differs. There is a
regression test asserting exactly this, and the shared Section 40(1) rule is loaded from the
database once for both forms — the formula is not duplicated anywhere.

---

## Warnings

| Code | When |
|---|---|
| `UNVERIFIED_EXPENSE_RULE` | a zero-valued income row of a type with no verified expense rule |
| `UNVERIFIED_ALLOWANCE_RULE` | unchanged from M4 |
| `UNVERIFIED_DONATION_RULE` | unchanged from M4 |
| `UNVERIFIED_FOREIGN_TAX_CREDIT_LIMIT`, `UNVERIFIED_TAX_CREDIT_RULE` | unchanged from M4 |
| `ROUNDING_RULE_PENDING` | unchanged; still returned on every calculation |

Expense warnings are listed first, matching the calculation order. No M4/M6 warning is
suppressed. `PARTIAL_EXPENSE_RULE` and `ACTUAL_EXPENSE_NOT_SUPPORTED` are **not** emitted as
warnings: under the chosen strategy both situations are validation errors instead, so they
cannot be mistaken for a usable result.

---

## Member PND90 workflow

| Step | Behaviour |
|---|---|
| `POST /tax-returns` with `form_code: "PND90"` | Creates a PND90 draft (previously 422) |
| `POST /tax-returns/{id}/incomes` | Accepts any income type **mapped to that return's form**; a PND91 return still refuses SECTION_40_2–40_8 |
| Saving an unverified income type | **Allowed.** Whether a saved type can be calculated is a separate, later gate |
| `POST /tax-returns/{id}/calculate` | 422 while any saved income type with positive income lacks a verified expense rule |
| `POST /tax-returns/{id}/complete` | Same 422; the return stays `draft` with `completed_at` null and no history row is written |
| `GET /tax-returns/{id}/calculations` | Unchanged; append-only history, brackets persisted |
| `POST /tax-returns/{id}/duplicate` | Copies form, rule version and every income type; history and completion are not copied |
| `GET /tax-returns/{id}/recommendations`, scenarios | Unchanged M6 behaviour, now also for PND90 |

Saving a type that is not yet calculable is deliberate: when its rule is later verified and
seeded, the existing draft calculates with no user action and no data migration. A regression
test covers that transition.

`tax_return_incomes.actual_expense` is now accepted on write and returned by
`TaxReturnIncomeResource`. It is rejected at write time if it exceeds income after exemption,
and at calculation time if the income type's rule does not permit actual expenses.

---

## Planning

**PND90 planning is supported**, on exactly the same terms as calculation.
`POST /api/v1/tax/plan` accepts `form_code: "PND90"` and applies the identical verified-rule
gate to `base.incomes`, so a base containing an unverified income type returns 422 on
`base.incomes.{i}.income_type`.

Enabling it rather than blanket-refusing PND90 planning was deliberate: `PlanTaxRequest` reuses
the calculation contract, the same gate already prevents every unsupported combination, and a
scenario cannot change income lines at all (it may only upsert/remove allowances, donations and
withholdings). There is therefore no way for planning to reach an income-expense combination
that `/tax/calculate` would refuse. PND91 planning is unchanged.

---

## Rule version behaviour

Unchanged. Guest calculation and Guest planning resolve the currently published version for the
tax year; a member return and its scenarios always use the return's own `rule_version`, and are
never silently upgraded when a newer version is published.

**No new rule version was created and no rule row was added, changed or removed by this
milestone.** The engine gained the ability to apply more rule *methods*; the rule *data* is
untouched, so every previously saved return calculates to exactly the same numbers.

---

## Money and rounding

Unchanged from M4–M6: `App\ValueObjects\Money` over `Brick\Math\BigDecimal`, no binary floats,
decimal strings on the wire, fractional satang preserved through aggregation and every expense
method. No legal rounding rule was invented; `ROUNDING_RULE_PENDING` still applies.

---

## Backward compatibility summary

| Change | Impact |
|---|---|
| `form_code` now accepts `PND90` | Additive. `PND91` unchanged |
| `incomes.*.actual_expense` accepted | Additive and optional |
| `income.items` added | Additive |
| `expenses.items[]` gains six fields | Additive; all M4 field names and values preserved |
| `tax-returns` accepts `form_code: "PND90"` | Additive |
| `tax_return_incomes` income type validation | Now driven by the return's form mapping instead of a hardcoded `SECTION_40_1` |
| `TaxReturnIncomeResource` gains `actual_expense` | Additive |

Two M4/M5 test rows asserted that `PND90` is rejected — by `POST /tax/calculate` and by
`POST /tax-returns`. M7 reverses both by design, so each row now uses the unknown form code
`PND92`, preserving the original intent (an unsupported form is a 422) while reflecting the
approved scope change. One M6 planning row did the same and now covers an unverified income
type instead. No assertion about a calculated amount was changed.

---

# Milestone 07.1 addendum — reconciled PND90 expense rules

Milestone 07.1 reconciled SECTION_40_2–SECTION_40_8 against `docs/tax-source/วิธีการกรอกแบบ ภ.ง.ด.90.pdf` and
seeded thirteen new expense rules. See `docs/tax/PND90_RULE_MATRIX.md` for every value and its
page citation, and `docs/tax/PND90_SOURCE_RECONCILIATION.md` for what the source does and does
not support.

The statements above about "one verified income type" are superseded by this addendum.

## Two new optional income fields

```json
{
  "income_type": "SECTION_40_6",
  "income_subtype": "MEDICAL_PRACTICE",
  "expense_method_selection": "percentage",
  "gross_amount": "500000.00",
  "exempt_amount": "0.00"
}
```

### `income_subtype`

ภ.ง.ด.90 prints SECTION_40_3, _5, _6 and _8 as several lines with different expense treatment,
so those types **require** a subtype; the rest must not carry one.

| Income type | Accepted subtypes |
|---|---|
| SECTION_40_1, _2, _4, _7 | none — must be omitted |
| SECTION_40_3 | `ANNUITY_FROM_WILL_OR_JUDGMENT`, `COPYRIGHT_GOODWILL_OTHER_RIGHTS` |
| SECTION_40_5 | `RENT_BUILDING_OR_RAFT`, `RENT_OTHER`*, `HIRE_PURCHASE_BREACH` |
| SECTION_40_6 | `MEDICAL_PRACTICE`, `FINE_ARTS`, `OTHER_LIBERAL_PROFESSION` |
| SECTION_40_8 | `BUSINESS_COMMERCE_OTHER`*, `MUTUAL_FUND_PROFIT_SHARE`, `IMMOVABLE_PROPERTY_INHERITED_OR_GIFTED`, `IMMOVABLE_PROPERTY_NON_TRADE`*, `GIFT_OR_SUPPORT_RECEIVED` |

`*` printed with a blank percentage on the form, so no rule is seeded: positive income under it
returns 422. The subtype is still accepted as a saved value on a member draft.

Errors: missing when required, supplied when not applicable, or unknown → 422 on
`incomes.{i}.income_subtype`.

### `expense_method_selection`

Where the form prints `หักค่าใช้จ่าย ☐ ร้อยละ N ☐ จริง`, the taxpayer elects one. The API makes
that election explicit and never infers it. Values: `percentage` or `actual`.

Required for 40(3) `COPYRIGHT_GOODWILL_OTHER_RIGHTS`, 40(5) `RENT_BUILDING_OR_RAFT`, all three
40(6) subtypes, and 40(7). Rejected for every other category.

| Situation | Response |
|---|---|
| Election missing where required | 422 on `incomes.{i}.expense_method_selection` |
| Election supplied where the form prints no choice | 422 on `incomes.{i}.expense_method_selection` |
| `actual` elected without `actual_expense` | 422 on `incomes.{i}.actual_expense` |
| `actual_expense` above that category's income after exemption | 422 on `incomes.{i}.actual_expense` |
| Two lines of one category electing different methods | 422 on `incomes.{i}.expense_method_selection` |

`actual_expense` is accepted only for 40(3), 40(5), 40(6) and 40(7), matching the form's
`รายการค่าใช้จ่ายจริง … สำหรับเงินได้ตามมาตรา 40 (3) (5) (6) (7) หรือ (8)` schedule; no seeded 40(8)
subcategory prints a `จริง` checkbox.

## Response additions

`expenses.items[]` gains four fields, all additive:

```json
{
  "income_type": null,
  "income_types": ["SECTION_40_1", "SECTION_40_2"],
  "income_subtype": null,
  "expense_group": "SECTION_40_1_2",
  "expense_method_selection": null,
  "basis": "300000.00",
  "eligible_amount": "100000.00",
  "rule_status": "VERIFIED"
}
```

`income_type` still carries the code for a single-category group and is `null` only for a group
spanning several income types, whose members are listed in `income_types`.

`income.items[]` gains `income_subtype` and `expense_method_selection`.

`GET /tax-years/{year}/income-types/{code}` gains `expense_rules`, a per-subcategory readiness
list. `expense_rule` is unchanged and still carries the whole-type rule, or `null` when the type
is split into subcategories. Unverified subcategories expose `status` only — never a fake number.

```json
{
  "expense_rules": [
    {"income_subtype": "RENT_BUILDING_OR_RAFT", "label": "ข้อ 4 ข้อย่อย 1 (1) — บ้าน โรงเรือน …",
     "status": "VERIFIED", "method": "percentage_or_actual", "percentage": "30.0000",
     "maximum_amount": null, "fixed_amount": null, "expense_group": null,
     "actual_expense_supported": true, "expense_method_selection_required": true},
    {"income_subtype": "RENT_OTHER", "label": "ข้อ 4 ข้อย่อย 1 (2)–(4) — อื่นๆ (ระบุ)",
     "status": "UNVERIFIED"}
  ]
}
```

## Shared deduction across 40(1) and 40(2)

ข้อ 1 adds 40(1) and 40(2) together and takes **one** `ร้อยละ 50 แต่ไม่เกิน 100,000` deduction from
the combined total. The cap is never applied per income type. A return with 150,000 of each
deducts 100,000, not 150,000. PND91 maps 40(1) only, so its result is unchanged.

## New warning code

| Code | When |
|---|---|
| `PND90_MINIMUM_TAX_NOT_APPLIED` | PND90 where gross income excluding 40(1) reaches 120,000 |

ภ.ง.ด.90 ข้อ 11 items 9–10 set a minimum tax of 0.5% of gross income before expenses excluding
40(1). Its base is defined by form line numbers this schema does not record, so the rule is
documented rather than guessed — and flagged at runtime, because ignoring it silently could
understate the simulated tax. ภ.ง.ด.91 prints no equivalent line, so PND91 never emits it.

## Member workflow

`income_subtype` and `expense_method_selection` are accepted on
`POST|PATCH /tax-returns/{id}/incomes`, validated against the return's form, persisted, and
returned by `TaxReturnIncomeResource`. Saving a subcategory whose rule is not verified is
allowed; calculation and completion are blocked with 422 naming
`incomes.{i}.income_subtype` until the rule is seeded.

## Rule version

`2568.1` was extended, not replaced. The added rules change no previously calculable outcome:
40(1) mechanics are identical, and 40(2)–40(8) previously returned 422 so no saved return can
contain them. No return was migrated. See the reconciliation document for the full assessment.

---

# Milestone 07.2 addendum — remaining PND90 rules

Milestone 07.2 reconciled the rules M7.1 left open against the same repository sources. See
`docs/tax/PND90_REMAINING_RULES_2568.md` for every rule and its page citation, and
`docs/tax/ALLOWANCE_RULE_MATRIX_2568.md` for the allowance attachment line by line.

## Donations are now calculated

`donation_rules` was empty; two codes are now seeded, mirroring the two lines ข้อ 11 prints.

| Code | Multiplier | Cap | Cap base |
|---|---|---|---|
| `SPECIAL_DONATION` | 2.000 | 10% | income after expenses and allowances (ข้อ 11 item 3) |
| `GENERAL_DONATION` | 1.000 | 10% | that base **less** the special deduction (ข้อ 11 item 5) |

The caps stand on different bases and are applied in the printed order — never as one combined
cap. Which line a donation belongs on remains the user's declaration, exactly as on the paper
form; the project asserts no charity eligibility.

`donations.items[]` gains `multiplier`, `cap_base`, `cap_percentage` and `multiplied_amount`
alongside the existing `code`, `stage`, `input_amount` and `eligible_amount`:

```json
{
  "code": "SPECIAL_DONATION", "stage": "special", "input_amount": "31000.00",
  "multiplier": "2.0000", "cap_base": "620000.00", "cap_percentage": "10.0000",
  "multiplied_amount": "62000.00", "eligible_amount": "62000.00"
}
```

Several entries on one line share that line's cap, allocated in submission order.

## One allowance is now calculated

`PROVIDENT_FUND` deducts `min(declared, 10,000)` — ใบแนบ item 8,
*เงินสะสมกองทุนสำรองเลี้ยงชีพ (ส่วนที่ไม่เกิน 10,000 บาท)*. Every other allowance code still returns
`eligible_amount: "0.00"` with `UNVERIFIED_ALLOWANCE_RULE`, because the attachment prints no
amount for it.

`allowances.items[]` gains `method`, `maximum_amount`, `fixed_amount` and `rule_status`
(`VERIFIED` or `UNVERIFIED`).

## `pnd94` accepted; `pnd93` now reduces the balance

ข้อ 11 item 15 carries three checkboxes — withholding, ภ.ง.ด.93, ภ.ง.ด.94 — on one deduction line
feeding item 16 with no stated limit. All three now reduce the balance in full.

| Credit type | Before M7.2 | After M7.2 |
|---|---|---|
| `withholding` | deducted | deducted |
| `pnd93` | warned, granted `0.00` | **deducted** |
| `pnd94` | rejected by validation | **accepted and deducted** |
| `foreign_tax_credit` | warned, granted `0.00` | unchanged |
| `other_credit` | warned, granted `0.00` | unchanged |

`credits` gains a `pnd94` key. **`pnd93` is a behaviour change** for input that was previously
accepted; the rationale and its impact assessment are in the reconciliation document. Stored
calculation snapshots are immutable, so no historical result moved.

Foreign tax credit stays unverified: ข้อ 11 item 13 says *ไม่เกินภาษีที่ต้องเสียตามกฎหมายประเทศไทย*,
which states a ceiling but not which one, and gives no eligibility condition.

## `tax_treatment` — the ข้อ 9 separate-rate election (new, optional)

ภ.ง.ด.90 prints the same มาตรา 42 (26)(27)(28) gift income on two mutually exclusive routes:

- **ข้อ 7 item 4** — *โดยเลือกนำมารวมคำนวณภาษีกับเงินได้อื่น ๆ*, joining the progressive base;
- **ข้อ 9** — *โดยเลือกเสียภาษีในอัตราร้อยละ 5*, taxed apart and added at ข้อ 11 item 19.

The choice is the taxpayer's, so the API makes it explicit and never infers it:

```json
{
  "income_type": "SECTION_40_8",
  "income_subtype": "GIFT_OR_SUPPORT_RECEIVED",
  "gross_amount": "400000.00",
  "tax_treatment": "SEPARATE_RATE"
}
```

Values: `PROGRESSIVE` (default) or `SEPARATE_RATE`. Accepted only for 40(8)
`GIFT_OR_SUPPORT_RECEIVED` — the only category ข้อ 9 prints.

| Situation | Response |
|---|---|
| `SEPARATE_RATE` on any other category | 422 on `incomes.{i}.tax_treatment` |
| An unknown value | 422 on `incomes.{i}.tax_treatment` |
| Every income line elects the separate rate | 422 on `incomes` — ข้อ 11 needs a progressive base |

Elected income is excluded from `income` totals, takes no expense deduction (ข้อ 9 prints none),
and its tax is added **after** the credits, per item 19. The response gains:

```json
{
  "separate_tax": {
    "items": [{"income_type": "SECTION_40_8", "income_subtype": "GIFT_OR_SUPPORT_RECEIVED",
               "description": null, "base": "400000.00", "rate": "5", "tax": "20000.00"}],
    "base": "400000.00", "tax": "20000.00", "rate": "5"
  },
  "tax_components": {"progressive_tax": "45500.00", "separate_tax": "20000.00", "total": "65500.00"}
}
```

`result.components` gains `separate_tax`. The trace gains `SEPARATE_TAX_BASE` and `SEPARATE_TAX`
before `RESULT` **only when income was elected**, so a return without an election keeps its
sixteen steps and existing clients see no change.

`tax_return_incomes.tax_treatment` stores it for members;
`POST|PATCH /tax-returns/{id}/incomes` accepts it and `TaxReturnIncomeResource` returns it.

## Minimum tax remains unimplemented

ข้อ 11 items 9–10 print the rate (0.5%), the 120,000 threshold, the exclusion of 40(1), the
comparison against item 8 and the 5,000 exception — but what `ข้อ 1 ถึง ข้อ 7 1. ถึง 4.` scopes is
ambiguous, so the rule is not applied. `PND90_MINIMUM_TAX_NOT_APPLIED` is returned whenever
PND90 gross excluding 40(1) reaches 120,000. PND91 never emits it.

## Planning and recommendations

Unchanged in mechanism. Because planning reuses `TaxCalculationService`, a scenario that adds a
`PROVIDENT_FUND` allowance or a donation now produces a **real** `estimated_tax_saving` with no
planning-side formula — covered by regression tests. Recommendation wording is unchanged and
still asks the user to verify rather than asserting eligibility.

## Backward compatibility

| Change | Impact |
|---|---|
| `incomes.*.tax_treatment` accepted | Additive and optional |
| `separate_tax`, `tax_components` added | Additive |
| `donations.items[]` gains four fields | Additive; existing fields unchanged |
| `allowances.items[]` gains four fields | Additive; existing fields unchanged |
| `credits.pnd94` added | Additive |
| `result.components.separate_tax` added | Additive |
| Trace gains two steps | Only when an election is present |
| `pnd93` now reduces the balance | **Behaviour change**, documented above |

A PND91 request with no donation, no allowance and no election returns exactly what it returned
in M7.1, which is asserted by regression tests.

## Milestone 07.3 - family allowances and the PND90 minimum tax

> An earlier draft of this section reported M7.3 as source-blocked. That was written against
> files that turned out to be the blank forms renamed. The genuine filing-instruction booklets
> are in the repository and M7.3 was executed against them; the description below is the
> delivered behaviour.

Request additions, all optional and additive: `spouse` (`birth_date`, `has_income`) and
`dependents[]` (`relation_type`, `child_type`, `birth_order`, `birth_date`, `eligible`) on
`POST /tax/calculate`, and the same three keys inside `base` on `POST /tax/plan`. `profile`
was already accepted. Saved dependent rows gain `child_type` and `birth_order`.

ใบแนบ items 1-5 are server-derived. A declared PERSONAL/SPOUSE/CHILD/PARENT/DISABLED_PERSON
entry still decides whether the line is claimed, but its amount is ignored and replaced by the
amount derived from the declared family facts; a differing amount raises
FAMILY_ALLOWANCE_DERIVED. Such items report `rule_status: DERIVED` with a `basis` string.
Client `eligible_amount` is still rejected with 422.

Response additions: a `minimum_tax` block (`applicable`, `base`, `tax`, `payable`, `method`)
and `tax_components.minimum_tax` / `tax_components.tax_before_credits`. `progressive_tax` is
unchanged and still reports method 1 alone. Three trace steps - MINIMUM_TAX_BASE, MINIMUM_TAX,
TAX_PAYABLE - appear only when the second method applies.

PND90_MINIMUM_TAX_NOT_APPLIED is withdrawn; PND90_MINIMUM_TAX_APPLIED is emitted when the 0.5%
method is the one paid. ROUNDING_RULE_PENDING remains. 2568.1 was extended, with no automatic
return upgrades and no snapshot rewrites. Milestone 08 was not started.

---

## Milestone 07.4 - allowance cap engine, percentage bases, PND90 expense subcategories

All additive. No route, field or warning code was removed or renamed.

### Request

Two optional fields on an income line, on `POST /tax/calculate`, inside `base` on
`POST /tax/plan`, and on a saved member income row:

| Field | Type | Required where |
| --- | --- | --- |
| `expense_activity` | string, max 50 | `SECTION_40_8` / `BUSINESS_COMMERCE_OTHER` only - one of the 44 rows of ตารางที่ 2 |
| `holding_years` | integer, 1-200 | `SECTION_40_8` / `IMMOVABLE_PROPERTY_NON_TRADE` only |

Each is rejected with 422 on any other category, and its absence on the category that needs it
is also a 422. `SECTION_40_5`'s `RENT_OTHER` subtype is retired and now returns 422; the four
asset classes that replace it are `RENT_LAND_AGRICULTURAL`, `RENT_LAND_NON_AGRICULTURAL`,
`RENT_VEHICLE` and `RENT_OTHER_PROPERTY`.

Two new allowance codes are accepted: `EASY_E_RECEIPT_OTOP` and `THAI_ESGX_SWITCH`.

### Response

Allowance items gain `percentage`, `percentage_base`, `base_amount`, `calculated_before_cap`,
`combined_cap_group` and `allocated_amount`; every item now carries the full key set, so a
client never has to test for a key's presence. `allowances.combined_cap_groups[]` is new:
`code`, `name`, `member_codes`, `pre_cap_total`, `maximum_amount`, `eligible_total`,
`source_reference`.

Expense items gain `expense_activity`, `holding_years` and `tiers[]` (`tier_code`,
`sort_order`, `threshold_amount`, `percentage`, `taxable_slice`, `eligible_amount`); `tiers` is
an empty array for every non-tiered rule.

Metadata: `allowances/{code}.rule` gains `percentage_base`. A subcategory whose rate is keyed
by a further fact reports `keyed_by` (`expense_activity` or `holding_years`) and `keys` in
`income-types/{code}.expense_rules[]`, instead of a single method and percentage that would be
a fiction across 44 rules.

### New warning

`COMBINED_ALLOWANCE_CAP_APPLIED` - a group of allowance codes exceeded the ceiling the filing
instructions state across them, so the group total was reduced. Emitted only when the ceiling
actually binds.

### Rule version

2568.1 extended. M7.4 adds rules for combinations that previously had none and changes no
existing rule's method, rate or ceiling, so no saved return changes meaning and no snapshot is
rewritten. Milestone 08 was not started.

---

## Milestone 07.5 - baseline closure and runtime guards

Audit and closure milestone. No route was added, removed or redesigned, and no response field
was removed or renamed. One behavioural change, applied deliberately.

### Silent zeroing became a refusal

Before M7.5, declaring an allowance or a credit the baseline cannot calculate returned 200 with
a zero deduction and a warning. The closure audit classified that as the one shape of silence
that can mislead: the taxpayer asked for a deduction they had reason to expect, and the response
carried a lower total without saying no. A **positive** amount on such a line is now a 422.

| Path | HTTP | Stable code in the message |
| --- | --- | --- |
| `allowances[].code = PENSION_INSURANCE` | 422 on `allowances.*.code` | `PENSION_INSURANCE_RULE_PARTIAL` |
| `allowances[].code = SOCIAL_SECURITY` | 422 on `allowances.*.code` | `SOCIAL_SECURITY_RULE_UNSUPPORTED` |
| `allowances[].code` in `INSURANCE`, `ANNUAL_TAX_MEASURES`, `OTHER` | 422 on `allowances.*.code` | `ALLOWANCE_RULE_UNSUPPORTED` |
| `withholdings[].type = foreign_tax_credit` | 422 on `withholdings.*.type` | `FOREIGN_TAX_CREDIT_UNSUPPORTED` |
| `withholdings[].type = other_credit` | 422 on `withholdings.*.type` | `TAX_CREDIT_TYPE_UNSUPPORTED` |

A **zero** amount on any of them is still accepted and behaves exactly as before: it deducts
nothing and carries the existing `UNVERIFIED_ALLOWANCE_RULE`,
`UNVERIFIED_FOREIGN_TAX_CREDIT_LIMIT` or `UNVERIFIED_TAX_CREDIT_RULE` warning. Nothing that was
being calculated stopped being calculated; only paths that were returning zero now refuse.

The guard lives in `TaxCalculationInputGuard`, so Guest `/tax/calculate`, planning `/tax/plan`
(under the `base.` prefix) and Member calculate/complete all refuse identically.

### Everything else is unchanged

Supported allowances, expenses, donations, credits, the minimum tax, family derivation, the
trace, `ROUNDING_RULE_PENDING`, Guest/Member parity and completed-return immutability all behave
exactly as they did after M7.4. The PND91 no-family baseline is still net 620,000 / tax 45,500.

One recommendation's wording was corrected: `CHECK_SOCIAL_SECURITY` no longer implies the
simulator will apply that line, since the API now refuses it.

Rule version 2568.1 is **frozen**. See
[TAX_ENGINE_BASELINE_2568.md](../tax/TAX_ENGINE_BASELINE_2568.md) and
[SOURCE_COVERAGE_2568.md](../tax/SOURCE_COVERAGE_2568.md). Milestone 08 was not started.

# PND90 production baseline — tax year 2568

Milestone 07.5, closure audit. Rule version **2568.1**.

Sources: `docs/tax-source/PND90-2568-form.pdf` (5 pages, ใบแนบ on p.5) and
`docs/tax-source/PND90-2568-filing-instructions.pdf` (18 pages), plus
`docs/tax-source/tax-rates-2568.jpg`.

Every line carries exactly one final status: **SUPPORTED**, **PARTIAL_BLOCKED**,
**UNSUPPORTED** or **NOT_APPLICABLE**.

---

## 1. Income categories

| Box | Section | Status | Expense treatment | Source |
| --- | --- | --- | --- | --- |
| ข้อ 1 | `SECTION_40_1` | SUPPORTED | 50% capped 100,000, shared group `SECTION_40_1_2` | form p.2 ข้อ 1 item 5 |
| ข้อ 1 | `SECTION_40_2` | SUPPORTED | same group, deducted once across both | form p.2 ข้อ 1 item 5 |
| ข้อ 2 | `SECTION_40_3` | SUPPORTED (both subtypes) | annuity 0.00; copyright 50% capped 100,000 with `จริง` | form p.2 ข้อ 2 |
| ข้อ 3 | `SECTION_40_4` | SUPPORTED | fixed 0.00 — the form prints no expense line | booklet p.3 — *"เงินได้อื่นตามมาตรา 40 (4) ทั้งหมด ไม่ให้หักค่าใช้จ่าย"* |
| ข้อ 4 | `SECTION_40_5` | SUPPORTED (all six subtypes) | rent 30/20/15/30/10 with `จริง`; hire-purchase breach 20% only | booklet p.3 ข้อ 4 |
| ข้อ 5 | `SECTION_40_6` | SUPPORTED (all three subtypes) | 60 / 60 / 30 with `จริง` | form p.3 ข้อ 5 |
| ข้อ 6 | `SECTION_40_7` | SUPPORTED | 60% with `จริง` | booklet p.3 ข้อ 6 |
| ข้อ 7 | `SECTION_40_8` | SUPPORTED (all five subtypes) | see below | booklet p.3, p.17 |

### ข้อ 7 subtypes

| Subtype | Status | Treatment | Key |
| --- | --- | --- | --- |
| `BUSINESS_COMMERCE_OTHER` | SUPPORTED | one rule per ตารางที่ 2 activity — 42 at 60%, row (1) banded 60/40 capped 600,000, row (44) actual only | `expense_activity`, required |
| `MUTUAL_FUND_PROFIT_SHARE` | SUPPORTED | fixed 0.00 | — |
| `IMMOVABLE_PROPERTY_INHERITED_OR_GIFTED` | SUPPORTED | 50%, no `จริง` | — |
| `IMMOVABLE_PROPERTY_NON_TRADE` | SUPPORTED | 92/84/77/71/65/60/55/50 by band, with `จริง` | `holding_years`, required |
| `GIFT_OR_SUPPORT_RECEIVED` | SUPPORTED | fixed 0.00 | — |

An absent, unknown or misplaced subtype, activity or holding period is a **422**; no key is
ever defaulted and no free-text description selects a rule. Full detail:
[PND90_EXPENSE_SUBTYPE_MATRIX_2568.md](PND90_EXPENSE_SUBTYPE_MATRIX_2568.md).

### Boxes outside the progressive base

| Box | Status | Runtime behaviour |
| --- | --- | --- |
| ข้อ 8 การขายอสังหาริมทรัพย์ที่เลือกเสียภาษีแยก | UNSUPPORTED | no request shape carries it; it never enters the progressive base |
| ข้อ 9 เงินได้จากการให้/การรับ ร้อยละ 5 | SUPPORTED | `tax_treatment` election on a 42(26)(27)(28) gift line only; taxed by `SeparateTaxCalculator`, added at ข้อ 11 item 19; refused on any other category (422) |
| ข้อ 10 เงินได้ที่เลือกเสียภาษีโดยไม่นำมารวม | UNSUPPORTED | not modelled; excluded from every base |

## 2. Minimum tax (วิธีที่ 2)

| Element | Status | Value | Source |
| --- | --- | --- | --- |
| Applicability | SUPPORTED | ภ.ง.ด.90 only | booklet p.6 |
| Base | SUPPORTED | Σ gross assessable income of ข้อ 1–ข้อ 7, less มาตรา 40(1) | p.6, method 2 |
| Excluded | SUPPORTED | 40(1) by name; ข้อ 8, ข้อ 9, ข้อ 10 by falling outside the range | p.6 |
| Threshold | SUPPORTED | base ≥ 120,000 | p.6 |
| Rate | SUPPORTED | × 0.005 | p.6 |
| De-minimis floor | SUPPORTED | result ≤ 5,000 → pay method 1 | p.6 |
| Pay-the-greater | SUPPORTED | `max(progressive, minimum)` | p.6 heading |
| Interaction with expenses / allowances / donations | SUPPORTED | none — the base is gross assessable income, before all three | p.6 |
| Interaction with credits | SUPPORTED | credits apply to the payable figure after the comparison | ข้อ 11 items 15–16 |

Reported as `minimum_tax` with `applicable`, `base`, `tax`, `payable`, `method`; three extra
trace steps only when it applies; `PND90_MINIMUM_TAX_APPLIED` when method 2 is paid.
See [MINIMUM_TAX_RECONCILIATION_2568.md](MINIMUM_TAX_RECONCILIATION_2568.md).

## 3. Allowances

Identical to ภ.ง.ด.91 — ใบแนบ is one attachment shared by both forms. Family items 1–5 are
server-derived; nine further ceilings are seeded; three combined-cap groups apply afterwards.
`PENSION_INSURANCE` and `SOCIAL_SECURITY` are PARTIAL_BLOCKED and refuse a positive amount;
`INSURANCE`, `ANNUAL_TAX_MEASURES` and `OTHER` are UNSUPPORTED and do the same.

Counts and per-code detail: [ALLOWANCE_RULE_MATRIX_2568.md](ALLOWANCE_RULE_MATRIX_2568.md) and
[ALLOWANCE_CAP_MODEL_2568.md](ALLOWANCE_CAP_MODEL_2568.md).

## 4. Donations

| Code | Status | Multiplier | Cap | Cap base | Order |
| --- | --- | --- | --- | --- | --- |
| `SPECIAL_DONATION` | SUPPORTED | 2.0000 | 10% | ข้อ 11 item 3 (income after expenses and allowances) | first |
| `GENERAL_DONATION` | SUPPORTED | 1.0000 | 10% | ข้อ 11 item 5 (after the special deduction) | second |

The two caps stand on different bases and are never merged. Several entries on one line share
that line's cap. An unknown donation code is a 422.

## 5. Credits and prepayments

| Type | Status | Source | Runtime behaviour |
| --- | --- | --- | --- |
| `withholding` | SUPPORTED | ข้อ 11 item 15, no stated limit | reduces the balance in full |
| `pnd93` | SUPPORTED | ข้อ 11 item 15 checkbox | reduces the balance in full |
| `pnd94` | SUPPORTED | ข้อ 11 item 15 checkbox | reduces the balance in full |
| `foreign_tax_credit` | UNSUPPORTED | ข้อ 11 item 13 — limit wording not specific enough to apply | **422** `FOREIGN_TAX_CREDIT_UNSUPPORTED` on a positive amount |
| `other_credit` | UNSUPPORTED | no counterpart on the form | **422** `TAX_CREDIT_TYPE_UNSUPPORTED` on a positive amount |
| Dividend tax credit (ข้อ 3 item 5) | UNSUPPORTED | booklet p.2 carries it to ข้อ 11 item 15, but its gross-up is not modelled | no request shape carries it |

## 6. Result, trace and rounding

| Line | Status | Note |
| --- | --- | --- |
| PAYABLE / REFUND / ZERO | SUPPORTED | all three reachable and tested |
| Calculation trace | SUPPORTED | 16 steps; `SEPARATE_TAX_*` only on a ข้อ 9 election, `MINIMUM_TAX_*` only when วิธีที่ 2 applies |
| Legal final rounding | UNSUPPORTED | exact decimals kept; `ROUNDING_RULE_PENDING` on every response |
| Warnings | SUPPORTED | stable machine-readable codes throughout |

## 7. Entry points

Guest, member and planning all use `TaxCalculationService`. Guest and member results are
asserted identical for the same input and rule version, including the minimum-tax block and the
full trace. Planning persists nothing. A completed return is 409 on edit and its snapshot is
byte-identical afterwards. Cross-user access to another member's calculation is a scoped 404.

## 8. Tests

`tests/Feature/TaxEngineBaselineClosureTest.php` (PND90 sections), `Pnd90CalculationApiTest`,
`Pnd90SourceExpenseRuleTest`, `Pnd90RuleReconciliationTest`, `Pnd90RemainingRulesTest`,
`Pnd90ExpenseSubtypeTest`, `AllowanceCapEngineTest`, `FilingInstructionReconciliationTest`,
`MemberPnd90ApiTest`.

## 9. Disclaimer

The simulator supports the paths listed above and nothing else. It is **not** an official tax
filing system, and a completed simulation is not a verified ภ.ง.ด.90 return.

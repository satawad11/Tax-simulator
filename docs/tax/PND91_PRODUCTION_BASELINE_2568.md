# PND91 production baseline — tax year 2568

Milestone 07.5, closure audit. Rule version **2568.1**.

Sources: `docs/tax-source/PND91-2568-form.pdf` (3 pages),
`docs/tax-source/PND91-2568-filing-instructions.pdf` (15 pages),
`docs/tax-source/PND90-2568-filing-instructions.pdf` (shared ใบแนบ and bracket table),
`docs/tax-source/tax-rates-2568.jpg`.

Every line below carries exactly one final status: **SUPPORTED**, **PARTIAL_BLOCKED**,
**UNSUPPORTED** or **NOT_APPLICABLE**.

---

## Frozen regression figure

```
gross 720,000  →  expense 100,000  →  net 620,000  →  tax 45,500
```

Unchanged since Milestone 04. Asserted by
`TaxEngineBaselineClosureTest::test_pnd91_no_family_baseline_is_unchanged`.

It is deliberately a payload with **no family facts at all** — no profile, no spouse, no
dependants — which is why it carries no ใบแนบ line. Since M9.1 the ใบแนบ family lines are
claimed from the facts the filer declares, so the same income with a declared profile is
net 560,000 / tax 36,500. Both are correct; they are different filers.
See [FAMILY_ALLOWANCE_AUTOMATIC_CLAIM.md](FAMILY_ALLOWANCE_AUTOMATIC_CLAIM.md).

---

## 1. Form and income

| Line | Status | Source | Implementation | Runtime behaviour | Known limitation |
| --- | --- | --- | --- | --- | --- |
| Form applicability | SUPPORTED | ภ.ง.ด.91 form p.1; booklet ข้อ 1 — *"กรณีคู่สมรสต่างฝ่ายต่างมีเงินได้ตามมาตรา 40 (1) … ประเภทเดียวให้ยื่นแบบ ภ.ง.ด.91"* | `tax_form_income_types` mapping | only `SECTION_40_1` is mapped | the form-choice advice itself is M6 guidance, not a calculation |
| `SECTION_40_1` | SUPPORTED | form p.1 ข้อ ก; booklet ข้อ 1 | `IncomeCalculator` | aggregated per category | — |
| `SECTION_40_2`–`40_8` | NOT_APPLICABLE | ภ.ง.ด.91 prints no such line | form mapping | 422 `incomes.*.income_type` | use ภ.ง.ด.90 |
| Income aggregation | SUPPORTED | ข้อ 1 item 4 — คงเหลือ (1. − 2. + 3.) | `IncomeCalculator` | several rows of one category share one expense deduction | — |
| Exempt income (`exempt_amount`) | SUPPORTED as a taxpayer-stated figure | booklet p.2 *เงินได้ที่ได้รับยกเว้น*, ข้อ 1 item 2 | `IncomeCalculator` | subtracted before expenses; must not exceed gross (422) | the individual ceilings inside ข้อ 1 item 2 — PVD above 10,000 capped at 490,000 and 15% of wages, กบข. 500,000, กองทุนสงเคราะห์ครูโรงเรียนเอกชน 500,000 — are **not** enforced. The taxpayer states one total and the simulator does not verify its composition. |
| Fractional satang | SUPPORTED | project rule (CODING_RULES §Money) | `Money` over `BigDecimal` | exact decimals throughout | see rounding, below |

## 2. Expense

| Line | Status | Source | Implementation | Runtime behaviour |
| --- | --- | --- | --- | --- |
| 40(1) expense 50% capped 100,000 | SUPPORTED | form p.1 ข้อ ก item 5 — *หักค่าใช้จ่าย (ร้อยละ 50 แต่ไม่เกิน 100,000 บาท)* | `EmploymentExpenseRuleSeeder` → `PND91_SECTION_40_1_EXPENSE`, method `percentage_limit` | applied once to the aggregate, not per row |
| Actual expense on 40(1) | NOT_APPLICABLE | the form prints no `จริง` checkbox for ข้อ ก | `ExpenseRuleResolver::allowsActualExpense()` | 422 `incomes.*.actual_expense` |

## 3. Allowances (ใบแนบ, shared with ภ.ง.ด.90)

| ใบแนบ item | Code | Status | Amount | Runtime behaviour |
| --- | --- | --- | --- | --- |
| 1 ผู้มีเงินได้ | `PERSONAL` | SUPPORTED | 60,000 derived | claimed once a profile is declared; client amount ignored, `FAMILY_ALLOWANCE_DERIVED` when one was sent |
| 2 คู่สมรส | `SPOUSE` | SUPPORTED | 60,000 iff married and spouse has no income | derived; `SPOUSE_ALLOWANCE_FACTS_MISSING` when facts absent |
| 3 บุตร | `CHILD` | SUPPORTED (amounts, ordering) | 30,000 each; +30,000 for legitimate children from the 2nd born in/after พ.ศ. 2561; adopted capped into a combined three | derived; `CHILD_TYPE_NOT_DECLARED` / `CHILD_BIRTH_ORDER_NOT_DECLARED` / `ADOPTED_CHILD_LIMIT_APPLIED` |
| 4 บิดามารดา | `PARENT` | SUPPORTED (amount, spouse condition) | 30,000 per qualifying parent | derived; `SPOUSE_PARENT_NOT_ELIGIBLE` |
| 5 คนพิการฯ | `DISABLED_PERSON` | SUPPORTED (amount) | 60,000 each | derived; `DISABLED_PERSON_OTHER_LIMIT_UNMODELLED` above one person |
| 6, 7, 8, 9, 10, 11, 14, 15, 17, 18, 19, 21 | see [ALLOWANCE_RULE_MATRIX_2568.md](ALLOWANCE_RULE_MATRIX_2568.md) | SUPPORTED | printed ceilings | individual then combined caps |
| 7.6 เบี้ยประกันชีวิตแบบบำนาญ | `PENSION_INSURANCE` | PARTIAL_BLOCKED | — | **422** `PENSION_INSURANCE_RULE_PARTIAL` on a positive amount |
| 12 ประกันสังคม | `SOCIAL_SECURITY` | PARTIAL_BLOCKED | — | **422** `SOCIAL_SECURITY_RULE_UNSUPPORTED` on a positive amount |
| 13, 16, 20, 22 | — | UNSUPPORTED | — | no master code exists; an unknown code is a 422 |
| umbrella categories | `INSURANCE`, `ANNUAL_TAX_MEASURES`, `OTHER` | UNSUPPORTED | — | **422** `ALLOWANCE_RULE_UNSUPPORTED` on a positive amount; since M9.1 not offered in the simulator, because ใบแนบ prints no line for them |

**Which lines are claimed follows the declared facts.** Since M9.1 a family line is applied
because the filer described the family member, not because a client named the allowance code —
no client ever did, and the deduction was silently lost. Amounts are unchanged: each is still
derived by its strategy from the printed instructions, and a filer who does not qualify still
receives that strategy's zero. See [FAMILY_ALLOWANCE_AUTOMATIC_CLAIM.md](FAMILY_ALLOWANCE_AUTOMATIC_CLAIM.md).

**Eligibility is taxpayer-declared and the simulator says so.** Items 3, 4 and 5 depend on a
dependant's own income, study status, age or disability documentation, none of which any
repository source lets the engine verify. The engine counts declarations; it does not assess
them. See [FAMILY_ALLOWANCE_MODEL_2568.md](FAMILY_ALLOWANCE_MODEL_2568.md).

## 4. Donations, tax, credits and result

| Line | Status | Source | Runtime behaviour |
| --- | --- | --- | --- |
| `SPECIAL_DONATION` | SUPPORTED | ข้อ 11 item 4 — 2× paid, capped at 10% of item 3 | staged first |
| `GENERAL_DONATION` | SUPPORTED | ข้อ 11 item 6 — capped at 10% of item 5 | staged second, on the reduced base |
| Progressive tax | SUPPORTED | ตารางที่ 1, booklet p.17 | eight brackets, one calculation on combined net income |
| Minimum tax (0.5%) | NOT_APPLICABLE | the base excludes มาตรา 40(1), which is all ภ.ง.ด.91 carries | `minimum_tax.base` is always 0.00 and `applicable` false |
| Separate-rate election (ข้อ 9) | NOT_APPLICABLE | ภ.ง.ด.91 prints no ข้อ 9 | 422 `incomes.*.tax_treatment` |
| `withholding` | SUPPORTED | ข้อ 11 item 15 — no stated limit | reduces the balance in full |
| `pnd93` | SUPPORTED | ภ.ง.ด.91 line 15 — *ภาษีเงินได้ชำระไว้ตามแบบ ภ.ง.ด.93* | reduces the balance in full |
| `pnd94` | NOT_APPLICABLE | ภ.ง.ด.91 prints no such line — the string "94" does not occur anywhere in the form | **422** `PND94_NOT_ON_THIS_FORM` on a positive amount |
| `foreign_tax_credit` | UNSUPPORTED | ข้อ 11 item 13 prints no applicable limit | **422** `FOREIGN_TAX_CREDIT_UNSUPPORTED` on a positive amount |
| `other_credit` | UNSUPPORTED | no counterpart on the form | **422** `TAX_CREDIT_TYPE_UNSUPPORTED` on a positive amount |
| PAYABLE / REFUND / ZERO | SUPPORTED | ข้อ 11 items 16–18 | all three reachable and tested |
| Legal final rounding | UNSUPPORTED | no repository source states a unit, direction or stage | exact decimals kept; `ROUNDING_RULE_PENDING` on every response |
| Calculation trace | SUPPORTED | project requirement FR-012 | 16 steps; extra steps only when a printed branch applies |

## 5. Entry points

| Flow | Status | Note |
| --- | --- | --- |
| Guest `POST /tax/calculate` | SUPPORTED | stateless; persists nothing |
| Member saved return | SUPPORTED | same payload shape, same engine; identical result asserted |
| Planning `POST /tax/plan` | SUPPORTED | reuses `TaxCalculationService`; no separate saving formula |
| Recommendations | SUPPORTED as guidance only | no rule grants eligibility or computes a benefit |
| Completed-return immutability | SUPPORTED | 409 on edit; snapshot byte-identical |

## 6. Tests

`tests/Feature/TaxEngineBaselineClosureTest.php` (PND91 sections),
`TaxCalculationApiTest`, `FilingInstructionReconciliationTest`, `TaxRecommendationApiTest`,
`MemberTaxCalculationTest`, `TaxPlanningApiTest`.

## 7. Disclaimer

The simulator supports the paths listed above and nothing else. It is **not** an official tax
filing system, and a completed simulation is not a verified ภ.ง.ด.91 return.

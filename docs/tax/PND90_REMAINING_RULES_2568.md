# PND90 remaining rules — tax year 2568

> **Milestone 7.x is CLOSED.** This file records how a rule was reconciled, milestone by
> milestone, and its earlier sections are historical: a status there may have been superseded
> further down the same file. The authoritative final status of every rule is
> [TAX_ENGINE_BASELINE_2568.md](TAX_ENGINE_BASELINE_2568.md), with per-form detail in
> [PND91_PRODUCTION_BASELINE_2568.md](PND91_PRODUCTION_BASELINE_2568.md) and
> [PND90_PRODUCTION_BASELINE_2568.md](PND90_PRODUCTION_BASELINE_2568.md), and a source map in
> [SOURCE_COVERAGE_2568.md](SOURCE_COVERAGE_2568.md).

> **M7.3 source correction (2026-09-12).** The earlier audit banner on this file was written
> against `docs/tax-source/วิธีการกรอกแบบ ภ.ง.ด.90.pdf` / `…91.pdf`, which were the blank
> return forms renamed — their SHA-256 hashes matched `ภงด.90.pdf` / `ภงด.91.pdf` exactly.
> The genuine filing-instruction booklets are now in the repository:
> `docs/tax-source/PND90-2568-filing-instructions.pdf` (18 pages) and
> `docs/tax-source/PND91-2568-filing-instructions.pdf` (15 pages). Its conclusion that
> "M7.3 adds no verified numeric rule" is therefore **withdrawn**: M7.3 resolves ใบแนบ
> items 1–5, five allowance ceilings and the 0.5% minimum tax from those booklets. See
> [family allowance model](FAMILY_ALLOWANCE_MODEL_2568.md) and
> [minimum-tax reconciliation](MINIMUM_TAX_RECONCILIATION_2568.md).
> Sections below dated to earlier milestones remain historical records of what was known then.


Milestone 07.2 status of the eight blockers Milestone 07.1 left open, reconciled against the
repository sources only: `docs/tax-source/วิธีการกรอกแบบ ภ.ง.ด.90.pdf` (5 pages), `docs/tax-source/วิธีการกรอกแบบ ภ.ง.ด.91.pdf`
(3 pages) and `docs/tax-source/1788851201423.jpg`.

No web research was used. Where the form defers a value, the value stays absent.

## Status at a glance

| # | Rule | Status | Implemented |
|---|---|---|---|
| 1 | Minimum tax (ข้อ 11 items 9–10) | **PARTIAL** | No — warned at runtime |
| 2 | Donations (ข้อ 11 items 4 and 6) | **VERIFIED** | **Yes** |
| 3 | Allowances (ใบแนบ, 23 lines) | 1 VERIFIED / 3 PARTIAL / 13 UNVERIFIED | **Yes**, 1 of 23 |
| 4 | ภ.ง.ด.94 prepayment (ข้อ 11 item 15) | **VERIFIED** | **Yes** |
| 5 | Foreign tax credit (ข้อ 11 item 13) | **PARTIAL** | No — still granted nothing |
| 6 | Separate-tax elections (ข้อ 8, ข้อ 9, ข้อ 3) | ข้อ 9 **VERIFIED**; ข้อ 8 and ข้อ 3 **UNVERIFIED** | **Yes**, ข้อ 9 only |
| 7 | Three blank-percentage subcategories | **UNVERIFIED** | No |
| 8 | Legal rounding | **UNVERIFIED** | No — `ROUNDING_RULE_PENDING` retained |

---

## 1. Minimum tax — PARTIAL, not implemented

**Source:** `วิธีการกรอกแบบ ภ.ง.ด.90.pdf` page 4, ข้อ 11 items 9 and 10.

> 9. ภาษีคำนวณจากเงินได้พึงประเมิน ตั้งแต่ 120,000 บาทขึ้นไป คือร้อยละ 0.5 ของรวมยอดเงินได้ก่อนหักค่าใช้จ่ายตาม
>    **ข้อ 1** ถึง **ข้อ 7** 1. ถึง 4. (ไม่รวมเงินได้ตามมาตรา 40 (1)) = …………… X 0.005 =
> 10. ภาษีเงินได้ที่ต้องชำระ (จำนวนที่มากกว่าระหว่าง 8. กับ 9. เว้นแต่กรณี 9. คำนวณแล้วไม่เกิน 5,000 บาท ให้ชำระภาษีตาม 8.)

| Element | Determined? |
|---|---|
| Rate | **Yes** — 0.5% (`X 0.005`) |
| Threshold | **Yes** — the base must reach 120,000 (`ตั้งแต่ 120,000 บาทขึ้นไป`) |
| Excluded income | **Yes** — มาตรา 40(1) is excluded |
| Basis | **Yes** — gross income *before* expenses |
| Comparison | **Yes** — the larger of item 8 and item 9 |
| Exception | **Yes** — if item 9 ≤ 5,000, item 8 prevails |
| **Base scope** | **No** — `ข้อ 1 ถึง ข้อ 7 1. ถึง 4.` is ambiguous |

**The single unresolved element** is what `1. ถึง 4.` scopes. Two readings both fit the printed
text and give materially different bases:

- **(A)** items 1–4 of *each* of ข้อ 1 through ข้อ 7. This would exclude ข้อ 3 items 5–6
  (เครดิตภาษีเงินปันผล and the อื่นๆ list, which includes digital tokens and fund redemptions).
- **(B)** all of ข้อ 1 through ข้อ 6, plus items 1–4 of ข้อ 7 — ข้อ 7 being the only one of the
  seven whose numbered items run exactly 1 to 4.

The engine records income by Section 40 category, not by form line number, so neither reading
can even be expressed against the stored data without also mapping each income line to a form
item. Guessing between them would change the tax for exactly the taxpayers this rule targets.

**Behaviour chosen:** the milestone directs "DO NOT implement … return a clear domain
error/warning". Rejecting would block virtually every multi-category PND90 simulation, undoing
Milestone 07's value, so the engine calculates and **warns**:

```json
{"code": "PND90_MINIMUM_TAX_NOT_APPLIED",
 "message": "ภ.ง.ด.90 ข้อ 11 (9.)–(10.) กำหนดภาษีขั้นต่ำร้อยละ 0.5 ของเงินได้ก่อนหักค่าใช้จ่าย (ไม่รวมมาตรา 40(1)) ซึ่งระบบยังไม่ได้นำมาคำนวณ ภาษีที่แสดงอาจต่ำกว่าความเป็นจริง",
 "path": "progressive_tax.total"}
```

emitted whenever PND90 gross income excluding 40(1) reaches 120,000. ภ.ง.ด.91 prints no
equivalent line, so PND91 never emits it. No `MinimumTaxCalculator` was created, because
creating a service for a rule that must not run would be dead code.

**To unblock:** a statement of which form items the 0.5% base covers.

---

## 2. Donation rules — VERIFIED and implemented

**Source:** `วิธีการกรอกแบบ ภ.ง.ด.90.pdf` page 4, ข้อ 11 items 3–7. `วิธีการกรอกแบบ ภ.ง.ด.91.pdf` page 2 items 7–11 states the same
rule against its own line numbers.

> 3. คงเหลือ (1. - 2.)
> 4. หัก เงินบริจาค (**2 เท่า**ของจำนวนที่ได้จ่ายไปจริง แต่ไม่เกิน**ร้อยละ 10 ของ 3.**)
> 5. คงเหลือ (3. - 4.)
> 6. หัก เงินบริจาค (ไม่เกิน**ร้อยละ 10 ของ 5.**)
> 7. เงินได้สุทธิ (5. - 6.)

| Code | Stage | Multiplier | Cap | Cap base | Order |
|---|---|---|---|---|---|
| `SPECIAL_DONATION` | ข้อ 11 item 4 | 2.000 | 10% | item 3 — income after expenses and allowances | first |
| `GENERAL_DONATION` | ข้อ 11 item 6 | 1.000 | 10% | item 5 — item 3 less the special deduction | second |

The two caps stand on **different bases** and the order is load-bearing: the general cap is
measured after the special deduction has already reduced the base. The stages are never merged
into one combined cap. `DonationCalculator` follows the printed sequence exactly, and
`Pnd90RemainingRulesTest::test_the_general_cap_is_measured_after_the_special_deduction` proves
a combined cap would give a different answer.

**What is and is not decided here.** The form prints two lines and the taxpayer writes each
donation on one of them. The seeded codes mirror those two lines; deciding *which* line a
particular donation belongs on remains the user's declaration, exactly as on paper. This
project asserts no charity eligibility.

Several entries on one line share that line's cap, allocated in submission order — presentational
only, since the stage total is what reaches the tax base.

---

## 3. Allowance rules — 1 implemented of 23 lines

**Source:** `วิธีการกรอกแบบ ภ.ง.ด.90.pdf` page 5 / `วิธีการกรอกแบบ ภ.ง.ด.91.pdf` page 3 (byte-identical pages).

Full detail in **`docs/tax/ALLOWANCE_RULE_MATRIX_2568.md`**. Summary:

| Status | Count | Codes |
|---|---|---|
| VERIFIED + IMPLEMENTED | 1 | `PROVIDENT_FUND` — `actual`, capped at 10,000 (item 8: *ส่วนที่ไม่เกิน 10,000 บาท*) |
| PARTIAL | 3 | `PERSONAL`, `SPOUSE`, `CHILD` |
| UNVERIFIED | 13 | every line that prints no amount |
| NOT_APPLICABLE | 2 | `INSURANCE`, `OTHER` |

The attachment prints an amount on only four of its twenty-three lines. The three PARTIAL rows
each have a printed amount but a missing discriminator:

- **PERSONAL** — `60,000 บาท หรือ 120,000 บาท **แล้วแต่กรณี**`. The form gives two amounts and never
  says which applies. This is the highest-value blocker in the project: it is the largest
  allowance and it affects every taxpayer.
- **SPOUSE** — `60,000 บาท กรณีมีเงินได้รวมคำนวณภาษีหรือไม่มีเงินได้`. The condition is printed, but
  "income combined for tax calculation" is the combined-filing mode Milestone 05 explicitly
  deferred, and the calculation payload carries no spouse block.
- **CHILD** — `คนละ 30,000 บาท`, and `60,000 บาท` from the second child onward born in or after
  พ.ศ. 2561. Both amounts are printed; the birth-order rule, the eligibility conditions and any
  count limit are not, and the payload carries no dependents block.

There is **no combined cap** anywhere on the attachment — item 24 is a plain `รวม (1. ถึง 23.)`
with no ceiling — so no shared-cap modelling was added and no schema change was needed.

---

## 4. ภ.ง.ด.94 — VERIFIED and implemented

**Source:** `วิธีการกรอกแบบ ภ.ง.ด.90.pdf` page 4, ข้อ 11 item 15.

> 15. หัก ☐ ภาษีเงินได้หัก ณ ที่จ่ายและเครดิตภาษี
>        ☐ ภาษีเงินได้ชำระไว้ตามแบบ **ภ.ง.ด.93**
>        ☐ ภาษีเงินได้ชำระไว้ตามแบบ **ภ.ง.ด.94**
> 16. คงเหลือ ภาษีที่ ☐ ชำระเพิ่มเติม ☐ ชำระไว้เกิน

All three checkboxes sit on one `หัก` line feeding item 16, with no stated limit, so all three
reduce the balance in full. `pnd94` was added to the credit vocabulary and, with `withholding`
and `pnd93`, is deducted in full.

**This changed existing behaviour for `pnd93`,** which previously returned a warning and zero
credit. See the rule-version decision below.

`ภ.ง.ด.93` appears on ภ.ง.ด.91 page 2 item 15 as well; `ภ.ง.ด.94` does not, which is consistent
with ภ.ง.ด.94 being a half-year return for non-40(1) income.

---

## 5. Foreign tax credit — PARTIAL, still grants nothing

**Source:** `วิธีการกรอกแบบ ภ.ง.ด.90.pdf` page 4, ข้อ 11 item 13 (and `วิธีการกรอกแบบ ภ.ง.ด.91.pdf` page 2 item 13).

> 13. หัก เครดิตภาษีเงินได้จากต่างประเทศ (**ไม่เกินภาษีที่ต้องเสียตามกฎหมายประเทศไทย**)

The wording states a ceiling but not which ceiling: "the tax payable under Thai law" could mean
the total Thai tax, or the Thai tax attributable to the foreign income. Nothing in the
repository sources disambiguates it, and no eligibility condition is given at all.

Milestone 07.2 explicitly anticipated this case and directed that such wording keeps
`UNVERIFIED_FOREIGN_TAX_CREDIT_LIMIT`. Behaviour is therefore unchanged: a declared foreign tax
credit is echoed back, grants `0.00`, and returns that warning. `other_credit` has no
counterpart on the form at all and likewise grants nothing.

---

## 6. Separate-taxation elections — ข้อ 9 implemented, ข้อ 8 and ข้อ 3 not

### ข้อ 9 — VERIFIED and implemented

**Source:** `วิธีการกรอกแบบ ภ.ง.ด.90.pdf` page 4, ข้อ 9 and ข้อ 11 item 19.

> ข้อ 9 เงินได้จากการให้หรือการรับ (โดยเลือกเสียภาษีในอัตรา**ร้อยละ 5** ของเงินได้เฉพาะส่วนที่ไม่ได้รับยกเว้นตามมาตรา 42 (26) (27) (28))
> ข้อ 11 19. บวก ภาษีที่ชำระเพิ่มเติม (ยกมาจาก **ข้อ 9** (ถ้ามี))

| Element | Value |
|---|---|
| Eligible category | 40(8) subtype `GIFT_OR_SUPPORT_RECEIVED` — the same 42(26)(27)(28) income ข้อ 7 item 4 prints |
| Election | The taxpayer's, printed as two mutually exclusive routes |
| Rate | 5% |
| Base | The declared non-exempt portion; the form asks the taxpayer to write it |
| Expenses | None — ข้อ 9 prints no expense line |
| Progressive base | Excluded; elected income never joins ข้อ 1–ข้อ 7 |
| Where the tax lands | Added after the credits, at ข้อ 11 item 19 |

Implemented as `SeparateTaxCalculator` plus an explicit `tax_treatment` field. The engine never
infers the election and never picks the cheaper route. A return consisting only of elected
income is rejected, since ข้อ 11 needs a progressive base.

### ข้อ 8 — UNVERIFIED

**Source:** `วิธีการกรอกแบบ ภ.ง.ด.90.pdf` page 4, ข้อ 8 — property sold without trading intent, taxed apart.

The block prints columns (income, necessary-and-reasonable expense, holding years, tax payable,
withholding, balance) but **no rate and no formula**. The tax depends on a holding-year schedule
that is not in the repository.

### ข้อ 3 15% / 10% elections — UNVERIFIED

**Source:** `วิธีการกรอกแบบ ภ.ง.ด.90.pdf` page 2, ข้อ 3 — e.g. `(เฉพาะที่ไม่เลือกเสียภาษีในอัตราร้อยละ 15)` and
`(เฉพาะที่ไม่เลือกเสียภาษีในอัตราร้อยละ 10)`.

These phrases tell us what is **excluded** from ข้อ 3 — income for which the taxpayer elected a
final rate is simply not reported there. The form gives no computation for the elected portion,
because it is not part of this return. Nothing to implement.

---

## 7. Three blank-percentage subcategories — still UNVERIFIED

Re-searched exhaustively: every occurrence of `ร้อยละ` and `บาท` across all eight pages of both
PDFs was extracted and reviewed. The three subcategories still print `ร้อยละ ............` and no
value appears anywhere else in the repository sources.

| Subcategory | Source | Printed |
|---|---|---|
| 40(5) `RENT_OTHER` | p.2 ข้อ 4 item 1 (2)–(4) | `หักค่าใช้จ่าย ☐ ร้อยละ ............ ☐ จริง` |
| 40(8) `BUSINESS_COMMERCE_OTHER` | p.3 ข้อ 7 item 1 (1)–(4) | `หักค่าใช้จ่าย ☐ ร้อยละ ............ ☐ จริง` |
| 40(8) `IMMOVABLE_PROPERTY_NON_TRADE` | p.3 ข้อ 7 item 3 (2) | `หักค่าใช้จ่าย ☐ ร้อยละ ............ ☐ จริง` plus `จำนวนปีที่ถือครอง ………. ปี` |

The form deliberately defers these rates to the taxpayer, per the specific asset or business
activity. The rate lives in an instrument outside these documents.

---

## 8. Legal rounding — still UNVERIFIED

Neither document states which values are rounded, to what unit, in which direction, or at which
step. `ROUNDING_RULE_PENDING` is returned on every calculation and exact decimal arithmetic is
preserved throughout, unchanged from Milestone 04.

---

## Rule-version decision — 2568.1 extended

Added to the published `2568.1` rather than creating a new version. Assessment per element:

| Change | Alters a previously calculable outcome? |
|---|---|
| Two donation rules | **No** — `donation_rules` was empty, so every donation code previously returned 422 as unknown. No saved return can contain one. |
| PROVIDENT_FUND allowance | **No** — the code was previously accepted but always granted `0.00`. Granting it now changes the outcome **only for a return that declares it**, and no saved return does. |
| `pnd94` credit | **No** — the type was previously rejected by validation. |
| `pnd93` credit | **Yes, in principle** — previously warned and granted `0.00`. |
| ข้อ 9 election | **No** — `tax_treatment` did not exist, so no saved return can elect it. |

Only `pnd93` changes an outcome for input that was previously accepted. A new rule version would
not have contained that change in any case: credit handling lives in `TaxCreditCalculator`, not
in versioned rule data, so versioning cannot gate it. The mitigations that do apply:

- **historical snapshots are immutable** — `tax_calculations.result_snapshot` is never rewritten,
  so every completed simulation still reads back exactly as calculated;
- no saved return in the project carries a `pnd93` entry;
- the change makes the engine agree with the form, where ข้อ 11 item 15 has always deducted it.

This is recorded here rather than applied silently. No saved return was migrated, and every
return keeps its own `rule_version`.

---

## What each remaining gap needs

| Gap | Needed |
|---|---|
| Minimum-tax base | A statement of which form items `ข้อ 1 ถึง ข้อ 7 1. ถึง 4.` covers |
| PERSONAL allowance | Which case gives 60,000 and which 120,000 |
| SPOUSE allowance | The combined-filing definition, plus a spouse block in the calculation payload |
| CHILD allowance | Birth-order rule, eligibility conditions, any count limit, plus a dependents block |
| 13 unverified allowances | The ceilings the attachment does not print |
| Foreign tax credit | Which ceiling "ภาษีที่ต้องเสียตามกฎหมายประเทศไทย" means, and the eligibility conditions |
| ข้อ 8 election | The rate and the holding-year schedule |
| 3 blank subcategories | The deferred percentages |
| Rounding | Which values, what unit, which direction, at which step |

Page 5 of the form carries QR codes to **วิธีการกรอกแบบ ภ.ง.ด.90** and **วิธีการกรอกแบบ ภ.ง.ด.91** —
the filing-instruction booklets. Those documents are not in the repository and would most
plausibly resolve the allowance ceilings, the PERSONAL discriminator, the child ordering and
possibly the minimum-tax base. Obtaining them is the single highest-value next input.

## M7.3 current blockers

The files named “วิธีการกรอกแบบ” now exist, but their contents are exactly the old blank
forms, as established by full-page inspection and matching SHA-256 hashes. The explanatory
booklets remain missing. No source gap in the table above was closed.

Required next source material:
1. Actual PND90 filing instructions for ข้อ 11 items 9-10, including precise base mapping,
   exemption treatment and exclusions.
2. Actual PND90/PND91 allowance instructions for attachment items 1-3 and combined-filing
   detail, resolving PERSONAL 60,000/120,000 and SPOUSE/CHILD conditions and ordering.
3. Instructions defining the remaining allowance ceilings, shared-cap membership and
   eligibility; ล.ย.04 and instructions for disabled-person claims.
4. Foreign-credit eligibility and the exact Thai-tax ceiling; property-sale holding-year
   schedule/election formula; the three deferred expense percentages.
5. Explicit rounding unit, direction and stage.

The minimum-tax API remains warning-mode (HTTP 200 is possible), not production-complete.
M7.3 changed no numeric rule or existing snapshot. Do NOT begin Milestone 08 yet.

---

## Milestone 07.4 status of the remaining allowance blockers

| Blocker as recorded after M7.3 | M7.4 outcome |
| --- | --- |
| Percentage-based ceilings (RMF, Thai ESG, Thai ESG X, ประกันชีวิตแบบบำนาญ) had no way to name their base | **Resolved for three of four.** `allowance_rules.percentage_base` now names it; RMF, THAI_ESG and THAI_ESGX are seeded at 30% of `GROSS_AFTER_EXEMPTION`. `PENSION_INSURANCE` stays PARTIAL for a different reason — see below. |
| Cross-code combined caps (life/health 100,000; the 500,000 retirement basket) | **Resolved.** `allowance_cap_groups` + `CombinedAllowanceCapResolver`; three groups seeded. |
| Tiered ceilings (Easy E-Receipt, ค่าท่องเที่ยว) | **Resolved for Easy E-Receipt**, modelled as two printed boxes under one 50,000 group rather than as bands. **ค่าท่องเที่ยว remains blocked**: its second เมืองรอง band prints a 1.5× multiplier and no ceiling. |
| `SOCIAL_SECURITY` has no printed numeric limit | **Unchanged and deliberately so.** No repository source states it. |
| Three PND90 expense subcategories with a blank percentage | **Resolved.** See [PND90_EXPENSE_SUBTYPE_MATRIX_2568.md](PND90_EXPENSE_SUBTYPE_MATRIX_2568.md). |
| ใบแนบ item 5's "บุคคลอื่น … ไม่เกิน 1 คน" | **Unchanged.** Still needs a relation-type split to become applicable; still reported per calculation. |
| Legal final rounding | **Unchanged.** `ROUNDING_RULE_PENDING` still stands: no repository source states a rounding unit, direction or stage, and the prompt forbids inferring one from worked examples. |

### `PENSION_INSURANCE` — the one rate that is known but not usable

ใบแนบ item 7.6, page 10, states two things about the same allowance:

> ให้ยกเว้นเงินได้ที่จ่ายไปเป็นเบี้ยประกันภัยสำหรับการประกันชีวิตแบบบำนาญ … **ตามจำนวนที่จ่ายจริง
> แต่ไม่เกิน 90,000 บาท** และให้ยกเว้นเงินได้เพิ่มขึ้นอีกตามหลักเกณฑ์และวิธีการดังต่อไปนี้
> (1) … **ในอัตราร้อยละ 15 ของเงินได้พึงประเมินที่ได้รับซึ่งต้องเสียภาษีเงินได้ในแต่ละปี
> แต่ไม่เกิน 200,000 บาท**

Whether the 90,000 sits inside the 200,000 or on top of it is not printed, and the two readings
differ by 90,000 of deduction. The page also states an ordering the taxpayer performs before
filling the box — *"จะต้องใช้สิทธิ … สำหรับเบี้ยประกันชีวิตแบบอื่นเต็มจำนวนเงิน 100,000 บาทก่อน …
แล้วนำจำนวนเงินที่เหลือ … กรอกในช่องเบี้ยประกันชีวิตแบบบำนาญ"* — which is an instruction to the
filer, not engine arithmetic, and is not modelled.

Milestone prompt §5 is explicit that knowing the percentage is not sufficient to call a rule
VERIFIED. It stays PARTIAL, deducts nothing, and reports `UNVERIFIED_ALLOWANCE_RULE`.

---

## Milestone 07.5 — final status of every remaining item

This closes the list. Each entry now has one final status and a defined runtime behaviour; none
remains open for a future Milestone 7.x.

| Item | Final status | Runtime behaviour |
| --- | --- | --- |
| `PENSION_INSURANCE` (ใบแนบ 7.6) | PARTIAL_BLOCKED | 422 `PENSION_INSURANCE_RULE_PARTIAL` on a positive amount |
| `SOCIAL_SECURITY` (ใบแนบ 12) | PARTIAL_BLOCKED | 422 `SOCIAL_SECURITY_RULE_UNSUPPORTED` on a positive amount |
| ค่าท่องเที่ยวภายในประเทศ (ใบแนบ 22) | PARTIAL_BLOCKED | no master code exists, so an attempt to claim it is an unknown-code 422 |
| กล้องโทรทัศน์วงจรปิด (ใบแนบ 13) | UNSUPPORTED | same |
| เงินลงทุนในหุ้นวิสาหกิจเพื่อสังคม (ใบแนบ 16) | UNSUPPORTED | same |
| ค่าจ้างก่อสร้างอาคาร (ใบแนบ 20) | UNSUPPORTED | same |
| กบข. / กองทุนสงเคราะห์ครูโรงเรียนเอกชน | NOT_APPLICABLE as allowances | I90 p.2 places both in ข้อ 1 item 2 as income deductions, not ใบแนบ lines; they reach the engine through `exempt_amount` |
| ใบแนบ item 5 "บุคคลอื่น … ไม่เกิน 1 คน" | PARTIAL_BLOCKED (sub-limit only) | the 60,000 per person is applied; the sub-limit is reported via `DISABLED_PERSON_OTHER_LIMIT_UNMODELLED`, because the schema cannot tell a family member from a บุคคลอื่น and the source offers no other discriminator |
| Legal final rounding | UNSUPPORTED | exact decimals retained; `ROUNDING_RULE_PENDING` on every response |
| Three blank-percentage expense subcategories | SUPPORTED | closed in M7.4 |

**Why item 5's split was not resolved.** M7.5 checked whether the structured dependent data
could carry the discriminator. `relation_type` already distinguishes `child`, `father`,
`mother`, `spouse_father`, `spouse_mother` and `disabled_person`, but ใบแนบ item 5.1 covers a
disabled บิดามารดา, คู่สมรส, บุตร **or** บุคคลอื่น — the same person can be both a family member
and the disabled person being cared for, and the source gives no rule for deciding which. Adding
a flag would be a speculative field, which the milestone prompt forbids. The 60,000 per person
remains SUPPORTED; only the บุคคลอื่น count limit is unmodelled, and every affected calculation
says so.

See [TAX_ENGINE_BASELINE_2568.md](TAX_ENGINE_BASELINE_2568.md) for the frozen baseline.

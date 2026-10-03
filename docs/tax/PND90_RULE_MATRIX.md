# PND90 expense rule matrix — tax year 2568, rule version 2568.1

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


Reconciled in Milestone 07.1 and extended in Milestone 07.2 against
`docs/tax-source/วิธีการกรอกแบบ ภ.ง.ด.90.pdf` (5 pages) and `docs/tax-source/วิธีการกรอกแบบ ภ.ง.ด.91.pdf` (3 pages). Every value
below was read from those files; nothing was filled in from general tax knowledge or the public
internet.

This file covers **expense** rules. Allowances are in `docs/tax/ALLOWANCE_RULE_MATRIX_2568.md`;
donations, credits, the separate-rate election, the minimum tax and rounding are in
`docs/tax/PND90_REMAINING_RULES_2568.md`.

`Pnd90RuleReconciliationTest` asserts this matrix against the seeded data, so the two cannot
drift apart.

## Status definitions

| Label | Meaning |
|---|---|
| **VERIFIED** | The source states every value needed; a row exists in `expense_rules`; the engine applies it. |
| **PARTIAL** | The source states some subcategories of the income type but leaves at least one printed as a blank percentage. Verified subcategories calculate; unverified ones are rejected. |
| **UNVERIFIED** | The source states no usable value. The engine refuses to calculate the category. |

`N/A` means the form prints no such value. It is a fact read from the source, not an unknown.

## Income type summary

| Income Type | Section | Source File | Source Page / Section | Expense Rule Status |
|---|---|---|---|---|
| SECTION_40_1 | 40(1) | วิธีการกรอกแบบ ภ.ง.ด.90.pdf | p.2 ข้อ 1 item 5 | **VERIFIED** (shared with 40(2)) |
| SECTION_40_2 | 40(2) | วิธีการกรอกแบบ ภ.ง.ด.90.pdf | p.2 ข้อ 1 items 3–5 | **VERIFIED** (shared with 40(1)) |
| SECTION_40_3 | 40(3) | วิธีการกรอกแบบ ภ.ง.ด.90.pdf | p.2 ข้อ 2 | **VERIFIED** (both subcategories) |
| SECTION_40_4 | 40(4) | วิธีการกรอกแบบ ภ.ง.ด.90.pdf | p.2 ข้อ 3 | **VERIFIED** |
| SECTION_40_5 | 40(5) | วิธีการกรอกแบบ ภ.ง.ด.90.pdf | p.2–3 ข้อ 4 | **PARTIAL** (2 of 3 subcategories) |
| SECTION_40_6 | 40(6) | วิธีการกรอกแบบ ภ.ง.ด.90.pdf | p.3 ข้อ 5 | **VERIFIED** (all 3 subcategories) |
| SECTION_40_7 | 40(7) | วิธีการกรอกแบบ ภ.ง.ด.90.pdf | p.3 ข้อ 6 | **VERIFIED** |
| SECTION_40_8 | 40(8) | วิธีการกรอกแบบ ภ.ง.ด.90.pdf | p.3 ข้อ 7 | **PARTIAL** (3 of 5 subcategories) |

## Full matrix

Source wording is quoted from the form; implementation notes follow the `—` separator.

### SECTION_40_1 — เงินได้ตามมาตรา 40(1)

| Field | Value |
|---|---|
| Source File | `docs/tax-source/วิธีการกรอกแบบ ภ.ง.ด.90.pdf` |
| Source Page / Section | page 2, ข้อ 1 item 5 |
| Source-Supported Description | `1. มาตรา 40 (1) ได้แก่ เงินเดือน ค่าจ้าง บำ�นาญ ฯลฯ` … `5. หักค่าใช้จ่าย (ร้อยละ 50 แต่ไม่เกิน 100,000 บาท)` |
| Expense Rule Status | **VERIFIED** |
| Method | `percentage_limit` |
| Percentage | 50.0000 |
| Maximum Amount | 100,000.00 |
| Minimum Amount | N/A |
| Actual Expense Supported | No — the form prints no `จริง` checkbox for ข้อ 1 |
| Conditions | None |
| Special Notes | The deduction is taken on ข้อ 1 item 4 = 40(1) less the exempt items in item 2 **plus 40(2)**, so the cap is shared with 40(2). ภ.ง.ด.91 line 4 states the same 50% but says `แต่ไม่เกินที่กฎหมายกำ�หนด` without a number; the 100,000 figure comes from ภ.ง.ด.90. |
| Production Seeder Status | Seeded as `PND91_SECTION_40_1_EXPENSE`, `expense_group = SECTION_40_1_2` |
| Calculator Status | Applied |
| Warning / Error Behavior | None |

### SECTION_40_2 — เงินได้ตามมาตรา 40(2)

| Field | Value |
|---|---|
| Source File | `docs/tax-source/วิธีการกรอกแบบ ภ.ง.ด.90.pdf` |
| Source Page / Section | page 2, ข้อ 1 items 3–5 |
| Source-Supported Description | `3. มาตรา 40 (2) ได้แก่ เบี้ยประชุม ค่านายหน้า ฯลฯ` — added into item 4 before the single item 5 deduction |
| Expense Rule Status | **VERIFIED** |
| Method | `percentage_limit` |
| Percentage | 50.0000 |
| Maximum Amount | 100,000.00 (**shared with 40(1)**) |
| Minimum Amount | N/A |
| Actual Expense Supported | No |
| Conditions | None |
| Special Notes | The 100,000 cap is applied **once** to the combined 40(1) + 40(2) base, never once per type. |
| Production Seeder Status | Seeded as `PND90_SECTION_40_2_EXPENSE`, `expense_group = SECTION_40_1_2` |
| Calculator Status | Applied as one grouped deduction |
| Warning / Error Behavior | None |

### SECTION_40_3 — เงินได้ตามมาตรา 40(3)

Two subcategories with different treatment; they are never blended.

| Subtype | Source Page / Section | Source Wording | Status | Method | % | Cap | Actual | Seeded As |
|---|---|---|---|---|---|---|---|---|
| `ANNUITY_FROM_WILL_OR_JUDGMENT` | p.2 ข้อ 2 item 1 | `เงินได้มีลักษณะเป็นเงินรายปีอันได้มาจากพินัยกรรม นิติกรรมอย่างอื่น หรือคำ�พิพากษาของศาล ฯลฯ` — no หักค่าใช้จ่าย line is printed; the amount carries straight to คงเหลือ | **VERIFIED** | `fixed` | N/A | N/A | No | `PND90_SECTION_40_3_ANNUITY_EXPENSE` (fixed_amount 0.00) |
| `COPYRIGHT_GOODWILL_OTHER_RIGHTS` | p.2 ข้อ 2 item 2 | `ค่าแห่งลิขสิทธิ์ / ค่าแห่งกู๊ดวิลล์ ค่าสิทธิอย่างอื่น` — `หักค่าใช้จ่าย ☐ ร้อยละ 50 (แต่ไม่เกิน 100,000 บาท) ☐ จริง` | **VERIFIED** | `percentage_or_actual` | 50.0000 | 100,000.00 | Yes (elected) | `PND90_SECTION_40_3_COPYRIGHT_EXPENSE` |

### SECTION_40_4 — เงินได้ตามมาตรา 40(4)

| Field | Value |
|---|---|
| Source Page / Section | page 2, ข้อ 3 |
| Source-Supported Description | ดอกเบี้ย, เงินปันผล, เงินส่วนแบ่งของกำ�ไรจากกองทุนรวม, เครดิตภาษีเงินปันผล and the ข้อ 3 item 6 list (โทเคนดิจิทัล, RMF/LTF/SSF/Thai ESG redemption, …). **The block prints no หักค่าใช้จ่าย line at all**; every item carries straight to รวม. |
| Expense Rule Status | **VERIFIED** |
| Method | `fixed`, fixed_amount 0.00 |
| Percentage / Cap / Minimum | N/A |
| Actual Expense Supported | No |
| Special Notes | The separate 15% / 10% final-tax elections printed in ข้อ 3 are *not* implemented — see remaining gaps. |
| Production Seeder Status | `PND90_SECTION_40_4_EXPENSE` |
| Warning / Error Behavior | None |

### SECTION_40_5 — เงินได้ตามมาตรา 40(5) — **PARTIAL**

| Subtype | Source Page / Section | Source Wording | Status | Method | % | Cap | Actual | Seeded As |
|---|---|---|---|---|---|---|---|---|
| `RENT_BUILDING_OR_RAFT` | p.2 ข้อ 4 item 1 (1) | `บ้าน โรงเรือน สิ่งปลูกสร้างอย่างอื่น หรือแพ` — `หักค่าใช้จ่าย ☐ ร้อยละ 30 ☐ จริง` | **VERIFIED** | `percentage_or_actual` | 30.0000 | N/A | Yes (elected) | `PND90_SECTION_40_5_RENT_BUILDING_EXPENSE` |
| `RENT_OTHER` | p.2 ข้อ 4 item 1 (2)–(4) | `อื่นๆ (ระบุ)` — `หักค่าใช้จ่าย ☐ ร้อยละ ............ ☐ จริง` | **UNVERIFIED** | N/A | N/A | N/A | N/A | Not seeded |
| `HIRE_PURCHASE_BREACH` | p.3 ข้อ 4 item 2 | `การผิดสัญญาเช่าซื้อทรัพย์สิน/ซื้อขายเงินผ่อนฯ` — `หักค่าใช้จ่ายร้อยละ 20` (no จริง checkbox) | **VERIFIED** | `percentage` | 20.0000 | N/A | No | `PND90_SECTION_40_5_HIRE_PURCHASE_EXPENSE` |

`RENT_OTHER` prints the percentage as a blank the taxpayer fills in per the specific asset, so
the rate lives outside this document. Positive income under it returns 422.

### SECTION_40_6 — เงินได้ตามมาตรา 40(6)

Header: `เงินได้จากวิชาชีพอิสระ คือ วิชากฎหมาย การประกอบโรคศิลปะ วิศวกรรม สถาปัตยกรรม การบัญชี ประณีตศิลปกรรม`

| Subtype | Source Page / Section | Source Wording | Status | Method | % | Cap | Actual | Seeded As |
|---|---|---|---|---|---|---|---|---|
| `MEDICAL_PRACTICE` | p.3 ข้อ 5 item 1 | `การประกอบโรคศิลปะ` — `หักค่าใช้จ่าย ☐ ร้อยละ 60 ☐ จริง` | **VERIFIED** | `percentage_or_actual` | 60.0000 | N/A | Yes (elected) | `PND90_SECTION_40_6_MEDICAL_EXPENSE` |
| `FINE_ARTS` | p.3 ข้อ 5 item 2 | `ประณีตศิลปกรรม` — `หักค่าใช้จ่าย ☐ ร้อยละ 60 ☐ จริง` | **VERIFIED** | `percentage_or_actual` | 60.0000 | N/A | Yes (elected) | `PND90_SECTION_40_6_FINE_ARTS_EXPENSE` |
| `OTHER_LIBERAL_PROFESSION` | p.3 ข้อ 5 items 3–4 | `อื่นๆ (ระบุ)` — `หักค่าใช้จ่าย ☐ ร้อยละ 30 ☐ จริง` (printed identically on both lines) | **VERIFIED** | `percentage_or_actual` | 30.0000 | N/A | Yes (elected) | `PND90_SECTION_40_6_OTHER_EXPENSE` |

Items 3 and 4 are two blank lines carrying the same printed 30%, so they are one subtype here.
The remaining professions named in the header (วิชากฎหมาย, วิศวกรรม, สถาปัตยกรรม, การบัญชี) fall under
`อื่นๆ`; the form does not give them separate rates.

### SECTION_40_7 — เงินได้ตามมาตรา 40(7)

| Field | Value |
|---|---|
| Source Page / Section | page 3, ข้อ 6 |
| Source-Supported Description | `เงินได้จากการรับเหมาที่ผู้รับเหมาต้องลงทุนจัดหาสัมภาระในส่วนสำ�คัญนอกจากเครื่องมือ` — `หักค่าใช้จ่าย ☐ ร้อยละ 60 ☐ จริง` |
| Expense Rule Status | **VERIFIED** |
| Method | `percentage_or_actual` |
| Percentage | 60.0000 |
| Maximum / Minimum | N/A |
| Actual Expense Supported | Yes (elected) |
| Conditions | None — the form prints a single category with no subcategories |
| Production Seeder Status | `PND90_SECTION_40_7_EXPENSE` |
| Warning / Error Behavior | 422 `expense_method_selection` when the election is missing |

### SECTION_40_8 — เงินได้ตามมาตรา 40(8) — **PARTIAL**

| Subtype | Source Page / Section | Source Wording | Status | Method | % | Cap | Actual | Seeded As |
|---|---|---|---|---|---|---|---|---|
| `BUSINESS_COMMERCE_OTHER` | p.3 ข้อ 7 item 1 (1)–(4) | `เงินได้จากการธุรกิจ การพาณิชย์ การเกษตร การอุตสาหกรรม การขนส่งหรือการอื่นๆ รวมทั้งขายอสังหาริมทรัพย์ที่ได้มาโดยมุ่งในทางการค้าหรือหากำ�ไร` — `หักค่าใช้จ่าย ☐ ร้อยละ ............ ☐ จริง` | **UNVERIFIED** | N/A | N/A | N/A | N/A | Not seeded |
| `MUTUAL_FUND_PROFIT_SHARE` | p.3 ข้อ 7 item 2 | `เงินส่วนแบ่งของกำ�ไรจากกองทุนรวมตามประกาศคณะปฏิวัติฯ` — no หักค่าใช้จ่าย line printed | **VERIFIED** | `fixed` | N/A | N/A | No | `PND90_SECTION_40_8_MUTUAL_FUND_EXPENSE` (0.00) |
| `IMMOVABLE_PROPERTY_INHERITED_OR_GIFTED` | p.3 ข้อ 7 item 3 (1) | `เป็นมรดก หรือได้รับโดยเสน่หา` — `หักค่าใช้จ่ายร้อยละ 50` (no จริง checkbox) | **VERIFIED** | `percentage` | 50.0000 | N/A | No | `PND90_SECTION_40_8_INHERITED_PROPERTY_EXPENSE` |
| `IMMOVABLE_PROPERTY_NON_TRADE` | p.3 ข้อ 7 item 3 (2) | `ได้มาโดยมิได้มุ่งในทางการค้าหรือหากำ�ไร` — `หักค่าใช้จ่าย ☐ ร้อยละ ............ ☐ จริง` plus `จำ�นวนปีที่ถือครอง ………. ปี` | **UNVERIFIED** | N/A | N/A | N/A | N/A | Not seeded |
| `GIFT_OR_SUPPORT_RECEIVED` | p.3 ข้อ 7 item 4 | `เงินได้จากการให้หรือการรับ … ตามมาตรา 42 (26) (27) (28)` — no หักค่าใช้จ่าย line printed | **VERIFIED** | `fixed` | N/A | N/A | No | `PND90_SECTION_40_8_GIFT_RECEIVED_EXPENSE` |

`IMMOVABLE_PROPERTY_NON_TRADE` additionally needs a holding-period input the request schema does
not carry, and its ข้อ 8 variant is taxed separately; both are out of scope until the rule is
stated.

## Actual-expense schedule

ภ.ง.ด.90 page 3 prints `รายการค่าใช้จ่ายจริงที่ขอหักตามความจำ�เป็นและสมควร สำ�หรับเงินได้ตามมาตรา 40 (3) (5) (6) (7) หรือ (8)`.
That list is why actual expense is accepted for 40(3), 40(5), 40(6) and 40(7) and refused for
40(1), 40(2) and 40(4). Within 40(8) no seeded subcategory prints a `จริง` checkbox, so actual
expense is not accepted there yet.

## Expense methods the engine can apply

| Method | Required fields | Arithmetic |
|---|---|---|
| `fixed` | `fixed_amount` | `min(fixed_amount, income after exemption)` |
| `percentage` | `percentage` | `income after exemption × percentage` |
| `percentage_limit` | `percentage`, `maximum_amount` | `min(income × percentage, maximum_amount, income)` |
| `actual` | — | `min(declared actual expense, income after exemption)` |
| `percentage_or_actual` | `percentage`, optional `maximum_amount` | the taxpayer's election: the percentage side (capped when a cap is stated) or the declared actual expense |

`percentage_or_actual` became applicable in this milestone because ภ.ง.ด.90 settles its
semantics: the form prints two checkboxes, so it is the **taxpayer's election**, never an
automatic best-of. The client states it in `expense_method_selection`; the engine never infers.

`custom` remains unapproved — it needs a named, approved computation. `conditions` and
`minimum_amount` are still never interpreted; a rule carrying either fails closed with 409.

## Rule-data integrity, kept separate from rule absence

| Situation | Response |
|---|---|
| No `expense_rules` row for the income type/subtype | **422** on `incomes.{i}.income_type` or `.income_subtype` |
| A row that is inactive, unsourced, duplicated for one subtype, or has unsupported mechanics | **409** |
| A zero-valued row of an unverified category | **200** with an `UNVERIFIED_EXPENSE_RULE` warning |

## Aggregation

A rule applies to the **aggregate** of its category, never per income line, and an
`expense_group` aggregates across income types. Progressive tax is then calculated once on the
combined net income of every category — never per type.

## Remaining gaps beyond expense rules

Updated by Milestone 07.2, which reconciled these against the same sources. Full detail is in
**`docs/tax/PND90_REMAINING_RULES_2568.md`**; allowances are in
**`docs/tax/ALLOWANCE_RULE_MATRIX_2568.md`**.

1. **Minimum tax — p.4 ข้อ 11 items 9–10.** **PARTIAL, not implemented.** The rate (0.5%), the
   120,000 threshold, the exclusion of 40(1), the comparison against item 8 and the 5,000
   exception are all printed; what `ข้อ 1 ถึง ข้อ 7 1. ถึง 4.` scopes is not. The engine emits
   `PND90_MINIMUM_TAX_NOT_APPLIED` whenever non-40(1) gross reaches 120,000, because ignoring it
   silently could understate the tax. ภ.ง.ด.91 prints no equivalent line, so PND91 is unaffected.
2. **Donations — p.4 ข้อ 11 items 4 and 6.** **VERIFIED and implemented** in M7.2:
   `SPECIAL_DONATION` at 2x capped at 10% of item 3, then `GENERAL_DONATION` capped at 10% of
   item 5. The two caps stand on different bases and the printed order is preserved.
3. **Foreign tax credit — p.4 ข้อ 11 item 13.** **PARTIAL, still grants nothing.**
   `ไม่เกินภาษีที่ต้องเสียตามกฎหมายประเทศไทย` states a ceiling but not which one, and no eligibility
   condition is given. `UNVERIFIED_FOREIGN_TAX_CREDIT_LIMIT` is retained.
4. **ภ.ง.ด.94 — p.4 ข้อ 11 item 15.** **VERIFIED and implemented.** The line's three checkboxes —
   withholding, ภ.ง.ด.93 and ภ.ง.ด.94 — all feed item 16 with no stated limit, so all three now
   reduce the balance in full. This changed `pnd93`, which previously granted nothing.
5. **Separate-taxation elections.** ข้อ 9 (gift income at 5%) is **VERIFIED and implemented** via
   the `tax_treatment` field. ข้อ 8 prints no rate or formula and the ข้อ 3 15%/10% phrases only
   describe what is excluded from the return; both remain **UNVERIFIED**.
6. **Allowances — p.5 ใบแนบ.** The attachment was located (it is also ภ.ง.ด.91 p.3). It prints an
   amount on only four of twenty-three lines. `PROVIDENT_FUND` (capped at 10,000) is **VERIFIED
   and implemented**; `PERSONAL`, `SPOUSE` and `CHILD` are **PARTIAL**; the rest are
   **UNVERIFIED**. No combined cap is printed anywhere.
7. **Final rounding**: still unresolved — `ROUNDING_RULE_PENDING` on every calculation.
8. **Three blank-percentage expense subcategories** (40(5) `RENT_OTHER`, 40(8)
   `BUSINESS_COMMERCE_OTHER`, 40(8) `IMMOVABLE_PROPERTY_NON_TRADE`): re-searched exhaustively
   across all eight pages in M7.2; the percentages appear nowhere and remain **UNVERIFIED**.
## Adding a rule for a remaining gap

1. obtain the document stating the value and place it under `docs/tax-source/`;
2. complete that row above, including page and section;
3. extend `Pnd90ExpenseRuleSeeder` (it refuses to overwrite a differing existing row);
4. add the subtype to `IncomeSubtypeCatalogue` if new;
5. add the expectation to `Pnd90RuleReconciliationTest::MATRIX` — the test fails until the
   matrix and the data agree;
6. add boundary tests for the stated percentage and cap.

Saved member drafts already holding that category start calculating immediately, with no data
migration; `MemberPnd90ApiTest::test_a_blocked_pnd90_return_calculates_once_the_rule_becomes_verified`
covers that transition.

## M7.3 outcome

The apparent filing-instruction documents are the same blank forms under different names.
No expense rule changed. Minimum-tax inclusion/exemption scope remains PARTIAL; the
existing warning is not proof of a completed minimum-tax computation. See
[MINIMUM_TAX_RECONCILIATION_2568.md](MINIMUM_TAX_RECONCILIATION_2568.md).
No new source-supported family strategy or allowance ceiling was implemented.

---

## Milestone 07.4 — the three blank-percentage subcategories are closed

Blocker 8 of the list above ("three blank-percentage expense subcategories") is resolved. The
blank on the form was never an omission: in each case the rate belongs to a further fact the
form also prints, and the filing-instruction booklet carries the table that fact indexes into.

| Subcategory | Fact | Rate table | Rules seeded |
| --- | --- | --- | --- |
| 40(5) ข้อ 4 item 1 (2)–(4) | which asset is let | booklet p.3, (ก)–(จ) | 4 (30/20/15/30/10 across five classes, one already seeded in M7.1) |
| 40(8) ข้อ 7 item 1 | ตารางที่ 2 activity | booklet p.17 | 44 |
| 40(8) ข้อ 7 item 3 (2) | จำนวนปีที่ถือครอง | booklet p.3 | 8 (92/84/77/71/65/60/55/50) |

Every one keeps the form's `☐ จริง` checkbox, so all are election methods.

Full per-subtype detail, source quotations and the 44-row table:
**[PND90_EXPENSE_SUBTYPE_MATRIX_2568.md](PND90_EXPENSE_SUBTYPE_MATRIX_2568.md)**.

### Matrix corrections

- `RENT_OTHER` is **retired**. It stood for ข้อ 4 items (2)–(4) collectively and carried no
  rate; the booklet names five distinct asset classes with five distinct rates, so it is
  replaced by `RENT_LAND_AGRICULTURAL` (20%), `RENT_LAND_NON_AGRICULTURAL` (15%),
  `RENT_VEHICLE` (30%) and `RENT_OTHER_PROPERTY` (10%). A payload using `RENT_OTHER` is a 422.
- `BUSINESS_COMMERCE_OTHER` and `IMMOVABLE_PROPERTY_NON_TRADE` are no longer "one rule or
  none". Each holds one rule **per printed key** — 44 activities and 8 holding bands — selected
  by `expense_activity` and `holding_years` respectively. A missing, unknown or misplaced key
  is a 422; no key is ever defaulted.
- ตารางที่ 2 row (1) is the only banded rate in the ภ.ง.ด.90 expense tables: 60% of the first
  300,000, 40% above, together capped at 600,000. It is stored as `tiered_or_actual` with two
  `expense_rule_tiers`, so the boundary survives rather than becoming one blended percentage.
- ตารางที่ 2 row (44) prints no rate at all and is seeded as `actual`.

### Schema additions

`expense_rules.expense_activity`, `expense_rules.holding_years_min`,
`expense_rules.holding_years_max`, the `expense_rule_tiers` table, and the matching
`tax_return_incomes.expense_activity` / `holding_years` for saved member lines. The M7.1 unique
index on (version, income type, subtype) is widened to include the activity and the holding
band, preserving the guarantee that at most one rule exists per resolvable combination.

`expense_rules.method` gains `tiered_or_actual`; `source_reference` widens from 255 to 500
characters so a citation can quote the booklet sentence it comes from without truncation.

After M7.4 the ภ.ง.ด.90 expense matrix has **no** category or subcategory left UNVERIFIED.

# Allowance rule matrix — tax year 2568

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


Reconciled in Milestone 07.2 against the attachment
**ใบแนบแสดงรายละเอียดรายการลดหย่อนและยกเว้นหลังจากหักค่าใช้จ่าย — ใบแนบ ภ.ง.ด.90 / ภ.ง.ด.91 ปีภาษี 2568**,
which is page 5 of `docs/tax-source/วิธีการกรอกแบบ ภ.ง.ด.90.pdf` and page 3 of `docs/tax-source/วิธีการกรอกแบบ ภ.ง.ด.91.pdf`
(the two pages are byte-identical).

## The finding in one line

The attachment prints **twenty-three** allowance lines and an amount for only **four** of them.
Of those four, exactly **one** is fully determined by the input this engine receives, so one
allowance rule is seeded. Nothing else was inferred.

`Pnd90RemainingRulesTest` and `Pnd90RuleReconciliationTest` assert this matrix against the
seeded data, so the two cannot drift apart.

## Status definitions

| Label | Meaning |
|---|---|
| **VERIFIED** | The attachment states every value needed and the engine can resolve it from declared input. Seeded and applied. |
| **PARTIAL** | The attachment states an amount, but a condition, discriminator or input the calculation needs is not printed anywhere in the repository sources. |
| **UNVERIFIED** | The attachment prints the line but no amount. |

`N/A` means the attachment prints no such value. It is a fact read from the source, not an
unknown.

## Matrix

| Allowance Code | Source File | Page/Item | Status | Method | Fixed Amount | Percentage | Maximum Amount | Conditions | Combined Cap | Implementation Status | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|
| PERSONAL | วิธีการกรอกแบบ ภ.ง.ด.90.pdf | p.5 item 1 | **PARTIAL** | N/A | 60,000.00 **or** 120,000.00 | N/A | N/A | `แล้วแต่กรณี` — the discriminating case is not printed | None stated | Not seeded | The attachment gives two amounts and never says which applies. Choosing 60,000 would be a guess in the 120,000 case. **Highest-value blocker.** |
| SPOUSE | วิธีการกรอกแบบ ภ.ง.ด.90.pdf | p.5 item 2 | **PARTIAL** | N/A | 60,000.00 | N/A | N/A | `กรณีมีเงินได้รวมคำนวณภาษีหรือไม่มีเงินได้` | None stated | Not seeded | The amount and condition are printed, but "income combined for tax calculation" is a combined-filing mode the project deferred in Milestone 05, and the calculation payload carries no spouse block. |
| CHILD | วิธีการกรอกแบบ ภ.ง.ด.90.pdf | p.5 item 3 | **PARTIAL** | N/A | 30,000.00 per child; 60,000.00 from the second child onward born in or after พ.ศ. 2561 | N/A | N/A | Birth year printed; birth-order rule, eligibility conditions and any count limit are not | None stated | Not seeded | Both amounts are printed. What "คนที่ 2" is counted against, what makes a child eligible, and whether a maximum count applies are not stated, and the calculation payload carries no dependents block. |
| PARENT | วิธีการกรอกแบบ ภ.ง.ด.90.pdf | p.5 item 4 | **UNVERIFIED** | N/A | N/A | N/A | N/A | ID fields only | N/A | Not seeded | The line prints identity boxes and no amount. |
| DISABLED_PERSON | วิธีการกรอกแบบ ภ.ง.ด.90.pdf | p.5 item 5 | **UNVERIFIED** | N/A | N/A | N/A | N/A | `ยกมาจากแบบ ล.ย.04` | N/A | Not seeded | The amount is carried from form ล.ย.04, which is not in the repository. |
| HEALTH_INSURANCE (parents') | วิธีการกรอกแบบ ภ.ง.ด.90.pdf | p.5 item 6 | **UNVERIFIED** | N/A | N/A | N/A | N/A | ID fields only | N/A | Not seeded | No amount printed. |
| LIFE_INSURANCE | วิธีการกรอกแบบ ภ.ง.ด.90.pdf | p.5 item 7 | **UNVERIFIED** | N/A | N/A | N/A | N/A | None printed | N/A | Not seeded | `เบี้ยประกันชีวิต` — no amount printed. |
| HEALTH_INSURANCE | วิธีการกรอกแบบ ภ.ง.ด.90.pdf | p.5 item 7 | **UNVERIFIED** | N/A | N/A | N/A | N/A | None printed | N/A | Not seeded | `เบี้ยประกันสุขภาพ` — no amount printed. |
| PENSION_INSURANCE | วิธีการกรอกแบบ ภ.ง.ด.90.pdf | p.5 item 7 | **UNVERIFIED** | N/A | N/A | N/A | N/A | None printed | N/A | Not seeded | `เบี้ยประกันชีวิตแบบบำนาญ` — no amount printed. |
| **PROVIDENT_FUND** | วิธีการกรอกแบบ ภ.ง.ด.90.pdf | **p.5 item 8** | **VERIFIED** | `actual` | N/A | N/A | **10,000.00** | None | None stated | **Seeded** as `PND90_2568_PROVIDENT_FUND` | `เงินสะสมกองทุนสำรองเลี้ยงชีพ (ส่วนที่ไม่เกิน 10,000 บาท)` — eligible = min(declared, 10,000). The portion **above** 10,000 is treated as exempt income on ข้อ 1 item 2 (1) of the main form, which the engine does not apply automatically. |
| NSF | วิธีการกรอกแบบ ภ.ง.ด.90.pdf | p.5 item 9 | **UNVERIFIED** | N/A | N/A | N/A | N/A | None printed | N/A | Not seeded | `เงินสะสมกองทุนการออมแห่งชาติ` — no amount. |
| RMF | วิธีการกรอกแบบ ภ.ง.ด.90.pdf | p.5 item 10 | **UNVERIFIED** | N/A | N/A | N/A | N/A | None printed | N/A | Not seeded | No amount. |
| HOME_LOAN_INTEREST | วิธีการกรอกแบบ ภ.ง.ด.90.pdf | p.5 item 11 | **UNVERIFIED** | N/A | N/A | N/A | N/A | None printed | N/A | Not seeded | No amount. |
| SOCIAL_SECURITY | วิธีการกรอกแบบ ภ.ง.ด.90.pdf | p.5 item 12 | **UNVERIFIED** | N/A | N/A | N/A | N/A | None printed | N/A | Not seeded | No amount. The M6 recommendation still only asks the user to check this line. |
| EASY_E_RECEIPT | วิธีการกรอกแบบ ภ.ง.ด.90.pdf | p.5 item 17 | **UNVERIFIED** | N/A | N/A | N/A | N/A | `ตั้งแต่วันที่ 16 ม.ค. 2568 – 28 ก.พ. 2568`, e-Tax Invoice / e-Receipt only; four sub-lines 17.1–17.4 | N/A | Not seeded | Date range and sub-lines are printed; no amount is. |
| THAI_ESG | วิธีการกรอกแบบ ภ.ง.ด.90.pdf | p.5 item 18 | **UNVERIFIED** | N/A | N/A | N/A | N/A | None printed | N/A | Not seeded | No amount. |
| THAI_ESGX | วิธีการกรอกแบบ ภ.ง.ด.90.pdf | p.5 item 19 | **UNVERIFIED** | N/A | N/A | N/A | N/A | Sub-lines 19.1 purchase and 19.2 LTF switch | N/A | Not seeded | No amount. |
| ANNUAL_TAX_MEASURES | วิธีการกรอกแบบ ภ.ง.ด.90.pdf | p.5 items 13–16, 20–22 | **UNVERIFIED** | N/A | N/A | N/A | N/A | CCTV limited to 40(5)(6)(7)(8) in the special development zone; domestic travel `29 ต.ค. 2568 – 15 ธ.ค. 2568` | N/A | Not seeded | Seven measure lines, none with a printed amount. |
| INSURANCE | — | — | **NOT_APPLICABLE** | N/A | N/A | N/A | N/A | N/A | N/A | Not seeded | A project master code with no matching attachment line; the attachment splits insurance into items 6 and 7. |
| OTHER | วิธีการกรอกแบบ ภ.ง.ด.90.pdf | p.5 item 23 | **NOT_APPLICABLE** | N/A | N/A | N/A | N/A | `อื่น ๆ` free line | N/A | Not seeded | A free-text line with no rule to apply. |

## Counts

| Status | Count |
|---|---|
| VERIFIED | 1 |
| PARTIAL | 3 |
| UNVERIFIED | 13 |
| NOT_APPLICABLE | 2 |
| **IMPLEMENTED** | **1** (PROVIDENT_FUND) |

## Combined caps

The attachment states **no combined cap** anywhere: item 24 is a plain `รวม (1. ถึง 23.)` with no
ceiling. No shared-cap modelling was therefore added, and no schema change was needed for
allowances.

## Allowance methods the engine can apply

| Method | Required fields | Arithmetic |
|---|---|---|
| `fixed` | `fixed_amount` | the stated entitlement, independent of what the taxpayer declares |
| `actual` | optional `maximum_amount` | `min(declared amount, maximum_amount)`, or the declared amount when no ceiling is printed |

`percentage`, `conditions` and `minimum_amount` are never interpreted for allowances: a rule
carrying any of them is treated as unverified and returns zero with a warning rather than being
guessed at.

## Behaviour for everything not seeded

Unchanged from M4: the allowance is accepted, `input_amount` is echoed back, `eligible_amount`
is `0.00`, `rule_status` is `UNVERIFIED`, and an `UNVERIFIED_ALLOWANCE_RULE` warning is returned
against that entry. Nothing is silently defaulted, and a declared allowance never reduces tax
without a printed amount behind it.

## What would unblock the three PARTIAL rows

All three are named on page 5 of the form itself, whose QR codes point at
**วิธีการกรอกแบบ ภ.ง.ด.90** and **วิธีการกรอกแบบ ภ.ง.ด.91** — the filing-instruction booklets. Those
documents are not in the repository. Adding them would most likely resolve:

1. which case gives PERSONAL 60,000 and which gives 120,000;
2. what "มีเงินได้รวมคำนวณภาษี" requires for SPOUSE, and how it interacts with Milestone 05's
   deferred combined-filing mode;
3. how children are ordered and which ones qualify for CHILD;
4. the ceilings for insurance, RMF, Thai ESG/ESGX, social security, NSF, home-loan interest and
   the annual measures.

Implementing SPOUSE or CHILD would additionally require carrying a spouse block and a dependents
block into the calculation payload, which is an API change, not just a seeding change.

## Adding a rule once the values are available

1. place the document under `docs/tax-source/` and complete that row above, with page and item;
2. extend `AllowanceRuleSeeder` (it refuses to overwrite a differing existing row);
3. use one of the supported methods above, or get new mechanics approved first;
4. add boundary tests for the stated ceiling;
5. `TaxPlanningService` picks the rule up with no change, because planning reuses
   `TaxCalculationService` — covered by
   `Pnd90RemainingRulesTest::test_planning_a_verified_allowance_changes_tax_through_the_shared_engine`.

## M7.3 ceiling and required-fact reconciliation

No new instruction text was supplied: the newly named PDFs are byte-identical to the
previous forms. The matrix's numeric/status cells therefore remain unchanged. A blank
ceiling means unknown, not unlimited; “none stated” does not establish that no legal shared
cap exists. No shared-cap group or independent new cap was seeded.

Required facts below describe what the current source provides and what remains missing;
they are not new API fields or invented eligibility conditions.

| Approved code | Status | Required input facts / unresolved evidence |
|---|---|---|
| PERSONAL | PARTIAL | Case selecting 60,000/120,000 missing; marital status alone insufficient |
| SPOUSE | PARTIAL | Marital/income/filing facts exist in M5, but complete filing interaction is missing |
| CHILD | PARTIAL | Child relation/birth date exist in M5; eligibility, ordering universe and allocation rules missing |
| PARENT | UNVERIFIED | Parent relationship visible; amount and eligibility missing |
| DISABLED_PERSON | UNVERIFIED | Requires absent ล.ย.04 and its instructions |
| LIFE_INSURANCE | UNVERIFIED | Declared payment alone insufficient; ceiling and conditions missing |
| HEALTH_INSURANCE | UNVERIFIED | Separate self/parent lines; ceilings, conditions and allocation missing |
| PENSION_INSURANCE | UNVERIFIED | Payment/qualifying-income base and any shared ceiling unresolved |
| PROVIDENT_FUND | VERIFIED | Declared amount for attachment item 8 only; existing cap 10,000 preserved |
| NSF | UNVERIFIED | Payment alone insufficient; ceiling and conditions missing |
| RMF | UNVERIFIED | Payment/qualifying-income base, ceiling and conditions missing |
| HOME_LOAN_INTEREST | UNVERIFIED | Interest amount alone insufficient; qualifying conditions/ceiling missing |
| SOCIAL_SECURITY | UNVERIFIED | Contribution amount alone insufficient; numeric ceiling missing |
| EASY_E_RECEIPT | UNVERIFIED | Dates/electronic receipt categories printed; ceiling and complete conditions missing |
| THAI_ESG | UNVERIFIED | Investment amount alone insufficient; ceiling and conditions missing |
| THAI_ESGX | UNVERIFIED | Purchase versus LTF-switch split printed; ceilings and conditions missing |
| OTHER | NOT_APPLICABLE | Free-text line, no generic automatic rule |

Counts over the **17 approved codes**: VERIFIED 1, PARTIAL 3, UNVERIFIED 12,
NOT_APPLICABLE 1, IMPLEMENTED 1. Over the **19 retained master codes**, including
ANNUAL_TAX_MEASURES and INSURANCE: VERIFIED 1, PARTIAL 3, UNVERIFIED 13,
NOT_APPLICABLE 2, IMPLEMENTED 1. The separate parent-health attachment row above is not
an additional master code. Newly implemented codes in M7.3: none.

The earlier “booklets not in repository” finding means their **content** is missing:
matching filenames now exist, but contain the forms themselves. See
[FAMILY_ALLOWANCE_MODEL_2568.md](FAMILY_ALLOWANCE_MODEL_2568.md).

---

## Milestone 07.3 — ceilings read from the filing-instruction booklet

Source: `docs/tax-source/PND90-2568-filing-instructions.pdf` (18 pages), section
*รายการลดหย่อนและยกเว้นหลังจากหักค่าใช้จ่าย*, pages 7–16. Read column by column
(`pdftotext -layout -f N -l N -x/-y/-W/-H`); full-page extraction of this booklet interleaves
its two columns and produces text that exists in neither.

ใบแนบ items 1–5 are **not** in this table. They are per-person entitlements derived from
declared family facts — see [family allowance model](FAMILY_ALLOWANCE_MODEL_2568.md).

### Seeded — VERIFIED

A ceiling is seeded only when the booklet states it as a plain
"ตามจำนวนที่จ่ายจริงแต่ไม่เกิน X", which `AllowanceCalculator`'s `actual` method expresses
exactly.

| ใบแนบ item | Allowance code | Page | Printed ceiling | Seeded rule |
| --- | --- | --- | --- | --- |
| 6 เบี้ยประกันสุขภาพบิดามารดา | `PARENT_HEALTH_INSURANCE` | 9, item 6.3 | ตามจำนวนที่จ่ายจริงแต่ไม่เกิน 15,000 บาท | `actual`, max 15,000.00 |
| 11 ดอกเบี้ยเงินกู้ยืมเพื่อที่อยู่อาศัย | `HOME_LOAN_INTEREST` | 12, item 11 | ตามจำนวนเงินที่จ่ายจริงในปีภาษีนี้ แต่ไม่เกิน 100,000 บาท | `actual`, max 100,000.00 |
| 14 ค่าฝากครรภ์และค่าคลอดบุตร | `MATERNITY` | 12, item 14 | ตามจำนวนที่จ่ายจริง … แต่ไม่เกินหกหมื่นบาท | `actual`, max 60,000.00 |
| 15 เงินบริจาคแก่พรรคการเมือง | `POLITICAL_PARTY_SUPPORT` | 12, item 15 | ตามจำนวนที่จ่ายจริงแต่รวมกันไม่เกินหนึ่งหมื่นบาท | `actual`, max 10,000.00 |
| 21 ค่าซื้องานศิลปะ | `ART_PURCHASE` | 16, item 21 | ตามจำนวนที่จ่ายจริง แต่ไม่เกิน 100,000 บาท ในปีภาษี | `actual`, max 100,000.00 |

Item 8 (`PROVIDENT_FUND`, max 10,000) was already seeded in M7.2 from the attachment itself.
The booklet confirms it on page 10 and adds that the excess between 10,000 and 490,000, capped
at 15% of wages, is shown as a deduction from income rather than as an allowance — a route this
engine does not model and does not claim to.

### Read but deliberately NOT seeded — PARTIAL

Each of these has a ceiling the booklet prints clearly, in a shape a single
`allowance_rules` row cannot express. Seeding the headline number alone would apply half a rule
and overstate the deduction, so each stays UNVERIFIED and keeps returning zero with
`UNVERIFIED_ALLOWANCE_RULE`.

| ใบแนบ item | Code | Page | What the booklet prints | Why it is not seeded |
| --- | --- | --- | --- | --- |
| 7 เบี้ยประกันชีวิต | `LIFE_INSURANCE` | 9–10 | 100,000 (10,000 for a spouse without income) | shares its 100,000 with เบี้ยประกันสุขภาพ (item 7.4) — a cap across codes |
| 7.4 เบี้ยประกันสุขภาพ | `HEALTH_INSURANCE` | 10 | 25,000, รวมกันต้องไม่เกิน 100,000 | same shared cap, from the other side |
| 7.5 ประกันชีวิตแบบบำนาญ | `PENSION_INSURANCE` | 10 | ร้อยละ 15 ของเงินได้พึงประเมิน แต่ไม่เกิน 200,000 | percentage of a base the rule row cannot name |
| 9 กองทุนการออมแห่งชาติ | `NSF` | 10 | 500,000, รวมกับ PVD/กบข./RMF/บำนาญ ต้องไม่เกิน 500,000 | one 500,000 basket shared by five codes |
| 10 RMF | `RMF` | 11 | ร้อยละ 30 ของเงินได้พึงประเมิน, basket 500,000 | percentage **and** shared basket |
| 12 เงินสมทบกองทุนประกันสังคม | `SOCIAL_SECURITY` | 12 | หักลดหย่อนได้ตามที่จ่ายจริงตามกฎหมายว่าด้วยการประกันสังคม | no ceiling printed; the statutory maximum lives in another act, which is not a repository source |
| 13 กล้องโทรทัศน์วงจรปิด | — | 12 | ร้อยละหนึ่งร้อยของเงินได้ที่จ่าย, เขตพัฒนาพิเศษเฉพาะกิจ เท่านั้น | geographic condition the engine cannot verify |
| 16 เงินลงทุนในหุ้น startup | — | 13 | ไม่เกินกรณีละ 100,000 | "กรณีละ" — per investment case, not per taxpayer |
| 17 Easy E-Receipt 2.0 | `EASY_E_RECEIPT` | 13 | 50,000 for one band and 30,000 for another | two tiers depending on what was bought |
| 18 Thai ESG | `THAI_ESG` | 14 | ร้อยละ 30, เฉพาะส่วนที่ไม่เกิน 300,000 | percentage cap |
| 19 Thai ESG X | `THAI_ESGX` | 14–15 | ร้อยละ 30 with 100,000 / 300,000 / 500,000 bands | percentage and several bands |
| 20 ค่าจ้างก่อสร้างอาคาร | — | 16 | 10,000 per 1,000,000 of construction cost, รวมแล้วไม่เกิน 100,000 | rate on a value the payload does not carry |
| 22 ค่าท่องเที่ยวภายในประเทศ | — | 16 | 10,000 and a second band above 10,000 | two tiers, and a date window inside the tax year |

### Out of scope for M7.3

Page 17 carries **ตารางอัตราการหักค่าใช้จ่ายเป็นการเหมา**, the standard-expense-rate table for
มาตรา 40 (8) activities (~40 rows), which would unblock the three PND90 expense subcategories
M7.1 left with a blank percentage. That is expense work, not allowance work, and
MILESTONE_07_3_PROMPT.md §15 forbids unrelated scope creep. It is recorded here as the next
milestone's opportunity, not implemented.

---

## Milestone 07.4 — percentage bases, shared ceilings, and what they unblocked

M7.3 recorded thirteen lines as PARTIAL not because their numbers were missing but because
`allowance_rules` could not express their **shape**: a percentage with no way to name its base,
and a ceiling that covers several codes at once. M7.4 adds
`allowance_rules.percentage_base` and the `allowance_cap_groups` / `allowance_cap_group_members`
pair, and seeds the lines those two concepts settle.

Full model, calculation order and source quotations:
**[ALLOWANCE_CAP_MODEL_2568.md](ALLOWANCE_CAP_MODEL_2568.md)**.

### Newly VERIFIED and seeded

| ใบแนบ item | Code | Page | Method | Rate | Base | Individual cap | Shared ceiling |
| --- | --- | --- | --- | --- | --- | --- | --- |
| 7.2 (3) | `LIFE_INSURANCE` | 9–10 | actual | — | — | 100,000 | 100,000 with 7.4 |
| 7.4 | `HEALTH_INSURANCE` | 10 | actual | — | — | 25,000 | 100,000 with 7.2 |
| 9 | `NSF` | 10 | actual | — | — | 500,000 | 500,000 retirement basket |
| 10.4 | `RMF` | 11 | percentage_limit | 30% | `GROSS_AFTER_EXEMPTION` | 500,000 | 500,000 retirement basket |
| 18.1 | `THAI_ESG` | 14 | percentage_limit | 30% | `GROSS_AFTER_EXEMPTION` | 300,000 | — |
| 19.1 | `THAI_ESGX` | 14 | percentage_limit | 30% | `GROSS_AFTER_EXEMPTION` | 300,000 | — |
| 19.2 | `THAI_ESGX_SWITCH` | 15 | actual | — | — | 300,000 | — |
| 17.1 | `EASY_E_RECEIPT` | 13 | actual | — | — | 30,000 | 50,000 with 17.2 |
| 17.2 | `EASY_E_RECEIPT_OTOP` | 13 | actual | — | — | 20,000 | 50,000 with 17.1 |

`EASY_E_RECEIPT_OTOP` and `THAI_ESGX_SWITCH` are new master allowance types. Both exist because
ใบแนบ prints the line as separate boxes with separate ceilings (17.1–17.3 vs 17.4; 19.1 vs
19.2), and a single submitted amount could not be split between them without guessing.

### Shared ceilings seeded

| Group | Ceiling | Members | Source |
| --- | --- | --- | --- |
| `LIFE_AND_HEALTH_INSURANCE_2568` | 100,000 | `LIFE_INSURANCE`, `HEALTH_INSURANCE` | p.10, ใบแนบ 7.4 |
| `RETIREMENT_SAVINGS_2568` | 500,000 | `PROVIDENT_FUND`, `NSF`, `RMF` | p.10 ใบแนบ 9; p.11 ใบแนบ 10.4 |
| `EASY_E_RECEIPT_2568` | 50,000 | `EASY_E_RECEIPT`, `EASY_E_RECEIPT_OTOP` | p.13, ใบแนบ 17 |

The booklet never says which member gives way when a shared ceiling bites, so no priority is
seeded: the group total is capped and the per-member split reported alongside it is
presentation only.

### Still PARTIAL after M7.4

| Code | Why |
| --- | --- |
| `PENSION_INSURANCE` (ใบแนบ 7.6, p.10) | Prints "ตามจำนวนที่จ่ายจริง แต่ไม่เกิน 90,000 บาท" **and** "เพิ่มขึ้นอีก … ร้อยละ 15 ของเงินได้พึงประเมิน … แต่ไม่เกิน 200,000 บาท", without saying whether the 90,000 nests inside the 200,000. The two readings differ by 90,000 of deduction. Knowing the rate is not the same as knowing the rule. |
| `SOCIAL_SECURITY` (ใบแนบ 12, p.12) | "หักลดหย่อนได้ตามที่จ่ายจริงตามกฎหมายว่าด้วยการประกันสังคม" — no numeric limit is printed anywhere in the repository sources. Unchanged by M7.4 and deliberately so. |

### Lines with no master code, and why

| ใบแนบ item | Page | Blocker |
| --- | --- | --- |
| 13 กล้องโทรทัศน์วงจรปิด | 12 | 100% of spend, but only for 40(5)–(8) income in a เขตพัฒนาพิเศษเฉพาะกิจ — a geographic condition the payload cannot carry |
| 16 เงินลงทุนในหุ้นวิสาหกิจเพื่อสังคม | 13 | "ไม่เกินกรณีละ 100,000 บาท" — per investment case, and the payload has no notion of a case |
| 20 ค่าจ้างก่อสร้างอาคาร | 16 | 10,000 per 1,000,000 of construction cost, capped at 100,000 — a rate on a value the payload does not carry |
| 22 ค่าท่องเที่ยวภายในประเทศ | 16 | เมืองรอง carries a 1.5× multiplier and the second band prints **no ceiling at all**: "สำหรับส่วนที่เกิน 10,000 บาท ให้สามารถหักลดหย่อนได้ 1.5 เท่าของจำนวนที่จ่ายจริง". Implementing it would mean inventing the missing limit. |

### Rule and type counts after M7.4

`allowance_types` 25, `allowance_rules` 15, `allowance_cap_groups` 3, `expense_rules` 70.

# Family allowance model — tax year 2568

> **Milestone 7.x is CLOSED.** This file records how a rule was reconciled, milestone by
> milestone, and its earlier sections are historical: a status there may have been superseded
> further down the same file. The authoritative final status of every rule is
> [TAX_ENGINE_BASELINE_2568.md](TAX_ENGINE_BASELINE_2568.md), with per-form detail in
> [PND91_PRODUCTION_BASELINE_2568.md](PND91_PRODUCTION_BASELINE_2568.md) and
> [PND90_PRODUCTION_BASELINE_2568.md](PND90_PRODUCTION_BASELINE_2568.md), and a source map in
> [SOURCE_COVERAGE_2568.md](SOURCE_COVERAGE_2568.md).

Milestone 07.3. Every rule below was read from
**`docs/tax-source/PND90-2568-filing-instructions.pdf`** (วิธีการกรอกแบบ ภ.ง.ด.90 ปีภาษี 2568,
18 pages), section *รายการลดหย่อนและยกเว้นหลังจากหักค่าใช้จ่าย*, pages 7–9.

Nothing here comes from web research, general tax knowledge or inference. The booklet is a
two-column layout, so every page was read one column at a time
(`pdftotext -layout -f N -l N -x/-y/-W/-H`) rather than as a whole page, because full-page
extraction interleaves the columns and produces sentences that exist in neither of them.

## Why these five lines are server-derived

ใบแนบ items 1–5 print a **per-person amount**, not a ceiling on something the taxpayer paid.
A client-submitted number for them can therefore only be redundant or wrong. The engine
discards it and derives the amount from declared facts instead
(`App\Services\Tax\FamilyAllowanceResolver` and the strategies beside it).

The declared allowance entry still decides **whether** the line is claimed — the taxpayer must
ask for it — but never **how much**. When the submitted amount differs from the derived one the
response carries `FAMILY_ALLOWANCE_DERIVED` on that entry. An `eligible_amount` field on a
request is rejected outright with 422, as it has been since M4.

## Item 1 — ผู้มีเงินได้ (page 7)

> 1. ผู้มีเงินได้ 60,000 บาท
> 1.1 กรณีผู้มีเงินได้เป็นห้างหุ้นส่วนสามัญหรือคณะบุคคลที่มิใช่นิติบุคคล หากอยู่ในประเทศไทยเพียงคนเดียว
>     ให้หักลดหย่อนได้ 60,000 บาท หากอยู่ในประเทศไทยตั้งแต่ 2 คน ขึ้นไป ให้หักลดหย่อนได้ 120,000 บาท
> 1.2 กรณีคู่สมรสมีเงินได้ฝ่ายเดียว … ผู้มีเงินได้หักลดหย่อนสำหรับผู้มีเงินได้ 60,000 บาท

**Status: VERIFIED.** This resolves the 60,000 / 120,000 ambiguity M7.2 recorded. 120,000
belongs to a ห้างหุ้นส่วนสามัญ or คณะบุคคลที่มิใช่นิติบุคคล with **two or more members resident
in Thailand** — a taxpayer type this simulator does not model. An individual filer is always
60,000, whatever the marital status.

| Fact used | Source | Amount |
| --- | --- | --- |
| — (unconditional for an individual) | page 7, item 1 | 60,000.00 |

## Item 2 — คู่สมรส (page 7)

> 2. คู่สมรส 60,000 บาท
> 2.1 กรณีคู่สมรสไม่มีเงินได้ … ผู้มีเงินได้หักลดหย่อนคู่สมรส 60,000 บาท
> 2.2 กรณีคู่สมรสมีเงินได้ทั้ง 2 ฝ่าย คู่สมรสต่างฝ่ายต่างหักลดหย่อนสำหรับผู้มีเงินได้ 60,000 บาทแล้ว
>     จึงไม่มีสิทธิหักลดหย่อนคู่สมรส

**Status: VERIFIED.** No combined-filing concept is needed to decide this, which is what M7.2
had assumed. The whole test is: married **and** the spouse has no income.

| `profile.marital_status` | `spouse.has_income` | Amount | Warning |
| --- | --- | --- | --- |
| `married` | `false` | 60,000.00 | — |
| `married` | `true` | 0.00 | — (item 2.2 is an entitlement to nothing, not missing data) |
| anything else | any | 0.00 | `SPOUSE_ALLOWANCE_FACTS_MISSING` |
| `married` | not declared | 0.00 | `SPOUSE_ALLOWANCE_FACTS_MISSING` |

## Item 3 — บุตร (page 7)

> 3.1 บุตรชอบด้วยกฎหมายของผู้มีเงินได้ หรือบุตรชอบด้วยกฎหมายของคู่สมรสของผู้มีเงินได้ คนละ 30,000 บาท
>     และสำหรับบุตรชอบด้วยกฎหมายตั้งแต่คนที่สองเป็นต้นไปที่เกิดในหรือหลังปี พ.ศ. 2561 ให้หักลดหย่อน
>     ได้เพิ่มอีกคนละ 30,000 บาท โดยในการนับลำดับบุตร ให้นับลำดับของบุตรทุกคนไม่ว่าจะมีชีวิตอยู่หรือไม่ก็ตาม
> 3.2 บุตรบุญธรรมของผู้มีเงินได้ คนละ 30,000 บาท แต่รวมกันต้องไม่เกินสามคน
> 3.3 ในกรณีผู้มีเงินได้มีบุตรทั้ง 3.1 และ 3.2 … ให้นำบุตรตาม 3.1 ทั้งหมดมาหักก่อน แล้วจึงนำบุตรตาม 3.2
>     มาหัก เว้นแต่ในกรณีผู้มีเงินได้มีบุตรตาม 3.1 ที่มีชีวิตอยู่รวมเป็นจำนวนตั้งแต่สามคนขึ้นไป จะนำบุตร
>     ตาม 3.2 มาหักไม่ได้ แต่ถ้าบุตรตาม 3.1 มีจำนวนไม่ถึงสามคนให้นำบุตรตาม 3.2 มาหักได้ โดยเมื่อรวมกับ
>     บุตรตาม 3.1 แล้วต้องไม่เกินสามคน
>
> การหักลดหย่อนสำหรับบุตร ให้หักได้เฉพาะบุตรซึ่งมีอายุไม่เกิน 25 ปี และยังศึกษาอยู่ในมหาวิทยาลัยหรือ
> ชั้นอุดมศึกษา หรือซึ่งเป็นผู้เยาว์ หรือศาลสั่งให้เป็นคนไร้ความสามารถ หรือเสมือนไร้ความสามารถ อันอยู่ใน
> ความอุปการะเลี้ยงดู แต่มิให้หักลดหย่อนสำหรับบุตรดังกล่าวที่มีเงินได้พึงประเมินในปีภาษีที่ล่วงมาแล้ว
> ตั้งแต่ 30,000 บาทขึ้นไป

**Status: VERIFIED (amounts and ordering). PARTIAL (eligibility test — see below).**

Algorithm, in the booklet's own order:

1. Discard every child the taxpayer has not declared eligible.
2. Legitimate children (`child_type = legitimate`) each add 30,000, and a further 30,000 when
   `birth_order ≥ 2` **and** the birth year in พ.ศ. is ≥ 2561 (CE year + 543).
3. Adopted children (`child_type = adopted`) each add 30,000, but only up to
   `3 − (number of legitimate children counted)`, and not at all once three legitimate
   children already count.

`birth_order` is a stored fact, not a position in the submitted list, because 3.1 counts
**every** child "ไม่ว่าจะมีชีวิตอยู่หรือไม่ก็ตาม" — children with no dependent row at all.
Migration `2026_09_12_110000_add_dependent_child_order.php` adds `child_type` and `birth_order`
to `tax_return_dependents` for exactly this reason. Both are nullable, so every row saved
before M7.3 keeps the result it was calculated under.

| Missing fact | Behaviour | Warning |
| --- | --- | --- |
| `child_type` | the child is not counted | `CHILD_TYPE_NOT_DECLARED` |
| `birth_order` or `birth_date` on a legitimate child | 30,000 only, never the extra | `CHILD_BIRTH_ORDER_NOT_DECLARED` |
| more adopted children than the remainder of three | the excess is not counted | `ADOPTED_CHILD_LIMIT_APPLIED` |

**Why eligibility is PARTIAL.** The printed test depends on the child's study status and the
child's own assessable income, neither of which this schema records and neither of which the
engine may decide (PROJECT_REQUIREMENTS §3: no AI determination of eligibility). It is carried
as the taxpayer's declaration on the `eligible` column. The engine counts declarations; it does
not assess them.

## Item 4 — อุปการะเลี้ยงดูบิดามารดา (page 8)

> 4.1 บิดามารดาต้องมีอายุตั้งแต่ 60 ปีขึ้นไป และอยู่ในความอุปการะเลี้ยงดูของผู้มีเงินได้ แต่ต้องไม่มีเงินได้
>     พึงประเมินในปีภาษีที่ขอหักลดหย่อนเกิน 30,000 บาทขึ้นไป
> 4.2 ผู้มีเงินได้หรือคู่สมรสของผู้มีเงินได้ต้องเป็นบุตรชอบด้วยกฎหมาย (บุตรบุญธรรมไม่มีสิทธิหักลดหย่อน)
>     และการหักลดหย่อนหักได้ตลอดปีภาษี
> 4.3 หักลดหย่อนบิดามารดาของผู้มีเงินได้คนละ 30,000 บาท และหักลดหย่อนได้สำหรับบิดามารดา
>     ของคู่สมรสที่ไม่มีเงินได้อีกคนละ 30,000 บาท

**Status: VERIFIED (amount and the spouse condition). PARTIAL (4.1's age and income test).**

| Dependent `relation_type` | Counted when | Amount each |
| --- | --- | --- |
| `father`, `mother` | declared eligible | 30,000.00 |
| `spouse_father`, `spouse_mother` | declared eligible **and** married **and** `spouse.has_income = false` | 30,000.00 |

A spouse's parent declared while the spouse has income is excluded and raises
`SPOUSE_PARENT_NOT_ELIGIBLE`.

4.1's age ≥ 60 and income < 30,000 conditions turn on the parent's own assessable income, which
this schema does not hold. As with item 3, they are the taxpayer's declaration. The booklet
also prints a sole-claimant procedure via แบบ ล.ย.03 which this simulator does not model, since
it concerns who among several siblings may claim, not the amount.

## Item 5 — อุปการะเลี้ยงดูคนพิการหรือคนทุพพลภาพ (pages 8–9)

> 5.1 การหักลดหย่อนค่าอุปการะเลี้ยงดูบิดามารดา คู่สมรส บุตรชอบด้วยกฎหมายหรือบุตรบุญธรรมของผู้มีเงินได้
>     บิดามารดาหรือบุตรชอบด้วยกฎหมายของคู่สมรสของผู้มีเงินได้ หรือบุคคลอื่นที่ผู้มีเงินได้เป็นผู้ดูแล
>     ตามกฎหมายว่าด้วยการส่งเสริมและพัฒนาคุณภาพชีวิตคนพิการ **คนละ 60,000 บาท**

**Status: VERIFIED (amount). PARTIAL (the บุคคลอื่น sub-limit).**

60,000 per declared eligible person. Page 9 adds that a **บุคคลอื่น** — someone outside the
listed family relationships — may certify the taxpayer "ได้ไม่เกิน 1 คน". The schema stores a
single undifferentiated `disabled_person` relation and cannot tell a family member from a
บุคคลอื่น, so the sub-limit is **reported, not applied**: more than one declared person raises
`DISABLED_PERSON_OTHER_LIMIT_UNMODELLED`, which states plainly that the amount shown may exceed
the real entitlement. It is not silently assumed in either direction.

The booklet's documentary requirements (บัตรประจำตัวคนพิการ, ใบรับรองแพทย์, แบบ ล.ย.04 /
ล.ย.04-1) are filing-time evidence, not arithmetic, and are out of scope for a simulator.

## Where the facts come from

One payload shape serves every entry point, so there is one derivation, not two:

| Entry point | Facts source |
| --- | --- |
| `POST /api/v1/tax/calculate` | `profile`, `spouse`, `dependents` on the request |
| `POST /api/v1/tax/plan` | the same three keys inside `base` |
| Member saved return | `tax_return_profiles`, `tax_return_spouses`, `tax_return_dependents`, mapped to those same keys by `MemberTaxCalculationService::inputs()` |

A dependent row saved before M2 introduced `relation_type` carries only the legacy
`relationship` value, cannot name a ใบแนบ line, and is left out rather than assigned one.

## Status summary

| ใบแนบ item | Rule | Status |
| --- | --- | --- |
| 1 ผู้มีเงินได้ | 60,000 for an individual filer | VERIFIED |
| 2 คู่สมรส | 60,000 iff married and spouse has no income | VERIFIED |
| 3 บุตร | 30,000 each; +30,000 for legitimate children from the 2nd born in/after พ.ศ. 2561; adopted capped into a combined three | VERIFIED (amounts, ordering) / PARTIAL (eligibility declared) |
| 4 บิดามารดา | 30,000 per parent; spouse's parents only while the spouse has no income | VERIFIED (amount, spouse condition) / PARTIAL (age and income declared) |
| 5 คนพิการฯ | 60,000 per person | VERIFIED (amount) / PARTIAL (บุคคลอื่น limit of 1 reported, not applied) |

## Tests

- `tests/Unit/FamilyAllowanceStrategyTest.php` — each strategy in isolation, including the
  พ.ศ. 2561 boundary (2017-12-31 vs 2018-01-01) and the three-child interaction in item 3.3.
- `tests/Feature/FilingInstructionReconciliationTest.php` — the same rules through the HTTP
  layer, plus guest/member parity and the fact that a stored calculation is never rewritten.

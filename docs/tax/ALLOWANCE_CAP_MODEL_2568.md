# Allowance cap model — tax year 2568

> **Milestone 7.x is CLOSED.** This file records how a rule was reconciled, milestone by
> milestone, and its earlier sections are historical: a status there may have been superseded
> further down the same file. The authoritative final status of every rule is
> [TAX_ENGINE_BASELINE_2568.md](TAX_ENGINE_BASELINE_2568.md), with per-form detail in
> [PND91_PRODUCTION_BASELINE_2568.md](PND91_PRODUCTION_BASELINE_2568.md) and
> [PND90_PRODUCTION_BASELINE_2568.md](PND90_PRODUCTION_BASELINE_2568.md), and a source map in
> [SOURCE_COVERAGE_2568.md](SOURCE_COVERAGE_2568.md).

Milestone 07.4. Source: **`docs/tax-source/PND90-2568-filing-instructions.pdf`**
(วิธีการกรอกแบบ ภ.ง.ด.90 ปีภาษี 2568, 18 pages), section
*รายการลดหย่อนและยกเว้นหลังจากหักค่าใช้จ่าย*, pages 9–16.

M7.3 read those pages and found ceilings it could not express. The schema could store a
`percentage` but not say what it was a percentage **of**, and it had no way at all to state a
ceiling that covers several allowance codes at once. Rather than seed half of each rule, M7.3
left thirteen lines UNVERIFIED and recorded why. M7.4 adds the two missing concepts and seeds
the lines they unblock.

All pages were read column by column (`pdftotext -layout -f N -l N -x 0/-x 295 -W 300`) and
cross-checked against `pdftoppm -png` renders; this booklet's two-column layout interleaves
when a whole page is extracted at once.

---

## 1. Percentage base

### Vocabulary

`allowance_rules.percentage_base` (VARCHAR(50), nullable) names the amount a rate applies to.
It is a first-class column rather than a key in the conditions JSON because it is the single
most important fact about a percentage rule, and a reviewer must be able to see it beside the
rate. `App\Services\Tax\PercentageBaseResolver` owns the vocabulary:

| Code | Meaning | Available at the allowance stage |
| --- | --- | --- |
| `GROSS_INCOME` | เงินได้พึงประเมิน before any deduction | yes |
| `GROSS_AFTER_EXEMPTION` | assessable income less the income the form records as exempt — the booklet's "เงินได้พึงประเมินที่ได้รับซึ่งต้องเสียภาษีเงินได้" | yes |
| `INCOME_AFTER_EXPENSE` | เงินได้หลังหักค่าใช้จ่าย | yes |

The vocabulary is deliberately this short. A base is listed only when the engine can compute it
**exactly** at the point the rule is applied; the prompt's suggested
`NET_INCOME_BEFORE_ALLOWANCE`, `NET_INCOME_BEFORE_DONATION`, `ALLOWANCE_GROUP_SUBTOTAL` and
`CUSTOM` are **not** defined, because no 2568 rule names them and a speculative value is a
value someone can seed by mistake. A rule naming anything outside the vocabulary is reported
as UNVERIFIED and deducts nothing — it is never silently resolved to a default.

Only `GROSS_AFTER_EXEMPTION` is used by a seeded 2568 rule. The other two exist because the
resolver must be able to refuse a base *by name*, which means the set of names it recognises
has to be explicit.

### Calculation order

`AllowanceCalculator` applies `percentage_limit` in the order the booklet states it —
"เท่าที่ได้จ่าย … ในอัตราไม่เกินร้อยละ N ของ ‹base› … เฉพาะส่วนที่ไม่เกิน ‹cap›":

1. resolve the rule for the declared code;
2. resolve `percentage_base` to an amount;
3. `calculated_before_cap` = min(amount paid, base × percentage);
4. `eligible_amount` = min(that, `maximum_amount`);
5. the combined cap, if any, is applied afterwards by `CombinedAllowanceCapResolver`.

Every step is reported, so a reader can check the arithmetic without re-deriving it:

```json
{
  "code": "RMF",
  "input_amount": "400000.00",
  "method": "percentage_limit",
  "percentage": "30.0000",
  "percentage_base": "GROSS_AFTER_EXEMPTION",
  "base_amount": "1000000.00",
  "calculated_before_cap": "300000.00",
  "maximum_amount": "500000.00",
  "combined_cap_group": "RETIREMENT_SAVINGS_2568",
  "eligible_amount": "300000.00",
  "rule_status": "VERIFIED"
}
```

### Implemented percentage rules

| Code | Rate | Base | Individual cap | Source |
| --- | --- | --- | --- | --- |
| `RMF` | 30% | `GROSS_AFTER_EXEMPTION` | 500,000 | page 11, ใบแนบ item 10.4 |
| `THAI_ESG` | 30% | `GROSS_AFTER_EXEMPTION` | 300,000 | page 14, ใบแนบ item 18.1 |
| `THAI_ESGX` | 30% | `GROSS_AFTER_EXEMPTION` | 300,000 | page 14, ใบแนบ item 19.1 |

**Why `GROSS_AFTER_EXEMPTION`.** All three say *ร้อยละ 30 ของเงินได้พึงประเมินที่ได้รับ**ซึ่งต้อง
เสียภาษีเงินได้***. The qualifier excludes income the return records as exempt, which is exactly
this base and not `GROSS_INCOME`.

**Why `THAI_ESG`'s cap is 300,000 and not 100,000.** Item 18.1 prints a general 100,000 ceiling
for 21 Nov 2566 – 31 Dec 2575, then: *"หากผู้มีเงินได้มีการซื้อหน่วยลงทุนระหว่างวันที่ 1 มกราคม
พ.ศ. 2567 ถึงวันที่ 31 ธันวาคม พ.ศ. 2569 … เฉพาะส่วนที่ไม่เกิน 300,000 บาท"*. Tax year 2568 lies
wholly inside that window, so for this rule version the 300,000 applies to every purchase and
no purchase-date fact is needed. That is a reading of the printed dates, not an assumption.

---

## 2. Combined cap groups

### Model

- `allowance_cap_groups` — `rule_version_id`, `code`, `name`, `maximum_amount`, `percentage`,
  `percentage_base`, `conditions`, `source_reference`, `active`; unique on
  (`rule_version_id`, `code`).
- `allowance_cap_group_members` — `allowance_cap_group_id`, `allowance_type_id`, `sort_order`;
  unique on (group, type), so a type cannot join a group twice. A type may in principle belong
  to more than one group, but no 2568 group needs that and none is seeded that way.

`App\Services\Tax\CombinedAllowanceCapResolver` runs after `AllowanceCalculator`, on items that
have already been capped individually. For each group whose members appear in the calculation
it sums their eligible amounts, compares the sum to the group ceiling, and reduces the group
total to the ceiling when it is exceeded.

### Who gives way

**Nobody in particular.** The booklet states each shared ceiling without saying which member
line is reduced when two are claimed together, so assigning a priority would be inventing law.
The group therefore reports one capped total, and the taxable base depends on that total alone.
Each member also carries an `allocated_amount`, produced by proportional reduction with the
final member absorbing the rounding remainder — this is **presentation only**. Iteration order
cannot change the outcome: the group total is order-independent by construction, and a test
asserts the same total for members submitted in either order.

```json
{
  "combined_cap_groups": [
    {
      "code": "LIFE_AND_HEALTH_INSURANCE_2568",
      "member_codes": ["LIFE_INSURANCE", "HEALTH_INSURANCE"],
      "pre_cap_total": "125000.00",
      "maximum_amount": "100000.00",
      "eligible_total": "100000.00"
    }
  ]
}
```

A group whose ceiling is exceeded raises `COMBINED_ALLOWANCE_CAP_APPLIED`. A group under its
ceiling is still reported, with no warning. A group with a null `maximum_amount` reports its
total and caps nothing. An inactive group is ignored entirely.

### Implemented groups

#### `LIFE_AND_HEALTH_INSURANCE_2568` — 100,000

- **Members:** `LIFE_INSURANCE`, `HEALTH_INSURANCE`
- **Percentage base:** none
- **Source:** page 10, ใบแนบ item 7.4 — *"เบี้ยประกันสุขภาพ … ตามจำนวนที่จ่ายจริงแต่ไม่เกิน
  25,000 บาท ซึ่งเมื่อรวมกับค่าลดหย่อนตามมาตรา 47 (1) (ง) แห่งประมวลรัษฎากร … ต้องไม่เกิน
  100,000 บาท"* (มาตรา 47 (1) (ง) is the เบี้ยประกันชีวิต line)
- **Order:** individual caps 100,000 and 25,000 first, then the shared 100,000.

#### `RETIREMENT_SAVINGS_2568` — 500,000

- **Members:** `PROVIDENT_FUND`, `NSF`, `RMF`
- **Percentage base:** none (each member's own rate applies before the group)
- **Source:** page 10, ใบแนบ item 9 and page 11, ใบแนบ item 10.4
- **Order:** individual caps 10,000 / 500,000 / (30% then 500,000) first, then the shared
  500,000.

**How the membership was derived.** The booklet states the same 500,000 three times, each from
one line's point of view and each naming the others:

| Stated at | Says it is shared with |
| --- | --- |
| item 9 (NSF), page 10 | กองทุนสำรองเลี้ยงชีพ, กบข., กองทุนสงเคราะห์ครูโรงเรียนเอกชน, กองทุนรวมเพื่อการเลี้ยงชีพ, เบี้ยประกันชีวิตแบบบำนาญ |
| item 10.4 (RMF), page 11 | กองทุนสำรองเลี้ยงชีพ, กบข., กองทุนสงเคราะห์ |
| item 7.6 (บำนาญ), page 10 | กองทุนสำรองเลี้ยงชีพ, กบข., กองทุนสงเคราะห์, กองทุนรวมเพื่อการเลี้ยงชีพ |

Every pair that can be claimed together is named as shared by at least one of the three
statements, and all three give the same 500,000, so one group is the reading the source
supports.

**M7.5 correction.** M7.4 called the absence of กบข. and กองทุนสงเคราะห์ครูโรงเรียนเอกชน from
this group a "known gap" and suggested adding master allowance codes for them. The closure
audit read I90 page 2 and found that is the wrong route entirely: the booklet places both under
*เงินได้ที่ได้รับยกเว้น* in ข้อ 1 item 2 —

> (2) เงินสะสม กบข. เฉพาะส่วนที่ไม่เกิน 500,000 บาท
> (3) เงินสะสมกองทุนสงเคราะห์ครูโรงเรียนเอกชน เฉพาะส่วนที่ไม่เกิน 500,000 บาท

— that is, as deductions from income, not as ใบแนบ allowance lines. ใบแนบ prints no box for
either. They are therefore **NOT_APPLICABLE as allowance codes** and reach the engine through
`exempt_amount` on the 40(1) line, as part of a total the taxpayer states. Adding allowance
master codes for them would have invented a line the source does not print. The real limitation
is narrower and is recorded in
[TAX_ENGINE_BASELINE_2568.md](TAX_ENGINE_BASELINE_2568.md): the engine does not enforce the
individual ceilings inside ข้อ 1 item 2.

`PENSION_INSURANCE` is genuinely named in this basket but has no seeded rule, so it is not a
member either. M7.5 made it PARTIAL_BLOCKED — a positive amount is refused rather than silently
omitted, which closes the risk M7.4 described.

Note that `PROVIDENT_FUND`'s own allowance ceiling is 10,000 (ใบแนบ item 8), not 500,000: the
part of a PVD contribution above 10,000 is deducted from income rather than claimed here. Its
membership can therefore only reduce the group total, never raise it.

#### `EASY_E_RECEIPT_2568` — 50,000

- **Members:** `EASY_E_RECEIPT` (30,000), `EASY_E_RECEIPT_OTOP` (20,000)
- **Percentage base:** none
- **Source:** page 13, ใบแนบ item 17 — the overall *"ตามจำนวนที่จ่ายจริงแต่ไม่เกิน 50,000 บาท"*,
  split by 17.1 (*"ไม่เกิน 30,000 บาท"*) and 17.2 (*"หักลดหย่อนได้อีก … ไม่เกิน 20,000 บาท"*
  for OTOP / วิสาหกิจชุมชน / วิสาหกิจเพื่อสังคม)
- **Order:** individual caps first, then the shared 50,000 (which 30,000 + 20,000 already
  satisfies, so the group is a belt-and-braces statement of the printed total).

**Why two codes rather than tiers.** 17.1 and 17.2 are not sequential bands of one amount;
they are two categories of spending with separate ceilings, and ใบแนบ prints them as separate
boxes (17.1–17.3 feed the 30,000 band, 17.4 the 20,000 band). Two codes keep the input
unambiguous — the taxpayer states how much was OTOP spend — where a single amount could not be
split without guessing.

---

## 3. Tiered rules

No **allowance** in the 2568 sources is a sequential band structure, so no
`allowance_rule_tiers` table and no `TieredAllowanceStrategy` were created. Per the milestone
prompt, tiers were added only where a verified rule actually needs them — and the one place
that happens is on the **expense** side.

`expense_rule_tiers` (`expense_rule_id`, `tier_code`, `sort_order`, `threshold_amount`,
`percentage`, `source_reference`) stores ตารางที่ 2 row (1):

| Tier | Band | Rate | Source |
| --- | --- | --- | --- |
| `FIRST_300000` | income up to 300,000 | 60% | page 17, ตารางที่ 2 row (1) (ก) |
| `ABOVE_300000` | income above 300,000 | 40% | page 17, ตารางที่ 2 row (1) (ข) |

with the rule row carrying the printed combined ceiling of 600,000 —
*"การหักค่าใช้จ่ายตาม (ก) และ (ข) รวมกันต้องไม่เกิน 600,000 บาท"*. Each band deducts its rate
from the slice of income inside it; the bands are summed and then the ceiling applies. The
response reports each band's `taxable_slice` and `eligible_amount`, so the boundary is visible
rather than flattened into one blended rate. `ExpenseRuleResolver` refuses a tiered rule that
does not have ordered bands ending in exactly one open band.

See [PND90_EXPENSE_SUBTYPE_MATRIX_2568.md](PND90_EXPENSE_SUBTYPE_MATRIX_2568.md).

---

## 4. Full calculation order

```
income  →  expenses (incl. tiered bands)
        →  allowance: family derivation (ใบแนบ 1–5, M7.3)
        →  allowance: percentage base × rate                    (M7.4)
        →  allowance: individual ceiling
        →  allowance: combined cap groups                       (M7.4)
        →  donations
        →  progressive tax  /  ภ.ง.ด.90 minimum tax             (M7.3)
```

---

## 5. Status

### VERIFIED and seeded (M7.4)

| Code | Method | Rate | Base | Individual cap | Group |
| --- | --- | --- | --- | --- | --- |
| `LIFE_INSURANCE` | actual | — | — | 100,000 | `LIFE_AND_HEALTH_INSURANCE_2568` |
| `HEALTH_INSURANCE` | actual | — | — | 25,000 | `LIFE_AND_HEALTH_INSURANCE_2568` |
| `NSF` | actual | — | — | 500,000 | `RETIREMENT_SAVINGS_2568` |
| `RMF` | percentage_limit | 30% | `GROSS_AFTER_EXEMPTION` | 500,000 | `RETIREMENT_SAVINGS_2568` |
| `THAI_ESG` | percentage_limit | 30% | `GROSS_AFTER_EXEMPTION` | 300,000 | — |
| `THAI_ESGX` | percentage_limit | 30% | `GROSS_AFTER_EXEMPTION` | 300,000 | — |
| `THAI_ESGX_SWITCH` | actual | — | — | 300,000 | — |
| `EASY_E_RECEIPT` | actual | — | — | 30,000 | `EASY_E_RECEIPT_2568` |
| `EASY_E_RECEIPT_OTOP` | actual | — | — | 20,000 | `EASY_E_RECEIPT_2568` |

`THAI_ESGX_SWITCH` is ใบแนบ item 19.2, a separate printed box from 19.1: page 15 —
*"ยกเว้นภาษีเงินได้เท่ากับจำนวนมูลค่าหน่วยลงทุนที่สับเปลี่ยนดังกล่าวแต่ไม่เกิน 500,000 บาท …
(1) ปีภาษี 2568 ให้ได้รับยกเว้นภาษีเงินได้เฉพาะส่วนที่ไม่เกิน 300,000 บาท"*. For this rule
version the 300,000 is the operative figure; the 500,000 spans 2568–2572.

### Still PARTIAL after M7.4

| Code | Source | Why it is not seeded |
| --- | --- | --- |
| `PENSION_INSURANCE` | page 10, ใบแนบ item 7.6 | Two entitlements are printed — *"ตามจำนวนที่จ่ายจริง แต่ไม่เกิน 90,000 บาท"* and, *"เพิ่มขึ้นอีก"*, *"ร้อยละ 15 ของเงินได้พึงประเมิน … แต่ไม่เกิน 200,000 บาท"* — without saying whether the 90,000 sits inside the 200,000 or on top of it. The two readings differ by 90,000 of deduction, so the rate alone does not make the rule VERIFIED. |
| `SOCIAL_SECURITY` | page 12, ใบแนบ item 12 | *"หักลดหย่อนได้ตามที่จ่ายจริงตามกฎหมายว่าด้วยการประกันสังคม"* — the numeric limit is in the social security act, which is not a repository source. Unchanged from M7.3, and unchanged by M7.4. |

### Lines with no master allowance code

ใบแนบ items 13 (กล้องโทรทัศน์วงจรปิด), 16 (เงินลงทุนในหุ้นวิสาหกิจเพื่อสังคม), 20 (ค่าจ้าง
ก่อสร้างอาคาร) and 22 (ค่าท่องเที่ยวภายในประเทศ) still have no allowance type and no rule.
Item 22 is the one that cannot be fixed by modelling alone: page 16 gives เมืองรอง a 1.5×
multiplier and then states *"สำหรับส่วนที่เกิน 10,000 บาท ให้สามารถหักลดหย่อนได้ 1.5 เท่าของ
จำนวนที่จ่ายจริง"* **with no upper limit printed**, so an implementation would have to invent
the ceiling.

---

## 6. Tests

- `tests/Unit/PercentageBaseResolverTest.php` — the vocabulary, and that an unknown or
  unavailable base is a conflict rather than a zero.
- `tests/Feature/AllowanceCapEngineTest.php` — percentage arithmetic and its boundaries, each
  group, order independence, a ceiling-less group, an inactive group, seeding idempotency, and
  planning through the shared engine.
- `tests/Feature/Pnd90ExpenseSubtypeTest.php` — the tiered expense bands and their combined
  ceiling.

---

## 7. Milestone 07.5 closure — final status and runtime behaviour

The two lines this document left PARTIAL now **refuse** a positive amount instead of deducting
nothing. Silently returning zero was the one failure mode the closure audit set out to remove:
the taxpayer asked for a deduction they had reason to expect, and the response carried a lower
deduction total without saying no.

| Code | Final status | Runtime | Guard code |
| --- | --- | --- | --- |
| `PENSION_INSURANCE` | PARTIAL_BLOCKED | 422 on a positive amount; 0.00 accepted | `PENSION_INSURANCE_RULE_PARTIAL` |
| `SOCIAL_SECURITY` | PARTIAL_BLOCKED | 422 on a positive amount; 0.00 accepted | `SOCIAL_SECURITY_RULE_UNSUPPORTED` |
| `INSURANCE`, `ANNUAL_TAX_MEASURES`, `OTHER` | UNSUPPORTED | 422 on a positive amount; 0.00 accepted | `ALLOWANCE_RULE_UNSUPPORTED` |

`App\Services\Tax\AllowanceCoverageCatalogue` owns the classification, and a closure test
asserts it names exactly the master codes that are neither family-derived nor backed by a
seeded rule — so seeding a rule for a blocked code, or adding a code with no rule, cannot leave
the classification stale.

Everything else in this document is unchanged and is now frozen at rule version 2568.1. See
[TAX_ENGINE_BASELINE_2568.md](TAX_ENGINE_BASELINE_2568.md).

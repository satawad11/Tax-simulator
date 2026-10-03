# PND90 expense subtype matrix — tax year 2568

> **Milestone 7.x is CLOSED.** This file records how a rule was reconciled, milestone by
> milestone, and its earlier sections are historical: a status there may have been superseded
> further down the same file. The authoritative final status of every rule is
> [TAX_ENGINE_BASELINE_2568.md](TAX_ENGINE_BASELINE_2568.md), with per-form detail in
> [PND91_PRODUCTION_BASELINE_2568.md](PND91_PRODUCTION_BASELINE_2568.md) and
> [PND90_PRODUCTION_BASELINE_2568.md](PND90_PRODUCTION_BASELINE_2568.md), and a source map in
> [SOURCE_COVERAGE_2568.md](SOURCE_COVERAGE_2568.md).

Milestone 07.4. Sources:

- **`docs/tax-source/PND90-2568-filing-instructions.pdf`** — page 3 (ข้อ 4 and ข้อ 7 expense
  rules) and page 17 (ตารางที่ 2, ตารางอัตราการหักค่าใช้จ่ายเป็นการเหมาสำหรับเงินได้พึงประเมิน
  ตามมาตรา 40 (8));
- **`docs/tax-source/PND90-2568-form.pdf`** — pages 2–3, for the printed line structure and the
  `☐ จริง` checkboxes.

Page 17's table and page 3's two rate tables were read with column-isolated extraction and
then read again from `pdftoppm -png -r 170/200` renders of the same pages; every percentage
recorded here appears identically in both.

## What M7.4 closed

M7.1 reconciled the ภ.ง.ด.90 expense rules from the form alone and left three subcategories
UNVERIFIED, because the form prints them as `หักค่าใช้จ่าย ☐ ร้อยละ ............ ☐ จริง` — a
blank the taxpayer fills in. The blank is not an omission: the rate is a property of a further
fact, not of the income category, and the filing instructions supply the table that fact
indexes into.

| Subcategory | The further fact | Where the rate is printed |
| --- | --- | --- |
| ข้อ 4 item 1 (2)–(4) | which asset is let | booklet page 3, ข้อ 4 การหักค่าใช้จ่าย (1) วิธีที่ 2 |
| ข้อ 7 item 1 | which ตารางที่ 2 activity | booklet page 17, ตารางที่ 2 |
| ข้อ 7 item 3 (2) | จำนวนปีที่ถือครอง | booklet page 3, ข้อ 7 item 3 (2) วิธีที่ 2 |

All three keep the form's `จริง` checkbox, so every rule below is an election method: the
taxpayer chooses the printed rate or their actual expense, and the engine never chooses.

---

## 1. มาตรา 40 (5) — การให้เช่าทรัพย์สิน

Booklet page 3, verbatim:

> (1) การให้เช่าทรัพย์สิน ผู้มีเงินได้เลือกหักตามวิธีใดวิธีหนึ่งดังนี้
>   วิธีที่ 1 หักค่าใช้จ่ายจริงตามความจำเป็นและสมควร
>   วิธีที่ 2 หักค่าใช้จ่ายเป็นการเหมาในอัตราดังนี้
>   (ก) บ้าน โรงเรือน สิ่งปลูกสร้างอย่างอื่นหรือแพ  ร้อยละ 30
>   (ข) ที่ดินที่ใช้ในการเกษตรกรรม                  ร้อยละ 20
>   (ค) ที่ดินที่มิได้ใช้ในการเกษตรกรรม             ร้อยละ 15
>   (ง) ยานพาหนะ                                    ร้อยละ 30
>   (จ) ทรัพย์สินอย่างอื่น                          ร้อยละ 10

The form prints (1) with its 30% filled in and leaves (2), (3) and (4) as `อื่นๆ (ระบุ)` with a
blank rate — one printed line per further asset the taxpayer lets. The five classes are
therefore modelled as five subtypes, replacing M7.1's single `RENT_OTHER`.

| Income type | Subtype | Source label | Source | Percentage | Cap | Actual expense | Conditions | Status | Implementation |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `SECTION_40_5` | `RENT_BUILDING_OR_RAFT` | (ก) บ้าน โรงเรือน สิ่งปลูกสร้างอย่างอื่นหรือแพ | booklet p.3 / form p.2 ข้อ 4 item 1 (1) | 30% | none | yes | none | **VERIFIED** | seeded in M7.1 |
| `SECTION_40_5` | `RENT_LAND_AGRICULTURAL` | (ข) ที่ดินที่ใช้ในการเกษตรกรรม | booklet p.3 | 20% | none | yes | none | **VERIFIED** | seeded in M7.4 |
| `SECTION_40_5` | `RENT_LAND_NON_AGRICULTURAL` | (ค) ที่ดินที่มิได้ใช้ในการเกษตรกรรม | booklet p.3 | 15% | none | yes | none | **VERIFIED** | seeded in M7.4 |
| `SECTION_40_5` | `RENT_VEHICLE` | (ง) ยานพาหนะ | booklet p.3 | 30% | none | yes | none | **VERIFIED** | seeded in M7.4 |
| `SECTION_40_5` | `RENT_OTHER_PROPERTY` | (จ) ทรัพย์สินอย่างอื่น | booklet p.3 | 10% | none | yes | none | **VERIFIED** | seeded in M7.4 |
| `SECTION_40_5` | `HIRE_PURCHASE_BREACH` | ข้อ 4 item 2 — การผิดสัญญาเช่าซื้อ/ซื้อขายเงินผ่อนฯ | form p.3 | 20% | none | no (`ได้วิธีเดียว`) | none | **VERIFIED** | seeded in M7.1 |

`RENT_OTHER` is retired: it carried no rate and, now that all five classes are named, could
only be ambiguous. A payload using it is rejected with 422 on `income_subtype`.

**Not modelled.** Page 3 adds *"กรณีให้เช่าช่วง ให้หักค่าใช้จ่ายจากค่าเช่าที่เสียให้แก่ผู้ให้เช่าเดิม
หรือผู้ให้เช่าช่วง"* — sub-letting deducts the rent actually paid on. That is the `จริง`
election with a stated measure, and the engine already supports the election; nothing extra is
seeded for it.

---

## 2. มาตรา 40 (8) ข้อ 7 item 1 — ตารางที่ 2

Booklet page 3: *"1. เงินได้จากการธุรกิจ การพาณิชย์ การเกษตร การอุตสาหกรรม การขนส่งหรือการอื่น …
(การหักค่าใช้จ่ายดูตารางที่ 2 หน้า 17)"*. Form page 3 prints four `(ระบุ)` lines, each with
`หักค่าใช้จ่าย ร้อยละ............  ☐ จริง`.

Page 17's table numbers **44** activities. Forty-two carry a flat 60%; two do not.

### Schema

`expense_rules.expense_activity` (VARCHAR(50), nullable) selects the rule, alongside the income
type and subtype. `App\Services\Tax\ExpenseActivityCatalogue` holds the 44 codes with the
source label and row number of each, so the taxpayer picks a printed row rather than typing
text: **no free-text description selects a tax rule**. An absent or unknown activity on this
subcategory is a 422 on `incomes.*.expense_activity`; an activity on any other category is also
a 422. One rule row is seeded per activity, each citing its own table row.

### The three rule shapes

| Activity | Source | Percentage | Cap | Actual expense | Status | Implementation |
| --- | --- | --- | --- | --- | --- | --- |
| `TABLE2_01_PERFORMER` (row 1) | p.17 ตารางที่ 2 (1) (ก)(ข) | 60% of the first 300,000, then 40% | 600,000 combined | yes | **VERIFIED** | seeded, `tiered_or_actual` + two `expense_rule_tiers` |
| rows (2)–(43) | p.17 ตารางที่ 2 | 60% | none | yes | **VERIFIED** | seeded, `percentage_or_actual`, 42 rules |
| `TABLE2_44_UNLISTED` (row 44) | p.17 ตารางที่ 2 (44) | none printed | none | actual only | **VERIFIED** | seeded, `actual` |

Row (1) verbatim:

> (1) การแสดงของนักแสดงละคร ภาพยนตร์ วิทยุหรือโทรทัศน์ นักร้อง นักดนตรี นักกีฬาอาชีพ หรือ
>     นักแสดงเพื่อความบันเทิงใดๆ
>     (ก) สำหรับเงินได้ส่วนที่ไม่เกิน 300,000 บาท            60
>     (ข) สำหรับเงินได้ส่วนที่เกิน 300,000 บาท               40
>     การหักค่าใช้จ่ายตาม (ก) และ (ข) รวมกันต้องไม่เกิน 600,000 บาท

Row (44) verbatim: *"เงินได้ประเภทที่มิได้ระบุใน (1) ถึง (43) ให้หักค่าใช้จ่ายจริงตามความจำเป็น
และสมควร"* — which is why it is `actual` and offers no percentage election.

### The 44 rows

| # | Activity code | % | # | Activity code | % |
| --- | --- | --- | --- | --- | --- |
| 1 | `TABLE2_01_PERFORMER` | 60/40 | 23 | `TABLE2_23_ICE_MAKING` | 60 |
| 2 | `TABLE2_02_LAND_INSTALMENT_SALE` | 60 | 24 | `TABLE2_24_GLUE_AND_STARCH` | 60 |
| 3 | `TABLE2_03_GAMBLING_TABLE_FEES` | 60 | 25 | `TABLE2_25_BALLOONS_GLASS_PLASTIC_RUBBER` | 60 |
| 4 | `TABLE2_04_PHOTOGRAPHY` | 60 | 26 | `TABLE2_26_LAUNDRY_OR_DYEING` | 60 |
| 5 | `TABLE2_05_SHIPYARD` | 60 | 27 | `TABLE2_27_RESELLING_GOODS` | 60 |
| 6 | `TABLE2_06_FOOTWEAR_AND_LEATHER` | 60 | 28 | `TABLE2_28_RACEHORSE_PRIZES` | 60 |
| 7 | `TABLE2_07_GARMENT_MAKING` | 60 | 29 | `TABLE2_29_SALE_WITH_RIGHT_OF_REDEMPTION` | 60 |
| 8 | `TABLE2_08_FURNITURE` | 60 | 30 | `TABLE2_30_RUBBER_SMOKING_AND_SHEETING` | 60 |
| 9 | `TABLE2_09_HOTEL_OR_RESTAURANT` | 60 | 31 | `TABLE2_31_TANNING` | 60 |
| 10 | `TABLE2_10_HAIRDRESSING` | 60 | 32 | `TABLE2_32_SUGAR_MAKING` | 60 |
| 11 | `TABLE2_11_SOAP_SHAMPOO_COSMETICS` | 60 | 33 | `TABLE2_33_FISHING` | 60 |
| 12 | `TABLE2_12_LITERARY_WORK` | 60 | 34 | `TABLE2_34_SAWMILL` | 60 |
| 13 | `TABLE2_13_PRECIOUS_METAL_AND_GEM_TRADE` | 60 | 35 | `TABLE2_35_OIL_REFINING_OR_PRESSING` | 60 |
| 14 | `TABLE2_14_INPATIENT_MEDICAL_FACILITY` | 60 | 36 | `TABLE2_36_HIRE_PURCHASE_OF_MOVABLE_PROPERTY` | 60 |
| 15 | `TABLE2_15_STONE_MILLING` | 60 | 37 | `TABLE2_37_RICE_MILL` | 60 |
| 16 | `TABLE2_16_FORESTRY_AND_PLANTATION` | 60 | 38 | `TABLE2_38_ANNUAL_CROPS_AND_CEREALS` | 60 |
| 17 | `TABLE2_17_TRANSPORT_BY_VEHICLE` | 60 | 39 | `TABLE2_39_TOBACCO_CURING` | 60 |
| 18 | `TABLE2_18_PRINTING_AND_BOOKBINDING` | 60 | 40 | `TABLE2_40_LIVESTOCK` | 60 |
| 19 | `TABLE2_19_MINING` | 60 | 41 | `TABLE2_41_SLAUGHTERING` | 60 |
| 20 | `TABLE2_20_EXCISE_BEVERAGES` | 60 | 42 | `TABLE2_42_SALT_FARMING` | 60 |
| 21 | `TABLE2_21_CERAMICS_AND_CEMENT` | 60 | 43 | `TABLE2_43_SALE_OF_SHIPS_AND_RAFTS` | 60 |
| 22 | `TABLE2_22_ELECTRICITY` | 60 | 44 | `TABLE2_44_UNLISTED` | actual only |

The Thai label of each row is stored verbatim in `ExpenseActivityCatalogue::ACTIVITIES`, so a
reviewer can match a code back to page 17 without leaving the codebase.

---

## 3. มาตรา 40 (8) ข้อ 7 item 3 (2) — จำนวนปีที่ถือครอง

Form page 3 prints this line as `หักค่าใช้จ่าย ร้อยละ............  ☐ จริง` **plus a dedicated
field**, `จำนวนปีที่ถือครอง ………. ปี`. Booklet page 3:

> (2) การขายอสังหาริมทรัพย์ที่ได้มาโดยมิได้มุ่งในทางการค้าหรือหากำไร ให้เลือกหักค่าใช้จ่าย
>     ตามวิธีใดวิธีหนึ่งดังนี้
>     วิธีที่ 1 หักค่าใช้จ่ายจริงตามความจำเป็นและสมควร
>     วิธีที่ 2 หักค่าใช้จ่ายเป็นการเหมาในอัตราดังนี้
>     จำนวนปีที่ถือครอง*  1 ปี  2 ปี  3 ปี  4 ปี  5 ปี  6 ปี  7 ปี  8 ปีขึ้นไป
>     ร้อยละของเงินได้      92    84    77    71    65    60    55    50
> * จำนวนปีที่ถือครอง หมายถึง จำนวนปีนับตั้งแต่ปีที่ได้กรรมสิทธิ์ หรือสิทธิครอบครองในอสังหาริมทรัพย์
>   ถึงปีที่โอนกรรมสิทธิ์หรือสิทธิครอบครองในอสังหาริมทรัพย์นั้น ถ้าเกิน 10 ปี ให้นับเพียง 10 ปี
>   เศษของปีให้นับเป็น 1 ปี การนับจำนวนปีที่ถือครองให้ถือตามปีปฏิทิน

### Schema

`expense_rules.holding_years_min` / `holding_years_max` (both nullable, unsigned smallint)
express a band, so `8 ปีขึ้นไป` is one row with a null maximum. The taxpayer states
`holding_years` on the income line, which the guard requires for this subcategory and rejects
everywhere else.

| Band | Source | Percentage | Cap | Actual expense | Status | Implementation |
| --- | --- | --- | --- | --- | --- | --- |
| 1 year | booklet p.3 | 92% | none | yes | **VERIFIED** | seeded |
| 2 years | booklet p.3 | 84% | none | yes | **VERIFIED** | seeded |
| 3 years | booklet p.3 | 77% | none | yes | **VERIFIED** | seeded |
| 4 years | booklet p.3 | 71% | none | yes | **VERIFIED** | seeded |
| 5 years | booklet p.3 | 65% | none | yes | **VERIFIED** | seeded |
| 6 years | booklet p.3 | 60% | none | yes | **VERIFIED** | seeded |
| 7 years | booklet p.3 | 55% | none | yes | **VERIFIED** | seeded |
| 8 years or more | booklet p.3 | 50% | none | yes | **VERIFIED** | seeded |

`PropertyHoldingPeriodCatalogue::counted()` applies *"ถ้าเกิน 10 ปี ให้นับเพียง 10 ปี"* before
the table is read; since the table's open band starts at 8, that ceiling never changes the rate
in practice, but it is applied as printed rather than ignored. *"เศษของปีให้นับเป็น 1 ปี"* means
a stated period is a whole number of years and at least one, which the guard enforces (0 or a
negative value is a 422).

---

## 4. Unchanged subcategories

These were reconciled in M7.1 from the form itself and M7.4 does not alter them:

| Income type | Subtype | Method | % | Cap | Status |
| --- | --- | --- | --- | --- | --- |
| `SECTION_40_1` / `SECTION_40_2` | — (shared group `SECTION_40_1_2`) | percentage_limit | 50 | 100,000 | VERIFIED |
| `SECTION_40_3` | `ANNUITY_FROM_WILL_OR_JUDGMENT` | fixed | — | — | VERIFIED (0.00) |
| `SECTION_40_3` | `COPYRIGHT_GOODWILL_OTHER_RIGHTS` | percentage_or_actual | 50 | 100,000 | VERIFIED |
| `SECTION_40_4` | — | fixed | — | — | VERIFIED (0.00) |
| `SECTION_40_6` | `MEDICAL_PRACTICE` | percentage_or_actual | 60 | — | VERIFIED |
| `SECTION_40_6` | `FINE_ARTS` | percentage_or_actual | 60 | — | VERIFIED |
| `SECTION_40_6` | `OTHER_LIBERAL_PROFESSION` | percentage_or_actual | 30 | — | VERIFIED |
| `SECTION_40_7` | — | percentage_or_actual | 60 | — | VERIFIED |
| `SECTION_40_8` | `MUTUAL_FUND_PROFIT_SHARE` | fixed | — | — | VERIFIED (0.00) |
| `SECTION_40_8` | `IMMOVABLE_PROPERTY_INHERITED_OR_GIFTED` | percentage | 50 | — | VERIFIED |
| `SECTION_40_8` | `GIFT_OR_SUPPORT_RECEIVED` | fixed | — | — | VERIFIED |

---

## 5. Request shape

```json
{
  "income_type": "SECTION_40_8",
  "income_subtype": "BUSINESS_COMMERCE_OTHER",
  "expense_activity": "TABLE2_09_HOTEL_OR_RESTAURANT",
  "gross_amount": "1000000.00",
  "expense_method_selection": "percentage"
}
```

```json
{
  "income_type": "SECTION_40_8",
  "income_subtype": "IMMOVABLE_PROPERTY_NON_TRADE",
  "holding_years": 5,
  "gross_amount": "1000000.00",
  "expense_method_selection": "percentage"
}
```

Both fields are optional on the request and required by the guard exactly where the form prints
them. Validation: the subtype must exist and belong to the income type; the activity must be
one of the 44 printed rows; the holding period must be a whole number of years, at least 1.
Anything else is a 422 — never a default rate.

Two income lines that differ only in activity or holding period are two expense groups, each
with its own rate, and the response reports `expense_activity` and `holding_years` per item.

The same two fields exist on a saved member income line (`tax_return_incomes`), validated by
the same rules, so guest and member calculations use one payload shape and produce identical
results.

---

## 6. Remaining expense gaps

None in ภ.ง.ด.90's printed expense structure: every income type and subtype the form defines
now has a verified rule or a verified per-key rule set. What remains is outside the expense
tables — see [ALLOWANCE_CAP_MODEL_2568.md](ALLOWANCE_CAP_MODEL_2568.md) for the allowance
lines still PARTIAL, and [PND90_RULE_MATRIX.md](PND90_RULE_MATRIX.md) for the full matrix.

## 7. Tests

- `tests/Feature/Pnd90ExpenseSubtypeTest.php` — every rent class, a table-2 activity, all four
  performer band cases, the unlisted row, all eight holding-period bands plus the ten-year
  ceiling, the 422s for a missing/unknown/misplaced fact, a mixed four-line PND90 return, and
  guest/member parity.
- `tests/Feature/Pnd90RuleReconciliationTest.php` — that the seeded set holds exactly one rule
  per printed key, with no gaps and no invented keys.

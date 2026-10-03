# Line-by-line audit against the printed forms

2026-09-17. Asked to re-check for anything missed, because a wrong figure is the one failure this
product cannot afford. This audit reads **the form PDFs themselves**, not the notes about them —
which is what made the difference: the one real defect had been documented as correct.

Sources: `docs/tax-source/PND90-2568-form.pdf` (5 pages) and `PND91-2568-form.pdf` (3 pages), text
extracted with `pdftotext -layout`.

---

## The defect: ภ.ง.ด.94 was accepted on ภ.ง.ด.91 — **fixed**

| | ภ.ง.ด.90 item 15 | ภ.ง.ด.91 line 15 |
| --- | --- | --- |
| ภาษีเงินได้หัก ณ ที่จ่าย | printed | printed |
| ภาษีเงินได้ชำระไว้ตามแบบ **ภ.ง.ด.93** | printed | printed |
| ภาษีเงินได้ชำระไว้ตามแบบ **ภ.ง.ด.94** | printed | **absent** |

The string `94` does not occur anywhere in the ภ.ง.ด.91 form. That follows from what the form is
for: ภ.ง.ด.94 is the half-year return for มาตรา 40 (5)–(8), and ภ.ง.ด.91 carries มาตรา 40 (1)
alone, so a filer on this form cannot have filed one.

**The engine accepted it and subtracted it in full, with no warning:**

```
POST /tax/calculate  PND91, salary 720,000, withholdings [{pnd94, 5000}]
→ 200  tax 36,500 · credits 5,000 · PAYABLE 31,500      ← 5,000 too little
```

### Why it survived every review

`docs/tax/PND91_PRODUCTION_BASELINE_2568.md` justified it as *"ข้อ 11 item 15 checkboxes"* — and
**ข้อ 11 is a ภ.ง.ด.90 section**. ภ.ง.ด.91 has no ข้อ 11 at all; its calculation lines are numbered
1–22 directly. A ภ.ง.ด.90 fact had been carried onto the other form and then cited as its source.

Two tests had the same shape and locked it in rather than catching it:

- `Pnd90RemainingRulesTest::test_prepayments_accumulate_across_the_three_checkboxes` — "three
  checkboxes" is true of ภ.ง.ด.90, and the helper it used defaults to **PND91**;
- `TaxEngineBaselineClosureTest::test_every_supported_prepayment_…` ran every prepaid type against
  ภ.ง.ด.91.

A test that agrees with a defect is how the defect passes a suite of a thousand.

### The fix

`TaxCalculationInputGuard` now knows which form it is judging, and refuses a positive `pnd94` on
ภ.ง.ด.91 with `PND94_NOT_ON_THIS_FORM`, naming the reason. ภ.ง.ด.90 is untouched — it prints all
three lines and still accepts all three. A zero amount is still not refused, consistent with every
other blocked figure here: it cannot change the tax, so refusing it would be noise.

**The changed figure:** ภ.ง.ด.91 with salary 720,000 and a 5,000 ภ.ง.ด.94 entry was
`PAYABLE 31,500`; it is now a 422. The correct tax on that return, with no valid credit, is
`PAYABLE 36,500`.

---

## Verified correct, line by line

| Form line | Rule as printed | Engine |
| --- | --- | --- |
| ภ.ง.ด.91 1–5 | salary, exempt income, 50% expense capped | ✓ |
| ภ.ง.ด.91 6–11 / ภ.ง.ด.90 ข้อ 11 2–7 | allowances, then two donation stages | ✓ two stages, 2× capped 10%, then 10% of the reduced base |
| **ภ.ง.ด.90 ข้อ 11 9** | *เงินได้พึงประเมินตั้งแต่ 120,000 บาทขึ้นไป … ไม่รวมเงินได้ตามมาตรา 40 (1) × 0.005* | ✓ all four parameters match `MinimumTaxCalculator` |
| **ภ.ง.ด.90 ข้อ 11 10** | *จำนวนที่มากกว่าระหว่าง 8. กับ 9. เว้นแต่ 9. ไม่เกิน 5,000 บาท ให้ชำระตาม 8.* | ✓ greater-of with the 5,000 de-minimis |
| ใบแนบ 1 | *60,000 บาท หรือ 120,000 บาท แล้วแต่กรณี* | ✓ 120,000 belongs to a ห้างหุ้นส่วนสามัญ/คณะบุคคล with 2+ resident members, which this product does not model; an individual is always 60,000 (booklet p.7, cited in `PersonalAllowanceStrategy`) |
| ใบแนบ 2–5 | family lines, per-person amounts | ✓ derived from declared facts |
| ใบแนบ 6–12, 14, 15, 17–19, 21, 23 | printed ceilings | ✓ mapped to master codes |
| ภ.ง.ด.90 ข้อ 8, ข้อ 3 | separate-tax elections | documented **UNSUPPORTED** — not silent |
| ภ.ง.ด.90 ข้อ 9 | 5% on the non-exempt portion | ✓ |
| ภ.ง.ด.91 17–22 / ภ.ง.ด.90 16–21 | amended returns, เงินเพิ่ม | out of scope — filing mechanics, not calculation |

## Item 1 — four printed deduction lines were invisible — **fixed**

ใบแนบ items **13** (กล้องวงจรปิด), **16** (วิสาหกิจเพื่อสังคม), **20** (ค่าจ้างก่อสร้างบ้าน) and
**22** (ค่าท่องเที่ยวภายในประเทศ) had no master code, so they appeared nowhere in the product — not
even in the *"รายการที่มีในแบบแต่ยังไม่รองรับอัตโนมัติ"* section, which lists only codes that exist.

A filer entitled to one of them got a higher tax than the form would give, and **was never told
why**. That is the same shape as the family-allowance defect: not a refusal, a quiet wrong answer.

### What the fix changed

The root cause was one flag doing two jobs. "The engine cannot calculate this line" and "ใบแนบ does
not print this line at all" were both expressed as *absence* — no master code, or no rule — so the
interface could not distinguish a printed line it cannot compute from an umbrella category the
attachment never prints. The result was the worst of both: four printed lines hidden, three umbrella
categories shown under a heading promising they appear on the form.

`AllowanceCoverageCatalogue` now carries a separate `printed` flag, surfaced by the API as
`coverage.printed_on_form`, and the wizard lists a line when the form prints it and the engine
cannot calculate it — not when a rule happens to be missing.

| Code | ใบแนบ | Status | Why it cannot be calculated (the reason the reader sees) |
| --- | --- | --- | --- |
| `CCTV_SYSTEM` | ข้อ 13 | `UNSUPPORTED` | ร้อยละ 100 ของที่จ่ายจริง แต่ผูกกับเงื่อนไขเขตพัฒนาพิเศษเฉพาะกิจ และเงินได้ตามมาตรา 40 (5)-(8) |
| `SOCIAL_ENTERPRISE_INVESTMENT` | ข้อ 16 | `UNSUPPORTED` | สิทธิขึ้นกับการจดทะเบียนต่อสำนักงานส่งเสริมวิสาหกิจเพื่อสังคม ซึ่งไม่ใช่ข้อมูลที่ผู้ยื่นกรอกได้ |
| `NEW_HOME_CONSTRUCTION` | ข้อ 20 | `UNSUPPORTED` | 10,000 บาท ต่อทุก 1,000,000 บาท รวมไม่เกิน 100,000 บาท เฉพาะช่วง 9 เม.ย. 2567 – 31 ธ.ค. 2568 |
| `DOMESTIC_TRAVEL` | ข้อ 22 | `UNSUPPORTED` | เพดานต่างกันระหว่างเมืองหลักและเมืองรอง (10,000 บาทแรกหักได้ 1.5 เท่า) |

Each reason is quoted from the filing-instruction booklet for that item; none is a generic sentence,
and none is inferred. **No calculation changed**: all four refuse a positive amount with 422 exactly
as before, and accept zero. The product simply stopped being silent about them.

The three umbrella categories — `INSURANCE`, `ANNUAL_TAX_MEASURES`, `OTHER` — are now marked
`printed_on_form: false` and no longer appear in that section, because ใบแนบ prints no such line and
showing them sent the reader looking for something that does not exist.

### Verification of item 1

| Check | Result |
| --- | --- |
| `AllowanceCoverageMetadataTest` | 10 passed, 139 assertions (2 new tests) |
| SQLite full suite | 1,108 passed, 2 skipped, 6,162 assertions |
| MySQL full suite | 1,110 passed, 6,592 assertions |
| Pint | 363 files, pass |
| `npm run build` | 21 modules, pass |
| Live `/api/v1/tax-years/2568/allowances` | 29 types; **6 shown**, **3 hidden** — exactly as above |
| Live wizard, ภ.ง.ด.90 step 2 | six cards render, each with its own reason |

## One thing still to decide

### The foreign tax credit ceiling **is** printed

Both forms print line 13 as:

> หัก เครดิตภาษีเงินได้จากต่างประเทศ **(ไม่เกินภาษีที่ต้องเสียตามกฎหมายประเทศไทย)**

and line 14 as `12. − 13.`. The engine refuses any positive amount with
`FOREIGN_TAX_CREDIT_UNSUPPORTED: … ไม่ได้พิมพ์หลักเกณฑ์และเพดาน … ไว้อย่างชัดเจนพอ` — but the
**ceiling is printed**, and `TaxRefundService` already implements exactly that arithmetic
(`$tax->subtract($foreign)->max(0)`).

What the form does *not* print is **eligibility** — which foreign taxes qualify, under which treaty,
at what exchange rate. That is a real gap.

So the question is whether foreign tax credit should be treated like every other taxpayer-declared
figure in this product — the filer states the amount, the engine applies only the printed cap and
asserts no entitlement — as it already does for exempt income, dependants and every allowance.

**I have not changed this.** It would alter tax results, and the argument for refusing it is
coherent even if its stated reason is not quite right. Your call; the message should be corrected
either way, since it says the ceiling is missing and the ceiling is on the form.

## Known limitation, unchanged and documented

The exempt-income section (ภ.ง.ด.91 items 1–5 of รายการเงินได้ที่ได้รับยกเว้น) prints five
categories with their own ceilings — PVD above 10,000, กบข., กองทุนสงเคราะห์ครูโรงเรียนเอกชน, the
disabled/65-and-over exemption, and statutory severance. The engine takes **one** taxpayer-stated
total and does not verify its composition. Already recorded in the baseline.

## Verification

| Check | Result |
| --- | --- |
| `PrepaidCreditBelongsToItsFormTest` (new) | 7 passed |
| SQLite full suite | 1,098 passed, 2 skipped, 6,065 assertions |
| MySQL full suite | 1,099 passed, 2 skipped |
| Pint | 296 files, pass |
| Live dev server | PND91 + pnd94 → **422**; PND91 + pnd93 → 200; PND90 + pnd94 → 200 |

The frozen M4 regression figure (no family, net 620,000, tax 45,500) is untouched, and no published
rule version, seeder or source document was modified.

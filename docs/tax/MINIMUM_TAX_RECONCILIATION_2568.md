# Minimum tax reconciliation — ภ.ง.ด.90, tax year 2568

> **Milestone 7.x is CLOSED.** This file records how a rule was reconciled, milestone by
> milestone, and its earlier sections are historical: a status there may have been superseded
> further down the same file. The authoritative final status of every rule is
> [TAX_ENGINE_BASELINE_2568.md](TAX_ENGINE_BASELINE_2568.md), with per-form detail in
> [PND91_PRODUCTION_BASELINE_2568.md](PND91_PRODUCTION_BASELINE_2568.md) and
> [PND90_PRODUCTION_BASELINE_2568.md](PND90_PRODUCTION_BASELINE_2568.md), and a source map in
> [SOURCE_COVERAGE_2568.md](SOURCE_COVERAGE_2568.md).

Milestone 07.3. Source: **`docs/tax-source/PND90-2568-filing-instructions.pdf`**
(วิธีการกรอกแบบ ภ.ง.ด.90 ปีภาษี 2568, 18 pages), **page 6**, section
*คำนวณภาษีจาก 2 วิธี (แล้วให้ชำระภาษีจากยอดที่มากกว่า)*.

Read with column-isolated extraction (`pdftotext -layout -f 6 -l 6 -x 0 -W 300` for the left
column, `-x 295 -W 300` for the right) and cross-checked against a `pdftoppm -png -r 170`
render of the same page.

## The printed rule, verbatim

> คำนวณภาษีจาก 2 วิธี (แล้วให้ชำระภาษีจากยอดที่มากกว่า)
>
> 1. ภาษีที่คำนวณจากเงินได้สุทธิ ให้คำนวณตามอัตราภาษีเงินได้บุคคลธรรมดา
>    (ให้ดูอัตราภาษีในตารางที่ 1 หน้า 17)
> 2. ภาษีที่คำนวณจากเงินได้พึงประเมิน หากเงินได้พึงประเมินมีจำนวนตั้งแต่ 120,000 บาทขึ้นไป
>    ให้นำผลลัพธ์ที่ได้จากการนำยอดรวมเงินได้พึงประเมินตาม **ข้อ 1 ถึง ข้อ 7 1. ถึง 4.**
>    (ไม่รวมเงินได้พึงประเมินมาตรา 40 (1)) คูณด้วย 0.005 เว้นแต่คำนวณแล้วไม่เกิน 5,000 บาท
>    ให้ชำระภาษีจากวิธีที่ 1.

## Resolving "ข้อ 1 ถึง ข้อ 7 1. ถึง 4."

This phrase is what earlier reconciliations could not resolve; it occurs exactly once in the
booklet and is never restated. It is settled by the booklet's own citation convention rather
than by inference.

Two lines below it, on the same page, the booklet writes:

> … ให้คำนวณภาษีใหม่ทั้งหมด แล้วนำภาษีที่ต้องชำระเพิ่มเติม ตาม **ข้อ 11 23.** ของแบบ ภ.ง.ด.90 …
> การคำนวณเงินเพิ่มตาม **ข้อ 11 24.** กรณียื่นแบบฯ เพิ่มเติมเกินกำหนดเวลา …

`ข้อ 11 23.` and `ข้อ 11 24.` are unambiguously "ข้อ 11, line 23" and "ข้อ 11, line 24" — ข้อ 11
is การคำนวณภาษี and its lines are numbered. So `ข้อ 7 1. ถึง 4.` reads as "ข้อ 7, lines 1
through 4", not as a separate range.

Page 3 of the same booklet then shows that ข้อ 7 has exactly four lines:

> ข้อ 7 รายการเงินได้พึงประเมินตามมาตรา 40 (8)
> 1. เงินได้จากการธุรกิจ การพาณิชย์ การเกษตร การอุตสาหกรรม การขนส่ง หรือการอื่น …
> 2. เงินส่วนแบ่งของกำไรจากกองทุนรวมตามประกาศคณะปฏิวัติฯ …
> 3. เงินได้จากการขายอสังหาริมทรัพย์อันเป็นมรดกหรือที่ได้รับจากการให้โดยเสน่หา …
> 4. เงินได้จากการให้หรือการรับ (โดยเลือกนำมารวมคำนวณภาษีกับเงินได้อื่น ๆ …)

"1. ถึง 4." is therefore an exhaustive enumeration of ข้อ 7, and the range is simply **ข้อ 1
through ข้อ 7 in full**.

The booklet's own box map (pages 2–3) fixes what that means:

| Box | มาตรา | In the minimum-tax base? |
| --- | --- | --- |
| ข้อ 1 | 40 (1) (2) | 40 (2) only — 40 (1) is excluded by name |
| ข้อ 2 | 40 (3) | yes |
| ข้อ 3 | 40 (4) | yes |
| ข้อ 4 | 40 (5) | yes |
| ข้อ 5 | 40 (6) | yes |
| ข้อ 6 | 40 (7) | yes |
| ข้อ 7 | 40 (8) | yes (all four lines) |
| ข้อ 8 | การขายอสังหาริมทรัพย์ที่เลือกเสียภาษีแยก | no — outside the range |
| ข้อ 9 | เงินได้จากการให้/การรับที่เลือกเสียภาษีในอัตราร้อยละ 5 | no — outside the range |
| ข้อ 10 | เงินได้ที่เลือกเสียภาษีโดยไม่ต้องนำมารวมคำนวณ | no — outside the range |

That ข้อ 9 falls outside the range agrees with the engine's existing M7.2 behaviour, which
routes a ข้อ 9 election through `SeparateTaxCalculator` and never lets it join the progressive
base.

## Status: VERIFIED, and implemented

Every element of the rule is now explicit in the source, so the M7 placeholder warning
`PND90_MINIMUM_TAX_NOT_APPLIED` is withdrawn and replaced by the calculation itself
(`App\Services\Tax\MinimumTaxCalculator`).

| Element | Value | Source |
| --- | --- | --- |
| Forms | ภ.ง.ด.90 only | ภ.ง.ด.91 carries มาตรา 40 (1) alone, which the base excludes |
| Base | Σ **gross** เงินได้พึงประเมิน of ข้อ 1–ข้อ 7, less มาตรา 40 (1) | page 6, method 2 |
| Threshold | base ≥ 120,000 | "หากเงินได้พึงประเมินมีจำนวนตั้งแต่ 120,000 บาทขึ้นไป" |
| Rate | × 0.005 | "คูณด้วย 0.005" |
| De-minimis | result ≤ 5,000 → pay method 1 | "เว้นแต่คำนวณแล้วไม่เกิน 5,000 บาท ให้ชำระภาษีจากวิธีที่ 1." |
| Selection | pay the greater of method 1 and method 2 | "แล้วให้ชำระภาษีจากยอดที่มากกว่า" |

The base is **before expenses and before allowances** — it is เงินได้พึงประเมิน, the amount each
ข้อ box records, not เงินได้สุทธิ, which is what method 1 uses. That is the entire point of the
second method.

## What changed in the response

- New `minimum_tax` block: `applicable`, `base`, `tax`, `payable`, `method`
  (`MINIMUM_TAX` or `PROGRESSIVE`, naming which calculation was actually paid).
- `progressive_tax` is unchanged and still reports method 1 on its own.
- `tax_components` gains `minimum_tax` and `tax_before_credits`; its `total` is now
  `payable + separate_tax` rather than `progressive + separate_tax`.
- `result` and the refund/payable status are computed from `payable`.
- The trace gains three steps — `MINIMUM_TAX_BASE`, `MINIMUM_TAX`, `TAX_PAYABLE` — **only**
  when the second method applies, so the standard 16-step trace is unchanged for every return
  it does not reach.
- Warning `PND90_MINIMUM_TAX_APPLIED` is emitted when method 2 is the one paid.
- Warning `PND90_MINIMUM_TAX_NOT_APPLIED` no longer exists.

No stored calculation is recalculated or rewritten. Snapshots taken before M7.3 keep the
figures and warnings they were produced with.

## Boundaries covered by tests

`tests/Unit/MinimumTaxCalculatorTest.php` and
`tests/Feature/FilingInstructionReconciliationTest.php`:

| Case | Base | Method 2 | Outcome |
| --- | --- | --- | --- |
| below the threshold | 119,999.99 | not computed | method 1 |
| at the threshold | 120,000.00 | 600.00 | method 1 (de-minimis) |
| exactly at the de-minimis floor | 1,000,000.00 | 5,000.00 | method 1 ("ไม่เกิน" includes 5,000) |
| one satang past the floor | 1,000,000.02 | 5,000.0001 | method 2 |
| method 2 larger | 2,000,000.00 | 10,000.00 | method 2 |
| method 1 larger | 2,000,000.00 | 10,000.00 | method 1 |
| salary only | 0.00 (40 (1) excluded) | not computed | method 1 |
| ภ.ง.ด.91 | 0.00 | never computed | method 1 |

Fractional satang is preserved throughout: the 0.5% is exact decimal arithmetic on
`Brick\Math\BigDecimal`, never a float, and `ROUNDING_RULE_PENDING` still states that the legal
final rounding is not yet verified.

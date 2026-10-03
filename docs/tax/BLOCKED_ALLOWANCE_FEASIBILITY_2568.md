# Can the six blocked lines be given an input box? — what the source actually prints

The question: *"คุณเพิ่มช่องให้เขากรอกเลยได้ไหม"* for the six lines the wizard shows under
*"รายการที่มีในแบบแต่ยังไม่รองรับอัตโนมัติ"*.

An input box is not a UI change. It is a promise that the engine will produce a number, and here a
wrong number is worse than no number, because the reader would act on it. So the only thing that
decides is what the approved sources print. This is a reading of
`docs/tax-source/PND90-2568-filing-instructions.pdf`, quoted, with nothing inferred.

## Line by line

### ข้อ 20 — ค่าจ้างก่อสร้างอาคารเพื่ออยู่อาศัยขึ้นใหม่ — **fully determinate**

> "จำ�นวน 10,000 บาท ต่อทุกจำ�นวน 1,000,000 บาท ตามจำ�นวนที่จ่ายจริง แต่รวมแล้วไม่เกิน 100,000 บาท
> และไม่เกินหนึ่งหลัง" (20.1)

The arithmetic is complete and unambiguous: `floor(paid / 1,000,000) × 10,000`, capped at 100,000,
one house. The booklet also prints the apportionment rules in full — co-contracting earners share
by head count (20.2.1), one-earner couples take the whole amount (20.2.2), and separately-filing
couples each take their own (20.2.3).

Remaining conditions are **facts the taxpayer knows and can declare**: contract made and
construction started 9 เมษายน 2567 – 31 ธันวาคม 2568, stamp duty paid electronically, contractor
VAT-registered and not a property trader, and the right arises in the tax year construction
*finishes* (20.5).

### ข้อ 16 — เงินลงทุนในวิสาหกิจเพื่อสังคม — **determinate, with one ambiguity**

> "แต่เมื่อรวมกันแล้วต้องไม่เกินกรณีละ 100,000 บาท สำ�หรับปีภาษีนั้น"

The ceiling **is** printed — 100,000 — which the current blocking message does not mention. What is
not resolved is **"กรณีละ"**: whether a taxpayer with two qualifying investments has one ceiling or
two. The condition (registered social enterprise, shares held until it dissolves) is declarable.

### ข้อ 13 — ค่าซื้อและค่าติดตั้งระบบกล้องโทรทัศน์วงจรปิด — **determinate, but not an allowance**

> "เป็นจำ�นวนร้อยละหนึ่งร้อยของเงินได้เท่าที่ได้จ่าย…"

100% of what was paid, no ceiling. Conditions are declarable: 40(5)–(8) income, equipment unused,
installed at premises in เขตพัฒนาพิเศษเฉพาะกิจ, paid 1 มกราคม 2567 – 31 ธันวาคม 2569.

But 13.4 requires the filer to **elect the actual-expense method**, so this line is entangled with
the expense calculation rather than independent of it.

### ข้อ 22 — ค่าท่องเที่ยวภายในประเทศ — **partly determinate**

Window: 29 ตุลาคม 2568 – 15 ธันวาคม 2568 — **which the current message does not state at all.**

| | Paper or e-Tax Invoice | e-Tax Invoice only, portion above 10,000 |
| --- | --- | --- |
| เมืองหลัก (22.1) | actual, ≤ 10,000 | actual, ≤ 10,000 (so 20,000 in total) |
| เมืองรอง (22.2) | first 10,000 → **1.5×** (10,000 → 15,000) | **1.5× of actual — no printed ceiling** |

เมืองหลัก is fully determinate. The เมืองรอง excess portion prints a multiplier and **no cap**, which
cannot be resolved from this document. It also needs four inputs, not one — main-city amount,
secondary-city amount, and which of them carried an e-Tax Invoice.

### ข้อ 7.6 — เบี้ยประกันชีวิตแบบบำนาญ — **not determinate**

The form prints both "ไม่เกิน 90,000 บาท" and "เพิ่มขึ้นอีก … ร้อยละ 15 … แต่ไม่เกิน 200,000 บาท"
without saying whether the first is inside the second. A box here means the engine picks a reading.

### ข้อ 12 — ประกันสังคม — **not determinate**

> "หักลดหย่อนได้ตามที่จ่ายจริงตามกฎหมายว่าด้วยการประกันสังคม"

The ceiling is in another statute, which is not among this project's approved sources.

## Two blockers that apply regardless of the arithmetic

### 1. Published rule versions are immutable, by design

Every rule belongs to a `tax_rule_version`. Version **2568.1 is published**, and
[`ProtectsPublishedRules`](../../app/Models/Concerns/ProtectsPublishedRules.php) refuses any insert,
update or delete of a rule that belongs to it:

> `Rules belonging to a published version are immutable. Create a new version.`

So none of these lines can be added to the current baseline. They require a new rule version, which
is a deliberate, reviewed act — not a side effect of adding a field.

### 2. Three of the four are income exemptions, not ค่าลดหย่อน

ข้อ 13 (13.4), ข้อ 16 and ข้อ 20 (20.4) all say the same thing: the amount is deducted from
เงินได้พึงประเมิน **after** expenses under มาตรา 42 ทวิ–46 — it is `ยกเว้นเงินได้`, not a deduction in the
allowance block. Only ข้อ 22 is written as `หักลดหย่อน`.

Putting them in the allowance block would place them at the wrong point in the calculation. For a
purely progressive return the net income lands the same, but the two are **not** interchangeable
wherever the base matters — notably the ภ.ง.ด.90 minimum tax, which is computed on income excluding
40(1), not on net income.

## What this adds up to

| Line | Arithmetic printed in full? | Blocker that remains |
| --- | --- | --- |
| ข้อ 20 | Yes | New rule version; models as an income exemption |
| ข้อ 16 | Yes, but "กรณีละ" is ambiguous | As above, plus the aggregation question |
| ข้อ 13 | Yes | As above, plus entanglement with the expense election |
| ข้อ 22 เมืองหลัก | Yes | New rule version; needs three inputs |
| ข้อ 22 เมืองรอง | **No** — excess portion has no printed cap | Not resolvable from this source |
| ข้อ 7.6 | **No** — 90,000 vs 200,000 unresolved | Not resolvable from this source |
| ข้อ 12 | **No** — ceiling is in another statute | Not resolvable from this source |

**ข้อ 7.6 and ข้อ 12 stay as they are.** No reading of the approved sources produces a ceiling, and
guessing one is the single thing this project's rules forbid outright.

## Correction owed either way

Two of the messages currently on screen are incomplete in ways the source contradicts, and should be
fixed whether or not any input box is ever added:

- **ข้อ 16** says the blocker is registration status, but the source prints a ceiling of 100,000. The
  real blocker is "กรณีละ", not the absence of a number.
- **ข้อ 22** does not mention the 29 ตุลาคม – 15 ธันวาคม 2568 window, which is the first thing a reader
  needs in order to know whether the line applies to them at all.

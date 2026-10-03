# เปิดปีภาษีใหม่เมื่ออัตราภาษีเปลี่ยน

Milestone 09.1 — ขั้นตอนสำหรับผู้ดูแลระบบเมื่อปีภาษีถัดไปมีการเปลี่ยนแปลงอัตราหรือกฎการคำนวณ

## หลักการ

กฎภาษีผูกกับ **ปีภาษี** และ **รุ่นกฎ** ไม่ได้ผูกกับเวลาปัจจุบัน แต่ละปีมีรุ่นที่เผยแพร่ได้ปีละหนึ่งรุ่น
(`PublishedTaxRuleResolver` ปฏิเสธถ้าเจอมากกว่าหนึ่ง) การเผยแพร่รุ่นใหม่จำกัดขอบเขตอยู่ในปีของตัวเอง
เท่านั้น — `TaxRuleVersionPublishingService::supersede()` ค้นเฉพาะ `where('tax_year_id', ...)` ของรุ่นที่
กำลังเผยแพร่ ปีอื่นจึงไม่ถูกแตะ

## ขั้นตอน

1. **เปิดปีภาษีใหม่** ที่หน้า *ผู้ดูแลระบบ → ปีภาษี* โดยเลือก "คัดลอกโครงสร้างแบบฟอร์มจากปี" เป็นปีเดิม

   ปีที่ไม่มีแบบภาษีจะเริ่มทดลองคำนวณไม่ได้เลย เพราะ `tax_forms` และประเภทเงินได้ที่แต่ละแบบรับ
   ผูกกับ *ปี* ไม่ใช่กับรุ่นกฎ การสร้างปีจึงคัดลอกโครงสร้างนี้มาให้

2. **ทำสำเนาชุดกฎของปีเดิมไปยังปีใหม่** ที่หน้า *ชุดกฎภาษี* — กด "ทำสำเนาเป็นฉบับร่าง" แล้วระบุปีปลายทาง

   สำเนาจะนำมาทั้ง ขั้นภาษี กฎค่าใช้จ่าย (พร้อม tier) กฎค่าลดหย่อน กลุ่มเพดานรวม กฎเงินบริจาค
   กฎคำแนะนำ และการอ้างอิงเอกสารต้นทาง โดยทุกแถวเปลี่ยน `tax_year_id` เป็นปีใหม่

3. **แก้เฉพาะอัตราที่เปลี่ยน** ในฉบับร่างนั้น ที่หน้ารายละเอียดชุดกฎ

4. **ตรวจสอบ** (validate) แล้วจึง **เผยแพร่**

## สิ่งที่รับประกันว่าปีเก่ายังคำนวณถูกต้อง

| ชั้น | กลไก |
|---|---|
| การเผยแพร่ | `supersede()` จำกัดอยู่ใน `tax_year_id` เดียว — เผยแพร่ 2569.1 ไม่แตะ 2568.1 |
| แบบที่บันทึกไว้ | `TaxReturn.rule_version_id` ตรึงตั้งแต่วันสร้างและไม่เคยย้าย |
| ผลที่คำนวณแล้ว | `tax_calculations` เก็บ `rule_version_id` + `input_snapshot` + `result_snapshot` + `trace` ของตัวเอง การดูประวัติคือการอ่าน snapshot ไม่ใช่คำนวณใหม่ |
| กฎที่เผยแพร่แล้ว | `ProtectsPublishedRules` ปฏิเสธการเขียนทุกกรณีที่ระดับ model |

`NextTaxYearWorkflowTest` เดินขั้นตอนทั้งหมดนี้แล้วยืนยันสองด้าน: การคำนวณใหม่ของปีเก่าได้ผลเท่าเดิม
ทุกตัวอักษร และแบบที่บันทึกไว้ก่อนเปลี่ยนยังคำนวณด้วยรุ่นเดิมพร้อม snapshot ที่ไม่ถูกเขียนทับ

## ข้อจำกัดที่ตั้งใจ

- **แก้เลขปีภายหลังไม่ได้** ชุดกฎ แบบภาษี และแบบจำลองอ้างอิงแถวนี้อยู่ การเปลี่ยนเลขปีคือการเปลี่ยนป้าย
  ให้ประวัติทั้งหมด ไม่ใช่การแก้ให้ถูก — ถ้ากรอกผิดให้ปิดใช้งานแล้วเปิดปีที่ถูกต้อง
- **ลบปีภาษีไม่ได้** ทำได้แค่ปิดใช้งาน ปีที่ปิดจะไม่ถูกเสนอให้เลือกสำหรับแบบจำลองใหม่
  ส่วนแบบและผลที่ใช้ปีนั้นอยู่ยังทำงานเหมือนเดิมทุกประการ
- **ปีที่มีชุดกฎเผยแพร่อยู่ปิดใช้งานไม่ได้** ต้องจัดเก็บชุดกฎก่อน

---

## Defect found in use, 2026-09-16: opening a year broke the public simulator

**Symptom.** The wizard rendered an empty first step — no year or form selector — and every
metadata request answered **409 Conflict**.

**Cause.** Opening a tax year creates it *active*, with its forms copied and **no rule version**;
the version is cloned and published later, often months later. That is the workflow this document
describes and it is correct.

But `TaxMetadataService::years()` listed every active year. The wizard takes the newest year in
that list, so the moment tax year 2569 was opened it became the default — and 2569 has no published
baseline, so `PublishedTaxRuleResolver` threw on every request for it:

```
GET /api/v1/tax-years                   → 200   [2569, 2568]
GET /api/v1/tax-years/2569/forms/PND91  → 409   ← the wizard asks for this
```

Nothing was wrong with 2568. It simply was no longer the year being asked for.

**This defeated the entire premise of the feature.** The point of separating "open the year" from
"publish its rules" is that an administrator can prepare next year in advance without disturbing
this year's calculator. Instead, the first step of that preparation took the calculator down.

**Fix.** The public year list now offers only years that can actually be calculated:

```php
TaxYear::where('active', true)
    ->whereHas('ruleVersions', fn ($query) => $query->where('status', 'published'))
```

A public metadata list means *"these are the years you can calculate"*, so a year without a
published version does not belong in it. Three things are deliberately unchanged:

- **Naming the year directly still answers 409**, not 404 — a caller who asks about 2569 is told
  why it cannot be used yet, rather than being told it does not exist.
- **`/admin/tax-years` still shows it**, because hiding it from the person whose job is to publish
  it would be absurd.
- **Publishing is all it takes.** The moment a rule version is published into the year, it appears
  publicly and leads the list. No flag to set, nothing to remember.

`OpenedTaxYearDoesNotBreakTheSimulatorTest` covers all six of those properties, including a full
calculation after the next year is opened.

`TaxMetadataApiTest::test_active_years_are_listed_newest_first_with_only_public_fields` had encoded
the same wrong assumption — a bare year with no rule version satisfied it. It is now
`test_calculable_years_are_listed_…` and its fixture gives the new year what makes it calculable.
A test that agrees with a bug is how the bug survives a test suite.

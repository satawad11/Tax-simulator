# รายงานส่วนที่ยังไม่ได้ดำเนินการ — Backend และ Frontend

วันที่ตรวจสอบ: 14 กันยายน 2569 (2026-09-14)

## ขอบเขตการตรวจสอบ

รายงานนี้ตรวจสถานะจากโค้ด การทดสอบ เอกสาร milestone และเอกสารแหล่งอ้างอิงที่มีอยู่ใน repository ณ วันที่ระบุ โดยแยกสิ่งที่ยังไม่ได้ทำออกเป็น 3 กลุ่ม:

- งานที่ยังทำไม่ได้เพราะหลักฐานภาษีหรือโครงสร้างข้อมูลยังไม่เพียงพอ
- งานที่อยู่นอกขอบเขต Release 1.0 และถูกป้องกันไม่ให้คำนวณผิด
- งาน production และการทดสอบบนสภาพแวดล้อมภายนอกที่ยังต้องดำเนินการจริง

Release 1.0 ครอบคลุมเส้นทางจำลองภาษี PND90/PND91 ที่ประกาศไว้แล้ว รายการด้านล่างจึงไม่ใช่ทั้งหมดว่าเป็น defect หลายรายการเป็นข้อจำกัดที่ระบบตั้งใจ guard ไว้เพื่อไม่เดาค่าหรือสูตรภาษี

## Backend — Tax Engine และ API

| รหัส | ส่วนที่ยังไม่ได้ทำ | สถานะปัจจุบัน | เหตุผลที่ข้ามไว้ | สิ่งที่ต้องมีก่อนทำต่อ | ผลต่อผู้ใช้ |
|---|---|---|---|---|---|
| BE-01 | ค่าลดหย่อน `PENSION_INSURANCE` | รับค่ามากกว่า 0 แล้วตอบ 422 `PENSION_INSURANCE_RULE_PARTIAL` | เอกสารระบุเพดานและเปอร์เซ็นต์บางส่วน แต่ยังยืนยันความสัมพันธ์กับวงเงินประกัน/เงินออมร่วมไม่ได้ | แหล่งอ้างอิงที่อนุมัติและแบบจำลอง shared cap ที่ชัดเจน | ยังใช้ยอดบำนาญประกันชีวิตในการคำนวณไม่ได้ |
| BE-02 | ค่าลดหย่อน `SOCIAL_SECURITY` | รับค่ามากกว่า 0 แล้วตอบ 422 `SOCIAL_SECURITY_RULE_UNSUPPORTED` | เอกสารใน repository อ้างถึงกฎหมายประกันสังคมภายนอกและไม่มีเพดานที่ยืนยันได้ | เอกสารกฎหมายที่อนุมัติและ rule version ใหม่ | ยังใช้ยอดประกันสังคมในการคำนวณไม่ได้ |
| BE-03 | allowance แบบ umbrella: `INSURANCE`, `ANNUAL_TAX_MEASURES`, `OTHER` | มี master code แต่ไม่มี calculation rule; ค่ามากกว่า 0 ถูกปฏิเสธ | เป็นหมวดรวมที่ไม่มีบรรทัดหรือเพดานเดียวให้คำนวณอย่างปลอดภัย | mapping จากหมวดรวมไปยังรายการจริงและกติกาการรวมวงเงิน | API ไม่รับยอดรวมที่ไม่สามารถพิสูจน์องค์ประกอบได้ |
| BE-04 | มาตรการท่องเที่ยวในประเทศของ PND90 | ยังไม่มี request field/master rule สำหรับบรรทัดนี้ | เอกสารระบุอัตรา 1.5 เท่าสำหรับบางพื้นที่ แต่ไม่ให้เพดานที่ครบถ้วน | แหล่งอ้างอิงที่ระบุเพดานและเงื่อนไขครบ | รายการนี้ยังไม่รวมในค่าลดหย่อน |
| BE-05 | เครดิตภาษีเงินปันผลและ gross-up ของ PND90 ช่อง 5 | ไม่มี request shape และสูตร | ยังไม่มีแบบจำลองข้อมูลและหลักฐานเพียงพอสำหรับ gross-up/เครดิต | สัญญา API, schema และกติกาที่อนุมัติ | ผู้ใช้ยังจำลองเครดิตเงินปันผลไม่ได้ |
| BE-06 | เงินได้อสังหาริมทรัพย์ที่เลือกเสียภาษีแยก PND90 ช่อง 8 | ไม่มี request shape และเส้นทางคำนวณแยก | รายการต้องแยกออกจากฐานอัตราก้าวหน้า แต่ engine ยังไม่มีแบบจำลองนี้ | การออกแบบ payload และ calculation path ที่อนุมัติ | ยังจำลองการเลือกเสียภาษีแยกไม่ได้ |
| BE-07 | เงินได้ที่เลือกไม่นำมารวมคำนวณ PND90 ช่อง 10 | ไม่มี request shape | ยังไม่ได้กำหนดชนิดข้อมูลและผลต่อฐานคำนวณ | สัญญา API และกติกาที่อนุมัติ | ยังบันทึก/คำนวณทางเลือกนี้ไม่ได้ |
| BE-08 | `foreign_tax_credit` | ค่ามากกว่า 0 ถูกปฏิเสธด้วย 422 `FOREIGN_TAX_CREDIT_UNSUPPORTED` | ถ้อยคำในแบบฟอร์มไม่พอสำหรับคำนวณเพดานเครดิต | เอกสารวิธีคำนวณและข้อจำกัดที่อนุมัติ | ยังหักเครดิตภาษีต่างประเทศไม่ได้ |
| BE-09 | `other_credit` | ถูกปฏิเสธด้วย 422 `TAX_CREDIT_TYPE_UNSUPPORTED` | ยังไม่พบรายการที่ตรงกันและกติกาในแบบ PND90/PND91 ที่อนุมัติ | นิยามชนิดเครดิตและกฎที่ตรวจสอบได้ | ยังใช้เครดิตภาษีประเภทอื่นไม่ได้ |
| BE-10 | กฎการปัดเศษสุดท้ายตามกฎหมาย | engine เก็บค่าทศนิยมตามจริงและคืน warning `ROUNDING_RULE_PENDING` | ยังไม่มีแหล่งอ้างอิงใน repository ที่ยืนยันกฎการปัดเศษสุดท้าย | แหล่งอ้างอิงที่อนุมัติและ test vectors | ผลจำลองมีความแม่นยำเชิงคณิตศาสตร์ แต่ยังไม่ปัดแบบเอกสารยื่นจริง |
| BE-11 | การตรวจเพดานรายองค์ประกอบของเงินได้ยกเว้นบางกองทุน | รับยอดยกเว้นรวมจากผู้ใช้; ยังไม่ตรวจ PVD ส่วนเกิน 10,000, GPF และกองทุนครูเอกชนแยกรายการ | payload ไม่มีองค์ประกอบย่อยครบ | schema/payload ที่เก็บยอดแต่ละองค์ประกอบและกติกาที่อนุมัติ | ผู้ใช้ต้องรับรองยอดรวมเอง ระบบยังตรวจองค์ประกอบไม่ได้ |
| BE-12 | เพดานผู้พิการประเภท “บุคคลอื่น” | คำนวณยอดตามจำนวน แต่ยังไม่จำกัดบุคคลอื่น 1 คน; คืน warning `DISABLED_PERSON_OTHER_LIMIT_UNMODELLED` | schema แยกสมาชิกครอบครัวกับบุคคลอื่นไม่ได้ | เพิ่มบทบาทความสัมพันธ์ในข้อมูลผู้พิการผ่าน milestone ที่อนุมัติ | อาจต้องให้ผู้ใช้ตรวจคุณสมบัติและจำนวนเอง |
| BE-13 | การพิสูจน์คุณสมบัติ/เอกสารค่าลดหย่อน | ใช้คำรับรองของผู้ใช้ | ระบบไม่มีข้อมูลเอกสารราชการ รายได้ของผู้อยู่ในอุปการะ หรือสถานะตามกฎหมาย | integration/เอกสาร/consent และข้อกำหนดการตรวจสอบ | ผลลัพธ์เป็นการจำลองจากข้อมูลที่ผู้ใช้แจ้ง |
| BE-14 | tax year และ rule version อื่นนอกเหนือจาก 2568 / 2568.1 | โครงสร้างรองรับหลายปี แต่มี baseline ที่อนุมัติเพียง 2568.1 | ยังไม่มีชุดเอกสารและ seed rules สำหรับปีอื่น | เอกสารของแต่ละปีและ rule version ใหม่ | ระบบปัจจุบันไม่ควรถูกใช้แทนกติกาปีภาษีอื่น |

### งาน Backend ที่ไม่ใช่ calculation แต่ยังไม่มี

| รหัส | ส่วนที่ยังไม่ได้ทำ | ขอบเขตปัจจุบัน |
|---|---|---|
| BE-15 | ส่งแบบไปกรมสรรพากรจริง | ระบบเป็น tax simulation; ไม่มีการส่งแบบหรือเชื่อมต่อระบบยื่นภาษีราชการ |
| BE-16 | ลายมือชื่ออิเล็กทรอนิกส์และการยืนยันตัวตนเพื่อยื่นแบบ | มี authentication ของสมาชิก แต่ไม่ใช่ digital identity/signature สำหรับการยื่นแบบ |
| BE-17 | แนบเอกสารและจำนวนเอกสารประกอบแบบ | ไม่มีช่องอัปโหลด/จัดส่งเอกสารราชการ เพราะอยู่นอกขอบเขต simulation |
| BE-18 | ช่องทางชำระภาษีและคำขอคืนภาษีจริง | คำนวณยอดประมาณการได้ แต่ไม่มี payment/refund submission integration |

## Frontend — Web Client

| รหัส | ส่วนที่ยังไม่ได้ทำ | สถานะ/เหตุผล | ความสัมพันธ์กับ Backend |
|---|---|---|---|
| FE-01 | ช่องกรอก `PENSION_INSURANCE` และ `SOCIAL_SECURITY` ที่นำไปคำนวณได้ | UI แสดงสถานะไม่รองรับหรือคำอธิบาย แทนการเปิดช่องกรอกที่ให้ผลผิด | รอ BE-01 และ BE-02 |
| FE-02 | ช่องกรอก umbrella allowances ที่คำนวณได้ | UI ไม่เปิดให้กรอกยอดรวมที่ไม่ทราบองค์ประกอบ | รอ BE-03 |
| FE-03 | ช่องกรอกมาตรการท่องเที่ยวในประเทศ | ยังไม่แสดงเป็นรายการคำนวณ | รอ BE-04 |
| FE-04 | flow สำหรับเครดิตเงินปันผล, อสังหาริมทรัพย์เสียภาษีแยก และเงินได้ไม่นำมารวม | แสดงเป็นข้อจำกัด/ข้อมูลประกอบ ไม่มี interaction สำหรับคำนวณ | รอ BE-05 ถึง BE-07 และสัญญา API |
| FE-05 | เครดิตภาษีต่างประเทศและเครดิตอื่น | option ถูกปิดหรืออธิบายว่าไม่รองรับ | รอ BE-08 และ BE-09 |
| FE-06 | ขั้นตอนยื่นแบบจริง | ไม่มีหน้าลงนาม แนบเอกสาร ชำระภาษี หรือส่งแบบ | อยู่นอกขอบเขต simulation; รอ BE-15 ถึง BE-18 และ external integrations |
| FE-07 | mobile application แบบ native | ยังไม่มี application แยก; มีเพียงเว็บ responsive และ REST API สำหรับรองรับในอนาคต | เป็น roadmap หลัง Release 1.0 ไม่ใช่ข้อบกพร่องของ web client |

Frontend ไม่ได้คำนวณสูตรภาษีซ้ำใน JavaScript ผลรวมภาษีและสถานะสำคัญมาจาก backend API ซึ่งเป็นทิศทางที่ถูกต้องและควรรักษาไว้เมื่อเพิ่มรายการใหม่

## QA และการตรวจบนอุปกรณ์ที่ยังไม่ได้ทำ

| รหัส | รายการ | สถานะปัจจุบัน | สิ่งที่ยังต้องทำ |
|---|---|---|---|
| QA-01 | Safari | ยังไม่มีหลักฐานการทดสอบบน Safari จริง | ทดสอบ workflow หลักบน macOS/iOS Safari |
| QA-02 | Firefox | ยังไม่มีหลักฐานการทดสอบบน Firefox จริง | ทดสอบ workflow หลักและตรวจ layout/JavaScript |
| QA-03 | โทรศัพท์และแท็บเล็ตจริง | responsive layout ผ่านการตรวจในสภาพแวดล้อมที่มี แต่ยังไม่มีหลักฐานบน hardware จริง | ทดสอบ iOS/Android อย่างน้อยหนึ่งรุ่นต่อกลุ่มขนาดหน้าจอ |
| QA-04 | browser end-to-end automation | repository ไม่มีชุด Playwright/Cypress; การยืนยันปัจจุบันใช้ Laravel feature tests และ manual Chromium smoke | เพิ่มเมื่อทีมกำหนด browser support matrix และ CI environment |
| QA-05 | load/capacity test | ถูกจัดเป็น `NOT_APPLICABLE` เพราะยังไม่มี SLA และ production-sized environment | กำหนดเป้าหมาย latency/concurrency แล้วทดสอบใน staging |

รายการ QA-01 ถึง QA-03 เป็นข้อจำกัดของสภาพแวดล้อมตรวจรับ ไม่ใช่ release-blocking regression ที่พบในโค้ดปัจจุบัน

## Production และ Operations ที่ยังต้องดำเนินการจริง

เอกสาร runbook และ checklist มีแล้ว แต่งานต่อไปนี้ยังต้องทำใน production environment โดยผู้ดูแลระบบ:

- สร้างและเก็บ production secrets รวมถึง `APP_KEY` ที่ไม่ซ้ำกับ development
- ตั้งค่า production environment ให้ `APP_ENV=production`, `APP_DEBUG=false` และใช้ HTTPS/TLS
- วาง MySQL ใน private network และจำกัดสิทธิ์บัญชีฐานข้อมูล
- รัน production migration/seed ตาม runbook โดยไม่ทำลายข้อมูลเดิม
- สร้างบัญชีผู้ดูแลระบบจริงและเปลี่ยนรหัสผ่านชั่วคราว
- เปิด monitoring/log aggregation และ health monitoring ภายนอก
- ทำ backup และ restore drill จริง พร้อมบันทึกหลักฐาน
- ซ้อม rollback และตรวจ integrity หลัง rollback

Docker Compose ใน repository เหมาะกับ development และ verification การมี runbook ครบไม่ได้แปลว่า production deployment ข้างต้นถูกดำเนินการแล้ว

## สิ่งที่ทำครบแล้วและไม่ควรถูกนับเป็นช่องว่าง

- PND91 สำหรับเงินได้ `SECTION_40_1` ตาม baseline ที่อนุมัติ
- PND90 สำหรับ `SECTION_40_1` ถึง `SECTION_40_8` ในเส้นทางที่ประกาศรองรับ
- progressive tax brackets 8 ช่วงของปี 2568
- minimum tax path ที่รองรับและ backend trace
- guest calculation ที่ไม่บังคับ persistence
- member authentication, tax-return workflow, saved scenarios และ planning/recommendations ตาม scope
- versioned REST API ภายใต้ `/api/v1`
- เว็บ Blade + Tailwind + JavaScript ตาม mockup direction ที่อนุมัติ
- admin/content capabilities และ security controls ที่อยู่ใน Release 1.0
- published baseline `2568.1` ถูกป้องกันการแก้ไข และไม่ควรถูกแก้เพื่อปิด gap เหล่านี้

## ลำดับงานที่แนะนำ

1. ปิด BE-01, BE-02, BE-04, BE-08 และ BE-10 ด้วยเอกสารแหล่งอ้างอิงที่อนุมัติก่อนเขียนสูตร
2. อนุมัติ schema และ API contract สำหรับ BE-05 ถึง BE-07, BE-11 และ BE-12
3. สร้าง tax rule version ใหม่ แทนการแก้ published baseline `2568.1`
4. เพิ่ม backend tests จากตัวอย่างที่ตรวจสอบได้ แล้วจึงเปิด frontend inputs ที่เกี่ยวข้อง
5. ทำ cross-browser/device verification และกำหนด performance SLA
6. ดำเนิน production checklist ใน staging/production พร้อมหลักฐานการ backup, restore และ rollback

## ผลต่อสถานะ Release 1.0

รายการที่ยังไม่รองรับใน tax engine ถูกป้องกันด้วย validation, 422 responses, warnings หรือ UI disabled state จึงไม่พบเส้นทางที่รับข้อมูลแล้วคำนวณสูตรที่ยังไม่ยืนยันอย่างเงียบ ๆ Release 1.0 ยังใช้ได้ภายในขอบเขต simulation ที่ประกาศไว้ แต่ยังไม่ควรอ้างว่าเป็นระบบยื่นภาษีจริงหรือรองรับทุกบรรทัดของแบบ PND90/PND91

ผลตรวจ regression ล่าสุดที่บันทึกไว้ใน repository:

- SQLite suite: 830 ผ่าน, 2 ข้าม, 4,603 assertions
- MySQL suite: 832 ผ่าน, 5,032 assertions
- Laravel Pint: ผ่าน
- Frontend production build: ผ่าน

## เอกสารอ้างอิงใน Repository

- [Tax Engine Baseline 2568](../tax/TAX_ENGINE_BASELINE_2568.md)
- [Allowance Rule Matrix 2568](../tax/ALLOWANCE_RULE_MATRIX_2568.md)
- [PND90 Production Baseline 2568](../tax/PND90_PRODUCTION_BASELINE_2568.md)
- [PND91 Production Baseline 2568](../tax/PND91_PRODUCTION_BASELINE_2568.md)
- [Source Coverage 2568](../tax/SOURCE_COVERAGE_2568.md)
- [Release 1.0](../releases/RELEASE_1_0.md)
- [Release Test Matrix](RELEASE_TEST_MATRIX.md)
- [Production Readiness Checklist](../ops/PRODUCTION_READINESS_CHECKLIST.md)

# PROJECT_REQUIREMENTS.md

## 1. Project Name

**Thai Personal Income Tax Simulation Platform**

ชื่อภาษาไทยที่ใช้ในระบบ:

**ระบบจำลองการยื่นและวางแผนภาษีเงินได้บุคคลธรรมดา**

---

## 2. Project Goal

พัฒนาเว็บแอปพลิเคชันสำหรับให้ผู้ใช้เรียนรู้ ทดลองกรอก และจำลองการยื่นภาษีเงินได้บุคคลธรรมดาไทย โดยเน้นแบบ:

- ภ.ง.ด.90
- ภ.ง.ด.91

ระบบนี้มีวัตถุประสงค์เพื่อ:

1. ให้ผู้ใช้ทั่วไปทดลองกรอกข้อมูลและคำนวณภาษีได้โดยไม่ต้องสมัครสมาชิก
2. ให้ผู้ใช้เข้าใจขั้นตอนการคำนวณภาษีอย่างเป็นลำดับ
3. ให้ผู้ใช้เห็นผลว่าอาจต้องชำระเพิ่ม ชำระไว้เกิน หรือไม่มีภาษีคงเหลือ
4. ให้คำแนะนำเกี่ยวกับรายการที่ควรตรวจสอบ
5. ให้ทดลองวางแผนภาษีด้วย Scenario Simulation
6. ให้สมาชิกสามารถบันทึกแบบร่าง เรียกดูย้อนหลัง ทำสำเนา Scenario และกรอกต่อภายหลัง
7. มีส่วนให้ความรู้ บทความ ข่าวสาร และคำถามที่พบบ่อยด้านภาษี
8. รองรับ Mobile Application ที่พัฒนาร่วมกันโดยใช้ Backend และ Tax Engine ชุดเดียวกัน
9. รองรับกฎภาษีแยกตามปีภาษีและ Rule Version

> ระบบเป็น “ระบบจำลองเพื่อการเรียนรู้และวางแผน” ไม่ใช่ระบบยื่นแบบภาษีอย่างเป็นทางการของกรมสรรพากร

---

## 3. Source of Truth

Business Requirement ด้านภาษีต้องอ้างอิงจากเอกสารต้นฉบับที่ใช้กำหนดโครงการ ได้แก่:

- แบบ ภ.ง.ด.90 ปีภาษี 2568
- แบบ ภ.ง.ด.91 ปีภาษี 2568
- ใบแนบรายการลดหย่อนและยกเว้น
- ตารางอัตราภาษีที่ผู้ว่าจ้างจัดเตรียมให้
- Requirement ที่ได้รับอนุมัติในเอกสารนี้

ห้าม Codex หรือผู้พัฒนาเดากฎภาษีเพิ่มเติมเอง หาก Requirement หรือเอกสารยังไม่รองรับ ต้องทำเครื่องหมายเป็น TODO และแจ้งให้ตรวจสอบก่อน implement

---

## 4. Technology Stack

### Frontend

- HTML5
- Laravel Blade
- Tailwind CSS
- JavaScript ES Modules
- Responsive Web Design

### Backend

- Laravel
- PHP 8.4
- REST API
- Laravel Sanctum

### Database

- MySQL 8.4

### Infrastructure

- Docker
- Docker Compose
- Nginx

### Mobile

Mobile Application จะเรียก REST API ชุดเดียวกับ Web Application

หลักการสำคัญ:

**Laravel Backend และ Tax Engine เป็น Single Source of Truth**

Web และ Mobile ห้ามมี Business Logic ทางภาษีที่ซ้ำกับ Backend

---

## 5. High-Level Architecture

```text
Web Application
Blade + Tailwind + JavaScript
          |
          | HTTPS / JSON
          v
     Laravel REST API
          |
          +-- Authentication
          +-- Tax Calculation Engine
          +-- Tax Rule Engine
          +-- Tax Analysis
          +-- Tax Recommendation
          +-- Tax Planning
          +-- Refund Analysis
          +-- Content Management
          |
          v
        MySQL
          ^
          |
          | HTTPS / JSON
          |
Mobile Application
```

API Base Path:

```text
/api/v1
```

---

## 6. User Roles

### 6.1 Guest

สามารถ:

- ดูหน้าเว็บไซต์
- อ่านความรู้ภาษี
- อ่านข่าวสาร
- ค้นหาเนื้อหา
- เลือกปีภาษี
- เลือกแบบ ภ.ง.ด.90 / ภ.ง.ด.91
- ทดลองกรอกข้อมูล
- คำนวณภาษี
- ดูรายละเอียดการคำนวณ
- ดูคำแนะนำ
- ทดลอง Tax Planning Scenario
- ดูประมาณการชำระเพิ่ม / ชำระไว้เกิน

ไม่สามารถ:

- บันทึกข้อมูลลงบัญชีผู้ใช้
- เรียกดูประวัติ
- บันทึก Scenario ถาวร

Guest Mode ต้องไม่บังคับสมัครสมาชิกก่อนทดลองใช้งาน

---

### 6.2 Member

ทำได้ทุกอย่างเหมือน Guest และเพิ่ม:

- สมัครสมาชิก
- เข้าสู่ระบบ
- ออกจากระบบ
- จัดการ Profile
- บันทึก Tax Return
- บันทึก Draft
- กลับมากรอกต่อ
- ดูประวัติ
- ทำสำเนา Scenario
- บันทึก Tax Planning Scenario
- ดูผลคำนวณย้อนหลัง
- Archive / Delete รายการของตนเอง

---

### 6.3 Administrator

สามารถ:

- จัดการบทความ
- จัดการข่าวสาร
- จัดการหมวดหมู่
- จัดการ Tag
- จัดการปีภาษี
- จัดการแบบภาษี
- จัดการ Income Rules
- จัดการ Expense Rules
- จัดการ Allowance Rules
- จัดการ Donation Rules
- จัดการ Tax Brackets
- จัดการ Recommendation Rules
- จัดการ Tax Rule Version
- Publish Rule Version
- ดูข้อมูลผู้ใช้ในระดับที่จำเป็นต่อการดูแลระบบ

Admin ไม่ควรเข้าถึงรายละเอียดข้อมูลทางการเงินของสมาชิกโดยไม่มีเหตุผลทางระบบที่ชัดเจน

---

## 7. Supported Tax Forms

### 7.1 ภ.ง.ด.91

ใช้สำหรับการจำลองกรณีเงินได้ตามมาตรา 40(1) ประเภทเดียว

Workflow หลัก:

```text
ข้อมูลพื้นฐาน
-> เงินได้
-> เงินได้ยกเว้น
-> ค่าใช้จ่าย
-> ค่าลดหย่อน
-> เงินบริจาค
-> เงินได้สุทธิ
-> ภาษีแบบขั้นบันได
-> เครดิต/ภาษีหัก ณ ที่จ่าย
-> สรุปชำระเพิ่ม/ชำระไว้เกิน
```

### 7.2 ภ.ง.ด.90

รองรับเงินได้ตามมาตรา 40(1) ถึง 40(8)

Workflow หลัก:

```text
ข้อมูลพื้นฐาน
-> ประเภทเงินได้
-> เงินได้
-> ค่าใช้จ่ายตามประเภท
-> ค่าลดหย่อน
-> เงินบริจาค
-> เงินได้สุทธิ
-> ภาษี
-> เครดิต/ภาษีหัก ณ ที่จ่าย
-> สรุปผล
```

---

## 8. Core Functional Requirements

### FR-001 Guest Simulation

Guest ต้องสามารถทดลองกรอกและคำนวณภาษีได้โดยไม่ต้องเข้าสู่ระบบ

Guest payload ห้ามถูกบันทึกเป็น Tax Return ถาวรโดยอัตโนมัติ

---

### FR-002 Tax Year

ระบบต้องรองรับหลายปีภาษี

ทุก Tax Rule ต้องสัมพันธ์กับ:

- tax_year
- rule_version

---

### FR-003 Tax Form Selection

ระบบต้องให้เลือก:

- PND90
- PND91

และมี Form Recommendation ช่วยแนะนำจากประเภทเงินได้

---

### FR-004 Taxpayer Profile

รองรับข้อมูลที่จำเป็นต่อการคำนวณ เช่น:

- วันเกิด
- สถานภาพสมรส
- ข้อมูลคู่สมรส
- ผู้ที่อยู่ในอุปการะ
- บุตร
- บิดามารดา
- ข้อมูลประกอบสิทธิลดหย่อนที่จำเป็น

ไม่ควรบังคับเก็บเลขประจำตัวประชาชนจริงในระบบจำลอง หากไม่จำเป็นต่อ Business Logic

---

### FR-005 Income

รองรับ Income Types:

- SECTION_40_1
- SECTION_40_2
- SECTION_40_3
- SECTION_40_4
- SECTION_40_5
- SECTION_40_6
- SECTION_40_7
- SECTION_40_8

แต่ละ Tax Form ต้องจำกัด Income Type ตามกฎของแบบนั้น

---

### FR-006 Expense Calculation

ระบบต้องคำนวณค่าใช้จ่ายตาม Income Type และ Tax Rule

Expense Method รองรับอย่างน้อย:

- fixed
- percentage
- percentage_limit
- actual
- percentage_or_actual
- custom

Client ห้ามเป็นผู้กำหนด calculated_expense เป็น Source of Truth

---

### FR-007 Allowances

รองรับ Allowance Master และ Allowance Rules แบบแยกปี/เวอร์ชัน

ตัวอย่างหมวด:

- personal
- spouse
- child
- parent
- disabled person
- insurance
- provident fund
- RMF
- Thai ESG
- Thai ESGX
- home loan interest
- social security
- annual tax measures
- other

---

### FR-008 Donations

รองรับ:

- เงินบริจาคที่คำนวณแบบพิเศษ
- เงินบริจาคทั่วไป
- เพดานที่สัมพันธ์กับฐานเงินได้

---

### FR-009 Progressive Tax

ระบบต้องคำนวณภาษีแบบขั้นบันได

ห้ามใช้:

```text
net_income * highest_tax_rate
```

ต้องคืน Tax Bracket Breakdown เพื่อใช้แสดงผลทั้ง Web และ Mobile

---

### FR-010 Tax Credits / Withholding

รองรับอย่างน้อย:

- withholding
- foreign_tax_credit
- pnd93
- pnd94
- other_credit

---

### FR-011 Tax Result

ผลลัพธ์หลัก:

- PAYABLE
- REFUND
- ZERO

ต้องแสดง:

- gross_income
- exempt_income
- total_expense
- income_after_expense
- total_allowance
- total_donation
- net_income
- calculated_tax
- credits
- withholding
- final amount
- calculation trace

---

## 9. Tax Analysis

หลังคำนวณ ต้องมีการวิเคราะห์อย่างน้อย:

- Effective Tax Rate
- Marginal Tax Rate
- Tax Bracket ปัจจุบัน
- สาเหตุที่ต้องชำระเพิ่ม
- สาเหตุที่ชำระไว้เกิน
- Summary ของ Credits / Withholding

---

## 10. Tax Recommendation

ระบบต้องสามารถสร้างคำแนะนำแบบ Rule-based

ประเภทคำแนะนำ:

1. MISSING_INFORMATION
2. POTENTIAL_ALLOWANCE
3. TAX_PLANNING
4. PAYMENT
5. REFUND

ข้อความต้องใช้แนว:

- “ควรตรวจสอบสิทธิ”
- “อาจมีสิทธิ”
- “ทดลองจำลองได้”

หลีกเลี่ยงการยืนยันสิทธิแบบเด็ดขาด หากข้อมูลยังไม่เพียงพอ

---

## 11. Tax Planning Scenario

ผู้ใช้ต้องสามารถเปรียบเทียบ:

```text
สถานการณ์ปัจจุบัน
vs
สถานการณ์จำลอง
```

ระบบต้องคำนวณ:

- before.net_income
- before.tax
- after.net_income
- after.tax
- estimated_tax_saving
- differences

Scenario ห้ามเปลี่ยนผลของ Tax Return ต้นฉบับ

Member สามารถบันทึก Scenario

Guest สามารถใช้ Scenario ชั่วคราวได้

---

## 12. Refund Analysis

เมื่อ:

```text
total_paid_or_credited > calculated_tax
```

ระบบสามารถคืนสถานะ:

```text
REFUND
```

พร้อม:

- estimated_refund
- reason
- components
- explanation
- checklist

ต้องระบุว่าเป็น “ประมาณการ” ไม่ใช่การรับรองว่าจะได้รับเงินคืนจริง

---

## 13. Member Tax Return

Tax Return รองรับสถานะ:

- draft
- completed
- archived

Member สามารถ:

- create
- read
- update
- delete
- save draft
- complete simulation
- duplicate
- calculate
- view calculation history

ข้อความใน UI ต้องใช้:

**“เสร็จสิ้นการทดลอง”**

ไม่ใช้:

**“ยื่นแบบสำเร็จ”**

---

## 14. Content / Knowledge / News

เว็บไซต์ต้องมี:

### Knowledge Center

- ภาษีพื้นฐาน
- ภ.ง.ด.90
- ภ.ง.ด.91
- เงินได้มาตรา 40
- ค่าใช้จ่าย
- ค่าลดหย่อน
- เงินบริจาค
- ภาษีแบบขั้นบันได
- FAQ

### News

- ข่าวภาษี
- มาตรการภาษี
- ประกาศที่เกี่ยวข้อง
- ข่าวสารสำหรับผู้เสียภาษี

Content ต้องรองรับ:

- category
- tag
- slug
- search
- status
- publish date

---

## 15. API Requirements

Base URL:

```text
/api/v1
```

API ต้องรองรับ Web และ Mobile

### Public API

```text
GET  /api/v1/health

GET  /api/v1/tax-years
GET  /api/v1/tax-years/{year}
GET  /api/v1/tax-years/{year}/forms
GET  /api/v1/tax-years/{year}/forms/{form}
GET  /api/v1/tax-years/{year}/income-types
GET  /api/v1/tax-years/{year}/income-types/{code}
GET  /api/v1/tax-years/{year}/allowances
GET  /api/v1/tax-years/{year}/allowances/{code}
GET  /api/v1/tax-years/{year}/tax-brackets

POST /api/v1/tax/forms/recommend
POST /api/v1/tax/calculate
POST /api/v1/tax/plan

GET  /api/v1/content
GET  /api/v1/content/{slug}
GET  /api/v1/categories
GET  /api/v1/tags
GET  /api/v1/search
```

### Authentication API

```text
POST /api/v1/auth/register
POST /api/v1/auth/login
POST /api/v1/auth/logout
POST /api/v1/auth/logout-all
GET  /api/v1/auth/me
PUT  /api/v1/auth/me
```

### Member API

```text
GET    /api/v1/tax-returns
POST   /api/v1/tax-returns
GET    /api/v1/tax-returns/{id}
PATCH  /api/v1/tax-returns/{id}
DELETE /api/v1/tax-returns/{id}

PUT    /api/v1/tax-returns/{id}/profile

PUT    /api/v1/tax-returns/{id}/spouse
DELETE /api/v1/tax-returns/{id}/spouse

POST   /api/v1/tax-returns/{id}/dependents
PATCH  /api/v1/tax-returns/{id}/dependents/{dependentId}
DELETE /api/v1/tax-returns/{id}/dependents/{dependentId}

POST   /api/v1/tax-returns/{id}/incomes
PATCH  /api/v1/tax-returns/{id}/incomes/{incomeId}
DELETE /api/v1/tax-returns/{id}/incomes/{incomeId}

POST   /api/v1/tax-returns/{id}/allowances
PATCH  /api/v1/tax-returns/{id}/allowances/{allowanceId}
DELETE /api/v1/tax-returns/{id}/allowances/{allowanceId}

POST   /api/v1/tax-returns/{id}/donations
PATCH  /api/v1/tax-returns/{id}/donations/{donationId}
DELETE /api/v1/tax-returns/{id}/donations/{donationId}

POST   /api/v1/tax-returns/{id}/withholdings
PATCH  /api/v1/tax-returns/{id}/withholdings/{withholdingId}
DELETE /api/v1/tax-returns/{id}/withholdings/{withholdingId}

POST   /api/v1/tax-returns/{id}/calculate
GET    /api/v1/tax-returns/{id}/calculations
GET    /api/v1/tax-returns/{id}/calculations/{calculationId}
GET    /api/v1/tax-returns/{id}/recommendations

POST   /api/v1/tax-returns/{id}/complete
POST   /api/v1/tax-returns/{id}/duplicate

GET    /api/v1/tax-returns/{id}/scenarios
POST   /api/v1/tax-returns/{id}/scenarios
GET    /api/v1/tax-returns/{id}/scenarios/{scenarioId}
PATCH  /api/v1/tax-returns/{id}/scenarios/{scenarioId}
DELETE /api/v1/tax-returns/{id}/scenarios/{scenarioId}
POST   /api/v1/tax-returns/{id}/scenarios/{scenarioId}/calculate
```

---

## 16. API Response Standard

### Success

```json
{
  "success": true,
  "message": null,
  "data": {}
}
```

### Validation Error

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {}
}
```

### Pagination

```json
{
  "success": true,
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 0,
    "last_page": 1
  }
}
```

---

## 17. Core Database Domains

### User Domain

- users

### Tax Master Domain

- tax_years
- tax_forms
- tax_rule_versions
- tax_brackets
- income_types
- tax_form_income_types
- income_rules
- expense_rules
- allowance_types
- allowance_rules
- donation_rules
- recommendation_rules

### Tax Transaction Domain

- tax_returns
- tax_return_profiles
- tax_return_spouses
- tax_return_dependents
- tax_return_incomes
- tax_return_allowances
- tax_return_donations
- tax_return_withholdings
- tax_calculations
- tax_calculation_brackets
- tax_scenarios

### Content Domain

- content_posts
- content_categories
- content_tags
- content_post_tags

---

## 18. Tax Service Layer

ต้องมี Services อย่างน้อย:

```text
TaxCalculationService
TaxAnalysisService
TaxRecommendationService
TaxPlanningService
TaxRefundService

IncomeCalculator
ExpenseCalculator
AllowanceCalculator
DonationCalculator
ProgressiveTaxCalculator
TaxCreditCalculator
```

Controller ต้องบางและไม่ถือ Business Logic

---

## 19. UI / UX Requirements

UI ต้องยึด mockup ที่ได้รับอนุมัติแล้วเป็นหลัก

Reference file ที่ควรวางไว้ใน repository:

```text
docs/ui-reference/tax-simulator-mockup.png
```

### Visual Direction

- โทนหลัก: น้ำเงิน + ขาว
- พื้นหลังสะอาด
- Card Layout
- Rounded Corners
- เงาอ่อน
- Spacing โปร่ง
- Typography อ่านง่าย
- ดูทันสมัย
- เป็นมิตรกับผู้เริ่มเรียนรู้เรื่องภาษี
- ไม่ใช้ Dark Theme เป็นค่าเริ่มต้น
- ไม่ทำให้หน้า Public Website ดูเหมือน Admin Dashboard

### Navigation

```text
หน้าแรก
ทดลองยื่นภาษี
ความรู้ภาษี
ข่าวสาร
เกี่ยวกับเรา
เข้าสู่ระบบ
```

### Home Page

ต้องมี:

- Header / Navbar
- Hero Section
- ข้อความแนะนำระบบ
- CTA “เริ่มทดลองเลย”
- จุดเด่นของระบบ
- ความรู้แนะนำ
- ข่าวล่าสุด
- Footer

### Tax Form Selection

ใช้ Card:

- ภ.ง.ด.90
- ภ.ง.ด.91

มีคำอธิบายสั้น ๆ และปุ่มเลือก

### Tax Wizard

ใช้ Stepper เช่น:

```text
ข้อมูลพื้นฐาน
-> รายได้
-> ค่าใช้จ่าย
-> ค่าลดหย่อน
-> เงินบริจาค
-> ภาษีหัก ณ ที่จ่าย
-> สรุปผล
```

### Result Page

ต้องมี:

- Summary Card
- PAYABLE / REFUND / ZERO status
- Calculation Breakdown
- Progressive Tax Breakdown
- Chart / Visualization
- Recommendation Cards
- Potential Allowances
- Tax Planning CTA
- Refund Analysis (ถ้ามี)

### Knowledge / News

ใช้ Card และ List ตาม visual direction ของ mockup

### Authentication

Login / Register ต้องใช้ visual language ชุดเดียวกับ Public Site

### Responsive

ต้องรองรับ:

- Mobile
- Tablet
- Desktop

Mobile Layout ต้องยึดแนวทาง mockup ที่อนุมัติแล้ว

---

## 20. Guest Data Behavior

Guest Data:

- ไม่บันทึกข้อมูลส่วนตัวลง MySQL โดยอัตโนมัติ
- สามารถใช้ Browser localStorage/sessionStorage เพื่อช่วยให้กรอกต่อใน session
- เมื่อ Guest ต้องการ Save ให้เสนอ Register/Login
- หลัง Login สามารถสร้าง Tax Return ใหม่จาก payload ที่ผู้ใช้อนุมัติ

---

## 21. Security Requirements

- ใช้ Laravel Sanctum
- Password ต้อง Hash
- ใช้ FormRequest validation
- ใช้ Policy ตรวจ Ownership
- ห้าม Trust calculated values จาก Client
- Validate Income Type กับ Tax Form
- Validate Tax Year / Rule Version
- Admin endpoint ต้องมี Authorization
- ใช้ CSRF protection ตามรูปแบบ Web Authentication ที่เลือก
- API token ต้องสามารถ revoke ได้
- หลีกเลี่ยงเก็บข้อมูลส่วนบุคคลที่ไม่จำเป็น
- Money ใช้ DECIMAL ใน Database
- ห้ามใช้ FLOAT สำหรับยอดเงิน

---

## 22. Auditability

Calculation Result ต้องมี Calculation Trace

ตัวอย่าง:

```json
{
  "steps": [
    {
      "code": "GROSS_INCOME",
      "label": "เงินได้รวม",
      "amount": 720000
    },
    {
      "code": "EXPENSE",
      "label": "หักค่าใช้จ่าย",
      "amount": 100000
    },
    {
      "code": "NET_INCOME",
      "label": "เงินได้สุทธิ",
      "amount": 551000
    }
  ]
}
```

---

## 23. Testing Requirements

ต้องมีอย่างน้อย:

### Unit Tests

- Progressive tax
- Expense calculation
- Allowance calculation
- Donation calculation
- Tax credit
- Refund
- Planning comparison

### Boundary Tests

ต้องทดสอบบริเวณขอบ Tax Bracket เช่น:

- 0
- 150000
- 150001
- 300000
- 300001
- ขอบช่วงอื่นตาม Rule Version

### Feature Tests

- Health API
- Public tax metadata
- Guest calculate
- PND91 validation
- Authentication
- Member Tax Return CRUD
- Ownership
- Scenario
- Content API
- Admin Authorization

---

## 24. Development Milestones

### M1 Infrastructure

- Docker
- Laravel
- MySQL
- Nginx
- Tailwind
- API Health Check

### M2 Database Foundation

- Migrations
- Models
- Relationships
- Seeders

### M3 Public Tax Metadata API

- Tax Years
- Forms
- Income Types
- Allowances
- Tax Brackets

### M4 PND91 Tax Engine

- Calculation
- Analysis
- Refund
- Recommendation

### M5 Authentication / Member

- Sanctum
- Draft
- History
- Duplicate

### M6 Tax Planning

- Scenario
- Compare
- Recommendations

### M7 PND90

- 40(1)–40(8)
- Expense Rules
- Advanced cases

### M8 Content / Admin

- Knowledge
- News
- CMS
- Tax Rule Admin

### M9 Web UI

- Implement approved mockup

### M10 Mobile API Verification

- API contract
- Token auth
- Cross-client validation

---

## 25. Non-Goals for Initial Version

ยังไม่ทำจนกว่าจะได้รับอนุมัติ:

- การยื่นแบบจริงไปกรมสรรพากร
- การชำระภาษีจริง
- การเชื่อมต่อบัญชีธนาคาร
- การขอคืนเงินจริง
- การ OCR เอกสารอัตโนมัติ
- การดึงข้อมูลภาษีจริงของบุคคล
- การให้คำแนะนำการลงทุนแบบเฉพาะบุคคล
- การใช้ AI เปลี่ยน Business Rule ทางภาษีโดยอัตโนมัติ

---

## 26. Change Control

ห้าม Codex เปลี่ยน:

- Architecture หลัก
- Technology Stack
- API Prefix
- Tax Engine Ownership
- UI Visual Direction
- Guest / Member Behavior
- Approved Scope

โดยพลการ

หากต้องการเปลี่ยน ต้อง:

1. แจ้งเหตุผล
2. ระบุผลกระทบ
3. รอการอนุมัติก่อนแก้

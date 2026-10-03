# CODING_RULES.md

## 1. Purpose

ไฟล์นี้กำหนดมาตรฐานการเขียนโค้ดสำหรับโครงการ Thai Personal Income Tax Simulation Platform

ผู้พัฒนาและ Codex ต้องอ่านไฟล์นี้ก่อนแก้ไข Source Code

---

## 2. General Principles

1. เขียนโค้ดให้อ่านง่ายก่อนเขียนให้ฉลาด
2. หลีกเลี่ยง God Class และ God Controller
3. แยก Business Logic ออกจาก HTTP Layer
4. Backend เป็น Single Source of Truth สำหรับ Tax Logic
5. ทุกการเปลี่ยนแปลง Business Logic ต้องมี Test
6. ห้ามเปลี่ยน Requirement โดยพลการ
7. หากข้อมูลทางภาษียังไม่ชัด ให้ทำ TODO และแจ้ง ไม่เดากฎเอง

---

## 3. Laravel Architecture

ใช้แนวทาง:

```text
Request
-> FormRequest Validation
-> Controller
-> DTO / Application Input
-> Service
-> Calculator / Rule Engine
-> Resource
-> JSON Response
```

Controller ต้องบาง

ตัวอย่างที่ถูก:

```php
public function calculate(
    CalculateTaxRequest $request,
    TaxCalculationService $service
) {
    $result = $service->calculate(
        TaxCalculationData::fromArray($request->validated())
    );

    return TaxCalculationResource::make($result);
}
```

หลีกเลี่ยง:

```php
public function calculate(Request $request)
{
    // 300+ lines of tax logic here
}
```

---

## 4. Directory Structure

แนะนำ:

```text
app/
├── DTO/
│   └── Tax/
├── Enums/
├── Exceptions/
├── Http/
│   ├── Controllers/
│   │   └── Api/V1/
│   ├── Requests/
│   └── Resources/
├── Models/
├── Policies/
├── Services/
│   └── Tax/
├── Support/
└── ValueObjects/
```

---

## 5. API Versioning

ทุก Public/Mobile API ใช้:

```text
/api/v1
```

ห้ามสร้าง API ใหม่ใต้ `/api` โดยไม่มี version

---

## 6. API Response Format

### Success

```json
{
  "success": true,
  "message": null,
  "data": {}
}
```

### Failure

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
    "total": 100,
    "last_page": 5
  }
}
```

---

## 7. HTTP Status Codes

ใช้ให้เหมาะสม:

- 200 OK
- 201 Created
- 204 No Content
- 400 Bad Request
- 401 Unauthorized
- 403 Forbidden
- 404 Not Found
- 409 Conflict
- 422 Unprocessable Entity
- 500 Internal Server Error

Validation ใช้ 422

---

## 8. Naming Conventions

### Database

snake_case

```text
tax_years
tax_rule_versions
tax_return_incomes
```

### Model

Singular PascalCase

```text
TaxYear
TaxRuleVersion
TaxReturnIncome
```

### Controller

```text
TaxYearController
TaxCalculationController
TaxReturnController
```

### Service

```text
TaxCalculationService
TaxPlanningService
TaxRefundService
```

### Route

ใช้ RESTful resource naming

```text
/tax-years
/tax-returns
/tax-returns/{id}/incomes
```

---

## 9. Money and Numeric Rules

ยอดเงินใน Database ต้องใช้:

```sql
DECIMAL(15,2)
```

ห้ามใช้:

```sql
FLOAT
DOUBLE
```

สำหรับยอดเงินทางภาษี

ภายใน PHP หลีกเลี่ยง floating-point arithmetic สำหรับ Logic ที่ต้องการความแน่นอน

หากใช้ตัวเลขทศนิยม ต้องกำหนดวิธี rounding ให้ชัดเจนตาม Tax Rule

---

## 10. Tax Rule Ownership

ห้าม Client ส่งค่าเหล่านี้มาเป็น Source of Truth:

```text
calculated_expense
eligible_allowance
eligible_donation
net_income
tax_rate
calculated_tax
final_tax
estimated_refund
estimated_tax_saving
```

Client ส่งได้เฉพาะ input ที่ผู้ใช้กรอก

Backend ต้องคำนวณทุกผลลัพธ์

---

## 11. Tax Rule Versioning

Tax Return ที่บันทึกต้องผูกกับ:

```text
tax_year_id
rule_version_id
tax_form_id
```

ห้ามแก้ Rule Version ที่ Published แล้วแบบ destructive หากจะเปลี่ยนกฎให้สร้าง Version ใหม่

---

## 12. Service Layer

ต้องมีอย่างน้อย:

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

แต่ละ Service ต้องมีความรับผิดชอบชัดเจน

---

## 13. DTO

ใช้ DTO เพื่อแยก HTTP Request ออกจาก Business Logic

ตัวอย่าง:

```php
final readonly class TaxCalculationData
{
    public function __construct(
        public int $taxYear,
        public string $formCode,
        public array $profile,
        public array $incomes,
        public array $allowances,
        public array $donations,
        public array $withholdings,
    ) {}
}
```

---

## 14. Validation

ใช้ Laravel FormRequest

ห้าม validation ก้อนใหญ่ใน Controller

ต้องตรวจ:

- tax_year มีอยู่และ active ตามกรณี
- form_code ถูกต้อง
- income_type รองรับโดย form
- amount >= 0
- ownership ของ member resource
- rule_version valid
- enum values valid
- nested arrays มีโครงสร้างถูกต้อง

---

## 15. Authorization

ใช้:

- Laravel Sanctum
- Policy
- Middleware
- Role Authorization

Member ต้องเข้าถึงเฉพาะ Tax Return ของตนเอง

Admin routes ต้องมี:

```text
auth:sanctum
role:admin
```

---

## 16. Guest Privacy

Guest tax calculation:

- ไม่สร้าง TaxReturn record โดยอัตโนมัติ
- ไม่ persist personal financial input โดย default
- ห้าม Log payload ที่มีข้อมูลส่วนตัวแบบเต็ม
- สามารถใช้ localStorage ฝั่ง browser สำหรับ convenience ได้
- เมื่อ Guest ต้องการ save ต้อง Register/Login ก่อน

---

## 17. Database Constraints

ใช้ Foreign Key เมื่อเหมาะสม

ใช้ Unique Constraint เช่น:

```text
tax_years.year
income_types.code
allowance_types.code
content_posts.slug
```

และ:

```text
UNIQUE(tax_year_id, code)
```

สำหรับ tax_forms

---

## 18. Soft Deletes

ใช้ SoftDeletes กับข้อมูลที่ผู้ใช้อาจต้อง recover หรือ audit เช่น:

- tax_returns
- content_posts

ไม่จำเป็นกับทุก Master Table โดยอัตโนมัติ

---

## 19. Transactions

ใช้ Database Transaction สำหรับ operation ที่มีหลายขั้น เช่น:

- Duplicate Tax Return
- Save complex Tax Return
- Publish Rule Version
- Create Scenario Snapshot
- Complete Simulation

---

## 20. Eloquent Relationships

Model Relationship ต้องประกาศให้ครบและใช้ type return เมื่อเหมาะสม

ตัวอย่าง:

```php
public function incomes(): HasMany
{
    return $this->hasMany(TaxReturnIncome::class);
}
```

---

## 21. N+1 Prevention

ใช้ eager loading เมื่อ endpoint ต้องแสดง related data

เช่น:

```php
TaxReturn::query()
    ->with(['profile', 'incomes', 'allowances'])
    ->findOrFail($id);
```

ไม่ query ใน loop โดยไม่จำเป็น

---

## 22. API Resources

ใช้ Laravel API Resources สำหรับ Public JSON

ห้าม return Model ดิบโดย default ใน API หลัก

---

## 23. Exceptions

สร้าง Domain Exception เมื่อจำเป็น เช่น:

```text
UnsupportedTaxFormException
UnsupportedIncomeTypeException
TaxRuleNotFoundException
InvalidTaxScenarioException
```

แล้ว map เป็น API Error Response ที่เหมาะสม

---

## 24. Progressive Tax Calculator

ProgressiveTaxCalculator ต้อง:

1. รับ net income
2. รับ bracket rules
3. คำนวณทีละ bracket
4. คืน total tax
5. คืน bracket breakdown
6. รองรับ upper bound = null
7. มี unit tests ครอบ boundary

ห้าม hard-code bracket ใน Controller

---

## 25. Calculation Trace

Tax Calculation ต้องคืน trace ที่อธิบายได้

เช่น:

```text
gross income
exempt income
expense
allowance
donation
net income
tax brackets
credits
final result
```

Trace ต้อง serialize เป็น JSON เพื่อ Web/Mobile แสดงผลได้

---

## 26. Recommendation Rules

Recommendation Engine เริ่มจาก Rule-based

ห้ามให้ AI เปลี่ยนกฎภาษีหรือผลคำนวณ

คำแนะนำต้องเป็นผลจาก:

```text
input data
+
tax rules
+
recommendation rules
```

ประเภท:

```text
MISSING_INFORMATION
POTENTIAL_ALLOWANCE
TAX_PLANNING
PAYMENT
REFUND
```

---

## 27. Planning Rules

Tax Planning:

- ต้องคำนวณจาก Backend
- Scenario ต้องไม่ overwrite Source Tax Return
- ต้องแสดง before / after
- ต้องแสดง estimated_tax_saving
- ต้องระบุว่าเป็น simulation

---

## 28. Refund Rules

Refund Analysis:

- ต้องใช้คำว่า estimated / ประมาณการ
- ต้องแสดงเหตุผล
- ต้องแสดง components
- ห้ามรับรองการคืนเงินจริง
- ห้ามใช้คำว่า “ได้รับคืนแน่นอน”

---

## 29. UI Coding Rules

Frontend ใช้:

- Blade
- Tailwind CSS
- Vanilla JavaScript ES Modules

ห้ามเปลี่ยนไปใช้ React/Vue/Svelte โดยไม่ได้รับอนุมัติ

UI ต้องยึด reference:

```text
docs/ui-reference/tax-simulator-mockup.png
```

ห้าม redesign visual direction โดยพลการ

---

## 30. Tailwind Rules

สร้าง reusable component สำหรับ:

- navbar
- button
- card
- form-control
- stepper
- summary-card
- recommendation-card
- article-card
- news-card
- alert
- modal
- badge

หลีกเลี่ยงการ copy utility class ก้อนใหญ่ซ้ำ ๆ หากสามารถทำ component ได้

---

## 31. JavaScript Rules

ใช้ ES Modules

ตัวอย่าง:

```javascript
import { apiClient } from './api/client.js';
```

แยกอย่างน้อย:

```text
resources/js/api/
resources/js/simulator/
resources/js/components/
```

ห้ามสร้าง `app.js` ไฟล์เดียวหลายพันบรรทัด

---

## 32. Web and Mobile Contract

Web ห้ามอาศัย response structure พิเศษที่ Mobile ใช้ไม่ได้

REST API ต้องเป็น client-independent

UI logic อยู่ Client

Tax Business Logic อยู่ Server

---

## 33. Authentication

ใช้ Laravel Sanctum

สำหรับ Mobile token ให้รองรับ:

```text
device_name
token revoke
logout current device
logout all devices
```

---

## 34. Security

- ใช้ password hashing ของ Laravel
- Validate ทุก Request
- Escape content ตามบริบท
- ห้าม expose stack trace production
- ห้าม commit `.env`
- ห้าม commit secrets
- ใช้ `.env.example`
- จำกัด Admin endpoints
- หลีกเลี่ยง mass assignment ที่ไม่ปลอดภัย

---

## 35. Content Security

ถ้า `content_posts.content` รองรับ HTML ต้องมีแนวทาง sanitize

ห้าม render HTML จาก User-generated input แบบ raw โดยไม่มีการควบคุม

---

## 36. Docker Rules

Container หลัก:

- app
- nginx
- mysql

ถ้าต้องใช้ Node build อาจรวมใน app development image หรือแยกตามความเหมาะสม

ต้องมี:

- persistent MySQL volume
- health checks ตามความเหมาะสม
- predictable service names
- `.env.example`

---

## 37. Logging

ห้าม log:

- password
- auth token
- full financial payload
- sensitive personal data

ใช้ structured logging เมื่อเหมาะสม

---

## 38. Testing Rules

ทุก Milestone ต้องรันอย่างน้อย:

```bash
php artisan test
```

ถ้ามี frontend build:

```bash
npm run build
```

และถ้า Docker ใช้งาน:

```bash
docker compose config
```

---

## 39. Unit Test Naming

ชื่อ test ต้องสื่อ behavior

ตัวอย่าง:

```text
it_calculates_zero_tax_for_first_exempt_bracket
it_calculates_tax_across_multiple_brackets
it_rejects_section_40_8_for_pnd91
it_returns_refund_when_credits_exceed_tax
```

---

## 40. Test Data

ใช้ข้อมูลสมมติ

ห้ามใช้ข้อมูลส่วนบุคคลจริงใน Seeder / Tests

---

## 41. Code Style

ใช้ Laravel / PSR conventions

ก่อนส่ง milestone ควรตรวจ:

```bash
php artisan test
```

และ formatter/linter ที่ project เลือกใช้

---

## 42. No Silent Architecture Changes

ห้าม Codex:

- เปลี่ยน Database
- เปลี่ยน Framework
- เพิ่ม SPA Framework
- เปลี่ยน API version
- เปลี่ยน authentication strategy
- ย้าย Tax Logic ไป Client
- redesign UI ใหม่

โดยไม่แจ้งและได้รับอนุมัติ

---

## 43. Required Milestone Report

ทุก Milestone ต้องรายงาน:

1. Files created
2. Files modified
3. Database changes
4. Routes added
5. API added
6. Tests added
7. Commands executed
8. Test results
9. Known issues
10. TODO
11. Recommended next step

---

## 44. Golden Rule

ถ้าต้องเลือกระหว่าง:

```text
ทำเร็วแต่เดากฎ
```

กับ:

```text
หยุดและถามให้ชัด
```

ให้เลือกอย่างหลังเสมอ โดยเฉพาะ Business Rule ทางภาษี

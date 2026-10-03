# MILESTONE_09_PROMPT.md

## Codex Task: Milestone 09 — Public Simulator UI + Member Dashboard + Planning UI Integration

You are working on:

**Thai Personal Income Tax Simulation Platform**

Milestones 1–8 are complete.

The PND90 / PND91 production tax baseline is CLOSED and frozen at:

```text
rule version 2568.1
```

M8 Content / News / Knowledge + Admin CMS + Tax Rule Administration is complete.

This milestone is the first full end-user UI integration milestone.

The objective is to turn the already-working backend into a usable Web application while preserving the previously approved visual direction.

---

# Before Changing Any File

You MUST read:

```text
PROJECT_REQUIREMENTS.md
CODING_RULES.md

MILESTONE_04_PROMPT.md
MILESTONE_05_PROMPT.md
MILESTONE_06_PROMPT.md
MILESTONE_07_5_PROMPT.md
MILESTONE_08_PROMPT.md
```

Also inspect:

```text
docs/ui/
docs/ui-reference/
resources/
routes/web.php
routes/api.php
public/
app/Http/
app/Services/
tests/
```

And inspect the currently approved mockup/reference image(s), especially any files under:

```text
docs/ui-reference/
```

The UI must follow the approved visual direction already established in this project.

Do NOT replace it with a new design language.

---

# Technology Constraint

Frontend stack remains:

```text
HTML
Tailwind CSS
JavaScript
Laravel Blade / web routes
```

Backend remains:

```text
Laravel
MySQL
API-first architecture
```

Do NOT introduce:

```text
React
Vue
Angular
Inertia
Livewire
Alpine
SPA framework
frontend state-management framework
```

unless one is already part of the project and actively used.

Prefer:

```text
Blade
Tailwind
vanilla JavaScript
fetch()
```

---

# Core Objective

Implement the actual public and member-facing UI for:

```text
Home
PND91 simulator
PND90 simulator
Tax result
Tax recommendation / refund guidance
Tax planning
Login / Register
Member dashboard
Saved drafts
Calculation history
Duplicate return
Completed return
Knowledge / News / Guides / FAQ integration
Responsive mobile experience
```

The frontend must consume the existing APIs and services.

Do NOT rewrite tax formulas in JavaScript.

The backend is the single source of truth.

---

# Non-Negotiable Rule

No tax calculation logic may be reimplemented in frontend JavaScript.

JavaScript may:

```text
collect inputs
perform UX-only validation
show/hide fields
format currency for display
call APIs
render API responses
```

JavaScript must NOT:

```text
calculate tax brackets
calculate allowance eligibility
calculate minimum tax
calculate tax savings
calculate refund entitlement
```

All tax outcomes come from backend APIs.

---

# Approved Visual Direction

Keep the previously approved UI direction.

The public site should feel:

```text
clean
trustworthy
modern
Thai-language-first
simple
educational
mobile-friendly
not government-clone
not banking-app-like
```

Preferred characteristics:

```text
light neutral background
clear cards
large readable Thai typography
simple navigation
clear progress indicator
soft rounded containers
restrained use of accent color
clear result hierarchy
```

Do NOT redesign the entire brand.

If mockup and current implementation differ, use the approved mockup as the visual guide while preserving current technical conventions.

---

# Main Public Navigation

Implement a responsive public header/navigation with:

```text
หน้าแรก
ทดลองคำนวณภาษี
ความรู้ภาษี
ข่าวสาร
คำถามที่พบบ่อย
เข้าสู่ระบบ
```

When authenticated:

```text
แดชบอร์ด
แบบภาษีของฉัน
ออกจากระบบ
```

Keep mobile navigation usable without JavaScript-heavy frameworks.

---

# Part A — Public Home Page

Implement:

```text
/
```

The homepage should include:

## Hero

Purpose:

```text
ทดลองคำนวณภาษีบุคคลธรรมดา
โดยไม่ต้องสมัครสมาชิก
```

Primary CTA:

```text
เริ่มทดลองคำนวณ
```

Secondary CTA:

```text
ศึกษาข้อมูลภาษี
```

Do not imply official filing.

---

# Tax Form Selection

Show:

```text
ภ.ง.ด.91
สำหรับผู้มีเงินได้ตามมาตรา 40(1)

ภ.ง.ด.90
สำหรับผู้มีเงินได้หลายประเภทตามมาตรา 40(1)–40(8)
```

Use backend metadata where practical.

Do not duplicate rule eligibility logic in Blade.

---

# Homepage Education Sections

Include:

```text
ระบบนี้ทำอะไร
ขั้นตอนการทดลอง
ภ.ง.ด.90 กับ ภ.ง.ด.91 ต่างกันอย่างไร
บทความแนะนำ
ข่าว/อัปเดตล่าสุด
FAQ preview
simulation disclaimer
```

Content must come from M8 CMS/public content APIs or services where practical.

Avoid hard-coding all content into Blade.

---

# Part B — Guest Simulator UI

Implement:

```text
/tax-simulator
/tax-simulator/pnd91
/tax-simulator/pnd90
```

or equivalent approved routes.

Do not require login.

---

# Simulator Wizard

Use a multi-step UI.

Suggested flow:

```text
Step 1 — เลือกปีภาษี / แบบ
Step 2 — ข้อมูลผู้เสียภาษี
Step 3 — คู่สมรส / ผู้พึ่งพา
Step 4 — เงินได้
Step 5 — ค่าลดหย่อน
Step 6 — เงินบริจาค / ภาษีหัก ณ ที่จ่าย
Step 7 — ตรวจสอบข้อมูล
Step 8 — ผลการคำนวณ
```

Actual step ordering may be adjusted to match existing data/API shape.

Keep the UI simple.

---

# Wizard UX Requirements

Must support:

```text
Next
Back
Save to browser temporarily during session
validation summary
step progress
mobile-friendly controls
currency formatting
clear required vs optional fields
```

Guest browser state may use:

```text
sessionStorage
```

or in-memory JS state.

Do NOT persist guest financial data to backend.

Do NOT use localStorage for guest financial data unless absolutely necessary.

Preferred:

```text
sessionStorage
```

and clear when user chooses reset.

---

# Form State

Keep one canonical JavaScript state object matching backend API structure.

Example:

```javascript
{
  tax_year: 2568,
  form_code: "PND91",
  profile: {},
  spouse: null,
  dependents: [],
  incomes: [],
  allowances: [],
  donations: [],
  withholdings: []
}
```

Do not create a frontend-only incompatible schema.

---

# Tax Year / Form Metadata

Load from existing metadata APIs.

Do not hard-code:

```text
2568
PND90
PND91
income type list
allowance code list
```

except as fallback display constants where necessary.

---

# PND91 Income UI

For PND91:

```text
SECTION_40_1 only
```

Allow multiple employer/payer rows.

Fields may include:

```text
description
gross amount
exempt amount
withholding
```

Use existing backend contract.

Do not expose unsupported fields.

---

# PND90 Income UI

For PND90:

Allow:

```text
SECTION_40_1
SECTION_40_2
SECTION_40_3
SECTION_40_4
SECTION_40_5
SECTION_40_6
SECTION_40_7
SECTION_40_8
```

Where the backend exposes subtype/activity metadata:

render a subtype/activity selector.

Do not infer subtype from user description.

If the API reports a path as unsupported or partial-blocked:

show the backend message clearly.

Do not silently substitute another income type.

---

# Family Input UI

Use structured UI matching the current family DTO:

```text
profile
spouse
dependents
```

Do not ask the user to calculate PERSONAL/SPOUSE/CHILD allowance manually.

Server-derived family allowance rules remain authoritative.

---

# Allowance UI

Group allowance items into understandable sections.

Suggested grouping:

```text
ส่วนบุคคลและครอบครัว
ประกัน
กองทุน/การออม
ดอกเบี้ยที่อยู่อาศัย
มาตรการภาษีประจำปี
อื่น ๆ
```

Use metadata where possible.

If backend marks an allowance:

```text
SUPPORTED
```

allow normal input.

If:

```text
PARTIAL_BLOCKED
UNSUPPORTED
```

show:

```text
ยังไม่รองรับในการคำนวณอัตโนมัติ
```

with explanation.

Do NOT hide unsupported items if they help explain product coverage.

---

# Client-Side Validation

Client-side validation is UX only.

Examples:

```text
required field
numeric format
non-negative
date format
obvious array state
```

Backend validation remains authoritative.

Always display backend validation errors by field/path.

---

# Review Step

Before calculation, show a readable summary:

```text
แบบภาษี
ปีภาษี
ข้อมูลครอบครัว
เงินได้
ค่าลดหย่อน
บริจาค
ภาษีหัก ณ ที่จ่าย
```

Allow editing each section.

---

# Calculate Action

Call:

```text
POST /api/v1/tax/calculate
```

Show:

```text
loading state
disabled submit while pending
network error state
validation error state
domain unsupported state
```

Prevent accidental duplicate submissions.

---

# Part C — Calculation Result UI

The result screen must make the output understandable.

Primary result card:

```text
ต้องชำระเพิ่ม
คาดว่าจะได้รับคืน
ไม่มียอดเพิ่ม/คืน
```

Use backend:

```text
PAYABLE
REFUND
ZERO
```

Do not infer from sign.

---

# Result Summary

Show:

```text
รายได้รวม
ค่าใช้จ่าย
ค่าลดหย่อน
เงินบริจาค
เงินได้สุทธิ
ภาษีคำนวณ
ภาษีหัก/เครดิต
ผลสุดท้าย
```

For PND90 show minimum-tax component where applicable.

---

# Tax Breakdown

Provide expandable details:

```text
รายได้แยกตามประเภท
ค่าใช้จ่ายแยกตาม rule
ขั้นอัตราภาษี
ค่าลดหย่อน
combined caps
minimum tax
credits
```

Render from API result.

Do not reconstruct calculations.

---

# Calculation Trace

Provide optional:

```text
ดูวิธีคำนวณ
```

Render API trace in a readable step-by-step timeline.

This is educational.

Do not expose raw debug data.

---

# Warnings

Warnings must be visually distinct but not alarming.

Examples:

```text
ROUNDING_RULE_PENDING
unsupported optional rule
taxpayer-declared eligibility
```

Show Thai explanation.

Do not suppress warnings.

---

# Disclaimer

Every result page must show a clear disclaimer equivalent to:

```text
ผลลัพธ์เป็นการประมาณการจากข้อมูลที่กรอกในระบบจำลอง
ไม่ใช่การยื่นแบบภาษีจริงหรือการรับรองผลโดยหน่วยงานราชการ
```

---

# Part D — Recommendation / Refund / Payment Guidance UI

Render M6 output:

```text
recommendations
refund_guidance
payment_guidance
```

Recommendation cards must show:

```text
title
message
priority
action
```

Do not convert recommendations into guaranteed eligibility language.

---

# Refund Guidance

If result = REFUND:

show:

```text
estimated refund
reason
components
checklist
disclaimer
```

Use wording:

```text
ประมาณการเงินคืน
```

not:

```text
เงินคืนที่คุณจะได้รับแน่นอน
```

---

# Payment Guidance

If PAYABLE:

show:

```text
estimated additional payment
reason
```

Do not add real payment-channel instructions unless backend/content explicitly supports them.

---

# Part E — Guest Planning UI

After calculation, offer:

```text
ทดลองวางแผนภาษี
```

Use:

```text
POST /api/v1/tax/plan
```

Planning UI should allow safe scenario changes only for supported inputs.

Examples:

```text
change supported allowance amount
add/remove supported scenario allowance
modify withholding where appropriate
```

Do not expose unsupported rules as if they can save tax.

---

# Planning Comparison UI

Show:

```text
ก่อนวางแผน
หลังวางแผน
ส่วนต่าง
ภาษีที่คาดว่าลดลง
```

Use backend values:

```text
before
after
difference
estimated_tax_saving
```

Do not calculate tax saving in JavaScript.

---

# Part F — Save Guest Result to Member Account

When Guest completes a calculation:

offer:

```text
บันทึกผลการทดลอง
```

If not authenticated:

show:

```text
สมัครสมาชิก
เข้าสู่ระบบ
```

After successful auth:

map existing guest state into member APIs.

Do NOT ask user to re-enter the entire form.

Flow:

```text
guest calculation
-> login/register
-> create tax return
-> persist existing state
-> optionally calculate saved return
-> redirect to member return
```

No anonymous server-side persistence required.

---

# Part G — Login / Register UI

Implement public pages:

```text
/login
/register
```

Use existing M5 auth APIs.

Fields:

Register:

```text
name
email
password
password_confirmation
```

Login:

```text
email
password
```

Handle:

```text
401
422
network error
```

Do not leak invalid credential details.

---

# Auth Token Handling

The M8 admin console used localStorage for its token.

For the public/member UI, inspect current backend auth mode.

Preferred:

```text
secure Laravel/Sanctum session-cookie workflow
```

for browser UI if supported.

If the current architecture is token-only:

use the safest available existing mechanism.

Do not duplicate auth systems.

Document the decision.

Do not expose token values in rendered HTML.

---

# Part H — Member Dashboard

Implement:

```text
/dashboard
```

Show:

```text
active drafts
recent calculations
completed simulations
recent planning scenarios
quick start new simulation
```

Use current M5/M6 APIs.

---

# Dashboard Summary Cards

Suggested:

```text
แบบร่าง
คำนวณล่าสุด
รอกรอกต่อ
รายการที่เสร็จสิ้น
```

No unnecessary financial analytics.

---

# Tax Return List

Implement:

```text
/dashboard/tax-returns
```

Support UI filters:

```text
status
tax year
form
search
```

Use backend pagination/filtering.

---

# Tax Return Card / Row

Show:

```text
name
tax year
form
status
current step
last updated
latest result if available
```

Actions:

```text
กรอกต่อ
ดูผล
ทำสำเนา
ลบ
```

Completed return:

```text
read-only
```

Offer:

```text
ทำสำเนาเพื่อแก้ไข
```

---

# Resume Draft

Opening draft should load saved:

```text
profile
spouse
dependents
incomes
allowances
donations
withholdings
```

into the same wizard components used by Guest.

Avoid a second UI implementation.

Use shared Blade partials / JS modules where practical.

---

# Member Save Behavior

Member wizard should save changes using existing CRUD APIs.

Support:

```text
save draft
auto-save only if simple and robust
```

Preferred M9 baseline:

```text
explicit Save and Continue
```

Do not introduce aggressive autosave complexity.

---

# Member Calculate

Call:

```text
POST /api/v1/tax-returns/{id}/calculate
```

Render result with the same UI component as Guest result.

Member calculation history must not be recomputed on read.

---

# Calculation History UI

Implement:

```text
/dashboard/tax-returns/{id}/history
```

Show:

```text
date/time
net income
calculated tax
PAYABLE / REFUND / ZERO
amount
```

Detail opens stored calculation snapshot.

Do not rerun current rules to display historical result.

---

# Complete Simulation UI

For a draft:

```text
เสร็จสิ้นการทดลอง
```

Call existing complete endpoint.

Confirm before action.

Do not use wording:

```text
ยื่นภาษี
ส่งกรมสรรพากร
```

---

# Duplicate UI

Allow:

```text
ทำสำเนา
```

Request a new name.

Call existing duplicate endpoint.

Redirect to the new draft.

---

# Part I — Member Planning Scenarios

Implement:

```text
/dashboard/tax-returns/{id}/planning
```

Show saved scenarios.

Actions:

```text
สร้าง scenario
แก้ชื่อ/ข้อมูล
คำนวณ
ดูผล
ลบ
```

Scenario UI must reuse planning components.

---

# Scenario Comparison

Show:

```text
base
scenario
estimated tax saving
warnings
recommendations
```

Do not modify source TaxReturn.

Make this explicit in UI:

```text
การทดลองนี้ไม่เปลี่ยนข้อมูลแบบต้นฉบับ
```

---

# Part J — Content / Knowledge UI Integration

Use M8 content APIs/services.

Implement:

```text
/knowledge
/news
/article/{slug}
/faq
```

or equivalent approved routes.

Do not create duplicated CMS data in Blade.

---

# Knowledge Listing

Support:

```text
category
tag
tax year
search
pagination
```

Responsive card layout.

---

# Article Detail

Render:

```text
title
excerpt
body
category
tags
tax year
published date
```

Use sanitized content from M8.

---

# FAQ

Use accessible disclosure/accordion behavior.

Vanilla JavaScript is enough.

---

# Part K — Shared UI Components

Create reusable partials/components where practical:

```text
header
footer
breadcrumbs
button
form field
currency input
alert
modal
progress stepper
result card
warning box
recommendation card
pagination
empty state
loading state
```

Do not build an abstract component framework.

---

# Accessibility

At minimum:

```text
semantic labels
keyboard navigation
visible focus
aria-expanded for disclosures
error messages associated with fields
sufficient contrast
buttons are actual buttons
```

Do not rely only on color to communicate status.

---

# Responsive Design

Must work at:

```text
mobile
tablet
desktop
```

Test at least:

```text
375px
768px
1280px
```

No horizontal form overflow.

Large tax tables may scroll horizontally inside a contained region.

---

# Thai Typography / Formatting

Use readable Thai typography available in the current project.

Do not bundle new proprietary fonts.

Display currency with:

```text
บาท
thousands separators
2 decimals where API returns decimals
```

Do not change calculation precision.

---

# Loading / Error States

Every API-driven page must have:

```text
loading
empty
validation error
authorization error
network error
unsupported rule
```

states where relevant.

Do not leave blank pages on failure.

---

# HTTP Error UX

Map common responses:

```text
401 -> login required
403 -> no permission
404 -> not found
409 -> completed/read-only conflict
422 -> validation/domain rule problem
500 -> generic retry/support message
```

Never show raw exception traces.

---

# Security

Do not expose:

```text
tokens
passwords
other users' data
raw API secrets
internal exception stack traces
```

Use CSRF/session protections where applicable.

Escape user content unless it was sanitized by M8 CMS.

---

# Mobile API Compatibility

Do not make API changes solely for Web if the same API can support mobile.

Web-specific rendering belongs in Blade/JS.

API remains platform-neutral.

---

# SEO / Public Pages

Use M8 meta fields for:

```text
title
description
canonical path
```

Do not expose private/member pages to indexing intentionally.

Member dashboard pages should use:

```text
noindex
```

where practical.

---

# No Tax Engine Reopen

Do NOT modify:

```text
tax bracket formulas
expense rules
minimum tax
allowance eligibility
donation formulas
credits
refund formulas
```

unless an actual regression defect prevents UI integration.

If a backend defect is discovered:

1. document it;
2. make the smallest safe correction;
3. add regression test;
4. report it prominently.

Do not expand M7 scope.

---

# Routes

Suggested public web routes:

```text
/
tax-simulator
tax-simulator/pnd91
tax-simulator/pnd90
knowledge
news
article/{slug}
faq
login
register
```

Suggested authenticated:

```text
dashboard
dashboard/tax-returns
dashboard/tax-returns/{id}
dashboard/tax-returns/{id}/history
dashboard/tax-returns/{id}/planning
```

Use project conventions.

---

# JavaScript Structure

Avoid one giant file.

Suggested modules:

```text
api.js
auth.js
simulator-state.js
simulator-wizard.js
tax-result.js
tax-planning.js
member-return.js
content.js
ui.js
```

Use ES modules if current Vite setup supports them.

Do not add a framework.

---

# API Client

Create a small shared API wrapper for:

```text
base headers
JSON serialization
auth handling
422 normalization
network errors
```

Do not hide HTTP semantics excessively.

---

# Automated Tests

Add feature/browser-adjacent tests where practical.

At minimum backend-rendered web route tests:

```text
home 200
simulator pages 200
knowledge 200
article published visible
draft article not visible
login/register pages 200
dashboard requires auth
member dashboard 200
admin/member boundaries remain correct
```

---

# JavaScript Tests

If the project has no JS test runner, do not introduce a large testing stack solely for M9.

Prefer:

```text
backend feature tests
manual browser verification
frontend build validation
```

If a lightweight existing JS test setup already exists, use it.

---

# Integration Tests

Must verify:

```text
Guest PND91 full API flow
Guest PND90 full API flow
Guest planning
register/login
save Guest state into Member return
Member resume
Member calculate
history
complete
duplicate
scenario
content rendering
```

Some of these may remain API tests plus manual UI verification.

---

# Manual Browser Verification

Use the live Docker stack.

Test desktop and mobile widths.

Required manual flow:

## Guest PND91

1. open home
2. choose PND91
3. fill synthetic income
4. fill family data
5. review
6. calculate
7. inspect trace
8. inspect recommendation
9. planning comparison

## Guest PND90

10. choose PND90
11. add multiple income types
12. select supported subtype/activity
13. calculate
14. inspect minimum-tax path if applicable
15. confirm unsupported path error rendering

## Member

16. register/login
17. save Guest state
18. open dashboard
19. resume draft
20. calculate member return
21. open history
22. duplicate
23. complete
24. confirm completed return read-only

## Planning

25. create member scenario
26. calculate scenario
27. confirm source return unchanged

## Content

28. open knowledge
29. open news
30. open article
31. open FAQ

## Responsive

32. test at 375px
33. test at 768px
34. test desktop

---

# Visual Verification

Compare implementation with the approved UI mockup/reference.

Do not make broad visual changes without reason.

Final report must state:

```text
which reference/mockup files were used
which pages match them
any intentional deviation
```

---

# Frontend Build

Run:

```bash
npm run build
```

or current project equivalent.

No warnings that indicate broken imports/assets should remain.

---

# Regression

All M1–M8 tests must remain green.

Especially:

```text
PND91 baseline
PND90 baseline
minimum tax
family allowance
planning/recommendation
Member CRUD/history
content CMS
rule administration
published version immutability
```

Do not weaken backend tests to make UI work.

---

# Documentation

Create:

```text
docs/ui/MILESTONE_09_UI.md
docs/ui/SIMULATOR_FLOW.md
docs/ui/MEMBER_DASHBOARD_FLOW.md
```

Update README with:

```text
public URLs
member URLs
build/run instructions
```

---

# Completion Criteria

M9 is complete only when:

```text
[ ] public homepage implemented
[ ] PND91 simulator UI implemented
[ ] PND90 simulator UI implemented
[ ] Guest calculation works end-to-end
[ ] Guest planning works
[ ] calculation result UI works
[ ] trace/warnings/recommendations render
[ ] refund/payment guidance renders
[ ] login/register UI works
[ ] Guest-to-Member save flow works
[ ] member dashboard works
[ ] draft resume works
[ ] member calculation works
[ ] calculation history UI works
[ ] duplicate works
[ ] complete works
[ ] completed return is read-only
[ ] member planning scenario UI works
[ ] knowledge/news/article/FAQ pages work
[ ] UI is responsive
[ ] UI follows approved mockup direction
[ ] no frontend tax formulas were added
[ ] APIs remain mobile-compatible
[ ] frontend build passes
[ ] all M1–M8 regressions pass
```

---

# Required Final Report

Report exactly:

## Summary

## Reference / Mockup Files Used

## Public Routes Implemented

## Member Routes Implemented

## Blade Views / Partials Created

## JavaScript Modules Created

## Shared UI Components

## Guest Simulator Flow

## PND91 UI

## PND90 UI

## Result UI

## Recommendation / Refund / Payment UI

## Planning UI

## Authentication UI

## Guest-to-Member Save Flow

## Member Dashboard

## Draft / Resume Flow

## Calculation History UI

## Duplicate / Complete Flow

## Member Scenario UI

## Knowledge / News / FAQ UI

## Responsive Behavior

Report verification at:

```text
375px
768px
desktop
```

## Accessibility

## Auth Storage / Browser Security Decision

Explain cookie/session/token approach actually used.

## API Integration

List APIs consumed.

## Tax Logic Compliance

Confirm:

```text
No tax formulas exist in frontend JS.
TaxCalculationService/backend APIs remain authoritative.
```

## Files Created

## Files Modified

## Commands Executed

Only actual commands.

## Test Coverage

## Test Results

Report:

```text
SQLite passed/failed/skipped/assertions
MySQL passed/failed/skipped/assertions
frontend build
```

## Manual Browser Verification

Report each required flow and result.

## Visual Compliance

Explain how implementation follows approved mockup.

List intentional deviations, if any.

## Regression Status

Confirm:

```text
PND91 baseline unchanged
PND90 baseline unchanged
planning/recommendation unchanged
member history intact
M8 CMS/admin intact
published rule immutability intact
```

## Known Issues

List unresolved M9 UI issues or:

```text
None
```

## Architecture Compliance

Confirm:

```text
No frontend tax calculation engine was created.
No React/Vue/SPA framework was introduced.
Blade/Tailwind/vanilla JS remain the frontend stack.
Backend remains the source of truth.
Guest calculations remain stateless.
Member history remains persisted.
Web and future Mobile use the same APIs.
M7.x tax baseline was not reopened.
```

## Next Step

Recommend:

```text
Milestone 10 — QA / UX Polish / Security Hardening / Production Readiness
```

Do not begin Milestone 10 until approved.

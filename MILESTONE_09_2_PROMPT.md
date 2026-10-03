# MILESTONE_09_2_PROMPT.md

## Codex Task: Milestone 09.2 — PND90 / PND91 Form Fidelity + Guided Data Entry

Current status:
- M1–M8 complete
- M9 Public Simulator UI complete
- M9.1 UI Mockup Reconciliation + Development Seed Data complete
- M10 Production Readiness completed, but production rollout is PAUSED

Production rollout is paused because the current simulator form does not yet collect enough detail to faithfully reflect the real PND90 / PND91 forms and filing instructions.

This milestone fixes:
- FORM FIDELITY
- DATA COLLECTION COMPLETENESS
- USER GUIDANCE
- FORM-TO-API TRACEABILITY

This is NOT a tax-engine redesign milestone.
Do NOT reopen tax formulas unless a source-required field is impossible to represent with the current additive API/DTO design.

## Before changing files

Read:
- PROJECT_REQUIREMENTS.md
- CODING_RULES.md
- MILESTONE_07_5_PROMPT.md
- MILESTONE_09_PROMPT.md
- MILESTONE_09_1_PROMPT.md
- MILESTONE_10_PROMPT.md

Inspect all repository-approved sources under:
- docs/tax-source/

Especially:
- PND90 form
- PND91 form
- PND90 filing instructions
- PND91 filing instructions
- allowance attachments
- approved tax references

Inspect current code:
- resources/views/
- resources/js/
- resources/css/
- routes/web.php
- routes/api.php
- app/DTO/
- app/Http/Requests/
- app/Http/Resources/
- app/Services/
- database/
- tests/
- docs/ui/
- docs/api/
- docs/tax/

## Source policy

The real PND90 / PND91 forms and filing instructions are the source of truth.

Do NOT:
- invent fields from general tax knowledge
- use web research
- silently reinterpret source wording
- add unsupported numeric rules

If the source contains a field the system cannot support, classify it explicitly.

## Core objective

Make data entry complete enough that users can relate what they enter to the real PND90 / PND91 forms.

The goal is NOT to reproduce every printed box visually.

The goal is:
- every material source section/field has a known UI/API status
- users understand what data is required and why
- users can recognize where information belongs
- unsupported source items are visible and explained
- review output follows form logic

# Part A — Formal Form Coverage Matrices

Create:
- docs/ui/PND90_FORM_COVERAGE_MATRIX_2568.md
- docs/ui/PND91_FORM_COVERAGE_MATRIX_2568.md

For EVERY material section/field, document:
- source file
- page
- section/item/line
- source label
- plain-language Thai label
- form code
- UI section
- UI field/control
- API field/path
- database field/model if persisted
- status
- notes

Allowed statuses only:
- IMPLEMENTED
- MISSING_UI
- MISSING_API
- UNSUPPORTED
- NEEDS_GUIDANCE
- NOT_APPLICABLE

Definitions:
- IMPLEMENTED: UI + API mapping already correct
- MISSING_UI: API supports it but UI does not expose it clearly
- MISSING_API: required for an already-supported calculation but API/DTO lacks a safe field
- UNSUPPORTED: present in source but intentionally unsupported in this release
- NEEDS_GUIDANCE: present but too ambiguous/confusing in current UI
- NOT_APPLICABLE: outside simulator scope

Do not leave blank status cells.

# Part B — Preserve Source Organization

Rework the wizard to follow the logical form sequence more closely instead of backend-table structure.

Suggested high-level sequence:
1. ปีภาษีและแบบ
2. ข้อมูลผู้มีเงินได้
3. คู่สมรส / ผู้พึ่งพา
4. เงินได้
5. ค่าใช้จ่าย
6. ค่าลดหย่อน
7. เงินบริจาค
8. ภาษีหัก ณ ที่จ่าย / ภาษีชำระไว้
9. ตรวจสอบข้อมูล
10. ผลการคำนวณ

Adjust to source ordering where necessary.

# Part C — PND91 Fidelity

Audit PND91 field-by-field against the real form/instructions.

At minimum review:
- taxpayer information
- SECTION_40_1 income rows
- payer/employer information where relevant
- gross income
- exempt income
- withholding
- expense treatment
- personal/family facts
- supported allowances
- donations
- prepaid/withheld tax
- review structure

Do not present one generic “รายได้” field if the source expects more context.

Where supported, capture/display:
- payer/employer description
- income amount
- withholding amount
- exempt amount if applicable
- short source-reference helper

# Part D — PND90 Fidelity

Audit PND90 deeply for:
- SECTION_40_1
- SECTION_40_2
- SECTION_40_3
- SECTION_40_4
- SECTION_40_5
- SECTION_40_6
- SECTION_40_7
- SECTION_40_8

For each type, provide:
- source-derived title
- plain-language explanation
- source-supported examples
- supported subtypes/activities
- required fields
- expense-method explanation where useful
- unsupported/partial warnings

Never display raw machine codes as the primary user label.

# Part E — Progressive Disclosure

Do NOT show every field at once.

Use progressive disclosure:
- choose income type -> reveal relevant fields
- choose family status -> reveal spouse/dependent fields
- choose allowance category -> reveal relevant facts

The form should feel guided rather than overwhelming.

# Part F — Field-Level Guidance

For important fields add concise help:
- what the data is used for
- where the user can find it
- whether amount is before/after deductions
- when not to enter it

Where useful show:
- ดูคำอธิบายจากแบบ
- ดูตัวอย่าง
- อ้างอิง: ภ.ง.ด.90/91 ข้อ ...

Do not show repository paths to end users.

# Part G — Family / Dependent Inputs

Reconcile:
- PERSONAL
- SPOUSE
- CHILD
- PARENT
- DISABLED_PERSON

Where eligibility remains taxpayer-declared, explain this explicitly.

Review human-readable UI for:
- child_type
- birth_order
- birth_date
- eligibility declaration

Do not expose internal enum names directly.

# Part H — Allowance Entry Fidelity

Do not use a single generic allowance dropdown + amount where materially different source conditions exist.

Group allowances logically.

For each supported allowance show only relevant fields such as:
- amount
- date
- category
- relationship
- policy/payment type
- source-required condition

Only add fields the backend supports or that are required by an already-supported path.

For PARTIAL_BLOCKED / UNSUPPORTED allowances, do NOT silently hide them if that confuses form fidelity. Show a clear informational state:
“รายการนี้มีในแบบ แต่ระบบจำลองรุ่นปัจจุบันยังไม่รองรับการคำนวณอัตโนมัติ”

Do not allow misleading numeric entry for unsupported rules.

# Part I — Donations / Credits / Prepayments

Reconcile:
- donations
- withholding
- PND93 if supported
- PND94 if supported
- foreign tax credit
- other credit

Unsupported credit paths must not show enabled misleading amount fields.

# Part J — Expense Method Clarity

For PND90 categories with source-supported expense choices/subtypes, explain whether the system uses:
- standard/percentage expense
- actual expense
- activity/subtype rule

Where backend requires explicit selection, make it prominent.

# Part K — Review Screen Fidelity

The review step must follow real form logic.

PND91:
- income
- expense
- allowances
- donations
- withholding/prepaid tax

PND90:
- income by 40(1)–40(8)
- expense by type/activity
- family allowances
- other allowances
- donations
- credits/prepayments
- minimum-tax information where applicable

Allow jump-back editing per section.

# Part L — User-Friendly Terminology

Use:
source legal/form term + plain-language explanation

Example:
“เงินได้ตามมาตรา 40(1) — เงินเดือน ค่าจ้าง โบนัส และเงินได้จากการจ้างแรงงาน”

Do not replace legal/form terminology entirely.

# Part M — Missing API Fields

If the coverage matrix finds a source-required field necessary for a currently SUPPORTED calculation path but the API lacks it, a small additive backend change is allowed.

Rules:
- no formula changes
- no new tax rules
- no historical rewrite
- backward-compatible request field
- validation
- DTO mapping
- tests
- mobile-compatible API

Do NOT add fields merely because they are printed on the form.

Add only when required for:
- an already-supported calculation
- disambiguating a supported rule
- preventing misleading user input
- preserving Member resume fidelity

# Part N — Guided Examples

Add short source-safe examples for confusing sections.

Good:
“ตัวอย่าง: เงินเดือน โบนัส ค่าจ้าง”

Bad:
“กรอก 500,000 แล้วจะเสียภาษี 12,500”
unless dynamically returned from backend.

# Part O — Completion Indicators

Each wizard step should show:
- complete
- incomplete
- needs attention
- unsupported item present

# Part P — Validation Mapping

Backend paths like:
- incomes.2.actual_expense
- dependents.0.birth_date

must map to understandable Thai UI errors.

Do not show raw JSON paths to users.

# Part Q — Help Drawer / Modal

Implement a lightweight help mechanism where useful:
- คำอธิบายรายการ
- ตัวอย่าง
- อ้างอิงจากแบบ

Use Blade + vanilla JS.

# Part R — Mobile Fidelity

Keep the form understandable on mobile.

Prefer:
- stacked cards
- collapsible sections
- clear progress/actions

Avoid:
- wide dense grids
- tiny help text
- accidental horizontal overflow

# Part S — Persisted Member Fidelity

When a Member resumes a draft, reconstruct all supported detailed state:
- subtypes
- family facts
- allowance-specific facts
- unsupported-warning state

If necessary fields are not persisted, classify and minimally fix only where required.

# Part T — Guest Fidelity

Guest remains stateless on backend.

Detailed Guest form state may live in browser session only.

# Part U — Documentation

Create:
- docs/ui/PND90_FORM_COVERAGE_MATRIX_2568.md
- docs/ui/PND91_FORM_COVERAGE_MATRIX_2568.md
- docs/ui/FORM_FIDELITY_GUIDELINES.md
- docs/ui/M9_2_FORM_RECONCILIATION.md

FORM_FIDELITY_GUIDELINES.md must define:
- source term + plain-language explanation
- unsupported field presentation
- source reference display
- backend validation -> UI mapping
- progressive disclosure behavior

# Part V — Tests

Add/update tests for:
- PND91 required UI fields
- PND90 income-type/subtype UI
- unsupported rule visibility
- guidance rendering
- review grouping
- validation mapping
- Member resume fidelity
- Guest stateless behavior

Do not create brittle pixel tests.

# Part W — Manual Source Comparison

For both PND91 and PND90:
- open the real form/instructions side-by-side with simulator
- walk section by section
- record source item -> UI location -> status

Do not declare completeness based only on API tests.

# Part X — Required Manual Flows

PND91:
1. taxpayer data
2. 40(1) income
3. withholding
4. family
5. allowances
6. donations
7. review
8. calculate
9. compare to source form

PND90:
10. multiple 40(x) income types
11. subtype/activity
12. expense-related facts
13. family
14. allowances
15. donations/credits
16. review
17. calculate
18. compare to source form

Unsupported:
19. find one source-listed unsupported item
20. verify UI explains it
21. verify no misleading enabled amount field exists

Member:
22. save detailed draft
23. leave page
24. resume
25. verify all detailed selections/facts remain intact

# Part Y — Deployment Gate

Do NOT resume production deployment yet.

At the end of M9.2, if approved, recommend rerunning the M10 regression/security checklist.

# Completion Criteria

M9.2 is complete only when:
- PND90 coverage matrix exists
- PND91 coverage matrix exists
- every material source section/field has a status
- PND91 UI collects all data needed by supported baseline
- PND90 UI collects all data needed by supported baseline
- income guidance is clear
- subtype/activity guidance is clear
- family inputs are clear
- allowance entry is source-aware
- unsupported source items are disclosed
- donations/credits are clearly distinguished
- review page follows form logic
- backend validation maps to Thai UI messages
- Member resume preserves detailed data
- Guest remains stateless
- mobile layout remains usable
- no frontend tax formulas added
- no unverified tax rule added
- tax baseline semantics unchanged
- all regressions pass

# Required Final Report

Report exactly:

## Summary

## Source Documents Used
List exact repository paths.

## PND91 Form Coverage
Counts:
- IMPLEMENTED
- MISSING_UI
- MISSING_API
- UNSUPPORTED
- NEEDS_GUIDANCE
- NOT_APPLICABLE

List any remaining material non-IMPLEMENTED items.

## PND90 Form Coverage
Same counts and remaining material items.

## Fields Added to UI

## Fields Added to API / DTO
If none: None

For each backend field added, explain why it was necessary for an already-supported path.

## Guided Data Entry Improvements

## Unsupported / Partial Item Presentation

## Review Screen Changes

## Validation UX Changes

## Member Resume Fidelity

## Mobile Behavior

## Documentation Created

## Files Created

## Files Modified

## Commands Executed
Only actual commands.

## Test Results
Report:
- SQLite
- MySQL
- Pint/lint
- frontend build

## Manual PND91 Source Comparison

## Manual PND90 Source Comparison

## Known Limitations

## Tax Baseline Integrity
Confirm:
- No tax formula changed.
- No published rule semantics changed.
- No unverified numeric rule added.
- Historical calculation snapshots not rewritten.
- TaxCalculationService remains authoritative.

## Architecture Compliance
Confirm:
- Blade/Tailwind/vanilla JS retained.
- No frontend tax engine exists.
- Web and future Mobile use the same API fields.
- Guest remains stateless.
- Member draft persistence preserves supported detailed input.

## Closure Decision

Exactly one:
FORM_FIDELITY_BASELINE_APPROVED
or
FORM_FIDELITY_NOT_READY

If NOT READY, list only source-required fields/sections that still make supported PND90/PND91 data entry materially incomplete.

## Next Step

If approved:
“Re-run the M10 production-readiness regression/security checklist after M9.2 changes. Do not add new features.”

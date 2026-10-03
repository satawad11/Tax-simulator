# MILESTONE_09_2_2_PROMPT.md

## Codex Task: M9.2.2 — Data Entry Integrity + Exact Required Inputs Reconciliation

This is a focused corrective task after M9.2 / M9.2.1.

Do NOT add new tax rules.
Do NOT change tax formulas.
Do NOT modify published rule version 2568.1.
Do NOT publish draft 2568.2.
Do NOT redesign the overall UI.
Do NOT begin production deployment.

The goal is to fix two remaining usability/data-integrity problems:

1. Users can add duplicate tax items that should be unique.
2. The exact list of fields the user must enter is still not sufficiently aligned with the real PND90 / PND91 form logic.

# Read First

Read:
- REMAINING_IMPLEMENTATION_GAPS.md
- FINAL_GAP_CLOSURE_MATRIX.md
- docs/ui/PND90_FORM_COVERAGE_MATRIX_2568.md
- docs/ui/PND91_FORM_COVERAGE_MATRIX_2568.md
- docs/ui/FORM_FIDELITY_GUIDELINES.md
- docs/ui/M9_2_FORM_RECONCILIATION.md
- docs/tax/
- docs/tax-source/
- docs/api/

Inspect current:
- resources/views/
- resources/js/
- app/DTO/
- app/Http/Requests/
- app/Services/
- app/Models/
- database/
- tests/

# Core Objective

The simulator must obey this rule:

ผู้ใช้กรอกเฉพาะข้อเท็จจริงที่จำเป็น
ระบบป้องกันรายการซ้ำที่ไม่ควรซ้ำ
ระบบคำนวณยอดสรุป/ยอดอนุพันธ์เอง

The form must not behave like an unrestricted generic repeater.

# Part A — Add Input Cardinality to the Form Matrices

Update:
- docs/ui/PND90_FORM_COVERAGE_MATRIX_2568.md
- docs/ui/PND91_FORM_COVERAGE_MATRIX_2568.md

Add these columns:
- INPUT_CARDINALITY
- UNIQUE_KEY
- DUPLICATE_POLICY
- REQUIRED_CONDITION

Allowed INPUT_CARDINALITY:
- SINGLE
- OPTIONAL_SINGLE
- REPEATABLE
- CONDITIONAL_REPEATABLE
- DERIVED_ONLY

Allowed DUPLICATE_POLICY:
- REJECT
- MERGE
- ALLOW
- NOT_APPLICABLE

Every USER_INPUT / CONDITIONAL_INPUT row must have explicit cardinality.

# Part B — Define Unique Keys

For each repeatable input type, define what makes two rows the "same tax item".

Possible key dimensions, only where supported by current source/API:
- income section
- income subtype
- activity code
- payer/employer
- allowance code
- dependent identity slot
- relationship role
- donation type
- prepayment type
- policy/category

Do not guess legal uniqueness.

The goal is to distinguish legitimate multiple entries from accidental duplicate entry.

# Part C — Duplicate Rules

## Allowances

For allowance items that are logically one annual item per taxpayer/category:
- do not allow duplicate rows

Use either:
- one fixed field
- single selectable row

If the user selects the same allowance twice:
- reject or focus the existing row

Do not silently double-count.

# Part D — Income Duplicates

Income rows may legitimately repeat in some cases.

Do NOT globally deduplicate all incomes.

Examples that may be legitimate:
- two employers under 40(1)
- multiple rental properties under 40(5)
- multiple business activities under 40(8)

But duplicate rows that match the exact same unique key and exact same values should trigger a warning/confirmation if that is safe.

Do not automatically merge income rows unless the semantics make merging lossless.

# Part E — Family / Dependent Duplicates

Prevent the same dependent/person from being entered twice where the available data can identify that duplication safely.

Use current persisted identity/facts only.

Do not require national ID if the simulator intentionally does not collect it.

If identity cannot be proven, use a best-effort warning rather than destructive merging.

# Part F — Donation / Prepayment Duplicates

Determine per source/API whether each item is:
- single annual aggregate
- multiple source rows

If the backend ultimately consumes only one aggregate per type, prefer one input field rather than an unrestricted repeater.

# Part G — Exact Required Input Reconciliation

For every source section, classify user-facing data as:
- REQUIRED_ALWAYS
- REQUIRED_WHEN_CONDITION
- OPTIONAL
- DERIVED
- UNSUPPORTED
- FILING_ONLY

Add this to the matrices.

For every required input, document:
- why required
- when required
- which backend path consumes it
- which calculation/rule depends on it

If a field is not used by any supported path:
- do not mark it required

# Part H — PND91 Required Input Audit

Verify the exact minimum required PND91 user inputs for the supported baseline.

At minimum audit:
- tax year
- form selection
- marital/family facts where applicable
- 40(1) gross income
- exempt income only if applicable
- withholding/prepayment values
- supported allowances actually claimed
- donations if claimed
- dependent facts only when claimed

Do not require derived values such as:
- expense deduction
- net income
- tax
- refund/payable

# Part I — PND90 Required Input Audit

Verify required/conditional inputs for each:
- 40(1)
- 40(2)
- 40(3)
- 40(4)
- 40(5)
- 40(6)
- 40(7)
- 40(8)

For each section, document exactly:
- required base amount
- required subtype/activity
- required expense-method choice
- required actual expense when actual method chosen
- required holding period where relevant
- required tax-treatment election where supported
- optional withholding/prepayment

Do not show irrelevant fields before their condition is selected.

# Part J — Replace Generic Repeaters Where Wrong

If current UI uses a generic "+ เพิ่มรายการ" repeater for items that should be single-instance:
replace it with the correct control.

Examples may include:
- one annual allowance amount
- one tax-treatment election
- one marital-status selection
- one derived total

Keep repeaters only where the underlying data genuinely supports multiple rows.

# Part K — Frontend Duplicate Prevention

Implement UI-level protection:
- disable already-selected unique allowance options
- prevent duplicate unique category selection
- show inline duplicate warning
- focus existing item when useful

Do not rely only on frontend.

# Part L — Backend Duplicate Validation

Add authoritative server-side validation for duplicate-sensitive request arrays.

Examples:
- distinct allowance code when only one annual claim is permitted
- distinct unique combination for fixed category rows
- duplicate dependent guard where safely detectable

Return stable 422 error codes/messages.

Do not let duplicate-sensitive payloads reach calculation silently.

# Part M — Persisted Member Data Integrity

Apply the same duplicate rules when:
- creating
- updating
- resuming
- duplicating a TaxReturn

A duplicated TaxReturn may copy legitimate rows, but editing the new draft must still prevent accidental duplicate additions.

# Part N — Review Screen Duplicate Detection

Before calculation, run a client-side integrity scan for user convenience.

Show:
- พบรายการซ้ำ

with links to the offending section.

This is UX only; backend remains authoritative.

# Part O — Stable Error Codes

Add stable error codes where needed, for example:
- DUPLICATE_ALLOWANCE_CODE
- DUPLICATE_PREPAYMENT_TYPE
- DUPLICATE_DEPENDENT
- DUPLICATE_UNIQUE_TAX_ITEM

Use only codes that reflect actual implemented validation.

# Part P — Tests

Add tests covering:
- duplicate unique allowance rejected
- duplicate unique prepayment rejected where applicable
- legitimate multiple employers allowed
- legitimate multiple 40(5)/40(8) rows allowed
- duplicate dependent warning/rejection where safely detectable
- Member resume preserves rows without duplication
- duplicate TaxReturn creation does not corrupt child rows
- frontend selected-option disabling
- 422 duplicate validation mapping to Thai UI

Also retain:
- no DERIVED editable inputs
- no required user input missing
- Guest stateless
- published 2568.1 unchanged

# Part Q — Manual Verification

Manually verify:

## PND91
- enter 40(1)
- claim one allowance
- try to add the same unique allowance again
- confirm UI blocks/focuses existing item
- submit crafted duplicate payload and confirm backend 422
- verify legitimate second employer remains allowed

## PND90
- test 40(5) multiple legitimate properties
- test 40(8) multiple legitimate activities
- attempt duplicate unique allowance/prepayment item
- verify conditional required fields
- verify irrelevant fields stay hidden

## Member
- save
- resume
- duplicate TaxReturn
- edit duplicated draft
- confirm no accidental duplicate child rows are introduced

# Part R — Documentation

Create/update:
- docs/ui/INPUT_CARDINALITY_AND_DUPLICATE_RULES.md
- docs/ui/PND90_FORM_COVERAGE_MATRIX_2568.md
- docs/ui/PND91_FORM_COVERAGE_MATRIX_2568.md
- docs/api/DUPLICATE_VALIDATION.md

Document clearly:
- which inputs are single-instance
- which are repeatable
- which repeatable keys are allowed
- which duplicates are rejected
- which duplicates only warn

# Completion Criteria

M9.2.2 is complete only when:
- every USER_INPUT / CONDITIONAL_INPUT has cardinality
- every required input has an explicit condition
- unique tax items cannot be duplicated silently
- legitimate repeatable income rows still work
- backend rejects duplicate-sensitive payloads
- UI prevents obvious duplicate selection
- Member save/resume preserves integrity
- duplicate TaxReturn workflow preserves integrity
- derived fields remain non-editable
- unsupported items remain guarded
- full regression passes
- 2568.1 remains unchanged

# Required Final Report

## Summary

## Matrix Changes

## PND91 Input Cardinality Summary
Report counts:
- SINGLE
- OPTIONAL_SINGLE
- REPEATABLE
- CONDITIONAL_REPEATABLE
- DERIVED_ONLY

## PND90 Input Cardinality Summary
Same counts.

## Duplicate-Sensitive Items
List every item where duplicates are rejected or merged.

## Legitimate Repeatable Items
List every item intentionally allowed multiple times.

## Required Input Reconciliation
List fields changed from:
- optional -> required
- required -> conditional
- required -> optional
- generic -> derived

## UI Changes

## Backend Validation Changes

## Stable Error Codes Added

## Member Persistence Integrity

## Files Created

## Files Modified

## Commands Executed

## Tests

## Manual Verification

## Tax Baseline Integrity

Confirm:
- published 2568.1 unchanged
- no formula change
- no historical snapshot rewrite

## Closure Decision

Exactly one:
INPUT_INTEGRITY_APPROVED
or:
INPUT_INTEGRITY_NOT_READY

Use NOT_READY only if duplicate-sensitive supported items can still be double-counted silently or required supported inputs remain ambiguous.

## Next Step

If approved:
Run the final M10 regression/security re-check only.
Do not add new features.

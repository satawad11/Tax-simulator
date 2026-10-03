# MILESTONE_09_2_1_PROMPT.md

## Codex Task: M9.2.1 — Form Field Role Audit + Final Fidelity Verification

This is a SHORT verification task after M9.2.

Do NOT add new features.
Do NOT change tax formulas.
Do NOT reopen M7.x.
Do NOT redesign the UI.
Do NOT begin production deployment.

The purpose is to verify that every material PND90 / PND91 field is not only covered, but is also assigned the CORRECT INPUT ROLE.

# Sources

Read and use only repository-approved sources:

- docs/tax-source/
- docs/ui/PND90_FORM_COVERAGE_MATRIX_2568.md
- docs/ui/PND91_FORM_COVERAGE_MATRIX_2568.md
- docs/ui/M9_2_FORM_RECONCILIATION.md
- docs/ui/FORM_FIDELITY_GUIDELINES.md

Also inspect the current simulator UI and API/DTO mappings.

# Core Requirement

Add a second classification dimension to the PND90 / PND91 coverage matrices.

Every material source field/line must have exactly one FIELD_ROLE:

- USER_INPUT
- CONDITIONAL_INPUT
- DERIVED
- INFORMATIONAL
- FILING_ONLY
- UNSUPPORTED

These roles answer a different question from the existing coverage status.

Keep the existing coverage status:
- IMPLEMENTED
- MISSING_UI
- MISSING_API
- UNSUPPORTED
- NEEDS_GUIDANCE
- NOT_APPLICABLE

Each row must therefore have BOTH:
- COVERAGE_STATUS
- FIELD_ROLE

# FIELD_ROLE Definitions

## USER_INPUT

The taxpayer/user knows the value and must provide it for a supported calculation.

Examples may include:
- gross income
- withholding
- donation amount
- supported allowance amount
- payer/employer description where required

Only classify based on source + supported baseline.

## CONDITIONAL_INPUT

The user must provide the field only when a specific condition/path applies.

Examples may include:
- specific fund contribution
- severance-related amount
- income subtype/activity
- holding period
- actual expense
- expense method election
- spouse/dependent facts

The UI should use progressive disclosure.

A CONDITIONAL_INPUT must NOT be shown as a required always-visible field.

## DERIVED

The value must be calculated by the backend from prior inputs.

Examples may include:
- expense deduction
- remaining income
- net income
- allowance eligible amount
- donation deduction
- progressive tax
- minimum tax
- tax payable
- refund
- combined-cap result

A DERIVED field must NEVER be an editable numeric input.

It may appear in:
- review
- calculation preview
- result
- trace

## INFORMATIONAL

The field is useful for explanation/context but is not required to calculate the supported simulator path.

Examples may include:
- plain-language explanation
- source reference
- help text
- non-calculation descriptive information

## FILING_ONLY

The printed form contains the field, but it belongs to actual filing/administrative workflow rather than simulation.

Examples may include:
- signature
- attachment count
- filing declaration
- payment channel
- refund request instruction
- submission certification
- address/contact blocks when not needed for calculation

These should not become calculation inputs merely because they appear on the PDF.

## UNSUPPORTED

The source contains a meaningful calculation-related item but the current approved baseline does not support it.

The UI must:
- show explanation where useful
- disable numeric entry
- not silently ignore the item

# Required Matrix Update

Update:
- docs/ui/PND90_FORM_COVERAGE_MATRIX_2568.md
- docs/ui/PND91_FORM_COVERAGE_MATRIX_2568.md

Add columns:
- FIELD_ROLE
- USER_ACTION
- SYSTEM_ACTION

Suggested format:

| Source item | Coverage status | Field role | User action | System action |
|---|---|---|---|---|

Example only:

40(1) gross income
IMPLEMENTED
USER_INPUT
กรอกยอดเงินได้
ส่งให้ backend calculation

expense deduction
IMPLEMENTED
DERIVED
ไม่ต้องกรอก
backend คำนวณและแสดงผล

Do not copy example classifications blindly.
Use the source documents.

# Audit Rules

Perform a row-by-row audit and detect these errors:

## Error A — DERIVED exposed as editable input

Examples:
- ค่าใช้จ่าย
- เงินได้หลังหักค่าใช้จ่าย
- เงินได้สุทธิ
- ภาษีตามขั้น
- ภาษีขั้นต่ำ
- ภาษีชำระเพิ่ม
- เงินคืน

If any such source-derived field is editable, fix the UI.

## Error B — Required USER_INPUT missing

If a source-supported calculation path requires a user-known value but UI does not collect it:
- mark MISSING_UI or MISSING_API
- fix only if necessary for current supported baseline

## Error C — CONDITIONAL_INPUT always shown

Move it behind the correct condition/selection using progressive disclosure.

## Error D — Filing-only field incorrectly collected

Remove it from calculation input if it has no calculation purpose.

Do not delete informational references from docs.

## Error E — Unsupported item looks supported

If the current UI allows numeric input for an unsupported rule:
- disable/remove entry
- show unsupported explanation

# Special PND90 Audit

Review carefully:
- SECTION_40_1
- SECTION_40_2
- SECTION_40_3
- SECTION_40_4
- SECTION_40_5
- SECTION_40_6
- SECTION_40_7
- SECTION_40_8

For each section, verify:
- which fields are user-provided
- which are conditional
- which are derived
- which are unsupported

Pay particular attention to source rows related to:
- fund contributions
- severance-related items
- income subtype/activity
- expense elections
- holding period
- property/rental type
- actual expense
- separate-tax elections
- minimum-tax lines
- prepayments/credits

Do not guess any legal rule.

# Special PND91 Audit

Verify:
- 40(1) income
- withholding
- family facts
- allowances
- donations
- expense
- net income
- tax
- refund/payable

Only taxpayer-known facts should be editable.

All calculated lines must remain backend-derived.

# UI Behavior Requirement

The simulator should follow this principle:

ผู้ใช้กรอก "ข้อเท็จจริง"
ระบบคำนวณ "ผลลัพธ์"

The UI must not ask users to calculate a tax-form subtotal themselves where the backend can derive it.

# Review Screen Requirement

The review page may display DERIVED preview values only if they come from backend computation or trusted metadata.

Do NOT recompute tax logic in JavaScript.

# Backend/API Gate

Do NOT add backend request fields unless a row is USER_INPUT or CONDITIONAL_INPUT AND is necessary for a currently supported calculation path.

Do NOT add request fields for:
- DERIVED
- INFORMATIONAL
- FILING_ONLY
- UNSUPPORTED

# Tests

Add/update tests to verify at minimum:
- derived tax fields are not editable inputs
- unsupported items have no enabled numeric input
- conditional fields appear only when applicable
- required supported inputs remain available
- PND90 subtype/activity selection still works
- PND91 40(1) flow still works
- Member resume retains conditional inputs
- Guest remains stateless

Do not add frontend tax formulas.

# Manual Verification

Open the actual PND90 and PND91 forms side-by-side with the simulator.

For representative rows, verify:
- source row
- FIELD_ROLE
- UI control or no-control
- backend/system behavior

At minimum manually inspect:
- PND91 40(1)
- PND90 40(1)/(2)
- PND90 40(5)
- PND90 40(8)
- allowances
- donations
- withholding/prepayments
- tax-calculation summary
- minimum-tax summary

# No Tax Baseline Change

Confirm:
- no tax formula changed
- no published 2568.1 rule changed
- no allowance/expense numeric rule changed
- no historical snapshot rewritten
- TaxCalculationService remains authoritative

# Final Decision

Output exactly one:

FORM_FIELD_ROLE_AUDIT_APPROVED

or:

FORM_FIELD_ROLE_AUDIT_NOT_READY

Use NOT_READY only if a supported PND90/PND91 path still:
- asks user to enter a value that should be derived
- fails to collect a required user-known value
- presents an unsupported numeric rule as supported

# Required Final Report

## Summary

## Matrix Updates

## PND91 Field Role Counts
Report counts:
- USER_INPUT
- CONDITIONAL_INPUT
- DERIVED
- INFORMATIONAL
- FILING_ONLY
- UNSUPPORTED

## PND90 Field Role Counts
Same counts.

## Incorrect Editable Derived Fields Found
If none:
None

## Missing Required User Inputs Found
If none:
None

## Conditional Inputs Reworked

## Filing-Only Fields Removed From Calculation Entry

## Unsupported Inputs Guarded

## API / DTO Changes
If none:
None

## Files Modified

## Commands Executed

## Tests

## Manual Source Comparison

## Tax Baseline Integrity

Confirm all baseline protections.

## Closure Decision

Exactly one:
FORM_FIELD_ROLE_AUDIT_APPROVED
or
FORM_FIELD_ROLE_AUDIT_NOT_READY

## Next Step

If approved:

Re-run the M10 production-readiness regression/security checklist only.
Do not add new features.

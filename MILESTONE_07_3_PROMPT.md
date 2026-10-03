# MILESTONE_07_3_PROMPT.md

## Codex Task: Milestone 07.3 — Filing Instructions Reconciliation + Family Allowance Model + Minimum-Tax Clarification

Before changing any file, MUST read:

- PROJECT_REQUIREMENTS.md
- CODING_RULES.md
- MILESTONE_01_PROMPT.md
- MILESTONE_02_PROMPT.md
- MILESTONE_03_PROMPT.md
- MILESTONE_04_PROMPT.md
- MILESTONE_05_PROMPT.md
- MILESTONE_06_PROMPT.md
- MILESTONE_07_PROMPT.md
- MILESTONE_07_1_PROMPT.md
- MILESTONE_07_2_PROMPT.md

Also inspect:

- docs/tax-source/
- docs/tax/PND90_RULE_MATRIX.md
- docs/tax/PND90_SOURCE_RECONCILIATION.md
- docs/tax/ALLOWANCE_RULE_MATRIX_2568.md
- docs/tax/PND90_REMAINING_RULES_2568.md
- current tax services, seeders, DTOs, requests, models, and tests

Do NOT begin Milestone 08.
Do NOT use web research.
Do NOT use general tax knowledge to fill gaps.
Use only repository-approved source documents and approved project requirements.

---

# Objective

Use the newly added official filing-instruction documents under `docs/tax-source/`, especially the repository copies of:

- วิธีการกรอกแบบ ภ.ง.ด.90
- วิธีการกรอกแบบ ภ.ง.ด.91

to reconcile and, where fully supported, implement:

1. PND90 minimum-tax base and comparison logic
2. PERSONAL allowance logic
3. SPOUSE allowance logic
4. CHILD allowance logic
5. structured family/dependent inputs required by the engine
6. remaining allowance ceilings and conditions explicitly documented
7. server-derived family allowances for both Guest and Member calculation flows

Actual filenames may differ. Use files that actually exist.

If the new filing instructions still do not support a rule clearly, mark it PARTIAL or UNVERIFIED and do not guess.

---

# Source Inspection

Before implementation:

1. list files under `docs/tax-source/`;
2. identify the exact PND90 and PND91 filing-instruction documents;
3. record source filename, page, item/section, and concise source-derived summary for every implemented rule;
4. never fabricate page numbers or source references.

---

# 1. Minimum-Tax Base Clarification

This is the highest-priority blocker from M7.2.

Resolve the exact meaning of the PND90 minimum/special-tax computation around the previously identified wording in line/item 11 and the referenced items.

Determine from source:

- exact income lines/items included in the minimum-tax base
- lines/items excluded
- whether exempt income affects the base
- whether expenses affect the base
- whether allowances affect the base
- whether donations affect the base
- whether separate-tax income is excluded
- threshold for applicability
- applicable rate
- comparison with normal progressive tax
- which tax result is carried forward

Do not implement until this is sufficiently unambiguous.

If still unclear:
- status = PARTIAL
- return a blocking domain error/warning for affected PND90 cases
- do not claim a complete PND90 calculation

If VERIFIED, use a dedicated service such as `MinimumTaxCalculator`.

Do not embed this formula in a Controller.

Suggested orchestration:

normal_progressive_tax = ProgressiveTaxCalculator(...)
minimum_tax = MinimumTaxCalculator(...)
selected_tax = source-defined comparison result

Return transparent component values.

Add trace codes only when applicable, e.g.:

- MINIMUM_TAX_BASE
- MINIMUM_TAX_RATE
- MINIMUM_TAX_AMOUNT
- BASE_TAX_SELECTION

Tests if VERIFIED:
- below threshold
- exact threshold
- above threshold
- included-only base
- excluded categories
- mixed categories
- normal tax > minimum tax
- minimum tax > normal tax
- equal
- zero applicable base
- Guest/Member parity

---

# 2. Family Allowance Model

Family allowances should be derived from structured taxpayer facts wherever possible.

Do not trust client-calculated eligible amounts for:

- PERSONAL
- SPOUSE
- CHILD

Preferred calculation input shape:

```json
{
  "profile": {
    "birth_date": "1990-05-20",
    "marital_status": "married"
  },
  "spouse": {
    "birth_date": "1992-01-10",
    "has_income": false,
    "filing_status": "combined"
  },
  "dependents": [
    {
      "relation_type": "child",
      "birth_date": "2020-05-12"
    }
  ]
}
```

Guest and Member must use the same conceptual family input model.

Member calculation should map persisted profile/spouse/dependent rows into the same TaxCalculationData/DTO model used by Guest.

Do not create a second family-calculation implementation.

---

# 3. PERSONAL Allowance Reconciliation

Resolve the prior 60,000 vs 120,000 ambiguity using the filing instructions.

Determine exactly:

- what 60,000 represents
- what 120,000 represents
- whether 120,000 is a combined taxpayer/spouse amount
- which marital/filing conditions cause each outcome
- whether spouse income status matters
- whether separate vs combined filing matters
- whether the form is presenting a combined line rather than a single-person personal allowance

Do NOT seed 120,000 as a generic PERSONAL allowance unless source explicitly supports that interpretation.

If source behavior requires a custom family strategy, use a dedicated strategy/service.

Tests based only on source-supported cases:
- single taxpayer
- married taxpayer
- spouse with income
- spouse without income
- separate filing if source-supported
- combined filing if source-supported

---

# 4. SPOUSE Allowance Reconciliation

Determine from source:

- eligibility conditions
- spouse income condition
- filing-status condition
- amount
- relationship with combined taxpayer/spouse line
- any other printed source conditions

Do not accept eligible_amount from client.

Use source-supported fields such as:

- profile.marital_status
- spouse.has_income
- spouse.filing_status

Add only additional fields that are truly required by source.

If any required condition is missing from source, leave PARTIAL.

Tests where source-supported:
- not married -> no spouse allowance
- married + spouse income branch
- married + no-income branch
- filing-status branches
- Guest/Member parity

---

# 5. CHILD Allowance Reconciliation

Determine from source:

- amount
- ordering rules
- birth-date/year rules
- different amount by order if applicable
- maximum number if any
- eligibility/status conditions if printed
- disabled-child interaction if printed
- any allocation/shared-right rule if printed

Do not infer family-law conditions outside repository sources.

If order affects allowance, define deterministic server-side ordering.

Prefer deriving order from source-supported data rather than trusting client-supplied eligible amount.

If current fields cannot safely derive ordering:
1. document the gap;
2. add the minimum explicit field required by source;
3. validate it;
4. test it.

Tests where source-supported:
- no children
- one child
- multiple children
- order boundary
- birth/year boundary
- maximum-count boundary
- ineligible case
- Guest/Member parity

---

# 6. Other Family Allowances

Review PARENT and DISABLED_PERSON if the new instructions clarify them.

Implement only if:
- source is clear;
- required taxpayer facts are available or can be minimally added.

Otherwise mark PARTIAL and document missing facts.

Do not invent eligibility flags.

---

# 7. Allowance Ceiling Reconciliation

M7.2 reported multiple allowance ceilings still unresolved.

Use the new instruction booklets to update:

`docs/tax/ALLOWANCE_RULE_MATRIX_2568.md`

For each allowance code, document:

- status: VERIFIED / PARTIAL / UNVERIFIED / NOT_APPLICABLE
- source file
- page/item
- method
- fixed amount
- percentage
- maximum
- combined cap
- conditions
- required input facts
- implementation status

Only seed VERIFIED rules.

Do not create fake zero-value rules for unknown limits.

If an instruction only refers to another law/document but does not print the number, keep it PARTIAL unless another repository-approved source provides it.

---

# 8. Combined Allowance Caps

If the filing instructions define shared ceilings across multiple allowance types:

- model the shared cap explicitly;
- do not cap each item independently;
- add dedicated tests.

If existing schema is insufficient:
1. document the gap;
2. make the smallest safe schema change;
3. preserve backward compatibility;
4. add tests.

Do not guess cap group membership.

---

# 9. Allowance Engine Architecture

Reuse `AllowanceCalculator`.

Split responsibilities only if needed, e.g.:

- PersonalAllowanceStrategy
- SpouseAllowanceStrategy
- ChildAllowanceStrategy
- GenericFixedAllowanceStrategy
- PercentageCapAllowanceStrategy
- CombinedCapResolver

Avoid giant switch statements.

For server-derived family allowances, prefer synthesizing calculated allowance items from structured facts.

Example shape:

```json
{
  "code": "PERSONAL",
  "source": "derived",
  "input_amount": null,
  "eligible_amount": "60000.00"
}
```

Values are illustrative only; use source-supported values.

---

# 10. Existing Client Compatibility

If existing clients already submit PERSONAL/SPOUSE/CHILD in `allowances`, never trust the submitted eligible amount.

Choose one consistent behavior:

Preferred:
- reject client amount for server-derived family codes

or compatibility mode:
- ignore submitted family allowance amount;
- calculate server-side;
- return a warning saying the value is server-derived.

Document the choice and test it.

---

# 11. Request / DTO Changes

If Guest calculation now supports spouse/dependents, add focused validation.

Examples:

- spouse nullable object
- spouse.has_income boolean
- dependents array
- dependents.*.relation_type required
- dependents.*.birth_date date/null as required

Collect only data required by source-supported logic.

Do not over-collect personal data.

---

# 12. Member Persistence

Reuse existing M5 profile/spouse/dependent tables.

Do not create duplicate family tables.

If new source-required fields are missing:
- add minimal nullable migration;
- preserve existing data;
- do not rewrite completed calculation snapshots.

---

# 13. Minimum-Tax + Allowance Interaction

If the filing instructions clarify whether allowances affect the minimum-tax base, implement exactly that.

Add integration tests proving:
- normal-tax allowance effect
- minimum-tax base inclusion/exclusion of allowances
- final source-correct tax selection

This interaction is critical.

---

# 14. Planning / Recommendation Compatibility

M6 TaxPlanningService must continue to use the shared TaxCalculationService.

New verified family/allowance rules should automatically affect planning through the engine.

Do not add tax-saving formulas to TaxPlanningService.

Recommendation wording must remain cautious:
- "ควรตรวจสอบสิทธิ"
- "หากเข้าเงื่อนไข สามารถทดลองจำลองได้"

Do not claim eligibility unless every source-stated condition is actually evaluated.

---

# 15. Donation / Credit Scope

Do not expand donation/credit work unless the new filing instructions explicitly resolve an existing M7.2 blocker.

M7.3 priority is:
- minimum tax
- family allowances
- allowance ceilings

Avoid unrelated scope creep.

---

# 16. Legal Rounding

Keep `ROUNDING_RULE_PENDING` unless the instruction booklet explicitly defines:
- rounding unit
- rounding direction
- rounding stage

Do not infer rounding from examples alone.

---

# 17. Rule-Version Decision

Reassess rule-version impact carefully.

If this work only completes previously unsupported rules without changing outcomes for already-supported inputs, extending 2568.1 may be acceptable under the reconciliation policy.

If PERSONAL/SPOUSE/CHILD changes alter calculations that the system previously treated as supported:
- do not silently mutate history;
- consider a new internal rule version;
- do not auto-upgrade old returns.

Document:
- what changed
- affected saved returns
- draft behavior
- completed-return behavior
- new-return behavior

Historical TaxCalculation snapshots must never be rewritten.

---

# 18. Documentation

Update:

- docs/api/MILESTONE_07_API.md
- docs/tax/PND90_RULE_MATRIX.md
- docs/tax/PND90_SOURCE_RECONCILIATION.md
- docs/tax/ALLOWANCE_RULE_MATRIX_2568.md
- docs/tax/PND90_REMAINING_RULES_2568.md

Create:

- docs/tax/FAMILY_ALLOWANCE_MODEL_2568.md
- docs/tax/MINIMUM_TAX_RECONCILIATION_2568.md

`FAMILY_ALLOWANCE_MODEL_2568.md` must document for PERSONAL/SPOUSE/CHILD, and PARENT/DISABLED_PERSON only if reconciled:
- source
- page/item
- required facts
- eligibility logic
- amount/cap
- server-derived fields
- API contract
- known gaps
- implementation status

`MINIMUM_TAX_RECONCILIATION_2568.md` must document:
- exact source wording/summary
- source page/item
- base definition
- included/excluded lines
- threshold
- rate
- progressive-tax comparison
- interaction with allowances/donations/credits
- implementation status
- tests
- remaining ambiguity

---

# 19. Required Unit Tests

Add focused tests for newly verified:
- PersonalAllowanceStrategy
- SpouseAllowanceStrategy
- ChildAllowanceStrategy
- MinimumTaxCalculator
- CombinedCapResolver if applicable

Feature tests alone are not sufficient.

---

# 20. Required Integration Tests

At minimum, when supported by source:

- single PND91 taxpayer
- married PND91 taxpayer
- PND91 with child
- PND90 with family allowances
- PND90 minimum-tax path
- minimum-tax + family-allowance interaction
- Guest/Member parity
- saved-return calculation
- completed-return immutability
- planning regression
- recommendation regression

Only test legal branches actually supported by repository sources.

---

# 21. Regression

All M1–M7.2 tests must remain green.

Especially preserve:
- PND91 core results
- PND91 planning
- PND90 verified expense rules
- verified donation rules
- member history
- scenarios
- Guest stateless calculation
- ownership/cross-user protection

Do not weaken old tests.

---

# 22. Manual Verification

Use synthetic data only.

Verify:
1. single taxpayer PERSONAL path
2. married taxpayer
3. SPOUSE path
4. one-child path
5. multiple-child/order path if verified
6. PND90 minimum-tax path
7. normal vs minimum-tax comparison
8. Guest calculation
9. Member saved calculation
10. Guest/Member parity
11. planning reuse
12. historical completed snapshot unchanged
13. cross-user protection

---

# 23. Commands

Inspect first:

```bash
git status
docker compose ps
find docs/tax-source -maxdepth 2 -type f
```

Use non-destructive migrations/seeding.

Run:
```bash
docker compose exec app php artisan test
```

Run current MySQL-backed suite.
Run Pint/lint.
Run frontend build regression if part of current workflow.

Do not run `migrate:fresh` on active development data unless absolutely necessary and explicitly reported.

---

# 24. Completion Criteria

M7.3 is complete only when:

- filing-instruction documents were actually inspected
- source file/page references are documented
- minimum-tax base is VERIFIED+IMPLEMENTED or explicitly blocked
- PERSONAL discriminator is resolved or explicitly blocked
- SPOUSE is VERIFIED+IMPLEMENTED or explicitly blocked
- CHILD is VERIFIED+IMPLEMENTED or explicitly blocked
- family allowances are server-derived where implemented
- client eligible amounts are not trusted
- allowance ceilings are reconciled where source-supported
- no unknown numeric rule was guessed
- Guest and Member share one family calculation model
- minimum-tax / allowance interaction is tested if implemented
- rule-version decision is documented
- historical snapshots remain unchanged
- PND91 remains regression-safe
- PND90 verified rules remain regression-safe
- M6 planning/recommendation remains regression-safe
- all prior tests remain green
- no UI redesign occurred
- Milestone 08 was not started

---

# Required Final Report

Report exactly:

## Summary

## Filing-Instruction Sources Found
List exact repository paths.

## Filing-Instruction Sources Missing
List expected but unavailable files.

## Minimum-Tax Reconciliation
Report:
- status
- source file/page/item
- base definition
- included items
- excluded items
- threshold
- rate
- comparison rule
- interaction with allowances
- implementation status

## PERSONAL Allowance
Report:
- status
- source
- interpretation of any 60,000 / 120,000 distinction
- required facts
- calculation rule
- implementation status

Do not state values unsupported by source.

## SPOUSE Allowance
Report:
- status
- source
- required facts
- eligibility conditions
- amount
- implementation status

## CHILD Allowance
Report:
- status
- source
- ordering rule
- birth/year conditions
- amount/cap
- required facts
- implementation status

## Other Family Allowances
Report PARENT / DISABLED_PERSON only if reconciled.

## Allowance Ceiling Reconciliation
Provide counts:
- VERIFIED
- PARTIAL
- UNVERIFIED
- IMPLEMENTED

List newly implemented allowance codes.

## Family API / DTO Changes

## Schema Changes

## Rule Version Decision
Explain whether 2568.1 was extended or a new version was created, and why.

## Services Created / Modified

## API Changes

## Documentation Created / Updated

## Commands Executed
Only actual commands.

## Test Coverage

## Test Results
Report:
- passed
- failed
- skipped
- assertions

## Manual Verification

## Regression Status
Confirm:
- PND91 unchanged except source-supported additive family behavior
- PND90 verified expense behavior unchanged
- M6 planning/recommendation remains valid
- member history remains intact
- Guest stateless behavior remains intact

## Remaining Blockers
List exact remaining source/data blockers before PND90 is production-complete.

## Architecture Compliance
Confirm:
- only repository-approved sources were used
- no web/general-knowledge tax rules were used
- no unverified numeric rule was guessed
- family allowances are server-derived where implemented
- TaxCalculationService remains the shared engine
- planning continues to reuse the shared engine
- no AI determines tax eligibility or amounts
- historical calculation snapshots were not rewritten
- no approved UI redesign occurred
- Milestone 08 was not started

## Next Step

If all material PND90 blockers are closed:
`Recommend Milestone 08 — Content / News / Admin CMS + Tax Rule Administration`

If material blockers remain:
`Do NOT begin Milestone 08 yet. List the exact remaining source documents or clarifications required.`

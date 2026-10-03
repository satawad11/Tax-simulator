# MILESTONE_07_4_PROMPT.md

## Codex Task: Milestone 07.4 — Allowance Cap Engine + Percentage-Base Model + PND90 Expense Subcategory Completion

Before changing any file, you MUST read:

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
- MILESTONE_07_3_PROMPT.md

Also inspect:

- docs/tax-source/
- docs/tax/PND90_RULE_MATRIX.md
- docs/tax/PND90_SOURCE_RECONCILIATION.md
- docs/tax/ALLOWANCE_RULE_MATRIX_2568.md
- docs/tax/FAMILY_ALLOWANCE_MODEL_2568.md
- docs/tax/MINIMUM_TAX_RECONCILIATION_2568.md
- docs/tax/PND90_REMAINING_RULES_2568.md
- current allowance_rules schema
- current expense_rules schema
- AllowanceCalculator
- TaxCalculationService
- TaxPlanningService
- recommendation services
- all M7.3 tests

Do NOT begin Milestone 08.
Do NOT use web research.
Do NOT use general tax knowledge to fill gaps.
Use only repository-approved source documents and approved project rules.

---

# Objective

Complete the remaining PND90/allowance architecture gaps identified after M7.3:

1. percentage-based allowance rules need an explicit calculation base;
2. combined allowance caps need an explicit shared-cap model;
3. tiered allowance ceilings need structured representation;
4. three PND90 expense subcategories still need reconciliation from page 17 or other approved source pages;
5. SOCIAL_SECURITY and any other source-referred-but-not-numbered limits must remain unimplemented unless repository sources state the numeric rule;
6. legal final rounding remains pending unless repository source resolves it.

The goal is to make the allowance and expense rule engine expressive enough to represent verified 2568 rules without guessing and without embedding hidden business logic in Controllers.

---

# Source Policy

For every production rule, document:

- source file
- page
- item/section
- source-supported semantics

Do not infer:
- percentage base
- combined cap membership
- tier ordering
- numeric ceiling
- expense percentage
- rounding rule

If source does not support it, mark PARTIAL or UNVERIFIED and give it no production calculation effect.

---

# 1. Percentage-Based Allowance Base Model

Some allowance rules use percentages, but the current schema does not express "percentage of what?"

Introduce an explicit percentage-base vocabulary.

Suggested values only where needed by source-supported rules:

- GROSS_INCOME
- GROSS_AFTER_EXEMPTION
- INCOME_AFTER_EXPENSE
- NET_INCOME_BEFORE_ALLOWANCE
- NET_INCOME_BEFORE_DONATION
- ALLOWANCE_GROUP_SUBTOTAL
- CUSTOM

Do not seed unused speculative values.

If required, add an explicit field to allowance_rules, for example:

percentage_base VARCHAR(50) NULL

Do not bury this core tax semantic in opaque conditions JSON if a first-class column makes the rule more auditable.

Extend AllowanceCalculator so a VERIFIED rule may include:

- method
- percentage
- percentage_base
- maximum_amount
- minimum_amount
- conditions

Calculation sequence:

1. resolve rule;
2. resolve percentage base;
3. apply percentage;
4. apply individual cap;
5. apply combined cap later if applicable;
6. return transparent breakdown.

Example output shape:

```json
{
  "code": "EXAMPLE_ALLOWANCE",
  "method": "percentage_limit",
  "percentage": "30.000",
  "percentage_base": "INCOME_AFTER_EXPENSE",
  "base_amount": "500000.00",
  "calculated_before_cap": "150000.00",
  "maximum_amount": "100000.00",
  "eligible_amount": "100000.00"
}
```

Values are illustrative only.

---

# 2. Combined Cap Model

M7.3 identified cross-code combined caps.

The engine must not cap each code independently when the source defines one shared basket.

Introduce an auditable combined-cap model, for example:

- allowance_cap_groups
- allowance_cap_group_members

Possible allowance_cap_groups fields:

- id
- rule_version_id
- code
- name
- maximum_amount
- percentage
- percentage_base
- conditions JSON
- active
- timestamps

Possible member fields:

- id
- allowance_cap_group_id
- allowance_type_id
- sort_order/priority if source needs it
- timestamps

Use unique constraints to prevent duplicate memberships.

Do not support multiple group memberships unless source requires it.

Create a dedicated service:

CombinedAllowanceCapResolver

Responsibilities:
- receive individually calculated allowance items;
- load applicable groups;
- sum member eligible amounts;
- apply shared ceiling;
- return group breakdown.

Do not let iteration order accidentally determine who loses excess.

If the source defines only a group ceiling but no priority, do not invent legal priority. Prefer group-level capped totals and clearly document any presentation-only allocation behavior.

Example output:

```json
{
  "combined_cap_groups": [
    {
      "code": "EXAMPLE_GROUP",
      "member_codes": ["ALLOWANCE_A", "ALLOWANCE_B"],
      "pre_cap_total": "120000.00",
      "maximum_amount": "100000.00",
      "eligible_total": "100000.00"
    }
  ]
}
```

---

# 3. Tiered Ceiling Model

If source defines multiple tiers/buckets for one allowance, do not flatten them into one maximum.

Add structured tier representation only where source requires it.

Possible table:

allowance_rule_tiers

Possible fields:
- allowance_rule_id
- tier_code
- sort_order
- maximum_amount
- percentage
- percentage_base
- conditions JSON

Create TieredAllowanceStrategy only if actual verified rules need it.

It should:
- resolve tier eligibility;
- calculate each tier;
- apply each tier cap;
- sum eligible tiers;
- return transparent breakdown.

---

# 4. Allowance Rule Schema Reconciliation

Inspect current allowance_rules and decide the minimal schema required for:

- percentage_base
- combined-cap groups
- tiered rules
- condition vocabulary

Do not redesign unrelated tax schema.

If existing conditions JSON is used, define a small safe vocabulary only as needed by source, such as:
- age_min
- age_max
- marital_status
- spouse_has_income
- child_birth_year_min
- purchase_date_from
- purchase_date_to
- category
- taxpayer_type

Do not build an arbitrary expression evaluator.

---

# 5. Reconcile Partial Percentage-Based Allowances

Using only repository sources, revisit all allowance codes currently PARTIAL because their percentage base is unknown.

For each:
1. find exact source text;
2. determine percentage;
3. determine base;
4. determine individual cap;
5. determine combined-cap membership;
6. determine conditions;
7. assign VERIFIED / PARTIAL / UNVERIFIED;
8. seed only VERIFIED rules.

Do not promote a rule to VERIFIED just because percentage is known.

---

# 6. Combined Cap Rules

Reconcile each source-supported shared basket.

M7.3 mentioned examples such as life/health insurance shared caps and retirement/investment shared caps. Do not rely on those labels alone; use exact source pages/items.

For every implemented group, document:
- group code
- source file/page/item
- member allowance codes
- cap
- percentage base if applicable
- conditions
- calculation order

---

# 7. SOCIAL_SECURITY

M7.3 reported that the booklet does not print the numeric ceiling and refers elsewhere.

Therefore do not seed a numeric SOCIAL_SECURITY rule unless another repository-approved source explicitly gives the numeric limit.

Keep it PARTIAL/UNVERIFIED with warning/recommendation.

No web/general knowledge.

---

# 8. PND90 Expense Subcategory Completion

M7.3 identified three PND90 expense subcategories with blank percentages.

Inspect the filing instructions carefully, especially the page-17 table related to:

ตารางอัตราการหักค่าใช้จ่ายเป็นการเหมา

Use column-aware extraction and rendered-page cross-checking.

Do not rely on interleaved full-page extraction.

For each unresolved subcategory:
1. identify exact source label;
2. map to correct income type/subtype;
3. identify percentage;
4. identify cap;
5. identify whether actual expense is allowed;
6. identify conditions;
7. determine schema requirements;
8. implement only if VERIFIED.

---

# 9. Income Subtype Model

If the source defines multiple subcategories under one Section 40 income type with different expense rules, add an explicit subtype model if required.

Possible minimal design:

income_subtypes:
- id
- income_type_id
- code
- name
- description
- active
- timestamps

Expense rules may reference nullable income_subtype_id.

Do not use free-text description to select tax rules.

If subtype is required, request shape may include:

```json
{
  "income_type": "SECTION_40_8",
  "income_subtype": "SOURCE_DEFINED_CODE",
  "gross_amount": "100000.00"
}
```

Validate:
- subtype exists;
- subtype belongs to the income type;
- subtype is supported for year/form/rule version.

Unknown subtype -> 422.

Reuse ExpenseCalculator and introduce ExpenseRuleResolver if useful.

---

# 10. Rule Version Decision

M7.3 extended 2568.1 for rules that were previously absent and did not change existing supported semantics.

Use the same policy:

- if M7.4 only adds previously unsupported combinations, 2568.1 may be extended if consistent with project reconciliation policy;
- if an existing supported result would change, create a new internal rule version.

Do not silently change historical meaning.
Do not auto-upgrade saved returns.
Completed calculation snapshots remain immutable.

---

# 11. Planning Compatibility

Planning must continue to reuse TaxCalculationService.

Newly verified allowance/cap/tier/subtype rules should affect planning automatically through the engine.

Do not add separate tax-saving formulas.

Add at least one planning regression using a newly verified allowance rule.

---

# 12. Recommendation Compatibility

Recommendations may become more actionable only when the underlying rule is VERIFIED.

Still use cautious wording.

Do not claim eligibility if conditions remain taxpayer-declared or unverified.

---

# 13. API Transparency

Add response fields only as needed, preserving backward compatibility.

Useful additive fields may include:
- method
- percentage
- percentage_base
- base_amount
- individual_cap
- combined_cap_groups
- tiers
- rule_status

Do not remove or rename existing fields.

---

# 14. Calculation Trace

Add trace codes only for applied logic, for example:
- ALLOWANCE_PERCENTAGE_BASE
- ALLOWANCE_INDIVIDUAL_CAP
- ALLOWANCE_COMBINED_CAP
- ALLOWANCE_TIER
- EXPENSE_SUBTYPE_RULE

Avoid redundant trace noise.

---

# 15. Documentation

Update:
- docs/tax/ALLOWANCE_RULE_MATRIX_2568.md
- docs/tax/PND90_RULE_MATRIX.md
- docs/tax/PND90_SOURCE_RECONCILIATION.md
- docs/tax/PND90_REMAINING_RULES_2568.md
- docs/api/MILESTONE_07_API.md

Create:
- docs/tax/ALLOWANCE_CAP_MODEL_2568.md
- docs/tax/PND90_EXPENSE_SUBTYPE_MATRIX_2568.md

ALLOWANCE_CAP_MODEL_2568.md must document:
- percentage-base vocabulary;
- combined cap groups;
- member codes;
- source references;
- tiered rules;
- calculation order;
- implementation status;
- known gaps.

PND90_EXPENSE_SUBTYPE_MATRIX_2568.md must document each source-defined subtype:
- income type;
- subtype code;
- source label;
- source file/page;
- percentage;
- cap;
- actual-expense support;
- conditions;
- status;
- implementation status.

---

# 16. Required Unit Tests

Add focused tests for:
- PercentageBaseResolver
- CombinedAllowanceCapResolver
- TieredAllowanceStrategy if used
- ExpenseRuleResolver with subtype if used

and any new focused strategy.

---

# 17. Required Integration Tests

At minimum:
- percentage-based allowance
- individual cap
- combined cap
- tiered allowance if implemented
- planning with newly verified allowance
- newly verified PND90 expense subtype
- mixed PND90 subtype calculation
- Guest/Member parity
- PND91 regression
- minimum-tax regression
- family-allowance regression

---

# 18. Legal Rounding

Keep ROUNDING_RULE_PENDING unless a repository-approved source explicitly defines:
- rounding unit
- rounding direction
- rounding stage

Do not infer rounding from examples.

---

# 19. Regression

All M1–M7.3 tests must remain green.

Especially preserve:
- PND91 no-family baseline
- PND91 family rules
- PND90 minimum tax
- verified PND90 expense rules
- M6 planning
- member history
- scenario persistence
- Guest stateless behavior
- historical snapshots

Do not weaken old tests.

---

# 20. Manual Verification

Use synthetic data only.

Verify:
1. one percentage-based allowance;
2. one individual-cap boundary;
3. one combined-cap scenario;
4. one tiered allowance if implemented;
5. planning using a newly verified allowance;
6. one newly verified PND90 expense subtype;
7. mixed PND90 income/subtype calculation;
8. Guest/Member parity;
9. PND91 regression;
10. minimum-tax regression;
11. family allowance regression;
12. completed history unchanged.

---

# 21. Commands

Inspect first:

```bash
git status
docker compose ps
find docs/tax-source -maxdepth 2 -type f
```

Use source-aware PDF reading.
Use non-destructive migrations/seeding.

Run:

```bash
docker compose exec app php artisan test
```

Run MySQL-backed suite.
Run Pint/lint.
Run frontend build regression if required.

Do not run migrate:fresh against active development data unless absolutely necessary and explicitly reported.

---

# 22. Completion Criteria

M7.4 is complete only when:
- percentage-base model exists where required
- every implemented percentage allowance has an explicit source-supported base
- combined-cap model exists where required
- combined-cap membership is source-supported
- tiered rules are represented without flattening semantics
- newly verified allowance rules are seeded idempotently
- SOCIAL_SECURITY remains unseeded unless repository source gives numeric limit
- page-17 expense subcategories were inspected carefully
- every newly implemented expense subtype is source-supported
- no free-text description controls tax logic
- planning still reuses TaxCalculationService
- historical snapshots remain unchanged
- PND91 regression remains green
- M7.3 family/minimum-tax behavior remains green
- no unknown numeric rule was guessed
- legal rounding remains pending unless explicitly sourced
- Milestone 08 was not started

---

# Required Final Report

Report exactly:

## Summary

## Source Documents Used
List exact repository paths and pages/items used.

## Percentage-Based Allowance Model
Report:
- schema/design
- supported percentage bases
- implemented allowance codes
- source references

## Combined Cap Groups
For each:
- group code
- source
- member codes
- cap
- percentage base if any
- implementation status

## Tiered Allowance Rules
List each implemented rule and tiers.

## SOCIAL_SECURITY
Report status and why numeric rule is or is not implemented.

## PND90 Expense Subcategories
For each unresolved/implemented subtype:
- income type
- subtype
- source
- percentage
- cap
- actual-expense support
- status
- implementation status

## Schema Changes

## Rule Version Decision

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
- PND91 unchanged
- minimum tax unchanged
- family allowance behavior unchanged
- planning/recommendation remains valid
- member history intact
- Guest stateless behavior intact

## Remaining Blockers
List exact blockers before PND90 can be considered production-complete.

## Architecture Compliance
Confirm:
- only repository-approved sources were used
- no web/general-knowledge tax rules were used
- no unverified numeric rule was guessed
- percentage bases are explicit
- combined caps are explicit
- no free-text tax-rule selection exists
- TaxCalculationService remains the shared engine
- planning reuses the shared engine
- historical snapshots were not rewritten
- no approved UI redesign occurred
- Milestone 08 was not started

## Next Step

If all material PND90 blockers are closed:
Recommend Milestone 08 — Content / News / Admin CMS + Tax Rule Administration

If material blockers remain:
Do NOT begin Milestone 08 yet. List the exact remaining source or architecture decisions required.

# Tax rule version lifecycle — Milestone 08

```
            create                clone
               │                    │
               ▼                    ▼
          ┌─────────┐   edit   ┌─────────┐
          │  draft  │◄────────►│  draft  │
          └────┬────┘          └────┬────┘
               │  validate           │
               ▼                     │
        ┌──────────────┐             │
        │  valid = ?   │─── false ───┘   publication refused
        └──────┬───────┘
               │ true
               ▼
         ┌───────────┐   supersedes   ┌──────────┐
         │ published │───────────────►│ archived │
         └───────────┘                └──────────┘
              ▲                             ▲
              │                             │
        immutable from here on        still readable forever
```

## The four states

| State | New calculations | Editable | Readable |
| --- | --- | --- | --- |
| `draft` | no | **yes** | yes (admin only) |
| `published` | yes — exactly one per tax year | **no** | yes |
| `archived` | no | **no** | yes, forever |

## Create

`POST /api/v1/admin/tax-rule-versions` with `tax_year`, `version`, optional `description`.

Always a draft. The request may not carry `status`; `StrictApiRequest` refuses it, and
`ProtectsPublishedRules` refuses a version created directly as published in any case. The
identifier must be unique within the tax year.

## Clone

`POST /api/v1/admin/tax-rule-versions/{id}/clone` with `version`.

This is how a published version is changed: never in place, always by copying it into a draft.
`TaxRuleVersionCloneService` copies, in one transaction:

`tax_brackets`, `income_rules`, `expense_rules` (with their `expense_rule_tiers`),
`allowance_rules`, `allowance_cap_groups` (with their members), `donation_rules`,
`recommendation_rules`, and the `tax_rule_sources` citations.

It copies **no** member data: no tax return, no calculation, no scenario.

## Edit

`GET|POST /api/v1/admin/tax-rule-versions/{id}/{resource}` and
`PATCH|DELETE …/{resource}/{ruleId}`, where `{resource}` is one of six entries in
`DraftRuleRegistry` — `tax-brackets`, `expense-rules`, `allowance-rules`,
`allowance-cap-groups`, `donation-rules`, `recommendation-rules`. Anything else is a 404;
`{resource}` is never used as a table name.

M8 invents no new rule class. Every entity is one the engine already reads.

## Validate

`POST /api/v1/admin/tax-rule-versions/{id}/validate`

`TaxRuleVersionValidationService` checks structure, not sampled behaviour — running the engine
over invented cases proves nothing about a rule no case happened to touch.

| Area | Checks |
| --- | --- |
| Version | tax year exists; identifier unique within the year; status is draft |
| Brackets | present; `sort_order` runs 1..n; first starts at zero; no gap or overlap; rate within 0–100; exactly one open-ended band, and it is last |
| Mappings | every active form of the year has at least one mapped income type |
| Expense rules | no duplicate (type, subtype, activity, holding band); method is supported; a source is cited; a tiered rule has ordered bands ending in one open band |
| Allowance rules | at most one rule per allowance type; a source is cited; a `percentage_limit` names a supported percentage base, and nothing else names one |
| Cap groups | non-empty; a ceiling or a percentage is stated; every member has a rule in this version |
| Donation rules | stage is `special` or `general`; a percentage cap is stated; a source is cited |
| Recommendation rules | every condition key is in `TaxRecommendationService::SUPPORTED_CONDITIONS` — an unknown key would silently never fire |

```json
{ "success": true, "message": null,
  "data": { "valid": false,
    "errors": [{ "code": "TAX_BRACKET_GAP", "path": "tax_brackets.1", "message": "…" }],
    "warnings": [] } }
```

Warnings never block publication; they describe paths M7.5 classified as guarded.

## Publish

`POST /api/v1/admin/tax-rule-versions/{id}/publish`

1. admin authorization, then `TaxRuleVersionPolicy::publish()` — draft only;
2. one transaction, with the version row locked, so two concurrent publishes cannot both win;
3. validation runs again; any error aborts with 422 and an audited refusal;
4. the tax year's previous published version is **superseded** — moved to `archived`;
5. the draft becomes `published` and is stamped with `published_at`.

**Why step 4 exists.** `PublishedTaxRuleResolver` has refused a tax year with two published
versions since M3, and rightly: "which rules apply" must have one answer. Archiving is how the
old answer steps aside without being destroyed. Its rules stay readable and every saved return
that points at it keeps pointing at it and keeps resolving.

## Archive

`POST /api/v1/admin/tax-rule-versions/{id}/archive`

Takes a version out of selection for new calculations. Nothing is deleted, and a version
referenced by a return, a calculation or a scenario is reported as such
(`referenced_by_history`) and is never removable.

## Immutability, in two layers

**Layer 1 — the API.** `TaxRuleVersionPolicy::update()` answers 403 for any version that is not
a draft, so an administrator gets a clear refusal before anything is attempted.

**Layer 2 — the models.** `ProtectsPublishedRules` wraps every insert, update and delete of
`TaxRuleVersion`, `TaxBracket`, `IncomeRule`, `ExpenseRule`, `AllowanceRule`,
`AllowanceCapGroup`, `DonationRule` and `RecommendationRule`. It locks the version row and
throws `LogicException` when that version is published. This layer predates M8 and is the one
that matters: it refuses a write from a controller, a command, a future developer's script, or
anything else that reaches the model without passing layer 1.

`AllowanceCapGroup` gained the trait in M8. M7.4 created that table when the only writer was a
seeder running before publication, so the omission never bit; once an admin API can write rule
data, it would have been the one rule table a published version did not protect.

## What publishing never does

- It does not edit the version it supersedes.
- It does not move any saved `TaxReturn` onto the new version.
- It does not recalculate or rewrite a single stored `TaxCalculation` snapshot.

A member's completed simulation stays tied to the rules it was calculated under, for as long as
it exists. `AdminTaxRuleAdministrationTest::test_an_existing_return_keeps_its_rule_version_…`
publishes a version with a different top bracket rate and asserts the saved return's version,
its stored snapshot and its reported figures are all unchanged, while a new return resolves the
newly published version.

# Final gap data model decision

## Source decisions

| Gap | Decision | Approved source and location | Semantics obtained | Missing semantics |
|---|---|---|---|---|
| BE-01 pension insurance | BLOCKED_BY_SOURCE | `docs/tax-source/PND90-2568-filing-instructions.pdf`, p.10 item 7.6 | 15%/200,000 figures and references to insurance/retirement baskets | unambiguous nesting and allocation order |
| BE-02 social security | BLOCKED_BY_SOURCE | same, p.12 item 12 | amount actually paid | statutory ceiling/eligibility exists outside repository |
| BE-04 domestic tourism | BLOCKED_BY_SOURCE | same, p.16 item 22 | period, eligible services, location distinction, 1.5x secondary-city wording | ceiling for the second band |
| BE-08 foreign tax credit | BLOCKED_BY_SOURCE | same, p.1; `PND90-2568-form.pdf`, p.4 item 13 | broad Thai-tax ceiling and ordering before prepayments | per-income/country limit and unused treatment; instructions redirect externally |
| BE-10 final rounding | BLOCKED_BY_SOURCE | both forms and filing instructions | no approved rounding statement found | stage, unit, and direction |
| BE-12 disabled relationship | SOURCE_READY | `PND90-2568-filing-instructions.pdf`, pp.8-9 item 5 | 60,000 per eligible person; listed family relationships; one-person limit for `บุคคลอื่น` | none for the narrow discriminator/limit implemented here |

No numeric value is taken from model memory or the web.

## Additive schema

`tax_return_dependents.disabled_person_relationship` is nullable `VARCHAR(30)` and indexed with
`tax_return_id` and `relation_type`. Allowed API values are `family_member` and `other_person`.
Null preserves historical rows.

The draft rule is stored in `allowance_rules` under code
`PND90_2568_DISABLED_PERSON_OTHER_LIMIT`, attached to allowance type `DISABLED_PERSON`, with
`method=custom`, conditions naming the discriminator and `other_person_limit=1`, and an exact
repository source reference. The family allowance strategy reads this rule only from the resolved
version. Since 2568.1 has no such row, its calculation remains byte-for-byte compatible.

## Rule-version decision

Draft 2568.2 is cloned through the existing version lifecycle because BE-12 changes a supported
calculation for newly explicit input. It is not published by this task. The clone contains the
baseline data needed to run the complete engine plus the single new source-backed relation-limit
rule. Published 2568.1 rows are never updated.

## Historical data

- No existing dependent is assigned a relationship value.
- No TaxReturn changes rule version.
- No completed return is reopened or upgraded.
- No TaxCalculation input, trace, result snapshot, or bracket snapshot is rewritten.

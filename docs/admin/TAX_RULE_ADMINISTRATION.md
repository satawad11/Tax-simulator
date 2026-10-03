# Tax rule administration — Milestone 08

How an administrator inspects rules, records the evidence behind them, and changes them without
breaking anything Milestone 7.x froze.

For the state machine itself see
[TAX_RULE_VERSION_LIFECYCLE.md](TAX_RULE_VERSION_LIFECYCLE.md).

## Access

`/api/v1/admin/*` is behind `auth:sanctum` plus the `admin` middleware. There is no second
authentication stack: M5's Sanctum tokens still establish who the caller is, and the middleware
answers the separate question of whether they may administer the platform.

| Caller | Response |
| --- | --- |
| unauthenticated | **401** |
| authenticated member | **403** |
| authenticated admin | allowed |

`users.role` exists since M2 and defaults to `member`. It is **not** mass-assignable: no
registration or profile request can grant it, and a test asserts that. In development a role is
granted deliberately, e.g. `User::factory()->admin()->create()` or a one-off tinker command.

Each action additionally authorizes through `ContentPolicy`, `TaxRuleVersionPolicy` or
`TaxSourcePolicy`, so a route added later without the middleware is still not reachable by a
member. The policies are registered by name in `AppServiceProvider` — `ContentPost`'s policy is
`ContentPolicy`, which convention would not discover, and an authorization rule that silently
fails to load is the kind of bug that only shows up in production.

## Rule version read model

`GET /api/v1/admin/tax-rule-versions/{id}` returns the version plus a summary:

```json
{ "version": "2568.1", "status": "published", "tax_year": 2568, "editable": false,
  "counts": { "tax_brackets": 8, "expense_rules": 70, "allowance_rules": 15,
              "allowance_cap_groups": 3, "donation_rules": 2, "recommendation_rules": 4 },
  "source_coverage": { "citations": 1, "distinct_sources": 1 },
  "referenced_by_history": true,
  "validation": { "valid": null, "errors": [], "warnings": [] } }
```

`validation` is computed for drafts only; a published version is not a candidate for publication.

## Draft rule editing

`DraftRuleRegistry` declares, for each of the six rule entities, the model it maps to and the
exact fields an admin may write, with validation. One declaration per entity, side by side,
because the interesting part of each is its field list — and a missing or over-permissive field
is only visible when they can be compared.

`rule_version_id`, `tax_year_id` and `id` are never taken from a request: the route decides which
version a row belongs to.

`DraftRuleService` refuses a non-draft version with a readable 422, and refuses a rule that
belongs to a different version with `RULE_NOT_IN_VERSION`. The models refuse a published write
regardless.

## Source and evidence tracking

`tax_sources` is the registry of repository-approved documents:

| Field | Notes |
| --- | --- |
| `code` | Stable identifier, e.g. `PND90_INSTRUCTIONS_2568` |
| `source_type` | `OFFICIAL_FORM`, `FILING_INSTRUCTIONS`, `ATTACHMENT`, `INTERNAL_APPROVED_REFERENCE` |
| `file_path` | A path **inside this repository**. M8 introduces no external fetching, and a path containing `..`, a scheme or a URL is refused. |
| `active` | Retired sources are deactivated, never destroyed |

`tax_rule_sources` links one rule row of one version to one source, with `page_reference` and
`section_reference` — so an admin can answer "where did this rule come from?" for any rule they
can see. `rule_entity_type` is restricted to the engine's own rule tables
(`TaxRuleSource::ENTITY_TYPES`), which makes this a narrow mapping rather than an open
polymorphic store: a row cannot point at anything that is not a tax rule.

**A source cited by a published rule cannot be deleted** — only deactivated. Evidence for a rule
that is in force has to stay readable, or the rule stops being auditable. Attempting it returns
`TAX_SOURCE_CITED_BY_PUBLISHED_RULE`.

Citations follow a clone, so a draft can be audited exactly like the version it came from.

**Scope note.** M7.x recorded each rule's provenance in its `source_reference` string, and those
strings are untouched. `tax_sources` is the structured registry beside them; M8 does not
retro-fit 90 rule rows into it, and a citation is created as an administrator works.

## Audit log

`admin_audit_logs` records who changed what, for content, tax sources and rule versions.

| Action | When |
| --- | --- |
| `CONTENT_CREATED` / `CONTENT_UPDATED` / `CONTENT_PUBLISHED` / `CONTENT_UNPUBLISHED` / `CONTENT_ARCHIVED` / `CONTENT_DELETED` | CMS |
| `RULE_VERSION_CREATED` / `RULE_VERSION_CLONED` / `RULE_VERSION_VALIDATED` / `RULE_VERSION_PUBLISHED` / `RULE_VERSION_ARCHIVED` | Version lifecycle |
| `TAX_RULE_CREATED` / `TAX_RULE_UPDATED` / `TAX_RULE_DELETED` | Draft rule editing |
| `TAX_SOURCE_CREATED` / `TAX_SOURCE_UPDATED` / `TAX_SOURCE_ARCHIVED` | Source registry |

`before_json` and `after_json` hold the changed attributes. `AdminAuditService` strips
`password`, `password_confirmation`, `remember_token`, `token`, `api_token`, `input_snapshot`,
`result_snapshot` and `calculation_trace` before writing — a member's tax figures are theirs and
have no place in an administrative trail. Tests assert that none of those strings ever appears.

Read it at `GET /api/v1/admin/audit-logs` (filters `entity_type`, `action`).

## Admin console

Server-rendered Blade on the existing Tailwind stack. No SPA, no new framework.

| Page | Purpose |
| --- | --- |
| `/admin/login` | Signs in against the existing Sanctum token endpoint |
| `/admin` | Counts, the published rule version, drafts, recent activity |
| `/admin/content` | Content list with publish / unpublish / archive |
| `/admin/content/create`, `/admin/content/{id}/edit` | The CMS editor |
| `/admin/tax-rule-versions` | Version list, with clone |
| `/admin/tax-rule-versions/{id}` | Counts, source coverage, validation results, rule tables, publish |
| `/admin/tax-sources` | The source registry |

Every write the console performs is a `fetch()` against `/api/v1/admin`, so there is exactly one
code path that can change anything — the page routes render, and nothing more. The console holds
a Sanctum token; there is no session-authenticated form route to bypass the API. Admin pages
carry `noindex, nofollow`.

Rule editing in the console is presented as read-only tables plus the API, rather than a visual
rule builder. M8 explicitly does not build a DSL for tax rules.

## Tests

`tests/Feature/AdminTaxRuleAdministrationTest.php` — authorization, published immutability
through every write route and directly at the model layer, clone completeness, draft editing,
structural validation, refused and successful publication, history preservation, source
protection and audit content.

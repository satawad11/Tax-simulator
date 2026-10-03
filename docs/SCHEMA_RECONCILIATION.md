# M2/M3 schema reconciliation

## Scope and baseline

Compared PROJECT_REQUIREMENTS.md, CODING_RULES.md, MILESTONE_02_PROMPT.md,
MILESTONE_03_PROMPT.md, the original migrations and live MySQL information_schema.
The exact missing-field list below was reported before edits.

No old migration was rewritten. Corrective migration:
`2026_09_11_144046_reconcile_approved_m2_schema.php`.

## Missing allowance codes

LIFE_INSURANCE, HEALTH_INSURANCE, PENSION_INSURANCE, NSF, EASY_E_RECEIPT.

All five were added by the repeatable AllowanceTypeSeeder. The two existing
INSURANCE and ANNUAL_TAX_MEASURES records were retained to preserve references.
There are now 19 unique records: all 17 approved codes plus those two legacy codes.
No allowance or expense numeric rule was inserted.

## Missing columns before reconciliation

All 74 fields below have been added. No unlisted new domain field was introduced.

| Table | Missing fields |
| --- | --- |
| users | `role` |
| tax_years | `filing_start_date`, `filing_end_date`, `active` |
| tax_forms | `active` |
| tax_rule_versions | `effective_from`, `effective_to` |
| tax_brackets | `min_amount`, `max_amount`, `sort_order` |
| income_types | `section_code` |
| income_rules | `active`, `metadata` |
| expense_rules | `maximum_amount`, `minimum_amount`, `active` |
| allowance_rules | `method`, `fixed_amount`, `maximum_amount`, `minimum_amount`, `active` |
| donation_rules | `name`, `max_percentage`, `active` |
| recommendation_rules | `type`, `title`, `message_template`, `action_type`, `active` |
| tax_returns | `name`, `current_step` |
| tax_return_profiles | `filing_status`, `extra_data` |
| tax_return_spouses | `filing_status`, `extra_data` |
| tax_return_dependents | `relation_type`, `eligible`, `allowance_amount`, `metadata` |
| tax_return_incomes | `description`, `gross_amount`, `expense_method`, `calculated_expense`, `net_amount`, `metadata` |
| tax_return_allowances | `input_amount`, `eligible_amount`, `metadata` |
| tax_return_donations | `donation_code`, `input_amount`, `eligible_amount`, `metadata` |
| tax_return_withholdings | `type`, `payer_name`, `payer_tax_id`, `metadata` |
| tax_calculations | `tax_credit`, `withholding_tax`, `prepaid_tax`, `final_tax`, `calculation_trace` |
| tax_calculation_brackets | `from_amount`, `to_amount`, `sort_order` |
| tax_scenarios | `user_id`, `source_tax_return_id`, `tax_year_id`, `rule_version_id`, `payload`, `calculation_result` |
| content_posts | `category_id`, `cover_image`, `source_name`, `source_url` |

## Data preservation

- Existing rows, IDs, foreign keys, soft deletes, timestamps and original columns
  remain. Equivalent names are copied to the new columns without calculation.
- Published 2568.1, all original tax bracket columns and form-income mappings were
  compared before and after the live migration and allowance seed: identical.
  Version 2568.1 now additionally has null effective_from/effective_to columns.
- Canonical and equivalent legacy columns are synchronized by Eloquent attribute
  setters through HasLegacySchemaAliases. Raw/bulk DB writes must maintain both
  columns; they are not the supported application editing path. Published rule
  guards still apply to instance persistence, including quiet writes.
- New metadata whose values cannot be recovered remains null. In particular,
  generic legacy dependent "parent" does not identify father/mother/in-law;
  donation_type does not identify an approved donation_code; no mapping was guessed.
- New calculated amount columns have only M2's specified zero defaults. No
  calculation was run to populate eligibility, prepaid tax or results.
- Existing scenarios obtain user/year/version from their source return. New model
  saves check that owner/year/version agree with the source; relationships are typed.
- User role defaults to member and is deliberately not mass assignable.
- Correction preflight refuses duplicate income/allowance rules instead of deleting
  any. Rollback refuses incompatible status/priority or overlength data rather than
  narrowing it silently. Rolling back still removes newly added columns.

## Other differences corrected

- Tax rule status widened from draft/published DB ENUM to VARCHAR(20), supporting
  the approved retired value without changing any current status.
- Recommendation priority widened from unsigned SMALLINT to VARCHAR(20), default
  low for new records. Existing numeric values remain their original text because
  the specification defines no numeric-to-low/medium/high conversion.
- Added UNIQUE(rule_version_id, income_type_id) to income_rules and
  UNIQUE(rule_version_id, allowance_type_id) to allowance_rules.
- Added standalone version-status and version/bracket-sort indexes, plus return
  user/year and year/form indexes.
- allowance_types.category widened from 60 to the approved 100 characters.
- content_posts.slug widened from 191 to the approved 255 characters.
- Added the required scenario and category foreign keys and User.taxScenarios.

## Preserved compatibility differences from the exact M2 specification

These remain deliberate data-preserving differences, not missing columns:

| Area | Retained difference |
| --- | --- |
| Existing names | Legacy columns such as is_active, lower_bound, upper_bound, position, limit_amount, title, details, amount, credits, withholding, final_amount, trace, tax_return_id, input_overrides and content_category_id remain beside approved names. |
| Additional columns | Existing provenance, rule code/name/exemption fields, published_at, tax_year_id on child rules, transaction snapshots, bracket references and scenario comparison fields remain. Pivot IDs/timestamps also remain. |
| Rates | rate, percentage, multiplier and cap/max percentage retain DECIMAL(7,4), rather than reducing to DECIMAL(6,3). No monetary field uses float/double. |
| Lengths | tax_years.name and tax_forms.name remain 255 rather than 100; allowance_types.code remains 60 rather than 50; donation_rules.code remains 100 rather than 50; content_categories/content_tags names remain 255 rather than 150 and slugs 191 rather than 180. |
| Existing ENUMs | Expense methods; return status/marital status/legacy relationship/donation/credit types; calculation result; content type/status; legacy donation/recommendation categories remain ENUMs. Approved new counterparts use strings. |
| Unknown required values | New names, section_code for unmapped custom income codes, method, titles, relation_type, donation_code and scenario payload/context columns allow null to avoid inventing historical values. Source/owner context is backfilled where available. |
| Derived required values | min_amount, from_amount, gross_amount and sort_order remain nullable at database level for compatibility with old writers, but model aliases populate both representations. |
| Existing null/default behavior | Existing exempt_amount, spouse has_income, donation multiplier, calculation summary columns, trace and source snapshot retain original null/default behavior. New columns use explicit M2 defaults only. Old is_active on tax_years has its old DB default; normal model writes initialize/synchronize the approved active default. |
| Priority | Historical numeric recommendation priorities cannot be semantically converted without an approved mapping; new storage supports low/medium/high. |
| Timestamps | Existing completed_at, published_at and calculated_at remain TIMESTAMP rather than converting historical timezone semantics to DATETIME. New effective dates use DATETIME. |
| Content ownership | Existing author_id remains nullable with nullOnDelete instead of forcing an author or deleting content; category relationships retain nullOnDelete. |
| Historical delete strategy | Existing calculation/source references retain restrictive deletes rather than adopting the recommended cascade behavior. |
| Extra constraints | Existing composite year/form/version consistency and per-version code constraints remain. Return user/status/updated_at index covers the required user/status prefix. |
| Legacy masters | INSURANCE and ANNUAL_TAX_MEASURES remain alongside the 17 approved codes. |

No need to tighten or remove those compatibility fields before reading metadata.
Any later removal, required-value backfill, narrowing or destructive strategy change
needs a separate data migration based on approved semantics.

## M3 API changes

Resources now read stored filing dates, section_code, bracket aliases and
allowance method/fixed/minimum/maximum fields. Numeric values remain decimal
strings. Active flags on numeric rules are honored; unverified or absent numeric
rules still return null. Income rule active metadata comes from the published
version when a row exists, otherwise availability remains based on form mapping.
The allowance ambiguity test was replaced by database uniqueness coverage, since
two rules for one version/type are now rejected at insertion; ambiguous expense
rules still return 409.

Routes, response envelopes, port 8088, form mappings and published-version
resolution are unchanged. No UI or tax calculation logic was added.

## Verification

Live commands:
`docker compose exec app php artisan migrate --no-interaction` and
`docker compose exec app php artisan db:seed --class=AllowanceTypeSeeder --no-interaction`.

Full SQLite and dedicated MySQL suites verify all approved fields/codes, preserved
publication/mappings, metadata responses, foreign keys, uniqueness and privacy.
An isolated in-memory upgrade/rollback/re-upgrade fixture verifies old data survives.

No migrate:fresh command was run against development data. Tests rebuild only
isolated test databases. migrate:fresh would be destructive to development data.

Milestone 04 has not started.


# Input Cardinality and Duplicate Rules

This contract covers the supported PND90/PND91 simulation inputs for tax year 2568. It describes input shape only. It does not add or alter a tax formula.

## Cardinality contract

| Input | Cardinality | Unique key | Duplicate policy | Required condition |
| --- | --- | --- | --- | --- |
| Tax year and form | `SINGLE` | `tax_year + form_code` | Reject conflicting values | Always required. The server resolves the published rule version. |
| Taxpayer profile | `OPTIONAL_SINGLE` | Tax return | Reject a second profile | Marital status is required when spouse data is supplied. Taxpayer birth date is optional because no supported baseline path consumes it. |
| Spouse | `OPTIONAL_SINGLE` | Tax return | Reject a second spouse | `has_income` and married status are required when spouse data is supplied. |
| Income | `REPEATABLE` | Section, subtype, activity/holding period, and payer/source facts | Allow; warn when every material value is identical | At least one row. Type and gross amount are required on every row. Conditional details are listed below. |
| Allowance paid amount | `CONDITIONAL_REPEATABLE` | Allowance code | Reject | One annual amount per supported code when claimed. Family allowances remain derived. |
| Donation | `CONDITIONAL_REPEATABLE` | Donation code | Reject | One annual aggregate per special/general type when claimed. |
| Withholding | `REPEATABLE` | Payer/source certificate | Allow | One row per source when tax was withheld. |
| PND93/PND94 prepaid tax | `OPTIONAL_SINGLE` per type | Prepayment type | Reject | One annual aggregate for each type when paid. |
| Parent dependent | `CONDITIONAL_REPEATABLE` | Relationship role (`father`, `mother`, `spouse_father`, `spouse_mother`) | Reject | Relationship and the taxpayer eligibility declaration are required. |
| Child dependent | `CONDITIONAL_REPEATABLE` | `child_type + birth_order` when both facts exist | Reject for the safe key; otherwise warn | Child type and eligibility declaration are required. A legitimate child also requires birth order and birth date. |
| Disabled dependent | `CONDITIONAL_REPEATABLE` | No reliable person identity is collected | Allow with best-effort exact-facts warning | Eligibility is required. Relationship subtype remains conditional on a rule version that supports it. |
| Calculated totals | `DERIVED_ONLY` | Not applicable | Not applicable | Never entered by the user. |

The application does not collect a national ID for dependents. It therefore rejects only a duplicate that current facts identify safely. Ambiguous child or disabled-person matches produce a review warning and are not merged or deleted.

## Exact required inputs

The common minimum is tax year, form, at least one income row, income type, and gross annual amount. Exempt income is optional and is entered only when supported by evidence. Allowances, donations, dependents, withholding, and prepayments are optional until claimed or paid.

PND91 accepts repeatable section 40(1) rows so separate employers remain separate. No expense amount, net income, tax, refund, or payable amount is editable.

PND90 uses these conditional facts:

| Section | Always on each selected row | Conditional input |
| --- | --- | --- |
| 40(1) | Gross amount | Exempt amount when applicable; payer/source is optional |
| 40(2) | Gross amount | Exempt amount when applicable; payer/source is optional |
| 40(3) | Gross amount and subtype | Expense method for the supported copyright/right subtype; actual amount when actual expense is selected |
| 40(4) | Gross amount | Exempt amount when applicable; no expense input |
| 40(5) | Gross amount and subtype | Expense method where the selected subtype offers a choice; actual amount when actual is selected |
| 40(6) | Gross amount and subtype | Expense method; actual amount when actual is selected |
| 40(7) | Gross amount | Expense method; actual amount when actual is selected |
| 40(8) | Gross amount and subtype | Activity for business/commerce; holding years for the non-trade property subtype; method/actual amount where offered; tax-treatment election for gift/support income |

The UI hides subtype, activity, holding period, actual expense, and treatment controls until their prerequisite selection applies. Backend semantic validation is authoritative for Guest calculation, planning, and Member calculation.

## UI behaviour

- Allowances are fixed annual amount fields, one per code.
- Donations are two fixed annual aggregate fields rather than an unrestricted repeater.
- PND93 and PND94 options are disabled after use in another row; ordinary withholding remains repeatable.
- The review screen displays `พบรายการซ้ำ` and links to the relevant step.
- Exact duplicate income facts cause a warning only, because two employers, properties, or activities can legitimately have equal amounts.
- The backend still validates crafted requests and persisted Member changes.


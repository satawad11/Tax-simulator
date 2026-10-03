# Milestone 04 — PND91 calculation API

> **Superseded figures (M9.1).** The example request on this page declares a `profile`, so it
> now also claims ใบแนบ item 1 — `PERSONAL` 60,000 — and every derived amount shown below moves
> with it: net 560,000, tax 36,500, PAYABLE 11,500. The mechanics, fields, validation and
> warnings described on this page are unchanged. See
> [FAMILY_ALLOWANCE_AUTOMATIC_CLAIM.md](../tax/FAMILY_ALLOWANCE_AUTOMATIC_CLAIM.md).

## Endpoint and scope

`POST http://localhost:8088/api/v1/tax/calculate` is public and stateless.
Send `Content-Type: application/json` and `Accept: application/json`.
Only PND91 and SECTION_40_1 are supported. No authentication, financial-input logging,
TaxReturn, TaxCalculation history, or TaxScenario persistence is performed.

## Request

```json
{
  "tax_year": 2568,
  "form_code": "PND91",
  "profile": {"birth_date": "1990-05-20", "marital_status": "single"},
  "incomes": [
    {"income_type": "SECTION_40_1", "description": "Synthetic salary",
     "gross_amount": "720000.00", "exempt_amount": "0.00"}
  ],
  "allowances": [],
  "donations": [],
  "withholdings": [{"type": "withholding", "amount": "25000.00"}]
}
```

Required: active existing tax_year, active year-specific PND91 with SECTION_40_1 mapping,
and 1–100 income items. Exactly one published rule version must resolve.
Optional lists default to empty and each allow at most 100 items.
Each income requires income_type and gross_amount; exempt_amount defaults to zero and cannot exceed gross.
Exemptions are user declarations, not automated legal eligibility determinations.

Allowance and donation entries use `{"code":"...","amount":"1000.00"}`.
Allowance codes must exist and be active; duplicate allowance codes are rejected.
Donation codes must belong to an active rule in the selected published version. No production
donation codes/rules are seeded, so nonempty production donation input currently returns 422.

Withholding entries use type and amount. Supported structural types:
withholding, foreign_tax_credit, pnd93, other_credit.
Only withholding is granted in M4. Other types receive zero eligible credit with warnings.
Repeated withholding entries are summed.

Profile is optional; birth_date must be a real YYYY-MM-DD date no later than today;
marital_status is single, married, divorced, or widowed. Profile is never used to infer
unverified exemption eligibility, and is neither retained nor echoed. Since M9.1 a declared
profile does claim ใบแนบ item 1 `PERSONAL`, whose amount the filing instructions print.

Unknown fields are rejected at every request object level, including client-calculated expense,
eligible_amount, net_income, tax, or credit totals. Lists must be JSON arrays with sequential indexes.

## Money

Use nonnegative plain decimal **strings**, with at most 13 integer digits and 2 fractional digits.
Integer JSON numbers are also accepted. Fractional JSON numbers are rejected: quote them to avoid
binary-float parsing. Scientific notation, signs, commas, extra precision, null and negative amounts
return 422.

The Money value object uses the already-installed brick/math BigDecimal. All authoritative sums,
differences, percentages, caps and comparisons are exact. API money is always a decimal string,
with at least two fractional digits and additional nonzero digits preserved.
For example net income 150000.01 produces tax "0.0005".
There is no invented legal floor, ceiling, or final rounding.

Analysis effective_tax_rate = calculated_tax / gross_income * 100, or zero when gross is zero.
This display-only percentage is serialized to six decimal places using HalfUp; it never feeds
money arithmetic. Marginal rate is the highest bracket rate with positive taxable income,
so the lower bracket applies at an exact shared boundary.

## Response

The success envelope is `{"success":true,"message":null,"data":{...}}`.
For the request above, data includes:

- tax_year: 2568; form_code: PND91; rule_version: 2568.1.
- income: gross_income "720000.00", exempt_income "0.00", gross_after_exemption "720000.00".
- expenses: items with code, basis, percentage, maximum_amount, eligible_amount; total "100000.00".
- income_after_expense: "620000.00".
- allowances and donations: items, total_input "0.00", total_eligible "0.00".
- net_income: "620000.00".
- progressive_tax: all eight ordered brackets and total "45500.00".
  Each bracket contains sort_order, min_amount, max_amount (null for final), rate, taxable_amount, tax.
- credits: withholding "25000.00", foreign_tax_credit/pnd93/other_credit "0.00", total "25000.00".
- result: status PAYABLE, amount "20500.00", reason_code SIMULATED_PAYABLE;
  components contain calculated_tax, foreign_tax_credit, tax_after_foreign_credit, withholding_and_prepaid.
- analysis: gross_income, net_income, calculated_tax, effective_tax_rate "6.319444",
  marginal_tax_rate "15.0000", withholding_total, result_status, result_amount.
- trace: all 16 ordered stages below; each contains step, code, Thai label, amount.
  Final entry also includes status.
- warnings: ROUNDING_RULE_PENDING.
- disclaimer: the Thai simulation wording below.

Status is exactly PAYABLE, REFUND or ZERO, with a nonnegative amount; direction is conveyed by status.
These are simulated outcomes, not official assessments or approved refunds.

## Ordered trace codes

1. GROSS_INCOME
2. EXEMPT_INCOME
3. INCOME_AFTER_EXEMPTION
4. EXPENSE
5. INCOME_AFTER_EXPENSE
6. ALLOWANCES
7. INCOME_AFTER_ALLOWANCES
8. SPECIAL_DONATION
9. INCOME_AFTER_SPECIAL_DONATION
10. GENERAL_DONATION
11. NET_INCOME
12. PROGRESSIVE_TAX
13. FOREIGN_TAX_CREDIT
14. TAX_AFTER_FOREIGN_CREDIT
15. WITHHOLDING_AND_PREPAID
16. RESULT

## Errors

422: `{"success":false,"message":"Validation failed","errors":{"form_code":["..."]}}`
for invalid/missing input, unsupported PND90/income types, unknown/inactive years/forms,
unknown codes, malformed lists, negative amounts or calculated fields.

409: standard failure envelope for missing/ambiguous published versions, absent verified employment
expense rules, unsupported expense mechanics, or incomplete/gapped/overlapping brackets.
Calculation fails closed; an incomplete master configuration never produces a plausible zero-tax success.
Unexpected failures retain the existing sanitized API error handling.

## Approved production rules and seed operation

MILESTONE_04_PROMPT.md explicitly approves SECTION_40_1 employment expenses at 50% of aggregate
gross income less declared exemptions, capped once at 100000. The calculator loads this rule from
expense_rules for the resolved published version; it does not hard-code production percentages/caps.

EmploymentExpenseRuleSeeder adds only this approved rule to 2568.1 using an explicitly scoped
query-builder amendment under a parent-version lock. It neither unpublishes nor rewrites the version
or its eight existing brackets. It refuses conflicting existing expense records and is idempotent.
Normal model/bulk-write published-rule protections are unchanged.

Run without deleting development data:

```sh
docker compose exec app php artisan migrate --no-interaction
docker compose exec app php artisan db:seed --class=EmploymentExpenseRuleSeeder --no-interaction
```

DatabaseSeeder also calls it for new environments. No corrective schema migration is necessary.

The progressive calculator uses the existing eight approved database brackets. Each slice is the
portion above its lower threshold up to its upper threshold; the last upper threshold is null.

## Rule gaps and warnings

Known allowance masters receive eligible_amount "0.00" and UNVERIFIED_ALLOWANCE_RULE.
No numeric allowance rules or eligibility criteria were guessed. Even source-labelled rule metadata
does not enable unimplemented legal mechanics.

Known but unverified donation rules receive eligible zero and UNVERIFIED_DONATION_RULE;
unknown donation codes are rejected. Special/general stages remain separate.

foreign_tax_credit receives zero and UNVERIFIED_FOREIGN_TAX_CREDIT_LIMIT.
pnd93/other_credit receive zero and UNVERIFIED_TAX_CREDIT_RULE until eligibility is approved.
ROUNDING_RULE_PENDING applies to every estimate until legal final-rounding policy is verified.

Warnings contain code, message and path. No allowance/donation/credit warning is emitted for an absent
entry. TODO: approve numeric allowance eligibility, donation multipliers/limits/stage rules,
foreign/prepaid/other credit eligibility and caps, and final legal rounding before enabling them.

## Simulation disclaimer

ผลลัพธ์เป็นการประมาณการจากข้อมูลที่กรอกในระบบจำลอง
ไม่ใช่การยื่นแบบภาษีจริงหรือการรับรองผลโดยหน่วยงานราชการ

## Tests and regression commands

```sh
docker compose exec app php artisan test
docker compose exec app vendor/bin/phpunit --configuration phpunit.mysql.xml
docker compose config --quiet
docker compose exec node npm run build
```

Default tests use SQLite :memory:. MySQL tests use only tax_simulator_test, guarded by Tests\\TestCase.
No migrate:fresh is required on the development database.
M4 changes no UI, authentication/member CRUD, PND90 engine or planning/recommendation engine.

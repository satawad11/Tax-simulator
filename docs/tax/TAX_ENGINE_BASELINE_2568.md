# Tax engine baseline — 2568

**Milestone 7.x closure document.** Rule version **2568.1**, frozen.

This is the final Milestone 7 baseline. It states what the simulator calculates, what it
refuses, and why — so that a later milestone can build on it without re-deriving any of it.

> **The simulator supports the listed paths only.**
> **Unsupported or partially verified rules are explicitly blocked or disclosed.**
> **The simulator is not an official tax filing system.**

---

## 1. Scope

| Form | Scope | Detail |
| --- | --- | --- |
| ภ.ง.ด.91 | มาตรา 40(1) only | [PND91_PRODUCTION_BASELINE_2568.md](PND91_PRODUCTION_BASELINE_2568.md) |
| ภ.ง.ด.90 | มาตรา 40(1)–40(8), ใบแนบ, ข้อ 9 election, วิธีที่ 2 minimum tax | [PND90_PRODUCTION_BASELINE_2568.md](PND90_PRODUCTION_BASELINE_2568.md) |

Both forms share one engine (`TaxCalculationService`), one allowance attachment (ใบแนบ) and one
bracket table. There is no second engine and no per-form formula.

## 2. Status counts

### Allowance master codes — 25

| Status | Count | Codes |
| --- | --- | --- |
| SUPPORTED | 20 | `PERSONAL`, `SPOUSE`, `CHILD`, `PARENT`, `DISABLED_PERSON` (derived); `PROVIDENT_FUND`, `PARENT_HEALTH_INSURANCE`, `HOME_LOAN_INTEREST`, `MATERNITY`, `POLITICAL_PARTY_SUPPORT`, `ART_PURCHASE`, `LIFE_INSURANCE`, `HEALTH_INSURANCE`, `NSF`, `RMF`, `THAI_ESG`, `THAI_ESGX`, `THAI_ESGX_SWITCH`, `EASY_E_RECEIPT`, `EASY_E_RECEIPT_OTOP` (seeded rules) |
| PARTIAL_BLOCKED | 2 | `PENSION_INSURANCE`, `SOCIAL_SECURITY` |
| UNSUPPORTED | 3 | `INSURANCE`, `ANNUAL_TAX_MEASURES`, `OTHER` |
| NOT_APPLICABLE | 0 | — |

`TaxEngineBaselineClosureTest::test_every_allowance_master_code_has_exactly_one_final_status`
asserts the three sets are disjoint and together cover every master code, so a future seeded
rule cannot leave a stale classification behind.

### PND90 expense coverage

| Group | Status | Count |
| --- | --- | --- |
| Income types | SUPPORTED | 8 of 8 |
| Subtypes | SUPPORTED | 13 of 13 |
| ตารางที่ 2 activities | SUPPORTED | 44 of 44 |
| Holding-period bands | SUPPORTED | 8 of 8 |
| Total seeded expense rules | — | 70 |

No ภ.ง.ด.90 income category or subcategory is UNVERIFIED.

### Donations — 2

| Code | Status |
| --- | --- |
| `SPECIAL_DONATION` | SUPPORTED |
| `GENERAL_DONATION` | SUPPORTED |

### Credits and prepayments — 5

| Type | Status |
| --- | --- |
| `withholding`, `pnd93`, `pnd94` | SUPPORTED |
| `foreign_tax_credit`, `other_credit` | UNSUPPORTED |

### Other engine paths

| Path | Status |
| --- | --- |
| Progressive tax (8 brackets) | SUPPORTED |
| ภ.ง.ด.90 minimum tax (0.5%) | SUPPORTED |
| ภ.ง.ด.91 minimum tax | NOT_APPLICABLE |
| ข้อ 9 separate-rate election | SUPPORTED (ภ.ง.ด.90 only) |
| ข้อ 8, ข้อ 10 | UNSUPPORTED |
| Legal final rounding | UNSUPPORTED |

## 3. Runtime guard policy

A material path the baseline cannot calculate **refuses** rather than returning a smaller
deduction. A positive amount on a blocked allowance or an unsupported credit is a 422 carrying
a stable machine-readable code:

| Code | Path | Reason |
| --- | --- | --- |
| `PENSION_INSURANCE_RULE_PARTIAL` | ใบแนบ item 7.6 | 90,000 and 15%/200,000 printed without saying whether they nest |
| `SOCIAL_SECURITY_RULE_UNSUPPORTED` | ใบแนบ item 12 | ceiling referred to an act outside the repository sources |
| `ALLOWANCE_RULE_UNSUPPORTED` | `INSURANCE`, `ANNUAL_TAX_MEASURES`, `OTHER` | FR-007 umbrella categories with no ใบแนบ line |
| `FOREIGN_TAX_CREDIT_UNSUPPORTED` | ข้อ 11 item 13 | limit wording not specific enough to apply |
| `TAX_CREDIT_TYPE_UNSUPPORTED` | `other_credit` | no counterpart on the form |

A **zero** amount on any of these is still accepted, because it cannot change the tax in either
direction; it produces the existing `UNVERIFIED_*` warning and deducts nothing. Everything
below is a warning rather than a refusal, because continuing cannot materially mislead:

| Warning | Meaning |
| --- | --- |
| `ROUNDING_RULE_PENDING` | legal final rounding is UNSUPPORTED; exact decimals are retained |
| `PND90_MINIMUM_TAX_APPLIED` | วิธีที่ 2 was the greater and is what is payable |
| `FAMILY_ALLOWANCE_DERIVED` | a submitted family amount was discarded in favour of the derived one |
| `COMBINED_ALLOWANCE_CAP_APPLIED` | a shared ceiling reduced a group total |
| `SPOUSE_ALLOWANCE_FACTS_MISSING`, `CHILD_TYPE_NOT_DECLARED`, `CHILD_BIRTH_ORDER_NOT_DECLARED`, `ADOPTED_CHILD_LIMIT_APPLIED`, `SPOUSE_PARENT_NOT_ELIGIBLE`, `DISABLED_PERSON_OTHER_LIMIT_UNMODELLED` | a family fact is missing or a printed sub-limit cannot be applied |
| `UNVERIFIED_EXPENSE_RULE`, `UNVERIFIED_ALLOWANCE_RULE`, `UNVERIFIED_FOREIGN_TAX_CREDIT_LIMIT`, `UNVERIFIED_TAX_CREDIT_RULE` | a zero-valued line on a path with no verified rule |

## 4. Disclaimers

1. **Not an official filing system.** A completed simulation is an estimate from the figures
   entered, not a filed return and not a government certification.
2. **Legal final rounding is not established.** No repository source states a rounding unit,
   direction or stage, so exact decimal amounts are retained and fractional satang survives.
   Every response says so.
3. **Some eligibility is taxpayer-declared and not independently verified.** ใบแนบ items 3, 4,
   5 and 6 depend on a dependant's own income, age, study status or disability documentation.
   The engine counts declarations; it does not assess them, and no AI determines eligibility.
4. **Exempt income inside ข้อ 1 is a stated total.** The individual ceilings the booklet prints
   for PVD above 10,000, กบข. and กองทุนสงเคราะห์ครูโรงเรียนเอกชน are not enforced.
5. **One printed sub-limit is reported, not applied.** ใบแนบ item 5 limits certification by a
   บุคคลอื่น to one person; the schema cannot tell a family member from a บุคคลอื่น.

## 5. Rule version

**2568.1, extended and now frozen.** M7.5 added runtime guards, documentation, tests and one
recommendation wording correction. It changed no rule's method, rate, ceiling or base, and no
previously supported calculation changes its result. Historical snapshots were not rewritten
and no saved return was upgraded.

A later correctness fix that changes a previously supported result must create a new internal
rule version rather than amend 2568.1.

## 6. Test baseline

| Suite | Result |
| --- | --- |
| SQLite (default) | 712 passed, 0 failed, 2 skipped, 3,596 assertions |
| MySQL-backed | 714 passed, 0 failed, 0 skipped (the two MySQL-specific cases run here) |

Closure suite: `tests/Feature/TaxEngineBaselineClosureTest.php`.

## 7. API behaviour

Additive only throughout Milestone 7; no route or response field was removed or renamed. The
guards added in M7.5 turn three previously-200 responses into 422s on paths that were returning
a silently reduced deduction — a deliberate correctness change, documented in
[MILESTONE_07_API.md](../api/MILESTONE_07_API.md).

Guest, member and planning share one engine; for identical input and rule version the guest and
member responses are identical, asserted section by section.

## 8. Source map

[SOURCE_COVERAGE_2568.md](SOURCE_COVERAGE_2568.md) maps every implemented rule to its source
file, page and item, its machine code, its implementation class and its test.

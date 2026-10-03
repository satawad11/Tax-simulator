# Source coverage — 2568

Milestone 07.5. Every implemented rule, mapped to the source it came from, the machine code it
is stored under, the class that applies it and the test that pins it.

Source files (SHA-256 recorded in
[PND90_SOURCE_RECONCILIATION.md](PND90_SOURCE_RECONCILIATION.md)):

| Short name | Path |
| --- | --- |
| **F90** | `docs/tax-source/PND90-2568-form.pdf` |
| **F91** | `docs/tax-source/PND91-2568-form.pdf` |
| **I90** | `docs/tax-source/PND90-2568-filing-instructions.pdf` |
| **I91** | `docs/tax-source/PND91-2568-filing-instructions.pdf` |
| **RATES** | `docs/tax-source/tax-rates-2568.jpg` |

No reference below is invented; each was read from the file and page named.

---

## 1. Tax brackets

| Source | Machine rule | Implementation | Test | Status |
| --- | --- | --- | --- | --- |
| I90 p.17 ตารางที่ 1; RATES | `tax_brackets` (8 rows, 2568.1) | `ProgressiveTaxCalculator` | `DatabaseFoundationTest`, `TaxCalculationApiTest` | SUPPORTED |

## 2. Expense rules

| Source | Machine rule | Implementation | Test | Status |
| --- | --- | --- | --- | --- |
| F90 p.2 ข้อ 1 item 5 | `PND91_SECTION_40_1_EXPENSE` (group `SECTION_40_1_2`) | `ExpenseCalculator` | `Pnd90SourceExpenseRuleTest` | SUPPORTED |
| F90 p.2 ข้อ 1 item 5 | `PND90_SECTION_40_2_EXPENSE` (same group) | `ExpenseCalculator` | `Pnd90SourceExpenseRuleTest` | SUPPORTED |
| F90 p.2 ข้อ 2 item 1 | `PND90_SECTION_40_3_ANNUITY_EXPENSE` | `ExpenseCalculator` | `Pnd90RuleReconciliationTest` | SUPPORTED |
| F90 p.2 ข้อ 2 item 2 | `PND90_SECTION_40_3_COPYRIGHT_EXPENSE` | `ExpenseCalculator` | `Pnd90SourceExpenseRuleTest` | SUPPORTED |
| F90 p.2 ข้อ 3 | `PND90_SECTION_40_4_EXPENSE` | `ExpenseCalculator` | `Pnd90RuleReconciliationTest` | SUPPORTED |
| F90 p.2 ข้อ 4 item 1 (1); I90 p.3 (ก) | `PND90_SECTION_40_5_RENT_BUILDING_EXPENSE` | `ExpenseCalculator` | `Pnd90ExpenseSubtypeTest` | SUPPORTED |
| I90 p.3 ข้อ 4 (ข) | `PND90_SECTION_40_5_RENT_LAND_AGRICULTURAL_EXPENSE` | `ExpenseCalculator` | `Pnd90ExpenseSubtypeTest` | SUPPORTED |
| I90 p.3 ข้อ 4 (ค) | `PND90_SECTION_40_5_RENT_LAND_NON_AGRICULTURAL_EXPENSE` | `ExpenseCalculator` | `Pnd90ExpenseSubtypeTest` | SUPPORTED |
| I90 p.3 ข้อ 4 (ง) | `PND90_SECTION_40_5_RENT_VEHICLE_EXPENSE` | `ExpenseCalculator` | `Pnd90ExpenseSubtypeTest` | SUPPORTED |
| I90 p.3 ข้อ 4 (จ) | `PND90_SECTION_40_5_RENT_OTHER_PROPERTY_EXPENSE` | `ExpenseCalculator` | `Pnd90ExpenseSubtypeTest` | SUPPORTED |
| F90 p.3 ข้อ 4 item 2 | `PND90_SECTION_40_5_HIRE_PURCHASE_EXPENSE` | `ExpenseCalculator` | `Pnd90SourceExpenseRuleTest` | SUPPORTED |
| F90 p.3 ข้อ 5 item 1 | `PND90_SECTION_40_6_MEDICAL_EXPENSE` | `ExpenseCalculator` | `Pnd90SourceExpenseRuleTest` | SUPPORTED |
| F90 p.3 ข้อ 5 item 2 | `PND90_SECTION_40_6_FINE_ARTS_EXPENSE` | `ExpenseCalculator` | `Pnd90SourceExpenseRuleTest` | SUPPORTED |
| F90 p.3 ข้อ 5 items 3–4 | `PND90_SECTION_40_6_OTHER_PROFESSION_EXPENSE` | `ExpenseCalculator` | `Pnd90SourceExpenseRuleTest` | SUPPORTED |
| I90 p.3 ข้อ 6 | `PND90_SECTION_40_7_EXPENSE` | `ExpenseCalculator` | `Pnd90SourceExpenseRuleTest` | SUPPORTED |
| I90 p.17 ตารางที่ 2 rows (1)–(44) | `PND90_SECTION_40_8_TABLE2_*_EXPENSE` (44 rules; row (1) `tiered_or_actual` + 2 `expense_rule_tiers`; row (44) `actual`) | `ExpenseCalculator`, `ExpenseActivityCatalogue` | `Pnd90ExpenseSubtypeTest`, `Pnd90RuleReconciliationTest` | SUPPORTED |
| F90 p.3 ข้อ 7 item 2 | `PND90_SECTION_40_8_MUTUAL_FUND_EXPENSE` | `ExpenseCalculator` | `Pnd90RuleReconciliationTest` | SUPPORTED |
| F90 p.3 ข้อ 7 item 3 (1) | `PND90_SECTION_40_8_INHERITED_PROPERTY_EXPENSE` | `ExpenseCalculator` | `Pnd90SourceExpenseRuleTest` | SUPPORTED |
| I90 p.3 ข้อ 7 item 3 (2) | `PND90_SECTION_40_8_NON_TRADE_PROPERTY_{1..8}Y_EXPENSE` (8 bands) | `ExpenseCalculator`, `PropertyHoldingPeriodCatalogue` | `Pnd90ExpenseSubtypeTest` | SUPPORTED |
| F90 p.3 ข้อ 7 item 4 | `PND90_SECTION_40_8_GIFT_RECEIVED_EXPENSE` | `ExpenseCalculator` | `Pnd90RemainingRulesTest` | SUPPORTED |

## 3. Family allowances (ใบแนบ items 1–5)

| Source | Machine code | Implementation | Test | Status |
| --- | --- | --- | --- | --- |
| I90 p.7 item 1, 1.1, 1.2 | `PERSONAL` | `PersonalAllowanceStrategy` | `FamilyAllowanceStrategyTest`, `FilingInstructionReconciliationTest` | SUPPORTED |
| I90 p.7 items 2.1, 2.2 | `SPOUSE` | `SpouseAllowanceStrategy` | same | SUPPORTED |
| I90 p.7 items 3.1–3.3 | `CHILD` | `ChildAllowanceStrategy` | same | SUPPORTED (eligibility declared) |
| I90 p.8 items 4.1–4.3 | `PARENT` | `ParentAllowanceStrategy` | same | SUPPORTED (age/income declared) |
| I90 pp.8–9 item 5.1 | `DISABLED_PERSON` | `DisabledPersonAllowanceStrategy` | same | SUPPORTED (บุคคลอื่น limit reported) |

## 4. Allowance ceilings

| Source | Machine rule | Method | Implementation | Test | Status |
| --- | --- | --- | --- | --- | --- |
| F90 p.5 ใบแนบ item 8 | `PND90_2568_PROVIDENT_FUND` | actual ≤ 10,000 | `AllowanceCalculator` | `Pnd90RemainingRulesTest` | SUPPORTED |
| I90 p.9 item 6.3 | `PND90_2568_PARENT_HEALTH_INSURANCE` | actual ≤ 15,000 | `AllowanceCalculator` | `FilingInstructionReconciliationTest` | SUPPORTED |
| I90 pp.9–10 item 7.2 (3) | `PND90_2568_LIFE_INSURANCE` | actual ≤ 100,000 | `AllowanceCalculator` | `AllowanceCapEngineTest` | SUPPORTED |
| I90 p.10 item 7.4 | `PND90_2568_HEALTH_INSURANCE` | actual ≤ 25,000 | `AllowanceCalculator` | `AllowanceCapEngineTest` | SUPPORTED |
| I90 p.10 item 9 | `PND90_2568_NSF` | actual ≤ 500,000 | `AllowanceCalculator` | `AllowanceCapEngineTest` | SUPPORTED |
| I90 p.11 item 10.4 | `PND90_2568_RMF` | 30% of `GROSS_AFTER_EXEMPTION` ≤ 500,000 | `AllowanceCalculator`, `PercentageBaseResolver` | `AllowanceCapEngineTest` | SUPPORTED |
| I90 p.12 item 11 | `PND90_2568_HOME_LOAN_INTEREST` | actual ≤ 100,000 | `AllowanceCalculator` | `FilingInstructionReconciliationTest` | SUPPORTED |
| I90 p.12 item 14 | `PND90_2568_MATERNITY` | actual ≤ 60,000 | `AllowanceCalculator` | `FilingInstructionReconciliationTest` | SUPPORTED |
| I90 p.12 item 15 | `PND90_2568_POLITICAL_PARTY_SUPPORT` | actual ≤ 10,000 | `AllowanceCalculator` | `FilingInstructionReconciliationTest` | SUPPORTED |
| I90 p.13 item 17.1 | `PND90_2568_EASY_E_RECEIPT` | actual ≤ 30,000 | `AllowanceCalculator` | `AllowanceCapEngineTest` | SUPPORTED |
| I90 p.13 item 17.2 | `PND90_2568_EASY_E_RECEIPT_OTOP` | actual ≤ 20,000 | `AllowanceCalculator` | `AllowanceCapEngineTest` | SUPPORTED |
| I90 p.14 item 18.1 | `PND90_2568_THAI_ESG` | 30% of `GROSS_AFTER_EXEMPTION` ≤ 300,000 | `AllowanceCalculator` | `AllowanceCapEngineTest` | SUPPORTED |
| I90 p.14 item 19.1 | `PND90_2568_THAI_ESGX` | 30% of `GROSS_AFTER_EXEMPTION` ≤ 300,000 | `AllowanceCalculator` | `AllowanceCapEngineTest` | SUPPORTED |
| I90 p.15 item 19.2 | `PND90_2568_THAI_ESGX_SWITCH` | actual ≤ 300,000 | `AllowanceCalculator` | `AllowanceCapEngineTest` | SUPPORTED |
| I90 p.16 item 21 | `PND90_2568_ART_PURCHASE` | actual ≤ 100,000 | `AllowanceCalculator` | `FilingInstructionReconciliationTest` | SUPPORTED |

## 5. Combined cap groups

| Source | Machine code | Cap | Members | Implementation | Test | Status |
| --- | --- | --- | --- | --- | --- | --- |
| I90 p.10 item 7.4 | `LIFE_AND_HEALTH_INSURANCE_2568` | 100,000 | `LIFE_INSURANCE`, `HEALTH_INSURANCE` | `CombinedAllowanceCapResolver` | `AllowanceCapEngineTest` | SUPPORTED |
| I90 p.10 item 9; p.11 item 10.4 | `RETIREMENT_SAVINGS_2568` | 500,000 | `PROVIDENT_FUND`, `NSF`, `RMF` | `CombinedAllowanceCapResolver` | `AllowanceCapEngineTest` | SUPPORTED |
| I90 p.13 item 17 | `EASY_E_RECEIPT_2568` | 50,000 | `EASY_E_RECEIPT`, `EASY_E_RECEIPT_OTOP` | `CombinedAllowanceCapResolver` | `AllowanceCapEngineTest` | SUPPORTED |

## 6. Donations

| Source | Machine code | Implementation | Test | Status |
| --- | --- | --- | --- | --- |
| F90 p.4 ข้อ 11 item 4 | `SPECIAL_DONATION` (×2, ≤10% of item 3) | `DonationCalculator` | `Pnd90RemainingRulesTest` | SUPPORTED |
| F90 p.4 ข้อ 11 item 6 | `GENERAL_DONATION` (×1, ≤10% of item 5) | `DonationCalculator` | `Pnd90RemainingRulesTest` | SUPPORTED |

## 7. Tax computation and credits

| Source | Machine rule | Implementation | Test | Status |
| --- | --- | --- | --- | --- |
| I90 p.6 คำนวณภาษีจาก 2 วิธี | minimum tax: base ข้อ 1–ข้อ 7 less 40(1), ≥120,000, ×0.005, floor 5,000, pay the greater | `MinimumTaxCalculator` | `MinimumTaxCalculatorTest`, `FilingInstructionReconciliationTest` | SUPPORTED |
| F90 p.3 ข้อ 9; I90 p.3 ข้อ 7 item 4 | ข้อ 9 election at 5% | `SeparateTaxCalculator` | `Pnd90RemainingRulesTest` | SUPPORTED |
| F90 p.4 ข้อ 11 item 15 | `withholding`, `pnd93`, `pnd94` | `TaxCreditCalculator` | `Pnd90RemainingRulesTest`, `TaxEngineBaselineClosureTest` | SUPPORTED |
| F90 p.4 ข้อ 11 items 16–18 | PAYABLE / REFUND / ZERO | `TaxRefundService` | `TaxEngineBaselineClosureTest` | SUPPORTED |

## 8. Implemented guards (no tax effect, but part of the contract)

| Source | Guard code | Implementation | Test | Status |
| --- | --- | --- | --- | --- |
| I90 p.10 item 7.6 | `PENSION_INSURANCE_RULE_PARTIAL` | `AllowanceCoverageCatalogue`, `TaxCalculationInputGuard` | `TaxEngineBaselineClosureTest` | PARTIAL_BLOCKED |
| I90 p.12 item 12 | `SOCIAL_SECURITY_RULE_UNSUPPORTED` | same | same | PARTIAL_BLOCKED |
| PROJECT_REQUIREMENTS FR-007 | `ALLOWANCE_RULE_UNSUPPORTED` | same | same | UNSUPPORTED |
| F90 p.4 ข้อ 11 item 13 | `FOREIGN_TAX_CREDIT_UNSUPPORTED` | `TaxCalculationInputGuard` | same | UNSUPPORTED |
| no source | `TAX_CREDIT_TYPE_UNSUPPORTED` | same | same | UNSUPPORTED |
| I90 p.3 ข้อ 7 item 1 | `expense_activity` required / rejected | `ExpenseActivityCatalogue`, guard | `Pnd90ExpenseSubtypeTest` | SUPPORTED |
| F90 p.3 ข้อ 7 item 3 (2) | `holding_years` required / rejected | `PropertyHoldingPeriodCatalogue`, guard | `Pnd90ExpenseSubtypeTest` | SUPPORTED |

## 9. Read but deliberately not implemented

| Source | Subject | Status | Reason |
| --- | --- | --- | --- |
| I90 p.2 ข้อ 1 item 2 (2), (3) | เงินสะสม กบข.; กองทุนสงเคราะห์ครูโรงเรียนเอกชน, each ≤ 500,000 | NOT_APPLICABLE as allowances | the booklet places both under *เงินได้ที่ได้รับยกเว้น* in ข้อ 1, i.e. a deduction from income, not a ใบแนบ line. They reach the engine through `exempt_amount` as part of a taxpayer-stated total; their individual ceilings are not enforced. |
| I90 p.10 item 7.6 | เบี้ยประกันชีวิตแบบบำนาญ | PARTIAL_BLOCKED | 90,000 and 15%/200,000 printed without stating whether they nest |
| I90 p.12 item 12 | เงินสมทบประกันสังคม | PARTIAL_BLOCKED | ceiling referred to the social security act |
| I90 p.12 item 13 | กล้องโทรทัศน์วงจรปิด | UNSUPPORTED | limited to 40(5)–(8) income in a เขตพัฒนาพิเศษเฉพาะกิจ; the payload carries no location |
| I90 p.13 item 16 | เงินลงทุนในหุ้นวิสาหกิจเพื่อสังคม | UNSUPPORTED | "ไม่เกินกรณีละ 100,000 บาท" — per investment case, which the payload has no notion of |
| I90 p.16 item 20 | ค่าจ้างก่อสร้างอาคาร | UNSUPPORTED | 10,000 per 1,000,000 of construction cost; the payload carries no cost |
| I90 p.16 item 22 | ค่าท่องเที่ยวภายในประเทศ | PARTIAL_BLOCKED | the second เมืองรอง band prints a 1.5× multiplier and **no ceiling** |
| F90 p.2 ข้อ 3 item 5 | เครดิตภาษีเงินปันผล | UNSUPPORTED | the gross-up is not modelled and no request shape carries it |
| F90 p.3 ข้อ 8; p.4 ข้อ 10 | separately-taxed property sale; elected-out income | UNSUPPORTED | no request shape carries either |
| — | legal final rounding | UNSUPPORTED | no repository source states a unit, direction or stage |

## 10. How to re-audit

Both booklets are two-column; a whole-page extraction interleaves them and produces sentences
that exist in neither. Every citation above was read with:

```
pdftotext -layout -f N -l N -x 0   -y 0 -W 300 -H 842 <file> -   # left column
pdftotext -layout -f N -l N -x 295 -y 0 -W 300 -H 842 <file> -   # right column
```

and cross-checked against `pdftoppm -png -r 170` page renders (`-r 200` with `-x/-y/-W/-H`
crops for the three rate tables). poppler-utils runs inside the application container.
